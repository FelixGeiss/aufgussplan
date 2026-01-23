<?php
/**
 * BILDSCHIRME-VERWALTUNG
 *
 * Platzhalterseite fuer die Verwaltung der TV-Bildschirme.
 */

session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/auth.php';

require_login();
require_permission('bildschirme');

// Listet Upload-Dateien im Unterordner (optional nach Endung gefiltert).
function listUploadFiles($subDir, $allowedExtensions = []) {
    $files = [];
    $uploadDir = UPLOAD_PATH . $subDir . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        return $files;
    }

    foreach (scandir($uploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $uploadDir . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
            continue;
        }
        $files[] = $subDir . '/' . $entry;
    }

    sort($files);
    return $files;
}

$screenImages = listUploadFiles('screens', ['jpg', 'jpeg', 'png', 'gif']);
$backgroundImages = listUploadFiles('plan', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg']);
$werbungFiles = listUploadFiles('werbung');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <link rel="icon" href="/AufgussManager/branding/favicon/favicon.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildschirme verwalten - Aufgussplan</title>
    <link rel="stylesheet" href="../../dist/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admin.css'); ?>">
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-4">Bildschirme verwalten</h2>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-6">
                <p class="text-gray-600">
                    Stelle die Werbung f&uuml;r die Bildschirme ein.
                </p>
                <a href="../../index.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Zur Anzeige</a>
            </div>
            <div id="global-ad-card" class="mb-6"></div>
            <div id="screen-list" class="grid grid-cols-1 lg:grid-cols-2 gap-6"></div>
        </div>
    </div>

    <script>
        window.ScreenMediaOptions = <?php echo json_encode([
            'screens' => $screenImages,
            'backgrounds' => $backgroundImages,
            'ads' => $werbungFiles
        ], JSON_PRETTY_PRINT); ?>;
    </script>
    <script src="../../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin.js'); ?>"></script>
    <script src="../../assets/js/bildschirme.js"></script>
</body>
</html>


