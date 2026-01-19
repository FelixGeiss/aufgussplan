<?php
/**
 * API fuer Mitarbeiter-Verwaltung
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db/connection.php';

if (!is_admin_logged_in()) {
    sendResponse(false, 'Nicht angemeldet', null, 401);
}
if (!has_permission('mitarbeiter')) {
    sendResponse(false, 'Keine Berechtigung', null, 403);
}

$db = Database::getInstance()->getConnection();
ensureBackupPermissionColumn($db);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetMitarbeiter($db);
            break;
        case 'POST':
            handleCreateMitarbeiter($db);
            break;
        case 'PUT':
            handleUpdateMitarbeiter($db);
            break;
        case 'DELETE':
            handleDeleteMitarbeiter($db);
            break;
        default:
            sendResponse(false, 'HTTP-Methode nicht unterstuetzt', null, 405);
    }
} catch (Exception $e) {
    error_log('API-Fehler in mitarbeiter.php: ' . $e->getMessage());
    sendResponse(false, 'Interner Serverfehler', null, 500);
}

// Liefert die Mitarbeiterliste.
function handleGetMitarbeiter($db) {
    $stmt = $db->query(
        "SELECT id, name, position, username, aktiv, can_aufguesse, can_statistik, can_umfragen, can_mitarbeiter, can_bildschirme, can_backup, is_admin
         FROM mitarbeiter
         ORDER BY name ASC"
    );
    $mitarbeiter = $stmt->fetchAll();
    sendResponse(true, 'Mitarbeiter erfolgreich abgerufen', ['mitarbeiter' => $mitarbeiter]);
}

// Legt einen neuen Mitarbeiter an.
function handleCreateMitarbeiter($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        $name = 'Unbenannter Mitarbeiter';
    }

    $position = trim((string)($input['position'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($username !== '') {
        $stmt = $db->prepare('SELECT id FROM mitarbeiter WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            sendResponse(false, 'Benutzername ist bereits vergeben', null, 400);
        }
    }

    $passwordHash = $username !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

    $aktiv = normalizeBool($input['aktiv'] ?? true);
    $canAufguesse = normalizeBool($input['can_aufguesse'] ?? false);
    $canStatistik = normalizeBool($input['can_statistik'] ?? false);
    $canUmfragen = normalizeBool($input['can_umfragen'] ?? false);
    $canMitarbeiter = normalizeBool($input['can_mitarbeiter'] ?? false);
    $canBildschirme = normalizeBool($input['can_bildschirme'] ?? false);
    $canBackup = normalizeBool($input['can_backup'] ?? false);
    $isAdmin = normalizeBool($input['is_admin'] ?? false);

    $stmt = $db->prepare(
        "INSERT INTO mitarbeiter (name, position, username, password_hash, aktiv, can_aufguesse, can_statistik, can_umfragen, can_mitarbeiter, can_bildschirme, can_backup, is_admin)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $name,
        $position !== '' ? $position : null,
        $username !== '' ? $username : null,
        $passwordHash,
        $aktiv,
        $canAufguesse,
        $canStatistik,
        $canUmfragen,
        $canMitarbeiter,
        $canBildschirme,
        $canBackup,
        $isAdmin
    ]);

    $mitarbeiterId = $db->lastInsertId();
    sendResponse(true, 'Mitarbeiter erfolgreich erstellt', ['mitarbeiter_id' => $mitarbeiterId]);
}

// Aktualisiert einen Mitarbeiter.
function handleUpdateMitarbeiter($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['id'])) {
        sendResponse(false, 'Mitarbeiter-ID ist erforderlich', null, 400);
        return;
    }

    $mitarbeiterId = (int)$input['id'];

    $stmt = $db->prepare('SELECT password_hash FROM mitarbeiter WHERE id = ?');
    $stmt->execute([$mitarbeiterId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        sendResponse(false, 'Mitarbeiter nicht gefunden', null, 404);
        return;
    }

    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        $name = 'Unbenannter Mitarbeiter';
    }

    $position = trim((string)($input['position'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($username !== '') {
        $stmt = $db->prepare('SELECT id FROM mitarbeiter WHERE username = ? AND id != ?');
        $stmt->execute([$username, $mitarbeiterId]);
        if ($stmt->fetch()) {
            sendResponse(false, 'Benutzername ist bereits vergeben', null, 400);
        }
    }

    $passwordHash = $existing['password_hash'] ?? null;
    if ($username === '') {
        $username = null;
        $passwordHash = null;
    } elseif ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    } elseif (empty($passwordHash)) {
        sendResponse(false, 'Passwort fehlt fuer diesen Benutzer', null, 400);
    }

    $aktiv = normalizeBool($input['aktiv'] ?? true);
    $canAufguesse = normalizeBool($input['can_aufguesse'] ?? false);
    $canStatistik = normalizeBool($input['can_statistik'] ?? false);
    $canUmfragen = normalizeBool($input['can_umfragen'] ?? false);
    $canMitarbeiter = normalizeBool($input['can_mitarbeiter'] ?? false);
    $canBildschirme = normalizeBool($input['can_bildschirme'] ?? false);
    $canBackup = normalizeBool($input['can_backup'] ?? false);
    $isAdmin = normalizeBool($input['is_admin'] ?? false);

    $stmt = $db->prepare(
        "UPDATE mitarbeiter
         SET name = ?, position = ?, username = ?, password_hash = ?, aktiv = ?, can_aufguesse = ?, can_statistik = ?, can_umfragen = ?, can_mitarbeiter = ?, can_bildschirme = ?, can_backup = ?, is_admin = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $name,
        $position !== '' ? $position : null,
        $username,
        $passwordHash,
        $aktiv,
        $canAufguesse,
        $canStatistik,
        $canUmfragen,
        $canMitarbeiter,
        $canBildschirme,
        $canBackup,
        $isAdmin,
        $mitarbeiterId
    ]);

    sendResponse(true, 'Mitarbeiter erfolgreich aktualisiert');
}

// Loescht einen Mitarbeiter.
function handleDeleteMitarbeiter($db) {
    $mitarbeiterId = $_GET['id'] ?? null;

    if (!$mitarbeiterId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $mitarbeiterId = $input['id'] ?? null;
    }

    if (!$mitarbeiterId) {
        sendResponse(false, 'Mitarbeiter-ID ist erforderlich', null, 400);
        return;
    }

    $stmt = $db->prepare('DELETE FROM mitarbeiter WHERE id = ?');
    $stmt->execute([$mitarbeiterId]);

    sendResponse(true, 'Mitarbeiter erfolgreich geloescht');
}

// Normalisiert Bool-Werte fuer Datenbankfelder.
function normalizeBool($value) {
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_numeric($value)) {
        return (int)((int)$value === 1);
    }
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

// Stellt sicher, dass die Spalte can_backup existiert.
function ensureBackupPermissionColumn(PDO $db) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM mitarbeiter LIKE 'can_backup'");
        $stmt->execute();
        if ($stmt->fetch()) {
            return;
        }
        $db->exec("ALTER TABLE mitarbeiter ADD COLUMN can_backup TINYINT(1) NOT NULL DEFAULT 0 AFTER can_bildschirme");
    } catch (Exception $e) {
        error_log('Migration can_backup fehlgeschlagen: ' . $e->getMessage());
    }
}

// Sendet eine JSON-Antwort mit Statuscode.
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
