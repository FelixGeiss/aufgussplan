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
require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/auth.php';

/**
 * SICHERHEIT: LOGIN-PRÜFUNG (auskommentiert für Entwicklung)
 *
 * In Produktion: Geschützter Admin-Bereich
 */
require_login();
require_permission('aufguesse');

// Datenbankverbindung für PHP-Operationen
require_once __DIR__ . '/../../../src/db/connection.php';

// Aufguss-Model für Plan-Operationen
require_once __DIR__ . '/../../../src/models/aufguss.php';

/**
 * Gruppiert numerische Stärkeeinstellungen auf drei Badge-Kategorien.
 *
 * @param int|null $level
 * @return int
 */
function getStaerkeCategory($level) {
    $value = (int)($level ?? 0);
    if ($value <= 0) {
        return 0;
    }
    if ($value === 1) {
        return 1;
    }
    if ($value === 2) {
        return 2;
    }
    return 3;
}

function getStaerkeBadgeInfo($level) {
    switch (getStaerkeCategory($level)) {
        case 1:
            return ['text' => 'Leicht', 'bgColor' => 'bg-green-100', 'textColor' => 'text-green-800'];
        case 2:
            return ['text' => 'Mittel', 'bgColor' => 'bg-yellow-100', 'textColor' => 'text-yellow-800'];
        case 3:
            return ['text' => 'Stark', 'bgColor' => 'bg-red-100', 'textColor' => 'text-red-900'];
        default:
            return ['text' => 'Unbekannt', 'bgColor' => 'bg-gray-100', 'textColor' => 'text-gray-800'];
    }
}

$aufgussModel = new Aufguss();

// Pläne aus Datenbank laden
$Pläene = $aufgussModel->getAllPlans();
// Daten für Formular-Select-Felder laden
$mitarbeiter = $db->query("SELECT id, name, bild FROM mitarbeiter ORDER BY name")->fetchAll();
$saunen = $db->query("SELECT id, name, bild, beschreibung, temperatur FROM saunen ORDER BY name")->fetchAll();
$duftmittel = $db->query("SELECT id, name, beschreibung, bild FROM duftmittel ORDER BY name")->fetchAll();
$aufguss_optionen = $db->query("SELECT id, name, beschreibung FROM aufguss_namen ORDER BY name")->fetchAll();
$umfrage_bewertungen = $db->query(
    "SELECT r.id,
            r.aufguss_id,
            r.plan_id,
            r.aufguss_name_id,
            r.kriterium,
            r.rating,
            r.datum,
            p.name AS plan_name,
            n.name AS aufguss_name
     FROM umfrage_bewertungen r
     LEFT JOIN plaene p ON p.id = r.plan_id
     LEFT JOIN aufguss_namen n ON n.id = r.aufguss_name_id
     ORDER BY r.datum DESC, r.id DESC"
)->fetchAll();

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

foreach ($Pläene as $plan) {
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

$staerkeUploadDir = $uploadBaseDir . 'staerke' . DIRECTORY_SEPARATOR;
$staerkeUploadFiles = [];
if (is_dir($staerkeUploadDir)) {
    foreach (scandir($staerkeUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $staerkeUploadDir . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $staerkeUploadFiles[] = 'staerke/' . $entry;
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

$staerkeOptions = [];
$staerkeSeen = [];
foreach ($staerkeUploadFiles as $path) {
    $path = trim((string)$path);
    if ($path === '' || isset($staerkeSeen[$path])) {
        continue;
    }
    $staerkeSeen[$path] = true;
    $staerkeOptions[] = ['path' => $path, 'label' => basename($path)];
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

$toastMessage = '';
$toastType = 'success';
if (isset($_SESSION['toast_message'])) {
    $toastMessage = $_SESSION['toast_message'];
    $toastType = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_message'], $_SESSION['toast_type']);
} elseif ($message) {
    $toastMessage = $message;
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
                $_SESSION['toast_message'] = $message;
                $_SESSION['toast_type'] = 'success';
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
                $_SESSION['toast_message'] = $message;
                $_SESSION['toast_type'] = 'success';
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
                $_SESSION['toast_message'] = $message;
                $_SESSION['toast_type'] = 'success';
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
                $_SESSION['toast_message'] = $message;
                $_SESSION['toast_type'] = 'success';
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    } else {
        require_once __DIR__ . '/../../../src/services/aufgussService.php';
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
    <link rel="stylesheet" href="../../dist/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admin.css'); ?>">
    
</head>

<body class="bg-gray-100">    <?php include __DIR__ . '/../partials/navbar.php'; ?>



    <div class="container mx-auto px-4 py-8 space-y-8">

        


        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 text-center">Neuen Plan erstellen</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="form_type" value="create_plan">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="plan-name" class="block text-sm font-medium text-gray-900 text-center md:text-left">Planname</label>
                            <input type="text" id="plan-name" name="plan_name" class="block w-full h-12 rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Wellness-Tag, Power-Aufgüsse" required>
                        </div>
                        <div class="space-y-2">
                            <label for="plan-beschreibung" class="block text-sm font-medium text-gray-900 text-center md:text-left">Beschreibung</label>
                            <textarea id="plan-beschreibung" name="plan_beschreibung" rows="1" class="block w-full h-12 resize-none rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="Kurze Beschreibung für den Plan"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <button type="submit" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-1">
                            Plan erstellen <span aria-hidden="true">+</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($Pläene)): ?>
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
            <?php foreach ($Pläene as $plan): ?>
                <?php
                // Aufgüsse für diesen Plan laden
                $planAufgüsse = $aufgussModel->getAufgüsseByPlan($plan['id']);
                ?>

                <!-- Plan-Bereich -->
                <div id="plan-<?php echo $plan['id']; ?>" class="bg-white rounded-lg shadow-md relative">
                    <div class="relative p-6">
                        <!-- Plan-Header -->
                        <div class="relative plan-header flex flex-col gap-4 mb-6">
                            <div class="plan-header__title order-1 flex items-center gap-4">
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
                            <div class="plan-header__banner order-4 flex-1 flex items-center justify-start gap-2">
                                <span id="plan-banner-status-<?php echo $plan['id']; ?>" class="plan-banner-status hidden inline-flex flex-col items-center justify-center text-xs font-semibold text-white bg-[#2563eb] border border-[#2563eb] rounded-lg px-3 py-2 shadow-sm"></span>
                                <div id="plan-clock-admin-<?php echo $plan['id']; ?>" class="plan-clock-admin hidden inline-flex flex-col items-center justify-center bg-white/70 border border-gray-200 rounded-lg px-3 py-2 shadow-sm">
                                    <div class="plan-clock-admin-time text-lg font-semibold text-gray-900">--:--</div>
                                    <div class="plan-clock-admin-date text-xs text-gray-600">--.--.----</div>
                                </div>
                            </div>
                            <div class="plan-header__meta order-2 text-left">
                                
                                <div class="text-sm text-gray-500">Erstellt am</div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo date('d.m.Y', strtotime($plan['erstellt_am'])); ?>
                                </div>
                                <div class="plan-header__buttons mt-2 flex flex-col items-stretch gap-2">
                                    <button type="button"
                                        class="plan-select-btn"
                                        data-plan-select="<?php echo (int)$plan['id']; ?>"
                                        data-plan-name="<?php echo htmlspecialchars($plan['name'] ?? ''); ?>">
                                        Ausw&auml;hlen
                                    </button>
                                    <div class="flex items-stretch gap-2">
                                        <button type="button"
                                            class="flex-1 rounded-md admin-btn-save px-3 py-1.5 text-sm font-semibold text-white shadow-sm"
                                            onclick="saveAllPlanSettings(<?php echo (int)$plan['id']; ?>)">
                                            Speichern
                                        </button>
                                        <button type="button" onclick="deletePlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')"
                                            class="flex-1 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                            L&ouml;schen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative rounded-lg overflow-hidden">
                            <?php
                            $backgroundPath = $plan['hintergrund_bild'] ?? '';
                            $backgroundExt = strtolower(pathinfo((string)$backgroundPath, PATHINFO_EXTENSION));
                            $backgroundIsVideo = $backgroundPath !== '' && in_array($backgroundExt, ['mp4', 'webm', 'ogg'], true);
                            ?>
                            <?php if (!empty($backgroundPath)): ?>
                                <?php if ($backgroundIsVideo): ?>
                                    <video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline>
                                        <source src="../../uploads/<?php echo htmlspecialchars($backgroundPath); ?>" type="video/<?php echo htmlspecialchars($backgroundExt); ?>">
                                    </video>
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../../uploads/<?php echo htmlspecialchars($backgroundPath); ?>');"></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="relative">
                                <!-- Aufgüsse für diesen Plan -->
                                <div class="plan-table-wrap plan-table-scope" id="plan-table-wrap-<?php echo $plan['id']; ?>">
                                    <?php if (empty($planAufgüsse)): ?>
                                        <div class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg bg-white/70">
                                            <div class="text-4xl mb-2">🕐</div>
                                            <p class="text-lg font-medium">Noch keine Aufgüsse in diesem Plan</p>
                                            <p class="text-sm">Erstelle Aufgüsse im Dashboard und weise sie diesem Plan zu</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="overflow-x-auto plan-table-scroll">
                                            <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                                                <thead class="plan-table-head font-display">
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
                                                        <tr class="bg-white/35" data-aufguss-id="<?php echo $aufguss['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" data-staerke-level="<?php echo (int)($aufguss['staerke'] ?? 0); ?>" data-staerke-icon="<?php echo htmlspecialchars($aufguss['staerke_icon'] ?? ''); ?>">
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
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'zeit')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
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
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'aufguss')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'aufguss')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Stärke -->
                                                            <td class="px-6 py-4 whitespace-nowrap stärke-cell">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode relative cursor-pointer hover:bg-yellow-50 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'stärke')">
                                                                    <div class="staerke-icons-container pointer-events-none" aria-hidden="true"></div>
                                                                    <?php
                                                                    $stärke = $aufguss['staerke'] ?? 0;
                                                                    $badgeInfo = getStaerkeBadgeInfo($stärke);
                                                                    $stärkeText = $badgeInfo['text'];
                                                                    $bgColor = $badgeInfo['bgColor'];
                                                                    $textColor = $badgeInfo['textColor'];
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
                                                                    <?php $currentCategory = getStaerkeCategory($stärke); ?>
                                                                    <select name="stärke" class="rounded px-2 py-1 text-sm border border-gray-300">
                                                                        <option value="">-- Stärke wählen --</option>
                                                                        <option value="1" <?php echo ($currentCategory === 1) ? 'selected' : ''; ?>>1 Leicht</option>
                                                                        <option value="2" <?php echo ($currentCategory === 2) ? 'selected' : ''; ?>>2 Mittel</option>
                                                                        <option value="3" <?php echo ($currentCategory === 3) ? 'selected' : ''; ?>>3 Stark</option>
                                                                    </select>
                                                                    <div>
                                                                        <select name="staerke_icon" class="rounded px-2 py-1 text-sm border border-gray-300 w-full">
                                                                            <option value="">-- Kein Bild --</option>
                                                                            <?php foreach ($staerkeOptions as $iconOption): ?>
                                                                                <option value="<?php echo htmlspecialchars($iconOption['path']); ?>" <?php echo (($aufguss['staerke_icon'] ?? '') === $iconOption['path']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($iconOption['label']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'stärke')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'stärke')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
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
                                                                    <div class="flex flex-wrap justify-center gap-4 w-full aufgieser-list <?php echo count($aufgieserPeople) > 1 ? 'is-multi' : ''; ?>">
                                                                        <?php foreach ($aufgieserPeople as $person): ?>
                                                                            <div class="flex flex-col items-center">
                                                                                <?php
                                                                                $mitarbeiterBild = !empty($person['bild'])
                                                                                    ? '../../uploads/' . htmlspecialchars($person['bild'])
                                                                                    : '../../assets/placeholders/Platzhalter_Mitarbeiter.svg';
                                                                                ?>
                                                                                <img src="<?php echo $mitarbeiterBild; ?>"
                                                                                    alt="Aufgiesser-Bild"
                                                                                    class="h-10 w-10 rounded-full object-cover border border-gray-200"
                                                                                    onerror="this.onerror=null;this.src='../../assets/placeholders/Platzhalter_Mitarbeiter.svg';">
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

                                                                    
                                                                    <div class="border-t border-gray-200 pt-2">
                                                                        <div class="multi-select" data-placeholder="Mehrere Mitarbeiter wählen">
                                                                            <button type="button" class="multi-select-trigger">Wähle einen oder mehrere</button>
                                                                            <div class="multi-select-panel hidden">
                                                                                <?php foreach ($mitarbeiter as $m): ?>
                                                                                    <label class="multi-select-option">
                                                                                        <input type="checkbox" name="mitarbeiter_ids[]" value="<?php echo $m['id']; ?>">
                                                                                        <span><?php echo htmlspecialchars($m['name'] ?? ''); ?></span>
                                                                                    </label>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'mitarbeiter')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'mitarbeiter')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Sauna -->
                                                            <td class="px-6 py-4 whitespace-nowrap sauna-cell text-center">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode flex flex-col items-center cursor-pointer hover:bg-green-50 transition-colors duration-150 rounded px-2 py-2 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'sauna')">
                                                                    <div class="relative flex-shrink-0 h-10 w-10">
                                                                        <?php
                                                                        $saunaBild = !empty($aufguss['sauna_bild'])
                                                                            ? '../../uploads/' . htmlspecialchars($aufguss['sauna_bild'])
                                                                            : '../../assets/placeholders/Platzhalter_Sauna.svg';
                                                                        ?>
                                                                        <img src="<?php echo $saunaBild; ?>"
                                                                            alt="Sauna-Bild"
                                                                            class="h-10 w-10 rounded-full object-cover border border-gray-200"
                                                                            onerror="this.onerror=null;this.src='../../assets/placeholders/Platzhalter_Sauna.svg';">
                                                                        <?php
                                                                        $saunaTempValue = $aufguss['sauna_temperatur'] ?? null;
                                                                        if ($saunaTempValue !== null && $saunaTempValue !== ''):
                                                                        ?>
                                                                            <span class="sauna-temp-badge absolute inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" style="top:-4px; right:-30px;">
                                                                                <?php echo htmlspecialchars($saunaTempValue); ?>&deg;C
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
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'sauna')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                                        <button onclick="cancelEdit(<?php echo $aufguss['id']; ?>, 'sauna')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Duftmittel -->
                                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-cell text-center">
                                                                <!-- Anzeige-Modus -->
                                                                <div class="display-mode flex flex-col items-center cursor-pointer hover:bg-purple-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-2 group" onclick="toggleEdit(<?php echo $aufguss['id']; ?>, 'duftmittel')">
                                                                    <?php
                                                                    $duftBild = '../../assets/placeholders/Platzhalter_Duft.svg';
                                                                    if (!empty($aufguss['duftmittel_id'])) {
                                                                        foreach ($duftmittel as $dm) {
                                                                            if ((int)$dm['id'] === (int)$aufguss['duftmittel_id'] && !empty($dm['bild'])) {
                                                                                $duftBild = '../../uploads/' . htmlspecialchars($dm['bild']);
                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                                                                    ?>
                                                                    <div class="flex-shrink-0 h-10 w-10">
                                                                        <img src="<?php echo $duftBild; ?>"
                                                                            alt="Duftmittel-Bild"
                                                                            class="h-10 w-10 rounded-full object-cover border border-gray-200"
                                                                            onerror="this.onerror=null;this.src='../../assets/placeholders/Platzhalter_Duft.svg';">
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
                                                                        <button onclick="saveEdit(<?php echo $aufguss['id']; ?>, 'duftmittel')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
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
                                        Keine Werbung ausgewählt.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ausklappbares Aufguss-Formular -->
                        <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" data-toggle-form="<?php echo $plan['id']; ?>" class="mt-6 w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            Planbearbeitung
                        </button>
                        <div class="mt-6 border-t border-gray-200 pt-6 mb-6">
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
                                                    <div class="flex flex-col gap-3 md:flex-row">
                                                        <input type="text" id="aufguss-name-<?php echo $plan['id']; ?>" name="aufguss_name" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Wellness-Aufguss" />

                                                        <!-- Select fuer vorhandene Aufguesse -->
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

                                                <!-- Verwendete Duftmittel -->
                                                <div>
                                                    <label for="duftmittel-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Verwendete Duftmittel</label>
                                                    <div class="flex flex-col gap-3 md:flex-row">
                                                        <input type="text" id="duftmittel-<?php echo $plan['id']; ?>" name="duftmittel" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Eukalyptus, Minze" />

                                                        <!-- Select fuer vorhandene Duftmittel -->
                                                        <select id="duftmittel-select-<?php echo $plan['id']; ?>" name="duftmittel_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Duftmittel ausw&auml;hlen --</option>
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
                                                    <div class="flex flex-col gap-3 md:flex-row">
                                                        <input type="text" id="sauna-<?php echo $plan['id']; ?>" name="sauna" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Finnische Sauna" />
                                                        <select id="sauna-select-<?php echo $plan['id']; ?>" name="sauna_id" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                            <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Sauna auswählen --</option>
                                                            <?php foreach ($saunen as $s): ?>
                                                                <option class="text-center" value="<?php echo $s['id']; ?>" data-temperatur="<?php echo htmlspecialchars($s['temperatur'] ?? ''); ?>">
                                                                    <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Name des Aufgie?ers -->
                                                <div>
                                                    <label for="aufgieser-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Name des Aufgießers</label>
                                                    <div class="flex flex-col gap-3 md:flex-row">
                                                        <input type="text" id="aufgieser-<?php echo $plan['id']; ?>" name="aufgieser" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. Max Mustermann" />
                                                        <div class="multi-select" data-placeholder="Mehrere Mitarbeiter wählen">
                                                            <button type="button" class="multi-select-trigger">Mehrere Mitarbeiter wählen</button>
                                                            <div class="multi-select-panel hidden">
                                                                <?php foreach ($mitarbeiter as $m): ?>
                                                                    <label class="multi-select-option">
                                                                        <input type="checkbox" name="mitarbeiter_ids[]" value="<?php echo $m['id']; ?>">
                                                                        <span><?php echo htmlspecialchars($m['name'] ?? ''); ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

<!-- Temperatur (C) -->
                                                <div class="mt-3">
                                                    <label for="sauna-temperatur-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1 text-center">Temperatur (C)</label>
                                                    <input type="number" id="sauna-temperatur-<?php echo $plan['id']; ?>" name="sauna_temperatur" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)" placeholder="z.B. 90" min="0" step="1" />
                                                </div>
                                                <!-- Stärke des Aufgusses -->
                                                <div>
                                                    <label for="stärke-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2 text-center">Stärke des Aufgusses</label>
                                                    <select id="stärke-<?php echo $plan['id']; ?>" name="stärke" class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid text-center" style="border-color: var(--border-color)">
                                                        <option class="border-2 border-solid text-center" style="border-color: var(--border-color)" value="">-- Stärke wählen --</option>
                                                        <option class="text-center" value="1">1 Leicht</option>
                                                        <option class="text-center" value="2">2 Mittel</option>
                                                        <option class="text-center" value="3">3 Stark</option>
                                                    </select>
                                                </div>
                                                <div class="text-center mt-4">
                                                    <label class="block text-sm font-medium text-gray-900 mb-2">Zeitbereich des Aufgusses</label>
                                                    <div class="flex justify-center items-center gap-4">
                                                        <div class="flex flex-col items-center">
                                                            <label for="zeit_anfang-<?php echo $plan['id']; ?>" class="text-sm font-semibold text-gray-900 mb-1">Anfang</label>
                                                            <input type="time" id="zeit_anfang-<?php echo $plan['id']; ?>" name="zeit_anfang"
                                                                class="rounded-md bg-white px-3 py-2 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 border-2 border-solid w-32"
                                                                style="border-color: var(--border-color)">
                                                        </div>
                                                        <div class="flex items-center justify-center text-gray-400 self-stretch w-8">
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
                                                <div class="border-t border-gray-200 my-4"></div>

                                                <!-- Bilder hochladen -->
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Bilder hochladen</h3>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <!-- Bild der Sauna -->
                                                        <div>
                                                            <label for="sauna-bild-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2">Bild der Sauna</label>
                                                            <label for="sauna-bild-<?php echo $plan['id']; ?>" class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                                <div class="text-center pointer-events-none">
                                                                    <img src="../../assets/placeholders/Platzhalter_Sauna.svg" alt="Sauna Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Sauna-Bild hochladen</span>
                                                                        <input id="sauna-bild-<?php echo $plan['id']; ?>" name="sauna_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('sauna', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="sauna-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgewählte Datei: <span id="sauna-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('sauna', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                                                                </div>
                                                            </label>
                                                        </div>

                                                        <!-- Bild der Staerke -->
                                                        <div>
                                                            <label for="staerke-bild-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2">Bild der St&auml;rke</label>
                                                            <label for="staerke-bild-<?php echo $plan['id']; ?>" class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                                <div class="text-center pointer-events-none">
                                                                    <img src="../../assets/placeholders/Platzhalter_Staerke.svg" alt="St&auml;rke Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">St&auml;rke-Bild hochladen</span>
                                                                        <input id="staerke-bild-<?php echo $plan['id']; ?>" name="staerke_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('staerke', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="staerke-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgew&auml;hlte Datei: <span id="staerke-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('staerke', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
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
                                                                    <img src="../../assets/placeholders/Platzhalter_Mitarbeiter.svg" alt="Mitarbeiter Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Mitarbeiter-Bild hochladen</span>
                                                                        <input id="mitarbeiter-bild-<?php echo $plan['id']; ?>" name="mitarbeiter_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('mitarbeiter', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="mitarbeiter-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgewählte Datei: <span id="mitarbeiter-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('mitarbeiter', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF bis zu 10MB</p>
                                                                </div>
                                                            </label>
                                                        </div>

                                                        <!-- Bild des Duftmittels -->
                                                        <div>
                                                            <label for="duftmittel-bild-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-900 mb-2">Bild des Duftmittels</label>
                                                            <label for="duftmittel-bild-<?php echo $plan['id']; ?>" class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                                <div class="text-center pointer-events-none">
                                                                    <img src="../../assets/placeholders/Platzhalter_Duft.svg" alt="Duftmittel Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200">
                                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Duftmittel-Bild hochladen</span>
                                                                        <input id="duftmittel-bild-<?php echo $plan['id']; ?>" name="duftmittel_bild" type="file" accept="image/*" class="sr-only" onchange="updateFileName('duftmittel', <?php echo $plan['id']; ?>)" />
                                                                        <!-- Dateiname-Anzeige -->
                                                                        <div id="duftmittel-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                            <span>Ausgew&auml;hlte Datei: <span id="duftmittel-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                            <button type="button" onclick="removeFile('duftmittel', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
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
                                                    <p class="text-sm font-semibold text-gray-900 mt-1 text-center">Lädt...</p>
                                                </div>

                                                <!-- Buttons -->
                                                <div class="flex items-center justify-end gap-x-6 pt-4">
                                                    <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" class="text-sm font-semibold text-gray-900 hover:text-gray-700">Abbrechen</button>
                                                    <button type="submit" id="submit-btn-<?php echo $plan['id']; ?>" class="rounded-md admin-btn-save px-4 py-2 text-sm font-semibold text-white shadow-sm">Speichern</button>
                                                </div>
                                            </form>
                                            
                                    </div>

                                </div>

                                <!-- Rechte Spalte: Plan-Hintergrundbild -->
                                <div class="bg-gray-50 p-4 rounded-lg self-start h-fit">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Plan-Hintergrundbild</h3>
                                    <div class="mt-2">
                                        <?php if (!empty($plan['hintergrund_bild'])): ?>
                                            <?php
                                            $bgPath = $plan['hintergrund_bild'];
                                            $bgExt = strtolower(pathinfo((string)$bgPath, PATHINFO_EXTENSION));
                                            $bgIsVideo = in_array($bgExt, ['mp4', 'webm', 'ogg'], true);
                                            ?>
                                            <?php if ($bgIsVideo): ?>
                                                <video class="w-full h-48 object-cover" autoplay muted loop playsinline>
                                                    <source src="../../uploads/<?php echo htmlspecialchars($bgPath); ?>" type="video/<?php echo htmlspecialchars($bgExt); ?>">
                                                </video>
                                            <?php else: ?>
                                                <img src="../../uploads/<?php echo htmlspecialchars($bgPath); ?>"
                                                    alt="Plan Hintergrundbild"
                                                    class="w-full h-48 object-cover">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">
                                                Kein Hintergrundbild vorhanden
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-4 lg:flex-row">
                                        <div class="flex-1">
                                            <label for="plan-bild-<?php echo $plan['id']; ?>" class="upload-area flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                <div class="text-center pointer-events-none">
                                                    <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                        <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                    </svg>
                                                    <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                        <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Hintergrundbild oder Video hochladen</span>
                                                        <input id="plan-bild-<?php echo $plan['id']; ?>" name="plan_bild" type="file" accept="image/*,video/mp4,video/webm,video/ogg" class="sr-only" onchange="updateFileName('plan', <?php echo $plan['id']; ?>)" />
                                                        <div id="plan-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                            <span>Ausgew&auml;hlte Datei: <span id="plan-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                            <button type="button" onclick="removeFile('plan', <?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
                                                    </div>
                                                    <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF, MP4, WEBM bis zu 10MB</p>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="flex-1 space-y-3">
                                            <div class="flex flex-col gap-3">
                                                <button type="button" id="plan-upload-btn-<?php echo $plan['id']; ?>" onclick="uploadPlanBackgroundImage(<?php echo $plan['id']; ?>)" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center justify-center gap-1 text-center">
                                                    Hochladen <span aria-hidden="true">+</span>
                                                </button>
                                                <?php if (!empty($plan['hintergrund_bild'])): ?>
                                                    <button type="button" onclick="deletePlanBackgroundImage(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')" class="rounded-md bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
                                                        Hintergrundbild L&ouml;schen
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="rounded-md bg-red-200 px-4 py-2 text-sm font-semibold text-white shadow-sm cursor-not-allowed" disabled>
                                                        Hintergrundbild L&ouml;schen
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="space-y-2">
                                                <h4 class="text-sm font-semibold text-gray-700">Vorhandenes Hintergrundbild ausw&auml;hlen</h4>
                                                <select id="plan-background-select-<?php echo $plan['id']; ?>" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanBackground(<?php echo $plan['id']; ?>)">
                                                    <option value="">-- Hintergrundbild w&auml;hlen --</option>
                                                    <?php foreach ($hintergrundOptions as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option['path']); ?>" <?php echo ($plan['hintergrund_bild'] ?? '') === $option['path'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($option['label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-6 border-t border-gray-200 pt-4 space-y-4" data-plan-id="<?php echo $plan['id']; ?>">
                                        <h4 class="text-base font-semibold text-gray-900 text-center">Nächster Aufguss Popup</h4>
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
                                            <div class="flex flex-col gap-3 md:flex-row md:items-end">
                                                <div class="flex-1">
                                                <label for="next-aufguss-lead-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Sekunden vorher anzeigen</label>
                                                <input id="next-aufguss-lead-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="5">
                                                </div>
                                                <button id="next-aufguss-preview-btn-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="button" onclick="previewNextAufgussPopup(<?php echo $plan['id']; ?>)" class="w-full md:w-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                                    Vorschau anzeigen
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-6 border-t border-gray-200 pt-4">
                                                <h3 class="text-lg font-semibold text-gray-900 mb-3 text-center">Farben</h3>
                                                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-center">
                                                    <div class="flex-1">
                                                        <label for="next-aufguss-theme-color-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2 text-center">
                                                            Hintergrundfarbe
                                                        </label>
                                                        <div class="flex items-center justify-center gap-4">
                                                            <input id="next-aufguss-theme-color-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="color" class="h-10 w-20 rounded border border-gray-300 bg-white shadow-sm cursor-pointer">
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <label for="plan-text-color-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2 text-center">
                                                            Textfarbe
                                                        </label>
                                                        <div class="flex items-center justify-center gap-4">
                                                            <input id="plan-text-color-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="color" class="h-10 w-20 rounded border border-gray-300 bg-white shadow-sm cursor-pointer">
                                                        </div>
                                                    </div>
                                                </div>
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
                                                <div id="plan-ad-preview-<?php echo $plan['id']; ?>" class="mt-2">
                                                    <?php if (!empty($plan['werbung_media'])): ?>
                                                        <?php if (($plan['werbung_media_typ'] ?? '') === 'video'): ?>
                                                            <video class="w-full h-48 object-contain" autoplay muted loop playsinline>
                                                                <source src="../../uploads/<?php echo htmlspecialchars($plan['werbung_media']); ?>" type="video/<?php echo htmlspecialchars($plan['werbung_media_typ'] ?? 'mp4'); ?>">
                                                            </video>
                                                        <?php else: ?>
                                                            <img src="../../uploads/<?php echo htmlspecialchars($plan['werbung_media']); ?>"
                                                                alt="Werbung"
                                                                class="w-full h-48 object-contain">
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">
                                                            Keine Werbung vorhanden
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-4 flex flex-col gap-4 lg:flex-row">
                                                    <div class="flex-1">
                                                        <label for="plan-ad-file-<?php echo $plan['id']; ?>" class="upload-area flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                                                            <div class="text-center pointer-events-none">
                                                                <svg viewBox="0 0 24 24" fill="currentColor" data-slot="icon" aria-hidden="true" class="mx-auto size-8 text-gray-300">
                                                                    <path d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                                </svg>
                                                                <div class="mt-2 flex flex-col text-lg text-gray-600">
                                                                    <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Werbung hochladen</span>
                                                                    <input id="plan-ad-file-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="file" accept="image/*,video/*" class="sr-only" onchange="updateAdFileName(<?php echo $plan['id']; ?>)" />
                                                                    <div id="plan-ad-filename-<?php echo $plan['id']; ?>" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                                                        <span>Ausgew&auml;hlte Datei: <span id="plan-ad-filename-text-<?php echo $plan['id']; ?>"></span></span>
                                                                        <button type="button" onclick="removeAdFile(<?php echo $plan['id']; ?>)" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                            </svg>
                                                                        </button>
                                                                    </div>
                                                                    <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
                                                                </div>
                                                                <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF, MP4, WEBM, OGG bis zu 50MB</p>
                                                            </div>
                                                        </label>
                                                    </div>
                                                    <div class="flex-1 space-y-3">
                                                        <div class="flex flex-col gap-3">
                                                            <button type="button" id="plan-ad-upload-btn-<?php echo $plan['id']; ?>" onclick="uploadPlanAdMedia(<?php echo $plan['id']; ?>)" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center justify-center gap-1 text-center">
                                                                Hochladen <span aria-hidden="true">+</span>
                                                            </button>

                                                            <?php if (!empty($plan['werbung_media'])): ?>
                                                                <button type="button" id="plan-ad-delete-btn-<?php echo $plan['id']; ?>" onclick="deletePlanAdMedia(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'] ?? ''); ?>')" class="rounded-md bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
                                                                    Werbung L&ouml;schen
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" id="plan-ad-delete-btn-<?php echo $plan['id']; ?>" class="rounded-md bg-red-200 px-4 py-2 text-sm font-semibold text-white shadow-sm cursor-not-allowed" disabled>
                                                                    Werbung L&ouml;schen
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="space-y-2">
                                                            <h4 class="text-sm font-semibold text-gray-700">Vorhandene Werbung ausw&auml;hlen</h4>
                                                            <select id="plan-ad-select-<?php echo $plan['id']; ?>" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanAdMedia(<?php echo $plan['id']; ?>)">
                                                                <option value="">-- Werbung w&auml;hlen --</option>
                                                                <?php foreach ($werbungOptions as $option): ?>
                                                                    <option value="<?php echo htmlspecialchars($option['path']); ?>" data-type="<?php echo htmlspecialchars($option['type']); ?>" <?php echo ($plan['werbung_media'] ?? '') === $option['path'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($option['label']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="border-t border-gray-200 pt-4 space-y-3">
                                                    <div class="flex flex-col gap-3 md:flex-row md:items-end">
                                                        <div class="flex-1">
                                                            <label for="plan-ad-interval-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Intervall (Minuten)</label>
                                                            <input id="plan-ad-interval-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="<?php echo htmlspecialchars($plan['werbung_interval_minuten'] ?? 10); ?>">
                                                        </div>
                                                        <div class="flex-1">
                                                            <label for="plan-ad-duration-<?php echo $plan['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Anzeigedauer (Sekunden)</label>
                                                            <input id="plan-ad-duration-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="number" min="1" max="3600" step="1" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" value="<?php echo htmlspecialchars($plan['werbung_dauer_sekunden'] ?? 10); ?>">
                                                        </div>
                                                        <button id="plan-ad-preview-btn-<?php echo $plan['id']; ?>" data-plan-id="<?php echo $plan['id']; ?>" type="button" onclick="previewPlanAd(<?php echo $plan['id']; ?>)" class="w-full md:w-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                                            Vorschau anzeigen
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                            
                                        
                                    </div>
                                </div>

                            </div>
                            <div class="lg:col-span-2 mt-6 border-t border-gray-200 pt-4 mb-6">
                                <button type="button" onclick="toggleForm(<?php echo $plan['id']; ?>)" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 10a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    Planbearbeitung schliessen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Datenbank-Uebersicht eingebunden -->
        <?php include __DIR__ . '/../partials/aufguesse_db_overview.php'; ?>
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
                <input id="planBannerEnabled" type="checkbox" class="sr-only">
                <div>
                    <label for="planBannerText" class="block text-sm font-medium text-gray-700 mb-1">Banner-Text (optional)</label>
                    <textarea id="planBannerText" rows="5" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Text für den Info-Banner" style="width: 220px; box-sizing: border-box;"></textarea>
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
                        <span>Bild/Video anzeigen</span>
                    </label>
                </div>
                <input id="planBannerImage" type="hidden">
                <div>
                    <label for="planBannerImageSelect" class="block text-sm font-medium text-gray-700 mb-1">Vorhandene Werbung auswählen</label>
                    <select id="planBannerImageSelect" class="block w-full rounded-md bg-white px-3 py-2 text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="selectPlanBannerImage()">
                        <option value="">-- Werbung wählen --</option>
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
                                <span class="relative rounded-md bg-transparent font-semibold text-indigo-600 hover:text-indigo-500">Banner-Medien hochladen</span>
                                <input id="planBannerFile" type="file" accept="image/*,video/*" class="sr-only" onchange="updatePlanBannerFileName()" />
                                <div id="plan-banner-filename" class="mt-2 text-xs text-green-600 font-medium hidden flex items-center justify-between">
                                    <span>Ausgewählte Datei: <span id="plan-banner-filename-text"></span></span>
                                    <button type="button" onclick="removePlanBannerFile()" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">PNG, JPG, GIF, MP4, WebM, OGG bis zu 10MB</p>
                        </div>
                    </label>
                    <button type="button" onclick="uploadPlanBannerImage()" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Hochladen
                    </button>
                    <p class="text-xs text-gray-500">Hinweis: Für volle Seitenhöhe sollte das Bild etwa 1080px hoch sein (Full-HD).</p>
                    <p class="text-xs text-gray-500">Empfohlene Breite: 220px.</p>
                </div>
                <p class="text-xs text-gray-500">Der Banner passt seine Höhe automatisch an den Text an.</p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closePlanBannerModal()" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-200">Abbrechen</button>
                    <button type="button" onclick="savePlanBannerSettings()" class="rounded-md admin-btn-save px-4 py-2 text-sm font-semibold text-white">Speichern</button>
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
                        <label class="block text-sm font-medium text-gray-900 mb-2">Neues Bild auswählen</label>
                        <div class="upload-area mt-2 flex flex-col items-center rounded-lg border border-dashed border-gray-900/25 px-6 py-6 transition cursor-pointer">
                            <div class="text-center">
                                <img src="../../assets/placeholders/Platzhalter_Mitarbeiter.svg" alt="Mitarbeiter Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200" data-modal-placeholder="mitarbeiter">
                                <img src="../../assets/placeholders/Platzhalter_Sauna.svg" alt="Sauna Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200 hidden" data-modal-placeholder="sauna">
                                <img src="../../assets/placeholders/Platzhalter_Duft.svg" alt="Duftmittel Platzhalter" class="mx-auto h-10 w-10 rounded-full object-cover border border-gray-200 hidden" data-modal-placeholder="duftmittel">
                                <div class="mt-2 flex flex-col text-lg text-gray-600">
                                    <label for="modalImageInput" class="relative cursor-pointer rounded-md bg-transparent font-semibold text-indigo-600 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-indigo-600 hover:text-indigo-500">
                                        <span>Bild auswählen</span>
                                        <input id="modalImageInput" name="bild" type="file" accept="image/*" class="sr-only" onchange="updateModalFileName()" />
                                    </label>
                                    <!-- Dateiname-Anzeige -->
                                    <div id="modalFilename" class="mt-2 text-xs text-green-600 font-medium hidden">
                                        Ausgewählte Datei: <span id="modalFilenameText"></span>
                                        <button type="button" onclick="removeModalFile()" class="text-red-500 hover:text-red-700 ml-2" title="Datei entfernen">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="pl-1 flex justify-center text-center">oder ziehen und ablegen</p>
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
                        <p class="text-sm font-semibold text-gray-900 mt-1 text-center">Lädt...</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-x-6 pt-4">
                        <button type="button" onclick="closeImageModal()" class="text-sm font-semibold text-gray-900 hover:text-gray-700">Abbrechen</button>
                        <button type="submit" id="modalSubmitBtn" class="rounded-md admin-btn-save px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Nächster Aufguss Popup -->
    <div id="next-aufguss-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Nächster Aufguss</h3>
                <button type="button" onclick="closeNextAufgussPopup()" class="text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Schliessen</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="next-aufguss-body" class="p-5">
                <div class="text-sm text-gray-500">Lädt...</div>
            </div>
        </div>
    </div>

    <script>
        window.APP_BASE_URL = '<?php echo rtrim(BASE_URL, '/'); ?>/';
        window.APP_UPLOADS_URL = '<?php echo rtrim(BASE_URL, '/'); ?>/uploads/';
    </script>
    <script src="../../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin.js'); ?>"></script>
    <script src="../../assets/js/admin-db-overview.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin-db-overview.js'); ?>"></script>
    <script src="../../assets/js/admin-aufguesse.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin-aufguesse.js'); ?>"></script>
    
</body>

</html>

























