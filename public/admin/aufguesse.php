<?php

/**
 * AUFGUSS-PLANUNG UND -VERWALTUNG
 *
 * Diese Seite ermöglicht es Administratoren, Aufgüsse zu planen und zu verwalten:
 * - Aufgüsse für bestimmte Tage anzeigen
 * - Neue Aufgüsse zu bestimmten Zeiten hinzufügen
 * - Bestehende Aufgüsse bearbeiten oder löschen
 * - Datumsauswahl für verschiedene Tage
 *
 * Als Anfänger solltest du wissen:
 * - Diese Seite zeigt Zeit-basierte Planung
 * - JavaScript verwaltet das Datum und lädt Daten dynamisch
 * - Modal-Fenster für Formulare (ähnlich wie bei Mitarbeitern)
 * - AJAX für Echtzeit-Updates ohne Seitenreload
 *
 * URL: http://localhost/aufgussplan/admin/aufguesse.php
 */

// Session für Sicherheit starten
session_start();

// Konfiguration laden
require_once __DIR__ . '/../../src/config/config.php';

/**
 * SICHERHEIT: LOGIN-PRÜFUNG (auskommentiert für Entwicklung)
 *
 * In Produktion: Geschützter Admin-Bereich
 */
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

// Datenbankverbindung für PHP-Operationen
require_once __DIR__ . '/../../src/db/connection.php';

// Aufguss-Model für Plan-Operationen
require_once __DIR__ . '/../../src/models/aufguss.php';

$aufgussModel = new Aufguss();

// Pläne aus Datenbank laden
$plaene = $aufgussModel->getAllPlans();
// Daten für Formular-Select-Felder laden
$mitarbeiter = $db->query("SELECT id, name, bild FROM mitarbeiter ORDER BY name")->fetchAll();
$saunen = $db->query("SELECT id, name, bild, beschreibung, temperatur FROM saunen ORDER BY name")->fetchAll();
$duftmittel = $db->query("SELECT id, name, beschreibung FROM duftmittel ORDER BY name")->fetchAll();
$aufguss_optionen = $db->query("SELECT id, name, beschreibung FROM aufguss_namen ORDER BY name")->fetchAll();

// Alle Aufgüsse laden (für die Aufguss-Tabelle)
$aufgüsse = $aufgussModel->getAll();

// Hochgeladene Dateien sammeln (Werbung, Bilder, Hintergruende)
$uploadedFiles = [];
$addUploadedFile = function ($bereich, $name, $datei, $typ, $extra = []) use (&$uploadedFiles) {
    if (empty($datei)) {
        return;
    }
    $uploadedFiles[] = array_merge([
        'bereich' => $bereich,
        'name' => $name,
        'datei' => $datei,
        'typ' => $typ
    ], $extra);
};

foreach ($plaene as $plan) {
    $addUploadedFile('Plan', $plan['name'] ?? '', $plan['hintergrund_bild'] ?? '', 'Hintergrundbild', [
        'plan_id' => $plan['id'] ?? null
    ]);
    $adType = ($plan['werbung_media_typ'] ?? '') === 'video' ? 'Werbung (Video)' : 'Werbung (Bild)';
    $addUploadedFile('Plan', $plan['name'] ?? '', $plan['werbung_media'] ?? '', $adType, [
        'plan_id' => $plan['id'] ?? null
    ]);
}

foreach ($saunen as $sauna) {
    $addUploadedFile('Sauna', $sauna['name'] ?? '', $sauna['bild'] ?? '', 'Sauna-Bild');
}

foreach ($mitarbeiter as $mitarbeiterItem) {
    $addUploadedFile('Mitarbeiter', $mitarbeiterItem['name'] ?? '', $mitarbeiterItem['bild'] ?? '', 'Mitarbeiter-Bild');
}

// Dateien fuer Tabs filtern
$werbungFiles = array_values(array_filter($uploadedFiles, function ($file) {
    $typ = $file['typ'] ?? '';
    if (is_array($typ)) {
        $typ = implode(' ', $typ);
    }
    return stripos((string)$typ, 'Werbung') !== false;
}));
$hintergrundFiles = array_values(array_filter($uploadedFiles, function ($file) {
    $typ = $file['typ'] ?? '';
    if (is_array($typ)) {
        $typ = implode(' ', $typ);
    }
    return stripos((string)$typ, 'Hintergrund') !== false;
}));

// Dateien aus Upload-Ordnern einsammeln (auch wenn sie nicht in der DB stehen)
$uploadBaseDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR;
$werbungUploadDir = $uploadBaseDir . 'werbung' . DIRECTORY_SEPARATOR;
$planUploadDir = $uploadBaseDir . 'plan' . DIRECTORY_SEPARATOR;
$werbungUploadFiles = [];
if (is_dir($werbungUploadDir)) {
    foreach (scandir($werbungUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $werbungUploadDir . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $werbungUploadFiles[] = 'werbung/' . $entry;
    }
}
$planUploadFiles = [];
if (is_dir($planUploadDir)) {
    foreach (scandir($planUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $planUploadDir . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $planUploadFiles[] = 'plan/' . $entry;
    }
}

// Auswahl-Optionen fuer vorhandene Dateien
$werbungOptions = [];
$werbungSeen = [];
$werbungSource = array_unique(array_merge(
    $werbungUploadFiles,
    array_map(function ($file) {
        return (string)($file['datei'] ?? '');
    }, $werbungFiles)
));
foreach ($werbungSource as $path) {
    $path = trim((string)$path);
    if ($path === '' || isset($werbungSeen[$path])) {
        continue;
    }
    $werbungSeen[$path] = true;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mediaType = in_array($ext, ['mp4', 'webm', 'ogg'], true) ? 'video' : 'image';
    $label = basename($path);
    $werbungOptions[] = ['path' => $path, 'type' => $mediaType, 'label' => $label];
}

$hintergrundOptions = [];
$hintergrundSeen = [];
$hintergrundSource = array_unique(array_merge(
    $planUploadFiles,
    array_map(function ($file) {
        return (string)($file['datei'] ?? '');
    }, $hintergrundFiles)
));
foreach ($hintergrundSource as $path) {
    $path = trim($path);
    if ($path === '' || isset($hintergrundSeen[$path])) {
        continue;
    }
    $hintergrundSeen[$path] = true;
    $label = basename($path);
    $hintergrundOptions[] = ['path' => $path, 'label' => $label];
}

// Werbung-Dateien fuer die Datenbank-Ansicht (nur Upload-Ordner)
$werbungTabFiles = [];
foreach ($werbungUploadFiles as $path) {
    $path = trim((string)$path);
    if ($path === '') {
        continue;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $typ = in_array($ext, ['mp4', 'webm', 'ogg'], true) ? 'Werbung (Video)' : 'Werbung (Bild)';
    $werbungTabFiles[] = [
        'bereich' => 'Plan',
        'name' => 'Datei',
        'datei' => $path,
        'typ' => $typ,
        'plan_id' => null
    ];
}

// Hintergrund-Dateien fuer die Datenbank-Ansicht (nur Upload-Ordner)
$hintergrundTabFiles = [];
foreach ($planUploadFiles as $path) {
    $path = trim((string)$path);
    if ($path === '') {
        continue;
    }
    $hintergrundTabFiles[] = [
        'bereich' => 'Plan',
        'name' => 'Datei',
        'datei' => $path,
        'typ' => 'Hintergrundbild',
        'plan_id' => null
    ];
}
$message = '';
$errors = [];

// Lösch-Meldungen aus GET-Parametern (für Aufguss-Löschungen aus Tabellen)
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = 'Aufguss erfolgreich gelöscht!';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_id':
            $errors[] = 'Fehler: Keine Aufguss-ID angegeben.';
            break;
        case 'invalid_id':
            $errors[] = 'Fehler: Ungültige Aufguss-ID.';
            break;
        case 'not_found':
            $errors[] = 'Fehler: Aufguss nicht gefunden.';
            break;
        case 'delete_failed':
            $errors[] = 'Fehler: Aufguss konnte nicht gelöscht werden.';
            break;
        default:
            $errors[] = 'Unbekannter Fehler beim Löschen.';
    }
}

// Lösch-Meldungen aus Session (für Datenbank-Eintrags-Löschungen)
if (isset($_SESSION['delete_message'])) {
    $message = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']);
} elseif (isset($_SESSION['delete_error'])) {
    $errors[] = $_SESSION['delete_error'];
    unset($_SESSION['delete_error']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['form_type']) && $_POST['form_type'] === 'create_plan') {
        $planName = trim($_POST['plan_name'] ?? '');
        $planBeschreibung = trim($_POST['plan_beschreibung'] ?? '');

        if ($planName === '') {
            $errors[] = 'Bitte einen Plannamen eingeben.';
        } else {
            try {
                $aufgussModel->createPlan([
                    'name' => $planName,
                    'beschreibung' => $planBeschreibung !== '' ? $planBeschreibung : null
                ]);
                $message = 'Plan erfolgreich erstellt!';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    } elseif (!empty($_POST['form_type']) && $_POST['form_type'] === 'create_sauna') {
        $saunaName = trim($_POST['sauna_name'] ?? '');
        $saunaBeschreibung = trim($_POST['sauna_beschreibung'] ?? '');
        $saunaTemperaturRaw = trim($_POST['sauna_temperatur'] ?? '');
        $saunaTemperatur = null;

        if ($saunaName === '') {
            $errors[] = 'Bitte einen Saunanamen eingeben.';
        } elseif ($saunaTemperaturRaw !== '' && !is_numeric($saunaTemperaturRaw)) {
            $errors[] = 'Temperatur muss eine Zahl sein.';
        } else {
            if ($saunaTemperaturRaw !== '') {
                $saunaTemperatur = max(0, (int)$saunaTemperaturRaw);
            }
            try {
                $stmt = $db->prepare("INSERT INTO saunen (name, beschreibung, temperatur) VALUES (?, ?, ?)");
                $stmt->execute([
                    $saunaName,
                    $saunaBeschreibung !== '' ? $saunaBeschreibung : null,
                    $saunaTemperatur
                ]);
                $message = 'Sauna erfolgreich erstellt!';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    } elseif (!empty($_POST['form_type']) && $_POST['form_type'] === 'create_aufguss_name') {
        $aufgussName = trim($_POST['aufguss_name'] ?? '');
        $aufgussBeschreibung = trim($_POST['aufguss_beschreibung'] ?? '');
        if ($aufgussName === '') {
            $errors[] = 'Bitte einen Aufgussnamen eingeben.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO aufguss_namen (name, beschreibung) VALUES (?, ?)");
                $stmt->execute([
                    $aufgussName,
                    $aufgussBeschreibung !== '' ? $aufgussBeschreibung : null
                ]);
                $message = 'Aufgussname erfolgreich erstellt!';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    } elseif (!empty($_POST['form_type']) && $_POST['form_type'] === 'create_duftmittel') {
        $duftName = trim($_POST['duftmittel_name'] ?? '');
        $duftBeschreibung = trim($_POST['duftmittel_beschreibung'] ?? '');
        if ($duftName === '') {
            $errors[] = 'Bitte einen Duftmittel-Namen eingeben.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO duftmittel (name, beschreibung) VALUES (?, ?)");
                $stmt->execute([
                    $duftName,
                    $duftBeschreibung !== '' ? $duftBeschreibung : null
                ]);
                $message = 'Duftmittel erfolgreich erstellt!';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    } else {
        require_once __DIR__ . '/../../src/services/aufgussService.php';
        $service = new AufgussService();
        $result = $service->verarbeiteFormular($_POST, $_FILES);

        if ($result['success']) {
            $message = $result['message'];
            // Seite neu laden, um die ?"nderungen anzuzeigen
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aufgüsse verwalten - Aufgussplan</title>
    <!-- Lokale Tailwind CSS -->
    <link rel="stylesheet" href="../dist/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .next-aufguss-row {
            background-color: transparent;
        }

        .next-aufguss-row td,
        .next-aufguss-row .display-mode {
            animation: next-row-pulse 1.6s ease-in-out infinite;
            transform-origin: center;
            will-change: transform, opacity, filter;
        }

        .next-aufguss-row .display-mode {
            background-color: transparent;
            color: #111827;
        }

        @keyframes next-row-pulse {
            0% {
                transform: scale(1);
                opacity: 0.85;
                filter: brightness(0.95);
            }
            50% {
                transform: scale(1.06);
                opacity: 1;
                filter: brightness(1.08);
            }
            100% {
                transform: scale(1);
                opacity: 0.85;
                filter: brightness(0.95);
            }
        }

        .plan-clock-admin {
            background-color: var(--plan-accent-color, #ffffff);
        }

        .plan-table-head,
        .plan-table-head th {
            background-color: var(--plan-accent-color, #ffffff);
        }

        .plan-table-wrap {
            transition: opacity 300ms ease, transform 300ms ease;
        }

        .plan-table-wrap.is-hidden {
            opacity: 0;
            transform: translateX(-110%);
            pointer-events: none;
        }

        .plan-ad-wrap {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateX(110%);
            transition: opacity 300ms ease, transform 300ms ease;
            pointer-events: none;
        }

        .plan-ad-wrap.is-visible {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }

        .plan-ad-media {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .plan-ad-asset {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .plan-table-scroll {
            max-height: 520px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .plan-table-scroll .zeit-cell,
        .plan-table-scroll .zeit-cell .display-mode {
            text-align: center;
        }

        .plan-table-scroll .zeit-cell .display-mode {
            color: #111827 !important;
        }

        .plan-table-scroll tbody tr td:first-child {
            border-left: 1px solid #e5e7eb;
        }

        .plan-table-scroll tbody tr td:last-child {
            border-right: 1px solid #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-100">    <?php include __DIR__ . '/partials/navbar.php'; ?>



    <div class="container mx-auto px-4 py-8 space-y-8">

        <!-- Meldungen anzeigen -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-start justify-between gap-4">
                <div><?php echo htmlspecialchars($message); ?></div>
                <button type="button" class="text-green-700 hover:text-green-900 font-bold leading-none" aria-label="Meldung schliessen" onclick="this.parentElement.remove()">
                    &times;
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-start justify-between gap-4">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="text-red-700 hover:text-red-900 font-bold leading-none" aria-label="Meldung schliessen" onclick="this.parentElement.remove()">
                    &times;
                </button>
            </div>
        <?php endif; ?>


        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 text-center">Neuen Plan erstellen</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="form_type" value="create_plan">
                    <div>
                        <label for="plan-name" class="block text-sm font-medium text-gray-900 mb-2 text-center">Planname</label>
                        <input type="text" id="plan-name" name="plan_name" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Wellness-Tag, Power-Aufguesse" required>
                    </div>
                    <div>
                        <label for="plan-beschreibung" class="block text-sm font-medium text-gray-900 mb-2 text-center">Beschreibung</label>
                        <textarea id="plan-beschreibung" name="plan_beschreibung" rows="3" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="Kurze Beschreibung fuer den Plan"></textarea>
                    </div>
                    <div class="flex justify-center">
                        <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Plan erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($plaene)): ?>
            <!-- Keine Pläne vorhanden -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <div class="text-center py-12 text-gray-500">
                        <div class="text-6xl mb-4">📋</div>
                        <h2 class="text-2xl font-bold mb-4">Noch keine Pläne vorhanden</h2>
                        <p class="text-lg">Erstelle deinen ersten Plan im Dashboard-Formular</p>
                        <p class="text-sm mt-2">Jeder Plan kann verschiedene Aufgüsse, Duftmittel und Saunen enthalten.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Pläne mit ihren Aufgüssen -->
            <?php foreach ($plaene as $plan): ?>
                <?php
                // Aufgüsse für diesen Plan laden
                $planAufgüsse = $aufgussModel->getAufgüsseByPlan($plan['id']);
                ?>

                <!-- Plan-Bereich -->
                <div id="plan-<?php echo $plan['id']; ?>" class="bg-white rounded-lg shadow-md relative">
                    <div class="relative p-6">
                        <!-- Plan-Header -->
                        <div class="relative flex items-center justify-between mb-6">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center">
                                    <span class="text-white font-bold text-lg">
                                        <?php echo strtoupper(substr($plan['name'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <h2 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($plan['name'] ?? ''); ?></h2>
                                    <?php if ($plan['beschreibung']): ?>
                                        <p class="text-lg text-gray-600 mt-1"><?php echo htmlspecialchars($plan['beschreibung'] ?? ''); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="plan-clock-admin-<?php echo $plan['id']; ?>" class="plan-clock-admin hidden absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 inline-flex flex-col items-center justify-center bg-white/70 border border-gray-200 rounded-lg px-3 py-2 shadow-sm">
                                <div class="plan-clock-admin-time text-lg font-semibold text-gray-900">--:--</div>
                                <div class="plan-clock-admin-date text-xs text-gray-600">--.--.----</div>
                            </div>
                            <div class="text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button"
                                        class="plan-select-btn"
                                        data-plan-select="<?php echo (int)$plan['id']; ?>">
                                        Plan auswaehlen
                                    </button>
                                    <button type="button"
                                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                        onclick="saveAllPlanSettings(<?php echo (int)$plan['id']; ?>)">
                                        Speichern
                                    </button>
                                </div>
                                <div class="text-sm text-gray-500">Erstellt am</div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo date('d.m.Y', strtotime($plan['erstellt_am'])); ?>
                                </div>
                                <button onclick="deletePlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')"
                                    class="mt-2 text-red-600 hover:text-red-900 text-sm font-medium">
                                    Plan löschen
                                </button>
                            </div>
                        </div>

                        <div class="relative rounded-lg overflow-hidden">
                            <?php if (!empty($plan['hintergrund_bild'])): ?>
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../uploads/<?php echo htmlspecialchars($plan['hintergrund_bild']); ?>');"></div>
                            <?php endif; ?>
                            <div class="relative">
                                <!-- Aufgüsse für diesen Plan -->
                                <div class="plan-table-wrap" id="plan-table-wrap-<?php echo $plan['id']; ?>">
                                    <?php if (empty($planAufgüsse)): ?>
                                        <div class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg bg-white/70">
                                            <div class="text-4xl mb-2">🕐</div>
                                            <p class="text-lg font-medium">Noch keine Aufgüsse in diesem Plan</p>
                                            <p class="text-sm">Erstelle Aufgüsse im Dashboard und weise sie diesem Plan zu</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="overflow-x-auto plan-table-scroll">
                                            <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                                                <thead class="plan-table-head">
                                                    <tr>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Zeit
                                                        </th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Aufguss
                                                        </th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Stärke
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Aufgießer
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Sauna
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Duftmittel
                                                        </th>
                                                        <th class="px-6 py-4 text-left text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            datum
                                                        </th>
                                                        <th class="px-6 py-4 text-center text-sm font-semibold text-black-500 uppercase tracking-wider border-b">
                                                            Löschen
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-transparent divide-y divide-gray-200">
                                                    <?php foreach ($planAufgüsse as $aufguss): ?>
                                                        <tr class="bg-white/35" data-aufguss-id="<?php echo $aufguss['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>">
                                                            <!-- Zeit -->
                                                            <td class="px-6 py-4 whitespace-normal break-words zeit-cell">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode text-lg font-bold text-blue-600 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group whitespace-normal break-words" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'zeit')">
                                                                    <span>
                                                                        <?php
                                                                        // Prüfe, ob Zeitbereich vorhanden ist
                                                                        $zeitAnfang = $aufguss['zeit_anfang'] ?? null;
                                                                        $zeitEnde = $aufguss['zeit_ende'] ?? null;

                                                                        if ($zeitAnfang && $zeitEnde) {
                                                                            // Zeitbereich anzeigen (Sekunden entfernen)
                                                                            $zeitAnfangFormatted = date('H:i', strtotime($zeitAnfang));
                                                                            $zeitEndeFormatted = date('H:i', strtotime($zeitEnde));
                                                                            echo '<span class="flex flex-col leading-tight">';
                                                                            echo '<span>' . htmlspecialchars($zeitAnfangFormatted) . '</span>';
                                                                            echo '<span>' . htmlspecialchars($zeitEndeFormatted) . '</span>';
                                                                            echo '</span>';
                                                                        } else {
                                                                            // Fallback auf altes zeit-Feld
                                                                            $zeit = $aufguss['zeit'] ?? '--:--';
                                                                            if ($zeit !== '--:--') {
                                                                                $zeit = date('H:i', strtotime($zeit)); // Sekunden entfernen
                                                                                $zeit = ltrim($zeit, '0');
                                                                                if (strpos($zeit, ':') === 0) {
                                                                                    $zeit = '0' . $zeit;
                                                                                }
                                                                            }
                                                                            echo htmlspecialchars($zeit);
                                                                        }
                                                                        ?>
                                                                    </span>
                                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                                    <div class="flex items-center gap-2">
                                                                        <label class="text-sm font-semibold text-gray-900 w-12">Anfang:</label>
                                                                        <input type="time" name="zeit_anfang" value="<?php echo $zeitAnfang ? date('H:i', strtotime($zeitAnfang)) : ''; ?>"
                                                                            class="rounded px-2 py-1 text-sm border border-gray-300">
                                                                    </div>
                                                                    <div class="flex items-center gap-2">
                                                                        <label class="text-sm font-semibold text-gray-900 w-12">Ende:</label>
                                                                        <input type="time" name="zeit_ende" value="<?php echo $zeitEnde ? date('H:i', strtotime($zeitEnde)) : ''; ?>"
                                                                            class="rounded px-2 py-1 text-sm border border-gray-300">
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'zeit')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'zeit')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Aufguss-Name -->
                                                            <td class="px-6 py-4 whitespace-normal break-words aufguss-cell">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group whitespace-normal break-words" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'aufguss')">
                                                                    <span><?php echo htmlspecialchars($aufguss['name'] ?? 'Aufguss'); ?></span>
                                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2" data-aufguss-id="<?php echo $aufguss['id']; ?>">
                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Aufguss eingeben:</label>
                                                                        <input type="text" name="aufguss_name" value="<?php echo htmlspecialchars($aufguss['name'] ?? ''); ?>"
                                                                            placeholder="Aufguss eingeben" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            oninput="handleFieldInput(<?php echo $aufguss['id']; ?>, 'aufguss')">
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Vorhandenen Aufguss wählen:</label>
                                                                        <select name="select_aufguss_id" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            onchange="handleFieldSelect(<?php echo $aufguss['id']; ?>, 'aufguss')">
                                                                            <option value="">-- Aufguss wählen --</option>
                                                                            <?php foreach ($aufguss_optionen as $a): ?>
                                                                                <option value="<?php echo $a['id']; ?>" <?php echo ((int)($aufguss['aufguss_name_id'] ?? 0) === (int)$a['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($a['name'] ?? ''); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'aufguss')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'aufguss')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Stärke -->
                                                            <td class="px-6 py-4 whitespace-nowrap staerke-cell">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode cursor-pointer hover:bg-yellow-50 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'staerke')">
                                                                    <?php
                                                                    $stärke = $aufguss['staerke'] ?? 0;
                                                                    $stärkeText = '';
                                                                    $bgColor = 'bg-gray-100';
                                                                    $textColor = 'text-gray-800';

                                                                    switch ($stärke) {
                                                                        case 1:
                                                                            $stärkeText = '1 - Leicht';
                                                                            $bgColor = 'bg-green-100';
                                                                            $textColor = 'text-green-800';
                                                                            break;
                                                                        case 2:
                                                                            $stärkeText = '2 - Leicht+';
                                                                            $bgColor = 'bg-green-200';
                                                                            $textColor = 'text-green-800';
                                                                            break;
                                                                        case 3:
                                                                            $stärkeText = '3 - Mittel';
                                                                            $bgColor = 'bg-yellow-100';
                                                                            $textColor = 'text-yellow-800';
                                                                            break;
                                                                        case 4:
                                                                            $stärkeText = '4 - Stark';
                                                                            $bgColor = 'bg-orange-100';
                                                                            $textColor = 'text-orange-800';
                                                                            break;
                                                                        case 5:
                                                                            $stärkeText = '5 - Stark+';
                                                                            $bgColor = 'bg-red-100';
                                                                            $textColor = 'text-red-800';
                                                                            break;
                                                                        case 6:
                                                                            $stärkeText = '6 - Extrem';
                                                                            $bgColor = 'bg-red-200';
                                                                            $textColor = 'text-red-900';
                                                                            break;
                                                                        default:
                                                                            $stärkeText = 'Unbekannt';
                                                                            break;
                                                                    }
                                                                    ?>
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $bgColor; ?> <?php echo $textColor; ?>">
                                                                        <?php echo $stärkeText; ?>
                                                                    </span>
                                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                                    <select name="staerke" class="rounded px-2 py-1 text-sm border border-gray-300">
                                                                        <option value="">-- Stärke wählen --</option>
                                                                        <option value="1" <?php echo ($stärke == 1) ? 'selected' : ''; ?>>1 - Sehr leicht</option>
                                                                        <option value="2" <?php echo ($stärke == 2) ? 'selected' : ''; ?>>2 - Leicht</option>
                                                                        <option value="3" <?php echo ($stärke == 3) ? 'selected' : ''; ?>>3 - Mittel</option>
                                                                        <option value="4" <?php echo ($stärke == 4) ? 'selected' : ''; ?>>4 - Stark</option>
                                                                        <option value="5" <?php echo ($stärke == 5) ? 'selected' : ''; ?>>5 - Sehr stark</option>
                                                                        <option value="6" <?php echo ($stärke == 6) ? 'selected' : ''; ?>>6 - Extrem stark</option>
                                                                    </select>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'staerke')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'staerke')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Aufgießer -->
                                                            <td class="px-6 py-4 whitespace-nowrap mitarbeiter-cell text-center">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode flex flex-col items-center cursor-pointer hover:bg-gray-50 transition-colors duration-150 rounded px-2 py-2 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'mitarbeiter')">
                                                                    <?php
                                                                    $aufgieserPeople = [];
                                                                    $itemsRaw = $aufguss['aufgieser_items'] ?? '';
                                                                    if (!empty($itemsRaw)) {
                                                                        foreach (explode(';;', $itemsRaw) as $item) {
                                                                            $parts = explode('||', $item, 2);
                                                                            $name = trim($parts[0] ?? '');
                                                                            $bild = trim($parts[1] ?? '');
                                                                            if ($name !== '') {
                                                                                $aufgieserPeople[] = ['name' => $name, 'bild' => $bild];
                                                                            }
                                                                        }
                                                                    }
                                                                    if (empty($aufgieserPeople)) {
                                                                        $fallbackName = $aufguss['mitarbeiter_name'] ?? $aufguss['aufgieser_name'] ?? 'Unbekannt';
                                                                        $fallbackBild = $aufguss['mitarbeiter_bild'] ?? '';
                                                                        $aufgieserPeople[] = ['name' => $fallbackName, 'bild' => $fallbackBild];
                                                                    }
                                                                    ?>
                                                                    <div class="flex flex-wrap justify-center gap-4 w-full">
                                                                        <?php foreach ($aufgieserPeople as $person): ?>
                                                                            <div class="flex flex-col items-center">
                                                                                <?php if (!empty($person['bild'])): ?>
                                                                                    <img src="../uploads/<?php echo htmlspecialchars($person['bild']); ?>"
                                                                                        alt="Aufgiesser-Bild"
                                                                                        class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                                <?php else: ?>
                                                                                    <div class="h-10 w-10 bg-gray-300 rounded-full flex items-center justify-center">
                                                                                        <span class="text-gray-700 font-semibold text-sm">
                                                                                            <?php echo strtoupper(substr($person['name'], 0, 1)); ?>
                                                                                        </span>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <div class="mt-2 text-sm font-medium text-gray-900 text-center">
                                                                                    <?php echo htmlspecialchars($person['name']); ?>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                    <svg class="mt-1 w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Name eingeben:</label>
                                                                        <input type="text" name="aufgieser_name" value="<?php echo htmlspecialchars($aufguss['aufgieser_name'] ?? ''); ?>"
                                                                            placeholder="Name eingeben" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            oninput="handleFieldInput(<?php echo $aufguss['id']; ?>, 'mitarbeiter')">
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Mitarbeiter wählen:</label>
                                                                        <select name="mitarbeiter_id" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            onchange="handleFieldSelect(<?php echo $aufguss['id']; ?>, 'mitarbeiter')">
                                                                            <option value="">-- Mitarbeiter wählen --</option>
                                                                            <?php foreach ($mitarbeiter as $m): ?>
                                                                                <option value="<?php echo $m['id']; ?>" <?php echo ($aufguss['mitarbeiter_id'] == $m['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($m['name'] ?? ''); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="border-t border-gray-200 pt-2">
                                                                        <div class="text-center text-gray-400 text-xs mb-2">oder mehrere</div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Mehrere Mitarbeiter:</label>
                                                                        <select name="mitarbeiter_ids[]" multiple class="rounded px-2 py-1 text-sm border border-gray-300 w-full">
                                                                            <?php foreach ($mitarbeiter as $m): ?>
                                                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name'] ?? ''); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'mitarbeiter')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'mitarbeiter')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Sauna -->
                                                            <td class="px-6 py-4 whitespace-nowrap sauna-cell text-center">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode flex flex-col items-center cursor-pointer hover:bg-green-50 transition-colors duration-150 rounded px-2 py-2 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'sauna')">
                                                                    <div class="relative flex-shrink-0 h-10 w-10">
                                                                        <?php if (!empty($aufguss['sauna_bild'])): ?>
                                                                            <!-- Bild anzeigen wenn vorhanden -->
                                                                            <img src="../uploads/<?php echo htmlspecialchars($aufguss['sauna_bild']); ?>"
                                                                                alt="Sauna-Bild"
                                                                                class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                        <?php else: ?>
                                                                            <!-- Icon anzeigen wenn kein Bild vorhanden -->
                                                                            <div class="h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                                                                                <span class="text-green-600 text-sm">🏠</span>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <?php if ($aufguss['sauna_temperatur'] !== null && $aufguss['sauna_temperatur'] !== ''): ?>
                                                                            <span class="absolute -top-1 -right-6 bg-white text-[10px] leading-none px-2 py-0.5 rounded-full border border-gray-200 text-gray-700">
                                                                                <?php echo (int)$aufguss['sauna_temperatur']; ?>&deg;C
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="mt-2 text-sm font-medium text-gray-900">
                                                                        <?php echo htmlspecialchars($aufguss['sauna_name'] ?? 'Keine'); ?>
                                                                    </div>
                                                                    <svg class="mt-1 w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Sauna eingeben:</label>
                                                                        <input type="text" name="sauna_name" value="<?php echo htmlspecialchars($aufguss['sauna_name'] ?? ''); ?>"
                                                                            placeholder="Sauna eingeben" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            oninput="handleFieldInput(<?php echo $aufguss['id']; ?>, 'sauna')">
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Sauna wählen:</label>
                                                                        <select name="sauna_id" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            onchange="handleFieldSelect(<?php echo $aufguss['id']; ?>, 'sauna')">
                                                                            <option value="">-- Sauna wählen --</option>
                                                                            <?php foreach ($saunen as $s): ?>
                                                                                <option value="<?php echo $s['id']; ?>" <?php echo ($aufguss['sauna_id'] == $s['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'sauna')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'sauna')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Duftmittel -->
                                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-cell text-center">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode flex flex-col items-center cursor-pointer hover:bg-purple-50 transition-colors duration-150 rounded px-2 py-2 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'duftmittel')">
                                                                    <div class="flex-shrink-0 h-10 w-10">
                                                                        <div class="h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                                                                            <span class="text-purple-600 text-sm">🌸</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-2 text-sm font-medium text-gray-900">
                                                                        <?php echo htmlspecialchars($aufguss['duftmittel_name'] ?? 'Keines'); ?>
                                                                    </div>
                                                                    <svg class="mt-1 w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </div>

                                                                <!-- Bearbeitungs-Modus -->
                                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Duftmittel eingeben:</label>
                                                                        <input type="text" name="duftmittel_name" value="<?php echo htmlspecialchars($aufguss['duftmittel_name'] ?? ''); ?>"
                                                                            placeholder="Duftmittel eingeben" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            oninput="handleFieldInput(<?php echo $aufguss['id']; ?>, 'duftmittel')">
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-sm font-semibold text-gray-900 mb-1">Duftmittel wählen:</label>
                                                                        <select name="duftmittel_id" class="rounded px-2 py-1 text-sm border border-gray-300 w-full"
                                                                            onchange="handleFieldSelect(<?php echo $aufguss['id']; ?>, 'duftmittel')">
                                                                            <option value="">-- Duftmittel wählen --</option>
                                                                            <?php foreach ($duftmittel as $d): ?>
                                                                                <option value="<?php echo $d['id']; ?>" <?php echo ($aufguss['duftmittel_id'] == $d['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($d['name'] ?? ''); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'duftmittel')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'duftmittel')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Datum -->
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900">
                                                                    <?php echo date('d.m.Y', strtotime($aufguss['datum'] ?? $aufguss['erstellt_am'])); ?>
                                                                </div>
                                                            </td>

                                                            <!-- Aktionen -->
                                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                                <button onclick="deleteAufguss(<?php echo $aufguss['id']; ?>)"
                                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                                    title="Aufguss löschen">


                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div id="plan-ad-wrap-<?php echo $plan['id']; ?>" class="plan-ad-wrap hidden rounded-lg border border-gray-200 bg-white/70 p-4">
                                    <div id="plan-ad-media-<?php echo $plan['id']; ?>" class="plan-ad-media text-sm text-gray-500">
                                        Keine Werbung ausgewaehlt.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ausklappbares Aufguss-Formular -->
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                Neuen Aufguss zu "<?php echo htmlspecialchars($plan['name'] ?? ''); ?>" hinzufügen
                            </button>

                            <div id="form-<?php echo $plan['id']; ?>" class="hidden mt-6">
                                <!-- Zweispaltiges Layout für Formular und Hintergrundbild -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                                    <div class="grid grid-cols-1 gap-6">
                                        <!-- Linke Spalte: Aufguss-Formular -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <form method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="showLoading(<?php echo $plan['id']; ?>)">
                                                <!-- Versteckte Felder -->
                                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                <input type="hidden" name="datum" value="<?php echo date('Y-m-d'); ?>">

                                                <!-- Name des Aufgusses -->
                                                <div>
                                                    <label for="aufguss-name-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Name des Aufgusses</label>
                                                    <input type="text" id="aufguss-name-<?php echo $plan['id']; ?>" name="aufguss_name" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Wellness-Aufguss" />

                                                    <!-- Select für vorhandene Aufgüsse -->
                                                    <div class="mt-3">
                                                        <label for="aufguss-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Oder vorhandenen Aufguss auswählen:</label>
                                                        <select id="aufguss-select-<?php echo $plan['id']; ?>" name="select_aufguss_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Aufguss auswählen --</option>
                                                            <?php foreach ($aufguss_optionen as $a): ?>
                                                                <option class="text-center" value="<?php echo $a['id']; ?>">
                                                                    <?php echo htmlspecialchars($a['name'] ?? ''); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Zeitbereich auswählen -->
                                                <div class="text-center">
                                                    <label class="block text-sm font-medium text-gray-900 mb-2">Zeitbereich des Aufgusses</label>
                                                    <div class="flex justify-center items-center gap-4">
                                                        <div class="flex flex-col items-center">
                                                            <label for="zeit_anfang-<?php echo $plan['id']; ?>" class="text-sm font-semibold text-gray-900 mb-1">Anfang</label>
                                                            <input type="time" id="zeit_anfang-<?php echo $plan['id']; ?>" name="zeit_anfang"
                                                                class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid w-32"
                                                                style="border-color: var(--border-color)">
                                                        </div>
                                                        <div class="flex items-center text-gray-400">
                                                            <span class="text-sm">bis</span>
                                                        </div>
                                                        <div class="flex flex-col items-center">
                                                            <label for="zeit_ende-<?php echo $plan['id']; ?>" class="text-sm font-semibold text-gray-900 mb-1">Ende</label>
                                                            <input type="time" id="zeit_ende-<?php echo $plan['id']; ?>" name="zeit_ende"
                                                                class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid w-32"
                                                                style="border-color: var(--border-color)">
                                                        </div>
                                                    </div>
                                                    <!-- Für Abwärtskompatibilität: verstecktes zeit-Feld -->
                                                    <input type="hidden" id="zeit-<?php echo $plan['id']; ?>" name="zeit" value="">
                                                </div>

                                                <!-- Verwendete Duftmittel -->
                                                <div>
                                                    <label for="duftmittel-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Verwendete Duftmittel</label>
                                                    <input type="text" id="duftmittel-<?php echo $plan['id']; ?>" name="duftmittel" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Eukalyptus, Minze" />

                                                    <!-- Select für vorhandene Duftmittel -->
                                                    <div class="mt-3">
                                                        <label for="duftmittel-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Oder vorhandene Duftmittel auswählen:</label>
                                                        <select id="duftmittel-select-<?php echo $plan['id']; ?>" name="duftmittel_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Duftmittel auswählen --</option>
                                                            <?php foreach ($duftmittel as $d): ?>
                                                                <option class="text-center" value="<?php echo $d['id']; ?>">
                                                                    <?php echo htmlspecialchars($d['name'] ?? ''); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Sauna -->
                                                <div>
                                                    <label for="sauna-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Sauna</label>
                                                    <input type="text" id="sauna-<?php echo $plan['id']; ?>" name="sauna" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Finnische Sauna" />

                                                    <!-- Select für Sauna (Datenbank) -->
                                                    <div class="mt-3">
                                                        <label for="sauna-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Oder vorhandene Sauna auswählen:</label>
                                                        <select id="sauna-select-<?php echo $plan['id']; ?>" name="sauna_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Sauna auswählen --</option>
                                                            <?php foreach ($saunen as $s): ?>
                                                                <option class="text-center" value="<?php echo $s['id']; ?>" data-temperatur="<?php echo htmlspecialchars($s['temperatur'] ?? ''); ?>">
                                                                    <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mt-3">
                                                        <label for="sauna-temperatur-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Temperatur (C)</label>
                                                        <input type="number" id="sauna-temperatur-<?php echo $plan['id']; ?>" name="sauna_temperatur" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. 90" min="0" step="1" />
                                                    </div>
                                                </div>

                                                <!-- Name des Aufgießers -->
                                                <div>
                                                    <label for="aufgieser-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Name des Aufgießers</label>
                                                    <input type="text" id="aufgieser-<?php echo $plan['id']; ?>" name="aufgieser" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Max Mustermann" />

                                                    <!-- Select für Mitarbeiter (Datenbank) -->
                                                    <div class="mt-3">
                                                        <label for="mitarbeiter-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Oder vorhandenen Mitarbeiter auswählen:</label>
                                                        <select id="mitarbeiter-select-<?php echo $plan['id']; ?>" name="mitarbeiter_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Mitarbeiter auswählen --</option>
                                                            <?php foreach ($mitarbeiter as $m): ?>
                                                                <option class="text-center" value="<?php echo $m['id']; ?>">
                                                                    <?php echo htmlspecialchars($m['name'] ?? ''); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="border-t border-gray-200 pt-4 mt-4">
                                                        <label class="block text-sm font-medium text-gray-900 mb-2 text-center">Mehrere Aufgiesser</label>
                                                        <label for="mitarbeiter-multi-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Mitarbeiter auswaehlen (Mehrfachauswahl)</label>
                                                        <select id="mitarbeiter-multi-<?php echo $plan['id']; ?>" name="mitarbeiter_ids[]" multiple class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <?php foreach ($mitarbeiter as $m): ?>
                                                                <option class="text-center" value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name'] ?? ''); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <p class="text-xs text-gray-500 mt-2 text-center">Mehrere Namen mit Strg/Cmd auswaehlen.</p>

                                                    </div>
                                                </div>

                                                <!-- Stärke des Aufgusses -->
                                                <div>
                                                    <label for="staerke-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Stärke des Aufgusses</label>
                                                    <select id="staerke-<?php echo $plan['id']; ?>" name="staerke" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                        <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Stärke wählen --</option>
                                                        <option class="text-center" value="1">1 - Sehr leicht</option>
                                                        <option class="text-center" value="2">2 - Leicht</option>
                                                        <option class="text-center" value="3">3 - Mittel</option>
                                                        <option class="text-center" value="4">4 - Stark</option>
                                                        <option class="text-center" value="5">5 - Sehr stark</option>
                                                        <option class="text-center" value="6">6 - Extrem stark</option>
                                                    </select>
                                                </div>

                                                <!-- Bilder hochladen -->
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Bilder hochladen</h3>
                                                    <div class="space-y-4">
                                                        <!-- Bild der Sauna -->
                                                        <div>
                                                            <label for="sauna-bild-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2">Bild der Sauna</label>
                                                            <label for="sauna-bild-<?php echo $plan['id']; ?>" class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                                <div class="text-center pointer-events-none">
                                                                    <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                                        <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                                    </svg>
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Sauna-Bild hochladen</span>
                                                                        <input id="sauna-bild-<?php echo $plan['id']; ?>" name="sauna_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('sauna', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="sauna-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgewaehlte Datei: <span id="sauna-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('sauna', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex">oder ziehen und ablegen</p>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                                                                </div>
                                                            </label>
                                                        </div>

                                                        <!-- Bild des Mitarbeiters -->
                                                        <div>
                                                            <label for="mitarbeiter-bild-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2">Bild des Mitarbeiters</label>
                                                            <label for="mitarbeiter-bild-<?php echo $plan['id']; ?>" class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                                <div class="text-center pointer-events-none">
                                                                    <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                                        <path d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                                    </svg>
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Mitarbeiter-Bild hochladen</span>
                                                                        <input id="mitarbeiter-bild-<?php echo $plan['id']; ?>" name="mitarbeiter_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('mitarbeiter', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="mitarbeiter-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgewaehlte Datei: <span id="mitarbeiter-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('mitarbeiter', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex">oder ziehen und ablegen</p>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Ladebalken -->
                                                <div id="loading-bar-<?php echo $plan['id']; ?>" class="hidden pt-4">
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                                    </div>
                                                    <p class="text-sm font-semibold text-gray-900 mt-1 text-center">Laedt...</p>
                                                </div>

                                                <!-- Buttons -->
                                                <div class="flex items-center justify-end gap-x-6 pt-4">
                                                    <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" class="text-sm font-semibold text-gray-900 hover:text-gray-700">Abbrechen</button>
                                                    <button type="submit" id="submit-btn-<?php echo $plan['id']; ?>" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Speichern</button>
                                                </div>
                                            </form>
                                            <div class="mt-6 border-t border-gray-200 pt-4">
                                                <h3 class="text-lg font-semibold text-gray-900 mb-3 text-center">Farben</h3>
                                                <label for="next-aufguss-theme-color-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2 text-center">
                                                    Farbe fuer Uhr, Header und Row-Hervorhebung
                                                </label>
                                                <div class="flex items-center justify-center gap-4">
                                                    <input id="next-aufguss-theme-color-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="color" class="h-10 w-20 rounded border border-gray-300 bg-white shadow-sm cursor-pointer">
                                                    <span class="text-xs text-gray-500">Wird im Aufgussplan angezeigt</span>
                                                </div>
                                            </div>
                                    </div>

                                </div>

                                <!-- Rechte Spalte: Plan-Hintergrundbild -->
                                <div class="bg-gray-50 p-4 rounded-lg self-start h-fit">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Plan-Hintergrundbild</h3>
                                    <div class="rounded-lg overflow-hidden border border-gray-200 bg-white">
                                        <?php if (!empty($plan['hintergrund_bild'])): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($plan['hintergrund_bild']); ?>"
                                                alt="Plan Hintergrundbild"
                                                class="w-full h-48 object-cover">
                                        <?php else: ?>
                                            <div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">
                                                Kein Hintergrundbild vorhanden
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="plan-background-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700">Vorhandenes Hintergrundbild auswaehlen</label>
                                        <select id="plan-background-select-<?php echo $plan['id']; ?>" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanBackground(<?php echo $plan['id']; ?>)">
                                            <option value="">-- Hintergrundbild waehlen --</option>
                                            <?php foreach ($hintergrundOptions as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option['path']); ?>" <?php echo ($plan['hintergrund_bild'] ?? '') === $option['path'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($option['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <label for="plan-bild-<?php echo $plan['id']; ?>" class="upload-area flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                            <div class="text-center pointer-events-none">
                                                <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                    <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                </svg>
                                                <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                    <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Hintergrundbild hochladen</span>
                                                    <input id="plan-bild-<?php echo $plan['id']; ?>" name="plan_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('plan', <?php echo $plan['id']; ?>)" />
                                                    <div id="plan-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                        <span>Ausgewaehlte Datei: <span id="plan-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                        <button type="button" onclick="removeFile('plan', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <p class="pl-1 flex">oder ziehen und ablegen</p>
                                                </div>
                                                <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                                            </div>
                                        </label>

                                        <button type="button" id="plan-upload-btn-<?php echo $plan['id']; ?>" onclick="uploadPlanBackgroundImage(<?php echo $plan['id']; ?>)" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                            Hochladen
                                        </button>

                                        <?php if (!empty($plan['hintergrund_bild'])): ?>
                                            <button type="button" onclick="deletePlanBackgroundImage(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')" class="w-full rounded-md bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
                                                Hintergrundbild loeschen
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="w-full rounded-md bg-red-200 px-4 py-2 text-sm font-semibold text-white shadow-sm cursor-not-allowed" disabled>
                                                Hintergrundbild loeschen
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-6 border-t border-gray-200 pt-4 space-y-4" data-plan-id="<?php echo $plan['id']; ?>">
                                        <h4 class="text-base font-semibold text-gray-900 text-center">Naechster Aufguss Popup</h4>
                                        <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                                            <input id="next-aufguss-enabled-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="checkbox" class="sr-only peer">
                                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                            <span>Popup aktivieren</span>
                                        </label>
                                        <div id="next-aufguss-settings-fields-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" class="space-y-4">
                                            <div>
                                                <label for="next-aufguss-lead-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Sekunden vorher anzeigen</label>
                                                <input id="next-aufguss-lead-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="5">
                                            </div>
                                            <button id="next-aufguss-preview-btn-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="button" onclick="previewNextAufgussPopup(<?php echo $plan['id']; ?>)" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Vorschau anzeigen
                                            </button>
                                        </div>
                                        <div class="mt-4 border-t border-gray-200 pt-4 space-y-3">
                                            <h5 class="text-sm font-semibold text-gray-900">Anzeige-Optionen</h5>
                                            <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                                                <input id="next-aufguss-highlight-enabled-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="checkbox" class="sr-only peer">
                                                <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                                    <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>Row hervorheben</span>
                                            </label>
                                            <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                                                <input id="next-aufguss-clock-enabled-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="checkbox" class="sr-only peer">
                                                <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                                    <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>Digitale Uhr anzeigen</span>
                                            </label>
                                            <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                                                <input id="next-aufguss-banner-enabled-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="checkbox" class="sr-only peer">
                                                <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                                    <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>Banner anzeigen</span>
                                            </label>
                                            <button type="button" onclick="openPlanBannerModal(<?php echo $plan['id']; ?>)" class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 border border-gray-200 hover:bg-gray-50">
                                                Info-Banner unter Uhr bearbeiten
                                            </button>
                                        </div>
                                        <div class="mt-4 border-t border-gray-200 pt-4 space-y-4">
                                            <h5 class="text-sm font-semibold text-gray-900">Werbung</h5>
                                            <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                                                <input id="plan-ad-enabled-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="checkbox" class="sr-only peer" <?php echo !empty($plan['werbung_aktiv']) ? 'checked' : ''; ?>>
                                                <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                                    <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>Werbung aktivieren</span>
                                            </label>
                                            <div id="plan-ad-settings-fields-<?php echo $plan['id']; ?>" data-ad-path="<?php echo htmlspecialchars($plan['werbung_media'] ?? ''); ?>" data-ad-type="<?php echo htmlspecialchars($plan['werbung_media_typ'] ?? ''); ?>" class="space-y-4">
                                                <div id="plan-ad-preview-<?php echo $plan['id']; ?>" class="rounded-lg overflow-hidden border border-gray-200 bg-white">
                                                    <?php if (!empty($plan['werbung_media'])): ?>
                                                        <?php if (($plan['werbung_media_typ'] ?? '') === 'video'): ?>
                                                            <video src="../uploads/<?php echo htmlspecialchars($plan['werbung_media']); ?>" class="w-full h-48 object-contain" controls loop></video>
                                                        <?php else: ?>
                                                            <img src="../uploads/<?php echo htmlspecialchars($plan['werbung_media']); ?>"
                                                                alt="Werbung"
                                                                class="w-full h-48 object-contain">
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">
                                                            Keine Werbung vorhanden
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="space-y-2">
                                                    <label for="plan-ad-select-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700">Vorhandene Werbung auswaehlen</label>
                                                    <select id="plan-ad-select-<?php echo $plan['id']; ?>" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanAdMedia(<?php echo $plan['id']; ?>)">
                                                        <option value="">-- Werbung waehlen --</option>
                                                        <?php foreach ($werbungOptions as $option): ?>
                                                            <option value="<?php echo htmlspecialchars($option['path']); ?>" data-type="<?php echo htmlspecialchars($option['type']); ?>" <?php echo ($plan['werbung_media'] ?? '') === $option['path'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($option['label']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="space-y-3">
                                                    <label for="plan-ad-file-<?php echo $plan['id']; ?>" class="upload-area flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                        <div class="text-center pointer-events-none">
                                                            <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                                <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                            </svg>
                                                            <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Werbung hochladen</span>
                                                                <input id="plan-ad-file-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="file" accept="image/*,video/*" class="sr-only" onchange="updateAdFileName(<?php echo $plan['id']; ?>)" />
                                                                <div id="plan-ad-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                    <span>Ausgewaehlte Datei: <span id="plan-ad-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                    <button type="button" onclick="removeAdFile(<?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                <p class="pl-1 flex">oder ziehen und ablegen</p>
                                                            </div>
                                                            <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF, MP4, WEBM, OGG bis zu 50MB</p>
                                                        </div>
                                                    </label>
                                                    <?php if (!empty($plan['werbung_media'])): ?>
                                                        <div id="plan-ad-file-info-<?php echo $plan['id']; ?>" class="text-sm font-semibold text-gray-900">
                                                            Aktuelle Datei: <?php echo htmlspecialchars(basename($plan['werbung_media'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div id="plan-ad-file-info-<?php echo $plan['id']; ?>" class="text-xs text-gray-500">
                                                            Keine Datei gespeichert.
                                                        </div>
                                                    <?php endif; ?>

                                                    <button type="button" id="plan-ad-upload-btn-<?php echo $plan['id']; ?>" onclick="uploadPlanAdMedia(<?php echo $plan['id']; ?>)" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                                        Hochladen
                                                    </button>

                                                    <?php if (!empty($plan['werbung_media'])): ?>
                                                        <button type="button" id="plan-ad-delete-btn-<?php echo $plan['id']; ?>" onclick="deletePlanAdMedia(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')" class="w-full rounded-md bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
                                                            Werbung loeschen
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" id="plan-ad-delete-btn-<?php echo $plan['id']; ?>" class="w-full rounded-md bg-red-200 px-4 py-2 text-sm font-semibold text-white shadow-sm cursor-not-allowed" disabled>
                                                            Werbung loeschen
                                                        </button>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="border-t border-gray-200 pt-4 space-y-3">
                                                    <div>
                                                        <label for="plan-ad-interval-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Intervall (Minuten)</label>
                                                        <input id="plan-ad-interval-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="<?php echo htmlspecialchars($plan['werbung_interval_minuten'] ?? 10); ?>">
                                                    </div>
                                                    <div>
                                                        <label for="plan-ad-duration-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Anzeigedauer (Sekunden)</label>
                                                        <input id="plan-ad-duration-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="<?php echo htmlspecialchars($plan['werbung_dauer_sekunden'] ?? 10); ?>">
                                                    </div>
                                                    <button id="plan-ad-preview-btn-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="button" onclick="previewPlanAd(<?php echo $plan['id']; ?>)" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        Vorschau anzeigen
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="lg:col-span-2 mt-6 border-t border-gray-200 pt-4">
                                <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Einstellungen einklappen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Übersicht aller gespeicherten Daten -->
        <div class="bg-white rounded-lg shadow-md mt-8">
            <div class="p-6">
                <h2 class="text-3xl font-bold text-gray-900 mb-6">Datenbank-Übersicht</h2>

                <!-- Tabs für verschiedene Datenarten -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="showTab('aufguesse')" id="tab-aufguesse" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">
                                Aufgüsse (<?php echo count($aufguss_optionen); ?>)
                            </button>
                            <button onclick="showTab('saunen')" id="tab-saunen" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Saunen (<?php echo count($saunen); ?>)
                            </button>
                            <button onclick="showTab('duftmittel')" id="tab-duftmittel" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Duftmittel (<?php echo count($duftmittel); ?>)
                            </button>
                            <button onclick="showTab('mitarbeiter')" id="tab-mitarbeiter" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Mitarbeiter (<?php echo count($mitarbeiter); ?>)
                            </button>
                            <button onclick="showTab('werbung')" id="tab-werbung" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Werbung (<?php echo count($werbungTabFiles); ?>)
                            </button>
                            <button onclick="showTab('hintergrund')" id="tab-hintergrund" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Hintergrund (<?php echo count($hintergrundTabFiles); ?>)
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Aufgüsse Tab -->
                <div id="content-aufguesse" class="tab-content">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neuen Aufgussnamen anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_aufguss_name">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Aufgussname</label>
                                <input type="text" name="aufguss_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Citrus-Explosion" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="aufguss_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-500">Aufguss speichern</button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($aufguss_optionen)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Aufguesse in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($aufguss_optionen as $aufguss): ?>
                                        <tr class="bg-white/5" data-aufguss-name-id="<?php echo $aufguss['id']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($aufguss['id']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap aufguss-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($aufguss['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="aufguss_name" value="<?php echo htmlspecialchars($aufguss['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap aufguss-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($aufguss['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="aufguss_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($aufguss['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('aufguss', <?php echo $aufguss['id']; ?>, '<?php echo htmlspecialchars($aufguss['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Aufguss löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Saunen Tab -->
                <div id="content-saunen" class="tab-content hidden">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neue Sauna anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_sauna">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input type="text" name="sauna_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Finnische Sauna" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="sauna_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Temperatur (°C)</label>
                                <input type="number" name="sauna_temperatur" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. 90" min="0" step="1">
                            </div>
                            <div class="md:col-span-3 flex justify-end">
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-500">Sauna speichern</button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bild
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Temperatur
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($saunen)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Saunen in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($saunen as $sauna): ?>
                                        <tr class="bg-white/5" data-sauna-id="<?php echo $sauna['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($sauna['id']); ?>
                                            </td>

                                            <!-- Bild -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <?php if (!empty($sauna['bild'])): ?>
                                                        <img src="../uploads/<?php echo htmlspecialchars($sauna['bild']); ?>"
                                                            alt="Sauna-Bild"
                                                            class="h-8 w-8 rounded-full object-cover border border-gray-200 cursor-pointer hover:border-indigo-400 transition-colors"
                                                            onclick="openImageModal('sauna', <?php echo $sauna['id']; ?>, '<?php echo htmlspecialchars($sauna['name']); ?>')">
                                                    <?php else: ?>
                                                        <div class="h-8 w-8 bg-green-100 rounded-full flex items-center justify-center cursor-pointer hover:bg-green-200 transition-colors"
                                                            onclick="openImageModal('sauna', <?php echo $sauna['id']; ?>, '<?php echo htmlspecialchars($sauna['name']); ?>')">
                                                            <span class="text-green-600 text-sm">🏠</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($sauna['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="sauna_name" value="<?php echo htmlspecialchars($sauna['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'name')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Beschreibung (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($sauna['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="sauna_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($sauna['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Temperatur -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-temp-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')">
                                                    <span>
                                                        <?php if ($sauna['temperatur'] !== null && $sauna['temperatur'] !== ''): ?>
                                                            <?php echo (int)$sauna['temperatur']; ?>&deg;C
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="number" name="sauna_temperatur" value="<?php echo htmlspecialchars($sauna['temperatur'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300" min="0" step="1" placeholder="z.B. 90">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('sauna', <?php echo $sauna['id']; ?>, '<?php echo htmlspecialchars($sauna['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Sauna löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Duftmittel Tab -->
                <div id="content-duftmittel" class="tab-content hidden">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neues Duftmittel anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_duftmittel">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input type="text" name="duftmittel_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Eukalyptus" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="duftmittel_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-500">Duftmittel speichern</button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($duftmittel)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Duftmittel in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($duftmittel as $dm): ?>
                                        <tr class="bg-white/5" data-duftmittel-id="<?php echo $dm['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($dm['id']); ?>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($dm['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="duftmittel_name" value="<?php echo htmlspecialchars($dm['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Beschreibung (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($dm['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="duftmittel_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($dm['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('duftmittel', <?php echo $dm['id']; ?>, '<?php echo htmlspecialchars($dm['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Duftmittel löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mitarbeiter Tab -->
                <div id="content-mitarbeiter" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bild
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($mitarbeiter)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Mitarbeiter in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mitarbeiter as $mitarbeiter_item): ?>
                                        <tr class="bg-white/5" data-mitarbeiter-id="<?php echo $mitarbeiter_item['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($mitarbeiter_item['id']); ?>
                                            </td>

                                            <!-- Bild -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <?php if (!empty($mitarbeiter_item['bild'])): ?>
                                                        <img src="../uploads/<?php echo htmlspecialchars($mitarbeiter_item['bild']); ?>"
                                                            alt="Mitarbeiter-Bild"
                                                            class="h-8 w-8 rounded-full object-cover border border-gray-200 cursor-pointer hover:border-indigo-400 transition-colors"
                                                            onclick="openImageModal('mitarbeiter', <?php echo $mitarbeiter_item['id']; ?>, '<?php echo htmlspecialchars($mitarbeiter_item['name']); ?>')">
                                                    <?php else: ?>
                                                        <div class="h-8 w-8 bg-gray-300 rounded-full flex items-center justify-center cursor-pointer hover:bg-gray-400 transition-colors"
                                                            onclick="openImageModal('mitarbeiter', <?php echo $mitarbeiter_item['id']; ?>, '<?php echo htmlspecialchars($mitarbeiter_item['name']); ?>')">
                                                            <span class="text-gray-700 font-semibold text-xs">
                                                                <?php echo strtoupper(substr($mitarbeiter_item['name'] ?? 'U', 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap mitarbeiter-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="mitarbeiter_name" value="<?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">✓ Speichern</button>
                                                        <button onclick="cancelMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('mitarbeiter', <?php echo $mitarbeiter_item['id']; ?>, '<?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Mitarbeiter löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Werbung Tab -->
                <div id="content-werbung" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Vorschau
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Datei
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Typ
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bereich
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($werbungTabFiles)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Werbung in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($werbungTabFiles as $file): ?>
                                        <tr class="bg-white/5">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $isVideo = stripos($file['typ'], 'video') !== false;
                                                $fileRelPath = $file['datei'] ?? '';
                                                $filePath = '../uploads/' . $fileRelPath;
                                                ?>
                                                <?php if ($isVideo): ?>
                                                    <video src="<?php echo htmlspecialchars($filePath); ?>" class="h-12 w-20 object-cover rounded border border-gray-200" muted></video>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($filePath); ?>" alt="Datei" class="h-12 w-12 object-cover rounded border border-gray-200">
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(basename($file['datei'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['typ']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['bereich']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button type="button"
                                                    onclick="deleteUploadFile('werbung', <?php echo htmlspecialchars(json_encode($fileRelPath), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Datei loeschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Hintergrund Tab -->
                <div id="content-hintergrund" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Vorschau
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Datei
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Typ
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bereich
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($hintergrundTabFiles)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Hintergrundbilder in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($hintergrundTabFiles as $file): ?>
                                        <tr class="bg-white/5">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $isVideo = stripos($file['typ'], 'video') !== false;
                                                $filePath = '../uploads/' . $file['datei'];
                                                ?>
                                                <?php if ($isVideo): ?>
                                                    <video src="<?php echo htmlspecialchars($filePath); ?>" class="h-12 w-20 object-cover rounded border border-gray-200" muted></video>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($filePath); ?>" alt="Datei" class="h-12 w-12 object-cover rounded border border-gray-200">
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(basename($file['datei'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['typ']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['bereich']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button type="button"
                                                    onclick="deleteUploadFile('plan', <?php echo htmlspecialchars(json_encode($fileRelPath), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Datei loeschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan-Banner Modal -->
    <div id="planBannerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-xl shadow-lg rounded-md bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Info-Banner unter der Uhr</h3>
                <button type="button" onclick="closePlanBannerModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Schliessen</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <input type="hidden" id="planBannerPlanId" value="">
            <div class="space-y-4">
                <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                    <input id="planBannerEnabled" type="checkbox" class="sr-only peer">
                    <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                        <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <span>Banner anzeigen</span>
                </label>
                <div>
                    <label for="planBannerText" class="block text-sm font-medium text-gray-700 mb-1">Banner-Text (optional)</label>
                    <textarea id="planBannerText" rows="5" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Text fuer den Info-Banner" style="width: 220px; box-sizing: border-box;"></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Banner-Inhalt</label>
                    <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                        <input id="planBannerModeText" type="radio" name="planBannerMode" value="text" class="sr-only peer" checked>
                        <span class="h-4 w-4 rounded-full border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                            <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span>Text anzeigen</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                        <input id="planBannerModeImage" type="radio" name="planBannerMode" value="image" class="sr-only peer">
                        <span class="h-4 w-4 rounded-full border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                            <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span>Bild anzeigen</span>
                    </label>
                </div>
                <input id="planBannerImage" type="hidden">
                <div>
                    <label for="planBannerImageSelect" class="block text-sm font-medium text-gray-700 mb-1">Vorhandene Werbung auswaehlen</label>
                    <select id="planBannerImageSelect" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanBannerImage()">
                        <option value="">-- Werbung waehlen --</option>
                        <?php foreach ($werbungOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option['path']); ?>">
                                <?php echo htmlspecialchars($option['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-3">
                    <label for="planBannerFile" class="upload-area flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                        <div class="text-center pointer-events-none">
                            <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                            </svg>
                            <div class="mt-2 flex flex-col text-lg text-gray-600">
                                <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Banner-Bild hochladen</span>
                                <input id="planBannerFile" type="file" accept="image/*" class="sr-only" onchange="updatePlanBannerFileName()" />
                                <div id="plan-banner-filename" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                    <span>Ausgewaehlte Datei: <span id="plan-banner-filename-text"></span></span>
                                    <button type="button" onclick="removePlanBannerFile()" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="pl-1 flex">oder ziehen und ablegen</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                        </div>
                    </label>
                    <button type="button" onclick="uploadPlanBannerImage()" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Hochladen
                    </button>
                    <p class="text-xs text-gray-500">Hinweis: Fuer volle Seitenhoehe sollte das Bild etwa 1080px hoch sein (Full-HD).</p>
                </div>
                <p class="text-xs text-gray-500">Der Banner passt seine Hoehe automatisch an den Text an.</p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closePlanBannerModal()" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-200">Abbrechen</button>
                    <button type="button" onclick="savePlanBannerSettings()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bild-Upload Modal -->
    <div id="imageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Bild hochladen</h3>
                    <button onclick="closeImageModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Schliessen</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="imageUploadForm" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" id="modalEntityType" name="entity_type" value="">
                    <input type="hidden" id="modalEntityId" name="entity_id" value="">

                    <!-- Bild hochladen -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-2">Neues Bild auswaehlen</label>
                        <div class="mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6">
                            <div class="text-center">
                                <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                    <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                                <div class="mt-2 flex flex-col text-lg text-gray-600">
                                    <label for="modalImageInput" class="relative cursor-pointer rounded-md bg-transparent font-semibold text-indigo-600 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-indigo-600 hover:text-indigo-500">
                                        <span>Bild auswaehlen</span>
                                        <input id="modalImageInput" name="bild" type="file" accept="image/*" class="sr-only" onchange="updateModalFileName()" />
                                    </label>
                                    <!-- Dateiname-Anzeige -->
                                    <div id="modalFilename" class="mt-2 text-xs text-green-600 font-medium hidden">
                                        Ausgewaehlte Datei: <span id="modalFilenameText"></span>
                                        <button type="button" onclick="removeModalFile()" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="pl-1 flex">oder ziehen und ablegen</p>
                                </div>
                                <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Ladebalken -->
                    <div id="modalLoadingBar" class="hidden">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 mt-1 text-center">Laedt...</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-x-6 pt-4">
                        <button type="button" onclick="closeImageModal()" class="text-sm font-semibold text-gray-900 hover:text-gray-700">Abbrechen</button>
                        <button type="submit" id="modalSubmitBtn" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">Hochladen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Naechster Aufguss Popup -->
    <div id="next-aufguss-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Naechster Aufguss</h3>
                <button type="button" onclick="closeNextAufgussPopup()" class="text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Schliessen</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="next-aufguss-body" class="p-5">
                <div class="text-sm text-gray-500">Laedt...</div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/admin-functions.js"></script>
    <script>
        // Tab-Funktionalität für die Datenbank-Übersicht
        function showTab(tabName) {
            // Verstecke alle Tab-Inhalte
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Entferne aktive Tab-Stile
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-indigo-500', 'text-indigo-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Zeige ausgewählten Tab-Inhalt
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Setze aktiven Tab-Stil
            document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
            document.getElementById('tab-' + tabName).classList.add('border-indigo-500', 'text-indigo-600');
        }

        // Löschfunktion für Datenbank-Einträge
        function deleteDatenbankEintrag(type, id, name) {
            if (confirm('Möchten Sie wirklich "' + name + '" löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                // Erstelle ein Formular für den DELETE-Request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'deletes/delete-entry.php'; // Neue PHP-Datei für Lösch-Operationen

                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'type';
                typeInput.value = type;

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;

                form.appendChild(typeInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Dateiname-Feedback für Bild-Uploads
        function updateFileName(type, planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            if (loadingBar) {
                showLoading(planId);
            }

            const input = document.getElementById(`${type}-bild-${planId}`);
            const filenameDiv = document.getElementById(`${type}-filename-${planId}`);
            const filenameText = document.getElementById(`${type}-filename-text-${planId}`);

            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                // Dateigröße validieren
                if (fileSize > 10) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu groß! Max. 10MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else {
                filenameDiv.classList.add('hidden');
            }

            if (loadingBar) {
                hideLoading(planId);
            }
        }

        // Datei entfernen
        function removeFile(type, planId) {
            const input = document.getElementById(`${type}-bild-${planId}`);
            const filenameDiv = document.getElementById(`${type}-filename-${planId}`);

            // Datei-Eingabe zurücksetzen
            input.value = '';

            // Feedback ausblenden
            filenameDiv.classList.add('hidden');

            // Optional: Benutzer benachrichtigen
            console.log(`${type}-Bild für Plan ${planId} entfernt`);
        }

        function updateAdFileName(planId) {
            const input = document.getElementById(`plan-ad-file-${planId}`);
            const filenameDiv = document.getElementById(`plan-ad-filename-${planId}`);
            const filenameText = document.getElementById(`plan-ad-filename-text-${planId}`);

            if (input && input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                if (fileSize > 50) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu gross! Max. 50MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else if (filenameDiv) {
                filenameDiv.classList.add('hidden');
            }
        }

        function removeAdFile(planId) {
            const input = document.getElementById(`plan-ad-file-${planId}`);
            const filenameDiv = document.getElementById(`plan-ad-filename-${planId}`);
            if (input) input.value = '';
            if (filenameDiv) filenameDiv.classList.add('hidden');
        }


        // Ladebalken beim Formular-Submit
        function showLoading(planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            const submitBtn = document.getElementById(`submit-btn-${planId}`);
            const progressBar = loadingBar.querySelector('div');

            // Ladebalken anzeigen
            loadingBar.classList.remove('hidden');

            // Button deaktivieren
            submitBtn.disabled = true;
            submitBtn.textContent = 'Lädt...';

            // Animierter Ladebalken
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90; // Nicht auf 100% gehen, bis wirklich fertig

                progressBar.style.width = progress + '%';
            }, 200);

            // Interval-ID speichern für späteres Stoppen
            loadingBar.dataset.intervalId = interval;
        }

        // Ladebalken ausblenden
        function hideLoading(planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            const submitBtn = document.getElementById(`submit-btn-${planId}`);
            const progressBar = loadingBar.querySelector('div');

            // Ladebalken auf 100% setzen
            progressBar.style.width = '100%';

            // Nach kurzer Verzögerung ausblenden
            setTimeout(() => {
                loadingBar.classList.add('hidden');
                progressBar.style.width = '0%';

                // Button wieder aktivieren
                submitBtn.disabled = false;
                submitBtn.textContent = 'Speichern';

                // Interval stoppen falls noch läuft
                if (loadingBar.dataset.intervalId) {
                    clearInterval(loadingBar.dataset.intervalId);
                    delete loadingBar.dataset.intervalId;
                }
            }, 500);
        }

        // Inline-Editing für Saunen
        function toggleSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode`);
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);

            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        function cancelSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode`);
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        async function saveSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('updates/update_sauna.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: saunaId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode span`);
                    if (field === 'beschreibung') {
                        displayMode.textContent = (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;
                    } else if (field === 'temperatur') {
                        displayMode.textContent = (!newValue || newValue.trim() === '') ? '-' : `${parseInt(newValue, 10)}°C`;
                    } else {
                        displayMode.textContent = newValue;
                    }

                    cancelSaunaEdit(saunaId, field);
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing für Mitarbeiter
        function toggleMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);

            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        function cancelMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        async function saveMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);
            const input = editMode.querySelector('input');
            const newValue = input.value;

            try {
                const response = await fetch('updates/update_mitarbeiter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: mitarbeiterId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode span`);
                    displayMode.textContent = newValue;

                    cancelMitarbeiterEdit(mitarbeiterId, field);
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing fuer Aufgussnamen
        function toggleAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        function cancelAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        async function saveAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('updates/update_aufguss_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: aufgussId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode span`);
                    displayMode.textContent = field === 'beschreibung' && (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;

                    cancelAufgussNameEdit(aufgussId, field);
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing für Duftmittel
        function toggleDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        function cancelDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        async function saveDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('updates/update_duftmittel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: duftmittelId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode span`);
                    displayMode.textContent = field === 'beschreibung' && (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;

                    cancelDuftmittelEdit(duftmittelId, field);
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Bild-Modal Funktionen
        let currentEntityType = '';
        let currentEntityId = '';

        function openImageModal(entityType, entityId, entityName) {
            currentEntityType = entityType;
            currentEntityId = entityId;

            let title = '';
            if (entityType === 'plan') {
                title = `Hintergrundbild für Plan "${entityName}" ändern`;
            } else {
                title = `Bild für ${entityName} ändern`;
            }

            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalEntityType').value = entityType;
            document.getElementById('modalEntityId').value = entityId;
            document.getElementById('imageModal').classList.remove('hidden');

            // Reset form
            document.getElementById('imageUploadForm').reset();
            document.getElementById('modalFilename').classList.add('hidden');
            document.getElementById('modalLoadingBar').classList.add('hidden');
            document.getElementById('modalSubmitBtn').disabled = false;
            document.getElementById('modalSubmitBtn').textContent = 'Hochladen';
        }

        async function deleteUploadFile(type, path) {
            if (!confirm('Datei wirklich loeschen?')) {
                return;
            }

            try {
                const response = await fetch('deletes/delete_upload_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type,
                        path
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Loeschen der Datei.');
                    return;
                }
                location.reload();
            } catch (error) {
                alert('Netzwerkfehler beim Loeschen der Datei.');
            }
        }

        async function deletePlanBackgroundImage(planId, planName) {
            if (!confirm(`Möchten Sie das Hintergrundbild für "${planName}" wirklich löschen?`)) {
                return;
            }

            try {
                const response = await fetch('deletes/delete_entity_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        entity_type: 'plan',
                        entity_id: planId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Löschen');
                console.error('Delete error:', error);
            }
        }

        async function uploadPlanBackgroundImage(planId) {
            const input = document.getElementById(`plan-bild-${planId}`);
            const submitBtn = document.getElementById(`plan-upload-btn-${planId}`);

            if (!input || !input.files || !input.files[0]) {
                alert('Bitte waehlen Sie ein Bild aus.');
                return;
            }

            const file = input.files[0];
            const fileSize = file.size / 1024 / 1024;
            if (fileSize > 10) {
                alert('Die Datei ist zu gross. Maximale Groesse: 10MB');
                return;
            }

            const formData = new FormData();
            formData.append('entity_type', 'plan');
            formData.append('entity_id', planId);
            formData.append('bild', file);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Laedt...';

            try {
                const response = await fetch('upload_entity_image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Hochladen: ' + (result.error || 'Unbekannter Fehler'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Hochladen';
                }
            } catch (error) {
                alert('Netzwerkfehler beim Hochladen');
                console.error('Upload error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Hochladen';
            }
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            currentEntityType = '';
            currentEntityId = '';
        }

        function updateModalFileName() {
            const input = document.getElementById('modalImageInput');
            const filenameDiv = document.getElementById('modalFilename');
            const filenameText = document.getElementById('modalFilenameText');

            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                // Dateigröße validieren
                if (fileSize > 10) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu groß! Max. 10MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else {
                filenameDiv.classList.add('hidden');
            }
        }

        function removeModalFile() {
            document.getElementById('modalImageInput').value = '';
            document.getElementById('modalFilename').classList.add('hidden');
        }

        // Modal Form Submit
        document.getElementById('imageUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = document.getElementById('modalSubmitBtn');
            const loadingBar = document.getElementById('modalLoadingBar');
            const progressBar = loadingBar.querySelector('div');

            // Validierung
            if (!formData.get('bild') || !formData.get('bild').name) {
                alert('Bitte wählen Sie ein Bild aus.');
                return;
            }

            // Dateigröße prüfen
            const fileSize = formData.get('bild').size / 1024 / 1024;
            if (fileSize > 10) {
                alert('Die Datei ist zu groß. Maximale Größe: 10MB');
                return;
            }

            // Ladezustand aktivieren
            submitBtn.disabled = true;
            submitBtn.textContent = 'Lädt...';
            loadingBar.classList.remove('hidden');

            // Animierter Ladebalken
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
            }, 200);

            try {
                const response = await fetch('upload_entity_image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                clearInterval(interval);
                progressBar.style.width = '100%';

                if (result.success) {
                    // Erfolg - Seite neu laden um Änderungen zu zeigen
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Fehler beim Hochladen: ' + (result.error || 'Unbekannter Fehler'));
                    loadingBar.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Hochladen';
                }
            } catch (error) {
                clearInterval(interval);
                alert('Netzwerkfehler beim Hochladen');
                loadingBar.classList.add('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Hochladen';
            }
        });

        // Modal außerhalb klicken zum schließen
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
        document.getElementById('planBannerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePlanBannerModal();
            }
        });

        // Verbesserte Drag & Drop Funktionalität
        document.addEventListener('DOMContentLoaded', function() {
            const dropZones = document.querySelectorAll('.border-dashed');

            dropZones.forEach(zone => {
                zone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('border-indigo-400', 'bg-indigo-50');
                });

                zone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-indigo-400', 'bg-indigo-50');
                });

                zone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-indigo-400', 'bg-indigo-50');

                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const input = this.querySelector('input[type="file"]');
                        if (input) {
                            input.files = files;
                            // Trigger change event
                            const event = new Event('change');
                            input.dispatchEvent(event);
                        }
                    }
                });
            });
        });

        // Naechster Aufguss Popup (5 Sekunden vor Start)
        const nextAufgussQueue = [];
        const nextAufgussShown = new Set();
        const nextAufgussSettings = new Map();
        let nextAufgussActive = false;
        let nextAufgussActivePlanId = null;
        let nextAufgussHideTimer = null;
        let nextAufgussCountdownTimer = null;
        let nextAufgussCountdownTarget = null;
        let nextAufgussIsPreview = false;
        const planAdSettings = new Map();
        const planAdMedia = new Map();
        const planAdIntervals = new Map();
        const planAdHideTimers = new Map();
        let adminClockTimer = null;

        function parseStartTime(text) {
            if (!text) return null;
            const match = text.match(/(\d{1,2})(?::(\d{2}))?/);
            if (!match) return null;
            const hour = parseInt(match[1], 10);
            const minute = match[2] ? parseInt(match[2], 10) : 0;

            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
            if (start.getTime() < now.getTime() - 1000) {
                start.setDate(start.getDate() + 1);
            }
            return start;
        }

        function updateNextAufgussRowHighlight() {
            const rows = Array.from(document.querySelectorAll('tr[data-aufguss-id]'));
            if (rows.length === 0) return;

            const rowsByPlan = new Map();
            rows.forEach(row => {
                const planId = row.getAttribute('data-plan-id');
                if (!planId) return;
                if (!rowsByPlan.has(planId)) rowsByPlan.set(planId, []);
                rowsByPlan.get(planId).push(row);
            });

            rowsByPlan.forEach(planRows => {
                const planId = planRows[0]?.getAttribute('data-plan-id');
                const settings = planId ? (nextAufgussSettings.get(String(planId)) || getPlanSettings(planId)) : null;
                if (settings && !settings.highlightEnabled) {
                    planRows.forEach(row => row.classList.remove('next-aufguss-row'));
                    return;
                }

                let nextRow = null;
                let nextTime = null;

                planRows.forEach(row => {
                    const timeText = row.querySelector('.zeit-cell .display-mode span')?.textContent?.trim();
                    const startTime = parseStartTime(timeText);
                    if (!startTime) return;

                    if (!nextTime || startTime.getTime() < nextTime) {
                        nextTime = startTime.getTime();
                        nextRow = row;
                    }
                });

                planRows.forEach(row => row.classList.remove('next-aufguss-row'));
                if (nextRow) {
                    nextRow.classList.add('next-aufguss-row');
                }
            });
        }

        function buildNextAufgussHtml(data) {
            const aufgussName = data.name || 'Aufguss';
            const staerke = data.staerke ? `Starke: ${data.staerke}` : 'Starke: -';
            const aufgieserRaw = data.aufgieser_name || '-';
            const aufgieserList = aufgieserRaw
                .split(',')
                .map(item => item.trim())
                .filter(Boolean);
            const aufgieser = aufgieserList.length > 1 ? aufgieserList.join('<br>') : aufgieserRaw;
            const saunaName = data.sauna_name || 'Sauna: -';
            const saunaTempText = (data.sauna_temperatur !== null && data.sauna_temperatur !== undefined && data.sauna_temperatur !== '')
                ? String(data.sauna_temperatur)
                : '';
            const saunaTempLine = saunaTempText ? `Temperatur: ${saunaTempText}&deg;C` : 'Temperatur: -';
            const duftmittel = data.duftmittel_name || 'Duftmittel: -';

            const aufgieserItems = (data.aufgieser_items || '')
                .split(';;')
                .map(item => item.split('||'))
                .filter(parts => parts[0] && parts[0].trim() !== '');
            const aufgieserImages = aufgieserItems.map(parts => {
                const name = parts[0] ? parts[0].trim() : 'Aufgieser';
                const bild = parts[1] ? parts[1].trim() : '';
                const img = bild
                    ? `<img src="../uploads/${bild}" alt="${name}" class="w-full h-40 object-contain rounded-lg bg-gray-100">`
                    : `<div class="w-full h-40 rounded-lg bg-gray-100 flex items-center justify-center text-xs text-gray-500">Kein Bild</div>`;
                return `<div class="flex flex-col gap-2 text-center"><div>${img}</div><div class="text-sm font-semibold text-gray-900">${name}</div></div>`;
            });

            const mitarbeiterImg = aufgieserImages.length > 0
                ? `<div class="flex flex-col gap-3">${aufgieserImages.join('')}</div>`
                : (data.mitarbeiter_bild
                    ? `<img src="../uploads/${data.mitarbeiter_bild}" alt="Aufgieser" class="w-full h-72 object-contain rounded-lg bg-gray-100">`
                    : `<div class="w-full h-72 rounded-lg bg-gray-100 flex items-center justify-center text-sm text-gray-500">Kein Aufgieser-Bild</div>`);

            const saunaBadge = saunaTempText
                ? `<span class="absolute -top-2 -right-4 bg-white text-sm leading-none px-3 py-1.5 rounded-full border border-gray-200 text-gray-700">${saunaTempText}&deg;C</span>`
                : '';
            const saunaImg = data.sauna_bild ?
                `<div class="relative">${saunaBadge}<img src="../uploads/${data.sauna_bild}" alt="Sauna" class="w-full h-72 object-contain rounded-lg bg-gray-100"></div>` :
                `<div class="w-full h-72 rounded-lg bg-gray-100 flex items-center justify-center text-sm text-gray-500">Kein Sauna-Bild</div>`;

            return `
                <div class="relative flex flex-col gap-4">
                    <div class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none">
                        <div class="text-8xl font-bold text-gray-900 bg-white/80 border border-white/80 rounded-full px-10 py-4 shadow-lg" id="next-aufguss-countdown">--</div>
                    </div>
                    <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-6 min-h-[70vh]">
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-col gap-1">
                                <div class="text-3xl font-bold text-gray-900">${aufgussName}</div>
                                <div class="text-lg text-gray-600">${staerke}</div>
                                                                <div class="text-lg text-gray-600">Duftmittel: ${duftmittel}</div>
                                <div class="text-lg text-gray-600">${saunaTempLine}</div>
                            </div>
                            <div class="flex flex-col gap-2">
                                ${saunaImg}
                                <div class="text-sm font-semibold text-gray-900 text-center">${saunaName}</div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3">
                            ${mitarbeiterImg}
                        </div>
                    </div>
                </div>
            `;
        }

        function getPlanSettings(planId) {
            const keyEnabled = `nextAufgussEnabled_${planId}`;
            const keyLead = `nextAufgussLeadSeconds_${planId}`;
            const keyHighlight = `nextAufgussHighlightEnabled_${planId}`;
            const keyClock = `nextAufgussClockEnabled_${planId}`;
            const keyBannerEnabled = `nextAufgussBannerEnabled_${planId}`;
            const keyBannerMode = `nextAufgussBannerMode_${planId}`;
            const keyBannerText = `nextAufgussBannerText_${planId}`;
            const keyBannerImage = `nextAufgussBannerImage_${planId}`;
            const keyBannerHeight = `nextAufgussBannerHeight_${planId}`;
            const keyBannerWidth = `nextAufgussBannerWidth_${planId}`;
            const keyThemeColor = `nextAufgussThemeColor_${planId}`;
            const enabled = localStorage.getItem(keyEnabled);
            const leadSeconds = localStorage.getItem(keyLead);
            const highlightEnabled = localStorage.getItem(keyHighlight);
            const clockEnabled = localStorage.getItem(keyClock);
            const bannerEnabled = localStorage.getItem(keyBannerEnabled);
            const bannerMode = localStorage.getItem(keyBannerMode);
            const bannerText = localStorage.getItem(keyBannerText);
            const bannerImage = localStorage.getItem(keyBannerImage);
            const bannerHeight = localStorage.getItem(keyBannerHeight);
            const bannerWidth = localStorage.getItem(keyBannerWidth);
            const themeColor = localStorage.getItem(keyThemeColor);
            const settings = {
                enabled: enabled === null ? true : enabled === 'true',
                leadSeconds: leadSeconds ? Math.max(1, parseInt(leadSeconds, 10)) : 5,
                highlightEnabled: highlightEnabled === null ? true : highlightEnabled === 'true',
                clockEnabled: clockEnabled === null ? false : clockEnabled === 'true',
                bannerEnabled: bannerEnabled === null ? false : bannerEnabled === 'true',
                bannerMode: bannerMode === 'image' ? 'image' : 'text',
                bannerText: bannerText ? String(bannerText) : '',
                bannerImage: bannerImage ? String(bannerImage) : '',
                bannerHeight: bannerHeight ? Math.max(40, parseInt(bannerHeight, 10)) : 160,
                bannerWidth: bannerWidth ? Math.max(160, parseInt(bannerWidth, 10)) : 220,
                themeColor: themeColor ? String(themeColor) : '#ffffff'
            };
            nextAufgussSettings.set(String(planId), settings);
            return settings;
        }

        function applyPlanSettings(planId) {
            const settings = getPlanSettings(planId);
            const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
            const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
            const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
            if (enabledInput) enabledInput.checked = settings.enabled;
            if (leadInput) leadInput.value = settings.leadSeconds;
            if (highlightInput) highlightInput.checked = settings.highlightEnabled;
            if (clockInput) clockInput.checked = settings.clockEnabled;
            if (bannerInput) bannerInput.checked = settings.bannerEnabled;
            if (themeColorInput) themeColorInput.value = settings.themeColor || '#ffffff';
            const planCard = document.getElementById(`plan-${planId}`);
            if (planCard) {
                planCard.style.setProperty('--plan-accent-color', settings.themeColor || '#ffffff');
            }
            toggleAdminClock(planId, settings.clockEnabled);
            updateNextAufgussControls(planId);
        }

        function savePlanSettings(planId) {
            const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
            const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
            const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
            if (!enabledInput || !leadInput) return;

            const enabled = enabledInput.checked;
            const leadSeconds = Math.max(1, parseInt(leadInput.value || '5', 10));
            const highlightEnabled = highlightInput ? highlightInput.checked : true;
            const clockEnabled = clockInput ? clockInput.checked : false;
            const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const bannerEnabled = bannerInput ? bannerInput.checked : (currentSettings ? !!currentSettings.bannerEnabled : false);
            const bannerText = currentSettings ? String(currentSettings.bannerText || '') : '';
            const bannerImage = currentSettings ? String(currentSettings.bannerImage || '') : '';
            const bannerHeight = currentSettings ? Math.max(40, parseInt(currentSettings.bannerHeight || 160, 10)) : 160;
            const bannerWidth = currentSettings ? Math.max(160, parseInt(currentSettings.bannerWidth || 220, 10)) : 220;
            const themeColor = themeColorInput && themeColorInput.value
                ? themeColorInput.value
                : (currentSettings ? String(currentSettings.themeColor || '#ffffff') : '#ffffff');
            leadInput.value = leadSeconds;

            localStorage.setItem(`nextAufgussEnabled_${planId}`, String(enabled));
            localStorage.setItem(`nextAufgussLeadSeconds_${planId}`, String(leadSeconds));
            localStorage.setItem(`nextAufgussHighlightEnabled_${planId}`, String(highlightEnabled));
            localStorage.setItem(`nextAufgussClockEnabled_${planId}`, String(clockEnabled));
            localStorage.setItem(`nextAufgussBannerEnabled_${planId}`, String(bannerEnabled));
            localStorage.setItem(`nextAufgussThemeColor_${planId}`, String(themeColor));
            nextAufgussSettings.set(String(planId), {
                enabled,
                leadSeconds,
                highlightEnabled,
                clockEnabled,
                bannerEnabled,
                bannerText,
                bannerImage,
                bannerHeight,
                bannerWidth,
                themeColor
            });
            const planCard = document.getElementById(`plan-${planId}`);
            if (planCard) {
                planCard.style.setProperty('--plan-accent-color', themeColor);
            }
            toggleAdminClock(planId, clockEnabled);
            updateNextAufgussControls(planId);
            updateNextAufgussRowHighlight();
            notifyPublicPlanChange(planId);
            syncNextAufgussSettings(
                planId,
                enabled,
                leadSeconds,
                highlightEnabled,
                clockEnabled,
                bannerEnabled,
                currentSettings ? currentSettings.bannerMode : 'text',
                bannerText,
                bannerImage,
                bannerHeight,
                bannerWidth,
                themeColor
            );

            if (!enabled) {
                for (let i = nextAufgussQueue.length - 1; i >= 0; i -= 1) {
                    if (String(nextAufgussQueue[i].planId) === String(planId)) {
                        nextAufgussQueue.splice(i, 1);
                    }
                }
                if (String(nextAufgussActivePlanId) === String(planId)) {
                    closeNextAufgussPopup();
                }
            }
        }

        function syncNextAufgussSettings(planId, enabled, leadSeconds, highlightEnabled, clockEnabled, bannerEnabled, bannerMode, bannerText, bannerImage, bannerHeight, bannerWidth, themeColor) {
            fetch('../api/next_aufguss_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    plan_id: String(planId),
                    enabled: !!enabled,
                    lead_seconds: Number(leadSeconds),
                    highlight_enabled: !!highlightEnabled,
                    clock_enabled: !!clockEnabled,
                    banner_enabled: !!bannerEnabled,
                    banner_mode: String(bannerMode || 'text'),
                    banner_text: String(bannerText || ''),
                    banner_image: String(bannerImage || ''),
                    banner_height: Number(bannerHeight || 160),
                    banner_width: Number(bannerWidth || 220),
                    theme_color: String(themeColor || '#ffffff')
                })
            }).catch(() => {});
        }

        async function saveAllPlanSettings(planId) {
            savePlanSettings(planId);
            await savePlanAdSettings(planId);
        }

        function updateNextAufgussControls(planId) {
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const previewBtn = document.getElementById(`next-aufguss-preview-btn-${planId}`);
            const fields = document.getElementById(`next-aufguss-settings-fields-${planId}`);
            if (leadInput) leadInput.disabled = !settings.enabled;
            if (previewBtn) previewBtn.disabled = !settings.enabled;
            if (fields) fields.classList.toggle('opacity-50', !settings.enabled);
        }



        function updateNextAufgussCountdown() {
            const countdownEl = document.getElementById('next-aufguss-countdown');
            if (!countdownEl || !nextAufgussCountdownTarget) return;
            const diffMs = nextAufgussCountdownTarget - Date.now();
            const diffSec = Math.max(0, Math.ceil(diffMs / 1000));
            countdownEl.textContent = `${diffSec}s`;
            if (diffMs <= 0 && !nextAufgussIsPreview) {
                closeNextAufgussPopup();
            }
        }

        function closeNextAufgussPopup() {
            const overlay = document.getElementById('next-aufguss-overlay');
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            nextAufgussActive = false;
            nextAufgussActivePlanId = null;
            if (nextAufgussHideTimer) {
                clearTimeout(nextAufgussHideTimer);
                nextAufgussHideTimer = null;
            }
            if (nextAufgussCountdownTimer) {
                clearInterval(nextAufgussCountdownTimer);
                nextAufgussCountdownTimer = null;
            }
            nextAufgussCountdownTarget = null;
            showNextAufgussFromQueue();
        }

        function showNextAufgussFromQueue() {
            if (nextAufgussActive || nextAufgussQueue.length === 0) return;
            const next = nextAufgussQueue.shift();
            showNextAufgussPopup(next.id, next.startTs, next.planId);
        }

        async function showNextAufgussPopup(aufgussId, startTs, planId, previewData = null) {
            if (nextAufgussActive) {
                nextAufgussQueue.push({
                    id: aufgussId,
                    startTs,
                    planId
                });
                return;
            }
            nextAufgussActive = true;
            nextAufgussActivePlanId = planId;
            nextAufgussIsPreview = !!previewData;

            const overlay = document.getElementById('next-aufguss-overlay');
            const body = document.getElementById('next-aufguss-body');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            body.innerHTML = '<div class="text-sm text-gray-500">Laedt...</div>';

            if (previewData) {
                body.innerHTML = buildNextAufgussHtml(previewData);
            } else {
                try {
                    const response = await fetch(`next_aufguss.php?id=${aufgussId}`);
                    const result = await response.json();
                    if (!result.success) {
                        body.innerHTML = '<div class="text-sm text-red-600">Konnte Aufgussdaten nicht laden.</div>';
                    } else {
                        body.innerHTML = buildNextAufgussHtml(result.data);
                    }
                } catch (error) {
                    body.innerHTML = '<div class="text-sm text-red-600">Netzwerkfehler beim Laden.</div>';
                }
            }

            nextAufgussCountdownTarget = startTs;
            updateNextAufgussCountdown();
            nextAufgussCountdownTimer = setInterval(updateNextAufgussCountdown, 200);

            const hideDelay = previewData ? 20000 : Math.max(0, startTs - Date.now());
            nextAufgussHideTimer = setTimeout(() => {
                closeNextAufgussPopup();
            }, hideDelay);
        }

        function previewNextAufgussPopup(planId) {
            savePlanSettings(planId);
            const firstRow = document.querySelector(`tr[data-aufguss-id][data-plan-id="${planId}"]`);
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const startTs = Date.now() + (settings.leadSeconds * 1000);
            if (firstRow) {
                const aufgussId = firstRow.getAttribute('data-aufguss-id');
                showNextAufgussPopup(aufgussId, startTs, planId);
                return;
            }

            const previewData = {
                name: 'Vorschau Aufguss',
                staerke: 3,
                aufgieser_name: 'Max Mustermann',
                sauna_name: 'Finnische Sauna',
                duftmittel_name: 'Eukalyptus',
                sauna_temperatur: 90,
                sauna_bild: '',
                mitarbeiter_bild: ''
            };
            showNextAufgussPopup('preview', startTs, planId, previewData);
        }

        function getPlanAdSettings(planId) {
            const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const settings = {
                enabled: !!(enabledInput && enabledInput.checked),
                intervalMinutes: intervalInput ? Math.max(1, parseInt(intervalInput.value || '10', 10)) : 10,
                durationSeconds: durationInput ? Math.max(1, parseInt(durationInput.value || '10', 10)) : 10
            };
            planAdSettings.set(String(planId), settings);
            return settings;
        }

        function applyPlanAdSettings(planId) {
            getPlanAdSettings(planId);
            initPlanAdMedia(planId);
            updatePlanAdControls(planId);
            schedulePlanAd(planId);
        }

        async function savePlanAdSettings(planId, includeFile = false, options = {}) {
            const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            if (!enabledInput || !intervalInput || !durationInput) return;

            const enabled = enabledInput.checked;
            const intervalMinutes = Math.max(1, parseInt(intervalInput.value || '10', 10));
            const durationSeconds = Math.max(1, parseInt(durationInput.value || '10', 10));
            intervalInput.value = intervalMinutes;
            durationInput.value = durationSeconds;

            const formData = new FormData();
            formData.append('plan_id', planId);
            formData.append('enabled', enabled ? '1' : '0');
            formData.append('interval_minutes', String(intervalMinutes));
            formData.append('duration_seconds', String(durationSeconds));
            if (includeFile && fileInput && fileInput.files && fileInput.files[0]) {
                formData.append('media', fileInput.files[0]);
            }

            try {
                const response = await fetch('updates/update_plan_ad.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern der Werbung.');
                    return;
                }
                if (result.data && Object.prototype.hasOwnProperty.call(result.data, 'media_path')) {
                    if (result.data.media_path) {
                        setPlanAdMediaFromServer(planId, result.data.media_path, result.data.media_type, result.data.media_name);
                    } else {
                        clearPlanAdMedia(planId);
                    }
                }
                planAdSettings.set(String(planId), {
                    enabled,
                    intervalMinutes,
                    durationSeconds
                });
                updatePlanAdControls(planId);
                if (!enabled) {
                    hidePlanAd(planId);
                }
                if (includeFile) {
                    removeAdFile(planId);
                }
                schedulePlanAd(planId);
                if (options.notify !== false) {
                    notifyPublicPlanChange(planId);
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern der Werbung.');
            }
        }

        async function selectPlanBackground(planId) {
            const select = document.getElementById(`plan-background-select-${planId}`);
            if (!select) return;
            const backgroundPath = select.value;

            try {
                const response = await fetch('updates/update_plan_background.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        background_path: backgroundPath
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern des Hintergrundbilds.');
                    return;
                }
                location.reload();
            } catch (error) {
                alert('Netzwerkfehler beim Speichern des Hintergrundbilds.');
            }
        }

        async function selectPlanAdMedia(planId) {
            const select = document.getElementById(`plan-ad-select-${planId}`);
            if (!select) return;
            const option = select.options[select.selectedIndex];
            const mediaPath = select.value;
            const mediaType = option ? option.getAttribute('data-type') : '';

            if (!mediaPath) {
                return;
            }

            try {
                const response = await fetch('updates/update_plan_ad_select.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        media_path: mediaPath,
                        media_type: mediaType
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern der Werbung.');
                    return;
                }
                if (result.data && result.data.media_path) {
                    setPlanAdMediaFromServer(planId, result.data.media_path, result.data.media_type, result.data.media_name);
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern der Werbung.');
            }
        }

        function uploadPlanAdMedia(planId) {
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Bitte eine Datei auswaehlen.');
                return;
            }
            savePlanAdSettings(planId, true);
        }

        async function deletePlanAdMedia(planId, planName) {
            if (!confirm(`Werbung von "${planName}" loeschen?`)) {
                return;
            }

            try {
                const response = await fetch('deletes/delete_plan_ad_media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Loeschen der Werbung.');
                    return;
                }

                const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
                if (enabledInput) enabledInput.checked = false;

                clearPlanAdMedia(planId);
                hidePlanAd(planId);
                getPlanAdSettings(planId);
                updatePlanAdControls(planId);
                schedulePlanAd(planId);
            notifyPublicPlanChange(planId);
            } catch (error) {
                alert('Netzwerkfehler beim Loeschen der Werbung.');
            }
        }


        function updatePlanAdControls(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const previewBtn = document.getElementById(`plan-ad-preview-btn-${planId}`);
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            const uploadBtn = document.getElementById(`plan-ad-upload-btn-${planId}`);
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            const fields = document.getElementById(`plan-ad-settings-fields-${planId}`);
            if (intervalInput) intervalInput.disabled = !settings.enabled;
            if (durationInput) durationInput.disabled = !settings.enabled;
            if (previewBtn) previewBtn.disabled = !settings.enabled;
            if (fileInput) fileInput.disabled = !settings.enabled;
            if (uploadBtn) uploadBtn.disabled = !settings.enabled;
            if (deleteBtn) deleteBtn.disabled = !settings.enabled || !planAdMedia.has(String(planId));
            if (fields) fields.classList.toggle('opacity-50', !settings.enabled);
        }

        function getPlanAdElements(planId) {
            const adWrap = document.getElementById(`plan-ad-wrap-${planId}`);
            const mediaWrap = document.getElementById(`plan-ad-media-${planId}`);
            const tableBlock = document.getElementById(`plan-table-wrap-${planId}`);
            return {
                adWrap,
                mediaWrap,
                tableBlock
            };
        }

        function initPlanAdMedia(planId) {
            if (planAdMedia.has(String(planId))) return;
            const fields = document.getElementById(`plan-ad-settings-fields-${planId}`);
            if (!fields) return;
            const path = fields.dataset.adPath || '';
            const type = fields.dataset.adType || '';
            if (!path) return;
            const name = path.split('/').pop();
            setPlanAdMediaFromServer(planId, path, type, name);
        }

        function renderPlanAdPreview(planId, media) {
            const preview = document.getElementById(`plan-ad-preview-${planId}`);
            if (!preview) return;
            if (!media || !media.url) {
                preview.innerHTML = '<div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">Keine Werbung vorhanden</div>';
                return;
            }
            const isVideo = media.type && (media.type.startsWith('video/') || media.type === 'video');
            if (isVideo) {
                preview.innerHTML = `<video src="${media.url}" class="w-full h-48 object-contain" controls loop></video>`;
            } else {
                preview.innerHTML = `<img src="${media.url}" alt="Werbung" class="w-full h-48 object-contain">`;
            }
        }

        function setPlanAdMediaFromServer(planId, path, type, name) {
            const media = planAdMedia.get(String(planId));
            if (media && media.url && media.url.startsWith('blob:')) {
                URL.revokeObjectURL(media.url);
            }
            const url = `../uploads/${path}`;
            planAdMedia.set(String(planId), {
                url,
                type,
                name: name || path.split('/').pop()
            });
            const fileInfo = document.getElementById(`plan-ad-file-info-${planId}`);
            if (fileInfo) {
                fileInfo.textContent = `Aktuelle Datei: ${name || path.split('/').pop()}`;
                fileInfo.classList.remove('text-gray-500');
                fileInfo.classList.add('text-gray-600');
            }
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            if (deleteBtn) deleteBtn.disabled = false;
            renderPlanAdPreview(planId, planAdMedia.get(String(planId)));
            schedulePlanAd(planId);
        }

        function clearPlanAdMedia(planId) {
            const media = planAdMedia.get(String(planId));
            if (media && media.url && media.url.startsWith('blob:')) {
                URL.revokeObjectURL(media.url);
            }
            planAdMedia.delete(String(planId));
            renderPlanAdPreview(planId, null);
            const fileInfo = document.getElementById(`plan-ad-file-info-${planId}`);
            if (fileInfo) {
                fileInfo.textContent = 'Keine Datei gespeichert.';
                fileInfo.classList.remove('text-gray-600');
                fileInfo.classList.add('text-gray-500');
            }
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            if (deleteBtn) deleteBtn.disabled = true;
        }

        function showPlanAd(planId, durationSeconds, isPreview = false) {
            const media = planAdMedia.get(String(planId));
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            if (!media) {
                if (isPreview) {
                    alert('Bitte zuerst ein Bild oder Video auswaehlen.');
                }
                return;
            }
            if (!isPreview && !settings.enabled) return;

            const {
                adWrap,
                mediaWrap,
                tableBlock
            } = getPlanAdElements(planId);
            if (!adWrap || !mediaWrap || !tableBlock) return;

            const isVideo = media.type && (media.type.startsWith('video/') || media.type === 'video');
            if (isVideo) {
                mediaWrap.innerHTML = `<video src="${media.url}" class="plan-ad-asset rounded-lg" autoplay muted playsinline loop></video>`;
            } else {
                mediaWrap.innerHTML = `<img src="${media.url}" alt="Werbung" class="plan-ad-asset rounded-lg">`;
            }

            tableBlock.classList.add('is-hidden');
            adWrap.classList.remove('hidden');
            requestAnimationFrame(() => adWrap.classList.add('is-visible'));

            if (planAdHideTimers.has(String(planId))) {
                clearTimeout(planAdHideTimers.get(String(planId)));
            }
            const hideTimer = setTimeout(() => {
                hidePlanAd(planId);
            }, Math.max(1, durationSeconds) * 1000);
            planAdHideTimers.set(String(planId), hideTimer);
        }

        function hidePlanAd(planId) {
            const {
                adWrap,
                tableBlock
            } = getPlanAdElements(planId);
            if (!adWrap || !tableBlock) return;
            adWrap.classList.remove('is-visible');
            setTimeout(() => adWrap.classList.add('hidden'), 300);
            requestAnimationFrame(() => tableBlock.classList.remove('is-hidden'));
        }

        function schedulePlanAd(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            if (planAdIntervals.has(String(planId))) {
                clearInterval(planAdIntervals.get(String(planId)));
                planAdIntervals.delete(String(planId));
            }
            if (!settings.enabled) return;
            if (!planAdMedia.has(String(planId))) return;

            const intervalMs = Math.max(1, settings.intervalMinutes) * 60 * 1000;
            const timer = setInterval(() => {
                showPlanAd(planId, settings.durationSeconds);
            }, intervalMs);
            planAdIntervals.set(String(planId), timer);
        }

        function previewPlanAd(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            const planCard = document.getElementById(`plan-${planId}`);
            if (planCard) {
                const headerOffset = 80;
                const rect = planCard.getBoundingClientRect();
                const targetY = rect.top + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: Math.max(0, targetY),
                    behavior: 'smooth'
                });
            }
            showPlanAd(planId, settings.durationSeconds, true);
        }

        function initSaunaTemperatureSync() {
            const selects = document.querySelectorAll('select[id^="sauna-select-"]');
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    const planId = select.id.replace('sauna-select-', '');
                    const tempInput = document.getElementById(`sauna-temperatur-${planId}`);
                    if (!tempInput) return;
                    const option = select.options[select.selectedIndex];
                    const temp = option ? option.getAttribute('data-temperatur') : '';
                    tempInput.value = temp ? temp : '';
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-plan-id]').forEach(container => {
                const planId = container.getAttribute('data-plan-id');
                if (!planId) return;
                const hasSettings = container.querySelector(`[id^="next-aufguss-enabled-"]`);
                if (!hasSettings) return;

                applyPlanSettings(planId);
                applyPlanAdSettings(planId);
                const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
                const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
                const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
                const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
                const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
                const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
                const planForm = document.querySelector(`#form-${planId} form`);
                const adEnabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
                const adIntervalInput = document.getElementById(`plan-ad-interval-${planId}`);
                const adDurationInput = document.getElementById(`plan-ad-duration-${planId}`);
                const adFileInput = document.getElementById(`plan-ad-file-${planId}`);
                if (enabledInput) {
                    enabledInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (leadInput) {
                    leadInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (highlightInput) {
                    highlightInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (clockInput) {
                    clockInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (bannerInput) {
                    bannerInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (themeColorInput) {
                    themeColorInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (planForm) {
                    planForm.addEventListener('submit', () => savePlanSettings(planId));
                }
                if (adEnabledInput) {
                    adEnabledInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adIntervalInput) {
                    adIntervalInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adDurationInput) {
                    adDurationInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adFileInput) {
                    adFileInput.addEventListener('change', () => updateAdFileName(planId));
                }
            });

            initPlanSelectButtons();
            initSaunaTemperatureSync();
            startAdminClockTicker();
            updateNextAufgussRowHighlight();
            setInterval(() => {
                const rows = document.querySelectorAll('tr[data-aufguss-id]');
                const now = Date.now();

                rows.forEach(row => {
                    const aufgussId = row.getAttribute('data-aufguss-id');
                    if (!aufgussId) return;
                    const planId = row.getAttribute('data-plan-id');
                    if (!planId) return;
                    const key = `${planId}:${aufgussId}`;
                    if (nextAufgussShown.has(key)) return;

                    const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
                    if (!settings.enabled) return;

                    const timeText = row.querySelector('.zeit-cell .display-mode span')?.textContent?.trim();
                    const startTime = parseStartTime(timeText);
                    if (!startTime) return;

                    const diff = startTime.getTime() - now;
                    if (diff <= (settings.leadSeconds * 1000) && diff > 0) {
                        nextAufgussShown.add(key);
                        showNextAufgussPopup(aufgussId, startTime.getTime(), planId);
                    }
                });

                updateNextAufgussRowHighlight();
            }, 1000);
        });

        function initPlanSelectButtons() {
            const buttons = document.querySelectorAll('[data-plan-select]');
            if (!buttons.length) return;

            const storageKey = 'aufgussplanSelectedPlan';
            const stored = localStorage.getItem(storageKey);

            const setActive = (planId) => {
                buttons.forEach(button => {
                    const isActive = button.getAttribute('data-plan-select') === String(planId);
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            if (stored) {
                setActive(stored);
            }

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    const planId = button.getAttribute('data-plan-select');
                    if (!planId) return;
                    setActive(planId);
                    localStorage.setItem(storageKey, String(planId));
                    notifyPublicPlanChange(planId);
                });
            });
        }

        function updateAdminClockElement(clockEl) {
            if (!clockEl) return;
            const now = new Date();
            const timeEl = clockEl.querySelector('.plan-clock-admin-time');
            const dateEl = clockEl.querySelector('.plan-clock-admin-date');
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            }
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
        }

        function toggleAdminClock(planId, enabled) {
            const clockEl = document.getElementById(`plan-clock-admin-${planId}`);
            if (!clockEl) return;
            clockEl.classList.toggle('hidden', !enabled);
            if (enabled) {
                updateAdminClockElement(clockEl);
            }
        }

        function startAdminClockTicker() {
            if (adminClockTimer) return;
            adminClockTimer = setInterval(() => {
                document.querySelectorAll('.plan-clock-admin:not(.hidden)').forEach(clockEl => {
                    updateAdminClockElement(clockEl);
                });
            }, 1000);
        }

        function openPlanBannerModal(planId) {
            const modal = document.getElementById('planBannerModal');
            if (!modal) return;
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const planIdInput = document.getElementById('planBannerPlanId');
            const enabledInput = document.getElementById('planBannerEnabled');
            const modeTextInput = document.getElementById('planBannerModeText');
            const modeImageInput = document.getElementById('planBannerModeImage');
            const textInput = document.getElementById('planBannerText');
            const imageInput = document.getElementById('planBannerImage');
            const imageSelect = document.getElementById('planBannerImageSelect');

            if (planIdInput) planIdInput.value = String(planId);
            if (enabledInput) enabledInput.checked = !!settings.bannerEnabled;
            if (modeTextInput) modeTextInput.checked = settings.bannerMode !== 'image';
            if (modeImageInput) modeImageInput.checked = settings.bannerMode === 'image';
            if (textInput) textInput.value = settings.bannerText || '';
            if (imageInput) imageInput.value = settings.bannerImage || '';
            if (imageSelect) imageSelect.value = settings.bannerImage || '';

            modal.classList.remove('hidden');
        }

        function closePlanBannerModal() {
            const modal = document.getElementById('planBannerModal');
            if (!modal) return;
            modal.classList.add('hidden');
        }

        function savePlanBannerSettings() {
            const planId = document.getElementById('planBannerPlanId')?.value;
            if (!planId) return;
            const enabledInput = document.getElementById('planBannerEnabled');
            const modeTextInput = document.getElementById('planBannerModeText');
            const textInput = document.getElementById('planBannerText');
            const imageInput = document.getElementById('planBannerImage');

            const bannerEnabled = !!(enabledInput && enabledInput.checked);
            const bannerMode = modeTextInput && modeTextInput.checked ? 'text' : 'image';
            const bannerText = textInput ? textInput.value.trimEnd() : '';
            const bannerImage = imageInput ? imageInput.value.trim() : '';
            const bannerToggle = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            if (bannerToggle) bannerToggle.checked = bannerEnabled;
            const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const bannerWidth = currentSettings ? Math.max(160, parseInt(currentSettings.bannerWidth || 220, 10)) : 220;
            const bannerHeight = currentSettings ? Math.max(40, parseInt(currentSettings.bannerHeight || 160, 10)) : 160;

            localStorage.setItem(`nextAufgussBannerEnabled_${planId}`, String(bannerEnabled));
            localStorage.setItem(`nextAufgussBannerMode_${planId}`, String(bannerMode));
            localStorage.setItem(`nextAufgussBannerText_${planId}`, String(bannerText));
            localStorage.setItem(`nextAufgussBannerImage_${planId}`, String(bannerImage));
            localStorage.setItem(`nextAufgussBannerWidth_${planId}`, String(bannerWidth));
            localStorage.setItem(`nextAufgussBannerHeight_${planId}`, String(bannerHeight));

            nextAufgussSettings.set(String(planId), {
                ...currentSettings,
                bannerEnabled,
                bannerMode,
                bannerText,
                bannerImage,
                bannerWidth,
                bannerHeight
            });

            syncNextAufgussSettings(
                planId,
                currentSettings.enabled,
                currentSettings.leadSeconds,
                currentSettings.highlightEnabled,
                currentSettings.clockEnabled,
                bannerEnabled,
                bannerMode,
                bannerText,
                bannerImage,
                bannerHeight,
                bannerWidth,
                currentSettings.themeColor
            );
            notifyPublicPlanChange(planId);
            closePlanBannerModal();
        }

        function selectPlanBannerImage() {
            const select = document.getElementById('planBannerImageSelect');
            const input = document.getElementById('planBannerImage');
            if (!select || !input) return;
            input.value = select.value;
        }

        function updatePlanBannerFileName() {
            const input = document.getElementById('planBannerFile');
            const filenameDiv = document.getElementById('plan-banner-filename');
            const filenameText = document.getElementById('plan-banner-filename-text');
            if (!input || !filenameDiv || !filenameText) return;
            if (!input.files || !input.files[0]) {
                filenameDiv.classList.add('hidden');
                filenameText.textContent = '';
                return;
            }
            filenameText.textContent = input.files[0].name;
            filenameDiv.classList.remove('hidden');
        }

        function removePlanBannerFile() {
            const input = document.getElementById('planBannerFile');
            const filenameDiv = document.getElementById('plan-banner-filename');
            const filenameText = document.getElementById('plan-banner-filename-text');
            if (input) input.value = '';
            if (filenameDiv) filenameDiv.classList.add('hidden');
            if (filenameText) filenameText.textContent = '';
        }

        async function uploadPlanBannerImage() {
            const input = document.getElementById('planBannerFile');
            if (!input || !input.files || !input.files[0]) {
                alert('Bitte eine Datei auswaehlen.');
                return;
            }
            const formData = new FormData();
            formData.append('banner', input.files[0]);
            try {
                const response = await fetch('updates/upload_banner_image.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data || !data.success) {
                    throw new Error(data && data.error ? data.error : 'Upload fehlgeschlagen');
                }
                const path = data.data && data.data.path ? data.data.path : '';
                if (path) {
                    const imageInput = document.getElementById('planBannerImage');
                    const imageSelect = document.getElementById('planBannerImageSelect');
                    if (imageInput) imageInput.value = path;
                    if (imageSelect) imageSelect.value = path;
                }
                removePlanBannerFile();
            } catch (error) {
                alert(error && error.message ? error.message : 'Upload fehlgeschlagen');
            }
        }

        function notifyPublicPlanChange(planId) {
            localStorage.setItem('aufgussplanPlanChanged', String(Date.now()));
            if (!planId) return;
            fetch('../api/selected_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: String(planId) })
            }).catch(error => {
                console.warn('Failed to sync selected plan:', error);
            });
        }
    </script>
</body>

</html>

