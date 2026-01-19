<?php
/**
 * Upload fuer globale Werbung.
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

if (!isset($_FILES['werbung']) || $_FILES['werbung']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
    exit;
}

$file = $_FILES['werbung'];

if ($file['size'] > 50 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datei ist zu gross (max. 50MB)']);
    exit;
}

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'video/mp4',
    'video/webm',
    'video/ogg'
];

if (!in_array($file['type'], $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungueltiger Dateityp']);
    exit;
}

$uploadDir = UPLOAD_PATH . 'werbung' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . basename($file['name']);
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern der Datei']);
    exit;
}

$relativePath = 'werbung/' . $filename;
$mediaType = str_starts_with($file['type'], 'video/') ? 'video' : 'image';

try {
    $storageDir = __DIR__ . '/../../../storage';
    $storageFile = $storageDir . '/bildschirme.json';
    $config = readScreenConfig($storageFile);
    $currentGlobal = $config['global_ad'] ?? [];
    $config['global_ad'] = array_merge($currentGlobal, [
        'path' => $relativePath,
        'type' => $mediaType
    ]);

    writeScreenConfig($storageDir, $storageFile, $config);

    echo json_encode([
        'success' => true,
        'data' => [
            'path' => $relativePath,
            'type' => $mediaType,
            'global_ad' => $config['global_ad']
        ]
    ]);
} catch (Exception $e) {
    error_log('Global ad upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interner Serverfehler']);
}

// Laedt die Bildschirm-Konfiguration inkl. globaler Werbung.
function readScreenConfig($storageFile) {
    $config = ['screens' => [], 'global_ad' => [
        'path' => null,
        'type' => null,
        'enabled' => false,
        'order' => [],
        'display_seconds' => 10,
        'pause_seconds' => 10,
        'rotation_started_at' => null
    ]];
    if (file_exists($storageFile)) {
        $raw = file_get_contents($storageFile);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data)) {
            if (isset($data['screens']) && is_array($data['screens'])) {
                $config['screens'] = $data['screens'];
            }
            if (isset($data['global_ad']) && is_array($data['global_ad'])) {
                $config['global_ad'] = array_merge($config['global_ad'], $data['global_ad']);
            }
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
?>
