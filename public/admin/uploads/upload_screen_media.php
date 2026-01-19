<?php
/**
 * Upload fuer Bildschirm-Medien (Anzeige-Bild oder Hintergrund).
 */

session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$screenId = (int)($_POST['screen_id'] ?? 0);
$kind = $_POST['kind'] ?? '';

if ($screenId < 1 || $screenId > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungueltige Bildschirm-ID']);
    exit;
}

if (!in_array($kind, ['image', 'background'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungueltiger Typ']);
    exit;
}

if (!isset($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
    exit;
}

$file = $_FILES['bild'];

if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datei ist zu gross']);
    exit;
}

if (!in_array($file['type'], ALLOWED_IMAGE_TYPES, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungueltiger Dateityp']);
    exit;
}

$uploadSubDir = $kind === 'background' ? 'plan' : 'screens';
$uploadDir = UPLOAD_PATH . $uploadSubDir . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = sprintf('screen_%d_%s_%s.%s', $screenId, $kind, uniqid(), $extension);
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei']);
    exit;
}

$relativePath = $uploadSubDir . '/' . $filename;

try {
    $storageDir = __DIR__ . '/../../../storage';
    $storageFile = $storageDir . '/bildschirme.json';
    $config = readScreenConfig($storageFile);
    $screen = $config['screens'][$screenId] ?? defaultScreen($screenId);

    $field = $kind === 'background' ? 'background_path' : 'image_path';
    $oldPath = $screen[$field] ?? null;

    if ($oldPath) {
        $oldFullPath = UPLOAD_PATH . ltrim($oldPath, "/\\");
        if (file_exists($oldFullPath)) {
            unlink($oldFullPath);
        }
    }

    $screen[$field] = $relativePath;
    $screen['updated_at'] = date('c');
    $config['screens'][$screenId] = $screen;

    writeScreenConfig($storageDir, $storageFile, $config);

    echo json_encode(['success' => true, 'data' => ['path' => $relativePath, 'screen' => $screen]]);
} catch (Exception $e) {
    error_log('Screen upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interner Serverfehler']);
}

// Laedt die Bildschirm-Konfiguration aus der JSON-Datei.
function readScreenConfig($storageFile) {
    $config = ['screens' => []];
    if (file_exists($storageFile)) {
        $raw = file_get_contents($storageFile);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['screens']) && is_array($data['screens'])) {
            $config['screens'] = $data['screens'];
        }
    }

    return $config;
}

// Speichert die Bildschirm-Konfiguration als JSON.
function writeScreenConfig($storageDir, $storageFile, $config) {
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    file_put_contents($storageFile, json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);
}

// Liefert Default-Werte fuer einen Bildschirm.
function defaultScreen($screenId) {
    return [
        'id' => (int)$screenId,
        'mode' => 'plan',
        'plan_id' => null,
        'image_path' => null,
        'background_path' => null,
        'updated_at' => null
    ];
}
?>
