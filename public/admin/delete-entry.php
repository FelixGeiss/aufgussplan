<?php

/**
 * LÖSCHEN VON DATENBANK-EINTRÄGEN
 *
 * Diese Seite verarbeitet Lösch-Requests für:
 * - Aufgüsse
 * - Saunen
 * - Duftmittel
 *
 * Sicherheit: Nur POST-Requests erlaubt, mit Bestätigung
 */

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../src/config/config.php';

// Datenbankverbindung
require_once __DIR__ . '/../../src/db/connection.php';

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: aufguesse.php');
    exit;
}

// Parameter validieren
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';

if (empty($type) || empty($id) || !is_numeric($id)) {
    $_SESSION['delete_error'] = 'Ungültige Parameter für Lösch-Operation.';
    header('Location: aufguesse.php');
    exit;
}

// Lösch-Operation basierend auf Typ
$message = '';
$success = false;

try {
    switch ($type) {
        case 'aufguss':
            // Pruefen, ob der Aufgussname in Aufguessen verwendet wird
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM aufguesse WHERE aufguss_name_id = ?");
            $stmt->execute([$id]);
            $usage = $stmt->fetch();

            if ($usage['count'] > 0) {
                $_SESSION['delete_error'] = 'Aufgussname kann nicht geloescht werden, da er noch in Aufguessen verwendet wird.';
                header('Location: aufguesse.php');
                exit;
            }

            // Aufgussname loeschen
            $stmt = $db->prepare("DELETE FROM aufguss_namen WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['delete_message'] = 'Aufgussname erfolgreich geloescht.';
            break;

        case 'sauna':
            // Prüfen, ob die Sauna in Aufgüssen verwendet wird
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM aufguesse WHERE sauna_id = ?");
            $stmt->execute([$id]);
            $usage = $stmt->fetch();

            if ($usage['count'] > 0) {
                $_SESSION['delete_error'] = 'Sauna kann nicht gelöscht werden, da sie noch in Aufgüssen verwendet wird.';
                header('Location: aufguesse.php');
                exit;
            }

            // Sauna löschen
            $stmt = $db->prepare("DELETE FROM saunen WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['delete_message'] = 'Sauna erfolgreich gelöscht.';
            break;

        case 'duftmittel':
            // Prüfen, ob das Duftmittel in Aufgüssen verwendet wird
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM aufguesse WHERE duftmittel_id = ?");
            $stmt->execute([$id]);
            $usage = $stmt->fetch();

            if ($usage['count'] > 0) {
                $_SESSION['delete_error'] = 'Duftmittel kann nicht gelöscht werden, da es noch in Aufgüssen verwendet wird.';
                header('Location: aufguesse.php');
                exit;
            }

            // Duftmittel löschen
            $stmt = $db->prepare("DELETE FROM duftmittel WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['delete_message'] = 'Duftmittel erfolgreich gelöscht.';
            break;

        case 'mitarbeiter':
            // Pruefen, ob der Mitarbeiter in Aufguessen oder Aufguss-Aufgiessern verwendet wird
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM aufguesse WHERE mitarbeiter_id = ?");
            $stmt->execute([$id]);
            $usage = $stmt->fetch();

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM aufguss_aufgieser WHERE mitarbeiter_id = ?");
            $stmt->execute([$id]);
            $usageRelations = $stmt->fetch();

            $usageCount = (int)($usage['count'] ?? 0) + (int)($usageRelations['count'] ?? 0);

            if ($usageCount > 0) {
                $_SESSION['delete_error'] = 'Mitarbeiter kann nicht geloescht werden, da er noch in Aufguessen verwendet wird.';
                header('Location: aufguesse.php');
                exit;
            }

            $stmt = $db->prepare("DELETE FROM mitarbeiter WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['delete_message'] = 'Mitarbeiter erfolgreich geloescht.';
            break;

        default:
            $_SESSION['delete_error'] = 'Unbekannter Eintragstyp.';
            header('Location: aufguesse.php');
            exit;
    }

} catch (Exception $e) {
    $_SESSION['delete_error'] = 'Fehler beim Löschen: ' . $e->getMessage();
}

header('Location: aufguesse.php');
exit;

?>
