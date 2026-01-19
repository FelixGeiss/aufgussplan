<?php
session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/db/connection.php';
require_once __DIR__ . '/../../../src/auth.php';

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

if (is_admin_logged_in()) {
    header('Location: ' . BASE_URL . 'admin/pages/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['guest_login'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = 0;
        $_SESSION['admin_user_name'] = 'Gast';
        $_SESSION['admin_username'] = 'guest';
        $_SESSION['admin_is_admin'] = true;
        $_SESSION['admin_permissions'] = [
            'aufguesse' => true,
            'statistik' => true,
            'umfragen' => true,
            'mitarbeiter' => true,
            'bildschirme' => true,
            'backup' => true,
        ];

        header('Location: ' . BASE_URL . 'admin/pages/index.php');
        exit;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $db = Database::getInstance()->getConnection();
        ensureBackupPermissionColumn($db);
        $stmt = $db->prepare('SELECT id, name, username, password_hash, aktiv, can_aufguesse, can_statistik, can_umfragen, can_mitarbeiter, can_bildschirme, can_backup, is_admin FROM mitarbeiter WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !(int)$user['aktiv'] || empty($user['password_hash'])) {
            $error = 'Login fehlgeschlagen.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Login fehlgeschlagen.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_user_name'] = (string)$user['name'];
            $_SESSION['admin_username'] = (string)$user['username'];
            $_SESSION['admin_is_admin'] = (int)$user['is_admin'] === 1;
            $_SESSION['admin_permissions'] = [
                'aufguesse' => (int)$user['can_aufguesse'] === 1,
                'statistik' => (int)$user['can_statistik'] === 1,
                'umfragen' => (int)$user['can_umfragen'] === 1,
                'mitarbeiter' => (int)$user['can_mitarbeiter'] === 1,
                'bildschirme' => (int)$user['can_bildschirme'] === 1,
                'backup' => (int)($user['can_backup'] ?? 0) === 1,
            ];

            header('Location: ' . BASE_URL . 'admin/pages/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Aufgussplan</title>
    <link rel="stylesheet" href="../../dist/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-4">Admin Login</h1>
            <?php if ($error): ?>
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Benutzername</label>
                    <input type="text" name="username" class="w-full rounded border px-3 py-2" autocomplete="username" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                    <input type="password" name="password" class="w-full rounded border px-3 py-2" autocomplete="current-password" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Login</button>
            </form>
            <form method="post" class="mt-4">
                <input type="hidden" name="guest_login" value="1">
                <button type="submit" class="w-full text-white px-4 py-2 rounded hover:opacity-90" style="background: var(--admin-color-success-600);">
                    Gast-Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>
