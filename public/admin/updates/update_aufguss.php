<?php
/**
 * AJAX-Handler für Inline-Editing von Aufgüssen
 * Wird von aufguesse.php für Live-Updates verwendet
 */

header('Content-Type: application/json');

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/db/connection.php';

function normalizeStaerkeLevel($value) {
    if ($value === '' || $value === null) {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $level = (int)$value;
    if ($level <= 0) {
        return null;
    }
    if ($level === 1) {
        return 1;
    }
    if ($level === 2) {
        return 2;
    }
    return 3;
}

$response = ['success' => false, 'error' => ''];

try {
    // Nur POST-Requests erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Nur POST-Requests erlaubt');
    }


    // Parameter validieren
    $aufgussId = $_POST['aufguss_id'] ?? null;
    $field = $_POST['field'] ?? null;

    if (!$aufgussId || !$field) {
        throw new Exception('Aufguss-ID und Feld sind erforderlich');
    }

    // Datenbankverbindung
    $db = Database::getInstance()->getConnection();

    // Aufguss laden (Sicherheitscheck)
    $stmt = $db->prepare("SELECT * FROM aufguesse WHERE id = ?");
    $stmt->execute([$aufgussId]);
    $aufguss = $stmt->fetch();

    if (!$aufguss) {
        throw new Exception('Aufguss nicht gefunden');
    }

    // Update basierend auf dem Feld durchführen
    switch ($field) {
        case 'zeit':
            $zeitAnfangRaw = $_POST['zeit_anfang'] ?? '';
            $zeitEndeRaw = $_POST['zeit_ende'] ?? '';

            // Zeit-Validierung: Endzeit darf nicht vor Anfangszeit liegen
            if (!empty($zeitAnfangRaw) && !empty($zeitEndeRaw)) {
                $zeitAnfangTimestamp = strtotime($zeitAnfangRaw);
                $zeitEndeTimestamp = strtotime($zeitEndeRaw);

                if ($zeitEndeTimestamp < $zeitAnfangTimestamp) {
                    throw new Exception('Die Endzeit darf nicht vor der Anfangszeit liegen.');
                }
            }

            // Leere Strings zu NULL konvertieren
            $zeitAnfang = empty($zeitAnfangRaw) ? null : date('H:i:s', strtotime($zeitAnfangRaw));
            $zeitEnde = empty($zeitEndeRaw) ? null : date('H:i:s', strtotime($zeitEndeRaw));

            $stmt = $db->prepare("UPDATE aufguesse SET zeit_anfang = ?, zeit_ende = ? WHERE id = ?");
            $stmt->execute([$zeitAnfang, $zeitEnde, $aufgussId]);
            break;

        case 'staerke':
            $staerkeRaw = $_POST['staerke'] ?? '';
            $iconRaw = $_POST['staerke_icon'] ?? '';
            $planIdRaw = $_POST['plan_id'] ?? '';

            $staerkeValue = null;
            if ($staerkeRaw !== '' && $staerkeRaw !== null) {
                $normalized = normalizeStaerkeLevel($staerkeRaw);
                if ($normalized === null) {
                    throw new Exception('Ungültige Stärke');
                }
                $staerkeValue = $normalized;
            }

            $iconPath = trim((string)$iconRaw);
            if ($iconPath === '') {
                $iconPath = null;
            }

            $planId = is_numeric($planIdRaw) ? (int)$planIdRaw : null;

            $stmt = $db->prepare("UPDATE aufguesse SET staerke = ?, staerke_icon = ? WHERE id = ?");
            $stmt->execute([$staerkeValue, $iconPath, $aufgussId]);

            if ($planId !== null && $staerkeValue !== null) {
                $iconStmt = $db->prepare("UPDATE aufguesse SET staerke_icon = ? WHERE plan_id = ? AND staerke = ?");
                $iconStmt->execute([$iconPath, $planId, $staerkeValue]);
            }
            break;

        case 'mitarbeiter':
            $aufgieserName = trim($_POST['aufgieser_name'] ?? '');
            $mitarbeiterId = $_POST['mitarbeiter_id'] ?? '';
            $mitarbeiterIds = $_POST['mitarbeiter_ids'] ?? ($_POST['mitarbeiter_ids[]'] ?? []);
            $aufgieserNamesRaw = trim($_POST['aufgieser_names'] ?? '');

            // Entweder Name oder Mitarbeiter-ID kann angegeben werden
            if (!is_array($mitarbeiterIds)) {
                $mitarbeiterIds = [$mitarbeiterIds];
            }
            $mitarbeiterIds = array_values(array_filter($mitarbeiterIds, function ($value) {
                return (int)$value > 0;
            }));
            $hasMulti = count($mitarbeiterIds) > 0;

            $resolvedMitarbeiterId = null;
            if (!$hasMulti && empty($aufgieserNamesRaw)) {
                if (!empty($mitarbeiterId)) {
                    $resolvedMitarbeiterId = (int)$mitarbeiterId;
                } elseif (!empty($aufgieserName)) {
                    $stmt = $db->prepare("SELECT id FROM mitarbeiter WHERE name = ?");
                    $stmt->execute([$aufgieserName]);
                    $existing = $stmt->fetch();

                    if ($existing && !empty($existing['id'])) {
                        $resolvedMitarbeiterId = (int)$existing['id'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO mitarbeiter (name) VALUES (?)");
                        $stmt->execute([$aufgieserName]);
                        $resolvedMitarbeiterId = (int)$db->lastInsertId();
                    }
                }
            }

            if ($hasMulti || !empty($aufgieserNamesRaw)) {
                $stmt = $db->prepare("UPDATE aufguesse SET mitarbeiter_id = NULL WHERE id = ?");
                $stmt->execute([$aufgussId]);
            } elseif (!empty($resolvedMitarbeiterId)) {
                // Mitarbeiter zuweisen (vorhanden oder neu erstellt)
                $stmt = $db->prepare("UPDATE aufguesse SET mitarbeiter_id = ? WHERE id = ?");
                $stmt->execute([$resolvedMitarbeiterId, $aufgussId]);
            } else {
                // Beide Felder sind leer - das ist erlaubt, setze beide auf NULL
                $stmt = $db->prepare("UPDATE aufguesse SET mitarbeiter_id = NULL WHERE id = ?");
                $stmt->execute([$aufgussId]);
            }

            // Mehrfach-Aufgießer Tabelle synchron halten
            $stmt = $db->prepare("DELETE FROM aufguss_aufgieser WHERE aufguss_id = ?");
            $stmt->execute([$aufgussId]);
            if ($hasMulti) {
                $stmt = $db->prepare("INSERT INTO aufguss_aufgieser (aufguss_id, mitarbeiter_id, name) VALUES (?, ?, NULL)");
                foreach ($mitarbeiterIds as $mid) {
                    $mid = (int)$mid;
                    if ($mid > 0) {
                        $stmt->execute([$aufgussId, $mid]);
                    }
                }
            } elseif (!empty($aufgieserNamesRaw)) {
                $names = preg_split('/[,\n;]/', $aufgieserNamesRaw);
                $stmt = $db->prepare("INSERT INTO aufguss_aufgieser (aufguss_id, mitarbeiter_id, name) VALUES (?, NULL, ?)");
                foreach ($names as $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $stmt->execute([$aufgussId, $name]);
                    }
                }
            } elseif (!empty($resolvedMitarbeiterId)) {
                $stmt = $db->prepare("INSERT INTO aufguss_aufgieser (aufguss_id, mitarbeiter_id, name) VALUES (?, ?, NULL)");
                $stmt->execute([$aufgussId, $resolvedMitarbeiterId]);
            }
            break;

        case 'sauna':
            $saunaName = trim($_POST['sauna_name'] ?? '');
            $saunaId = $_POST['sauna_id'] ?? '';

            if (!empty($saunaId)) {
                // Vorhandene Sauna zuweisen
                $stmt = $db->prepare("UPDATE aufguesse SET sauna_id = ? WHERE id = ?");
                $stmt->execute([$saunaId, $aufgussId]);
            } elseif (!empty($saunaName)) {
                // Neue Sauna erstellen oder finden
                $stmt = $db->prepare("SELECT id FROM saunen WHERE name = ?");
                $stmt->execute([$saunaName]);
                $existingSauna = $stmt->fetch();

                if ($existingSauna) {
                    $stmt = $db->prepare("UPDATE aufguesse SET sauna_id = ? WHERE id = ?");
                    $stmt->execute([$existingSauna['id'], $aufgussId]);
                } else {
                    // Neue Sauna erstellen
                    $stmt = $db->prepare("INSERT INTO saunen (name) VALUES (?)");
                    $stmt->execute([$saunaName]);
                    $newSaunaId = $db->lastInsertId();

                    $stmt = $db->prepare("UPDATE aufguesse SET sauna_id = ? WHERE id = ?");
                    $stmt->execute([$newSaunaId, $aufgussId]);
                }
            } else {
                // Beide Felder sind leer - das ist erlaubt, setze sauna_id auf NULL
                $stmt = $db->prepare("UPDATE aufguesse SET sauna_id = NULL WHERE id = ?");
                $stmt->execute([$aufgussId]);
            }
            break;

        case 'duftmittel':
            $duftmittelName = trim($_POST['duftmittel_name'] ?? '');
            $duftmittelId = $_POST['duftmittel_id'] ?? '';

            if (!empty($duftmittelId)) {
                // Vorhandenes Duftmittel zuweisen
                $stmt = $db->prepare("UPDATE aufguesse SET duftmittel_id = ? WHERE id = ?");
                $stmt->execute([$duftmittelId, $aufgussId]);
            } elseif (!empty($duftmittelName)) {
                // Neues Duftmittel erstellen oder finden
                $stmt = $db->prepare("SELECT id FROM duftmittel WHERE name = ?");
                $stmt->execute([$duftmittelName]);
                $existingDuftmittel = $stmt->fetch();

                if ($existingDuftmittel) {
                    $stmt = $db->prepare("UPDATE aufguesse SET duftmittel_id = ? WHERE id = ?");
                    $stmt->execute([$existingDuftmittel['id'], $aufgussId]);
                } else {
                    // Neues Duftmittel erstellen
                    $stmt = $db->prepare("INSERT INTO duftmittel (name) VALUES (?)");
                    $stmt->execute([$duftmittelName]);
                    $newDuftmittelId = $db->lastInsertId();

                    $stmt = $db->prepare("UPDATE aufguesse SET duftmittel_id = ? WHERE id = ?");
                    $stmt->execute([$newDuftmittelId, $aufgussId]);
                }
            } else {
                // Beide Felder sind leer - das ist erlaubt, setze duftmittel_id auf NULL
                $stmt = $db->prepare("UPDATE aufguesse SET duftmittel_id = NULL WHERE id = ?");
                $stmt->execute([$aufgussId]);
            }
            break;

        case 'aufguss':
            $aufgussName = trim($_POST['aufguss_name'] ?? '');
            $aufgussIdSelect = $_POST['select_aufguss_id'] ?? '';


            if (!empty($aufgussIdSelect)) {
                $selectedId = (int)$aufgussIdSelect;


                $stmt = $db->prepare("UPDATE aufguesse SET aufguss_name_id = ? WHERE id = ?");
                $stmt->execute([$selectedId, $aufgussId]);

            } elseif (!empty($aufgussName)) {
                $stmt = $db->prepare("SELECT id FROM aufguss_namen WHERE name = ?");
                $stmt->execute([$aufgussName]);
                $existing = $stmt->fetch();
                if ($existing && !empty($existing['id'])) {
                    $nameId = (int)$existing['id'];
                } else {
                    $stmt = $db->prepare("INSERT INTO aufguss_namen (name) VALUES (?)");
                    $stmt->execute([$aufgussName]);
                    $nameId = (int)$db->lastInsertId();
                }
                $stmt = $db->prepare("UPDATE aufguesse SET aufguss_name_id = ? WHERE id = ?");
                $stmt->execute([$nameId, $aufgussId]);

            } else {
                // Feld ist leer - ID leeren
                $stmt = $db->prepare("UPDATE aufguesse SET aufguss_name_id = NULL WHERE id = ?");
                $stmt->execute([$aufgussId]);

            }
            break;

        default:
            throw new Exception('Unbekanntes Feld: ' . $field);
    }

    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log('Update Aufguss Fehler: ' . $e->getMessage());
}

echo json_encode($response);
?>
