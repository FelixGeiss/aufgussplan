<?php
session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/db/connection.php';

require_login();
require_permission('backup');

$db = Database::getInstance()->getConnection();

$errors = [];
$messages = [];
$localStorageRestoreJson = null;

if (!empty($_SESSION['localstorage_restore_json'])) {
    $localStorageRestoreJson = $_SESSION['localstorage_restore_json'];
    unset($_SESSION['localstorage_restore_json']);
}
$dbOverview = [
    'plaene' => 0,
    'aufguesse' => 0,
    'aufguss_namen' => 0,
    'saunen' => 0,
    'duftmittel' => 0,
    'mitarbeiter' => 0,
    'umfragen' => 0,
    'werbung_medien' => 0,
    'hintergrund_bilder' => 0,
    'uploads_count' => 0,
    'uploads_size' => 0
];
$backupMetaPath = ROOT_PATH . 'storage' . DIRECTORY_SEPARATOR . 'backup_meta.json';
$lastBackupLabel = 'Noch kein Backup erstellt.';
$aufguss_optionen = [];
$saunen = [];
$duftmittel = [];
$mitarbeiter = [];
$umfrage_bewertungen = [];
$werbungTabFiles = [];
$hintergrundTabFiles = [];

if (is_file($backupMetaPath)) {
    $metaRaw = file_get_contents($backupMetaPath);
    $meta = $metaRaw ? json_decode($metaRaw, true) : null;
    if (is_array($meta) && !empty($meta['created_at'])) {
        $createdAt = strtotime((string)$meta['created_at']);
        if ($createdAt) {
            $filename = !empty($meta['filename']) ? (string)$meta['filename'] : '';
            $dateLabel = date('d.m.Y H:i', $createdAt);
            $lastBackupLabel = $filename !== '' ? ($dateLabel . ' - ' . $filename) : $dateLabel;
        }
    }
}


try {
    $dbOverview['plaene'] = (int)$db->query("SELECT COUNT(*) FROM plaene")->fetchColumn();
    $dbOverview['aufguesse'] = (int)$db->query("SELECT COUNT(*) FROM aufguesse")->fetchColumn();
    $dbOverview['aufguss_namen'] = (int)$db->query("SELECT COUNT(*) FROM aufguss_namen")->fetchColumn();
    $dbOverview['saunen'] = (int)$db->query("SELECT COUNT(*) FROM saunen")->fetchColumn();
    $dbOverview['duftmittel'] = (int)$db->query("SELECT COUNT(*) FROM duftmittel")->fetchColumn();
    $dbOverview['mitarbeiter'] = (int)$db->query("SELECT COUNT(*) FROM mitarbeiter")->fetchColumn();
    $dbOverview['umfragen'] = (int)$db->query("SELECT COUNT(*) FROM umfrage_bewertungen")->fetchColumn();
    $aufguss_optionen = $db->query("SELECT id, name, beschreibung FROM aufguss_namen ORDER BY name")->fetchAll();
    $saunen = $db->query("SELECT id, name, bild, beschreibung, temperatur FROM saunen ORDER BY name")->fetchAll();
$duftmittel = $db->query("SELECT id, name, beschreibung, bild FROM duftmittel ORDER BY name")->fetchAll();
    $mitarbeiter = $db->query("SELECT id, name, bild FROM mitarbeiter ORDER BY name")->fetchAll();
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
} catch (Throwable $e) {
    $errors[] = 'Konnte Datenbank-Uebersicht nicht laden: ' . $e->getMessage();
}

if (is_dir(UPLOAD_PATH)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_PATH, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $dbOverview['uploads_count']++;
            $dbOverview['uploads_size'] += $file->getSize();
        }
    }
}
$uploadBaseDir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR;
$werbungUploadDir = $uploadBaseDir . 'werbung' . DIRECTORY_SEPARATOR;
$planUploadDir = $uploadBaseDir . 'plan' . DIRECTORY_SEPARATOR;
$staerkeUploadDir = $uploadBaseDir . 'staerke' . DIRECTORY_SEPARATOR;
$staerkeUploadFiles = [];
if (is_dir($werbungUploadDir)) {
    foreach (scandir($werbungUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $werbungUploadDir . $entry;
        if (is_file($fullPath)) {
            $dbOverview['werbung_medien']++;
            $path = 'werbung/' . $entry;
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
    }
}
if (is_dir($planUploadDir)) {
    foreach (scandir($planUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $planUploadDir . $entry;
        if (is_file($fullPath)) {
            $dbOverview['hintergrund_bilder']++;
            $path = 'plan/' . $entry;
            $hintergrundTabFiles[] = [
                'bereich' => 'Plan',
                'name' => 'Datei',
                'datei' => $path,
                'typ' => 'Hintergrundbild',
                'plan_id' => null
            ];
        }
    }
}

if (is_dir($staerkeUploadDir)) {
    foreach (scandir($staerkeUploadDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $staerkeUploadDir . $entry;
        if (is_file($fullPath)) {
            $staerkeUploadFiles[] = 'staerke/' . $entry;
        }
    }
}

function sql_value(PDO $db, $value) {
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return $db->quote((string)$value);
}

function stream_sql_backup(PDO $db, $dbName) {
    $timestamp = date('c');
    echo "-- Aufgussplan DB backup\n";
    echo "-- Database: {$dbName}\n";
    echo "-- Created: {$timestamp}\n\n";
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET time_zone = \"+00:00\";\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "START TRANSACTION;\n\n";

    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $table = (string)$table;
        echo "-- Table structure for `{$table}`\n";
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        $createRow = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = '';
        if ($createRow) {
            $values = array_values($createRow);
            $createSql = $values[1] ?? '';
        }
        if ($createSql !== '') {
            echo $createSql . ";\n\n";
        }

        $columns = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        if (!$columns) {
            echo "\n";
            continue;
        }
        $colList = '`' . implode('`,`', $columns) . '`';

        $stmt = $db->query("SELECT * FROM `{$table}`");
        $batch = [];
        $batchSize = 200;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = sql_value($db, $row[$col] ?? null);
            }
            $batch[] = '(' . implode(',', $values) . ')';
            if (count($batch) >= $batchSize) {
                echo "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if (!empty($batch)) {
            echo "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        echo "\n";
    }

    echo "COMMIT;\n";
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
}

function build_sql_backup(PDO $db, $dbName) {
    ob_start();
    stream_sql_backup($db, $dbName);
    return ob_get_clean();
}

function format_bytes($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = (int)floor(log($bytes, 1024));
    $pow = min($pow, count($units) - 1);
    $value = $bytes / pow(1024, $pow);
    return number_format($value, $value < 10 ? 2 : 1, '.', '') . ' ' . $units[$pow];
}

function add_folder_to_zip(ZipArchive $zip, $sourcePath, $zipRoot) {
    $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
    if (!is_dir($sourcePath)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        $filePath = $file->getPathname();
        $relative = substr($filePath, strlen($sourcePath) + 1);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        $zip->addFile($filePath, $zipRoot . '/' . $relative);
    }
}

function create_backup_zip(PDO $db, $destinationPath, $localStorageJson = null) {
    $zip = new ZipArchive();
    if ($zip->open($destinationPath, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Konnte Backup ZIP nicht erstellen.');
    }
    $sql = build_sql_backup($db, DB_NAME);
    $zip->addFromString('database.sql', $sql);
    add_folder_to_zip($zip, UPLOAD_PATH, 'uploads');
    if ($localStorageJson !== null && $localStorageJson !== '') {
        $zip->addFromString('localstorage.json', $localStorageJson);
    }
    $zip->close();
}

function safe_extract_zip(ZipArchive $zip, $dest) {
    if (!is_dir($dest) && !mkdir($dest, 0775, true)) {
        throw new RuntimeException('Konnte Temp-Ordner nicht erstellen.');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            continue;
        }
        if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) {
            continue;
        }
        if (preg_match('/^[a-zA-Z]:\\\\/', $name) || substr($name, 0, 1) === '/') {
            continue;
        }

        $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
            throw new RuntimeException('Konnte Ordner fuer Restore nicht erstellen.');
        }

        $stream = $zip->getStream($name);
        if ($stream === false) {
            continue;
        }
        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            throw new RuntimeException('Konnte Datei nicht schreiben: ' . $target);
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
    }
}

function execute_sql_dump(PDO $db, $sql) {
    $length = strlen($sql);
    $buffer = '';
    $inString = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if (!$inString) {
            if ($char === '-' && ($i + 1) < $length && $sql[$i + 1] === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($char === '/' && ($i + 1) < $length && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < ($length - 1)) {
                    if ($sql[$i] === '*' && $sql[$i + 1] === '/') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }
        }

        if ($char === "'") {
            if ($inString) {
                $prev = $i > 0 ? $sql[$i - 1] : '';
                $next = ($i + 1) < $length ? $sql[$i + 1] : '';
                if ($next === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                if ($prev !== '\\') {
                    $inString = false;
                }
            } else {
                $inString = true;
            }
        }

        if (!$inString && $char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $db->exec($statement);
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $db->exec($statement);
    }
}

function copy_directory($source, $dest) {
    if (!is_dir($source)) {
        return;
    }
    if (!is_dir($dest) && !mkdir($dest, 0775, true)) {
        throw new RuntimeException('Konnte Upload-Ordner nicht erstellen.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true)) {
                throw new RuntimeException('Konnte Ordner nicht erstellen: ' . $targetPath);
            }
        } else {
            if (!is_dir(dirname($targetPath)) && !mkdir(dirname($targetPath), 0775, true)) {
                throw new RuntimeException('Konnte Ordner nicht erstellen: ' . dirname($targetPath));
            }
            if (!copy($item->getPathname(), $targetPath)) {
                throw new RuntimeException('Konnte Datei nicht kopieren: ' . $targetPath);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'backup') {
        if (!class_exists('ZipArchive')) {
            $errors[] = 'ZipArchive ist nicht verfuegbar. Bitte PHP-Zip-Erweiterung aktivieren.';
        } else {
            $timestamp = date('Ymd_His');
            $filename = 'aufgussplan_backup_' . $timestamp . '.zip';
            $tmpFile = tempnam(sys_get_temp_dir(), 'aufgussplan_backup_');
            if ($tmpFile === false) {
                $errors[] = 'Konnte temporaere Backup-Datei nicht erstellen.';
            } else {
                try {
                    $localStorageJson = null;
                    if (!empty($_POST['localstorage_json'])) {
                        $decoded = base64_decode((string)$_POST['localstorage_json'], true);
                        if ($decoded !== false && trim($decoded) !== '') {
                            $localStorageJson = $decoded;
                        }
                    }
                    create_backup_zip($db, $tmpFile, $localStorageJson);

                    $metaDir = dirname($backupMetaPath);
                    if (!is_dir($metaDir)) {
                        mkdir($metaDir, 0775, true);
                    }
                    $metaPayload = [
                        'created_at' => date('c'),
                        'filename' => $filename
                    ];
                    @file_put_contents($backupMetaPath, json_encode($metaPayload));

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($tmpFile));
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    readfile($tmpFile);
                    unlink($tmpFile);
                    exit;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
                if (is_file($tmpFile)) {
                    unlink($tmpFile);
                }
            }
        }
    }

    if ($action === 'restore') {
        set_time_limit(0);
        $confirm = !empty($_POST['confirm_restore']);
        if (!$confirm) {
            $errors[] = 'Bitte bestätigen, dass die aktuelle Datenbank überschrieben wird.';
        }
        if (empty($_FILES['backup_file']) || ($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Bitte eine gueltige .sql- oder .zip-Datei auswaehlen.';
        }

        if (empty($errors)) {
            $tmpUpload = $_FILES['backup_file']['tmp_name'];
            $originalName = $_FILES['backup_file']['name'] ?? '';
            $isZip = (bool)preg_match('/\.zip$/i', $originalName);

            try {
                if ($isZip) {
                    if (!class_exists('ZipArchive')) {
                        throw new RuntimeException('ZipArchive ist nicht verfuegbar.');
                    }
                    $zip = new ZipArchive();
                    if ($zip->open($tmpUpload) !== true) {
                        throw new RuntimeException('ZIP-Datei konnte nicht geoeffnet werden.');
                    }

                    $extractRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aufgussplan_restore_' . uniqid();
                    safe_extract_zip($zip, $extractRoot);
                    $zip->close();

                    $sqlPath = $extractRoot . DIRECTORY_SEPARATOR . 'database.sql';
                    if (!is_file($sqlPath)) {
                        $sqlCandidates = glob($extractRoot . DIRECTORY_SEPARATOR . '*.sql');
                        if (!empty($sqlCandidates)) {
                            $sqlPath = $sqlCandidates[0];
                        }
                    }
                    if (!is_file($sqlPath)) {
                        throw new RuntimeException('Keine SQL-Datei im Backup gefunden.');
                    }

                    $sql = file_get_contents($sqlPath);
                    if ($sql === false || trim($sql) === '') {
                        throw new RuntimeException('Die SQL-Datei ist leer oder konnte nicht gelesen werden.');
                    }

                    $localStoragePath = $extractRoot . DIRECTORY_SEPARATOR . 'localstorage.json';
                    if (is_file($localStoragePath)) {
                        $localStorageRaw = file_get_contents($localStoragePath);
                        if ($localStorageRaw !== false && trim($localStorageRaw) !== '') {
                            $localStorageRestoreJson = $localStorageRaw;
                        }
                    }

                    $db->exec('SET FOREIGN_KEY_CHECKS=0');
                    execute_sql_dump($db, $sql);
                    $db->exec('SET FOREIGN_KEY_CHECKS=1');

                    $uploadsSource = $extractRoot . DIRECTORY_SEPARATOR . 'uploads';
                    if (is_dir($uploadsSource)) {
                        copy_directory($uploadsSource, UPLOAD_PATH);
                    }

                    $messages[] = 'Backup wurde erfolgreich wiederhergestellt (Datenbank und Uploads).';
                    if ($localStorageRestoreJson !== null) {
                        $_SESSION['localstorage_restore_json'] = $localStorageRestoreJson;
                        header('Location: ' . BASE_URL . 'admin/pages/backup.php?restored=1');
                        exit;
                    }
                } else {
                    $sql = file_get_contents($tmpUpload);
                    if ($sql === false || trim($sql) === '') {
                        throw new RuntimeException('Die Backup-Datei ist leer oder konnte nicht gelesen werden.');
                    }
                    $db->exec('SET FOREIGN_KEY_CHECKS=0');
                    execute_sql_dump($db, $sql);
                    $db->exec('SET FOREIGN_KEY_CHECKS=1');
                    $messages[] = 'Die Datenbank wurde erfolgreich wiederhergestellt.';
                }
            } catch (Throwable $e) {
                $db->exec('SET FOREIGN_KEY_CHECKS=1');
                $errors[] = 'Fehler beim Wiederherstellen: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Aufgussplan</title>
    <link rel="stylesheet" href="../../dist/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admin.css'); ?>">
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Backup & Restore</h2>

        

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-2">Backup erstellen</h3>
                <form method="post" id="backup-download-form">
                    <input type="hidden" name="action" value="backup">
                    <input type="hidden" name="localstorage_json" id="localstorage-json">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Backup herunterladen</button>
            <p class="text-xs text-gray-500 mt-3">Bitte führen Sie regelmäßig ein Backup durch.</p>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-2">Backup wiederherstellen</h3>
                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="restore">
                    <div>
                        <input type="file" name="backup_file" accept=".zip,.sql" class="block w-full text-sm text-gray-700">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="confirm_restore" value="1" class="peer sr-only">
                        <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                            <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                        Ich bestätige, dass die aktuelle Datenbank überschrieben wird.
                    </label>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Backup einspielen</button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 mt-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-sm text-gray-500">Uploads</div>
                <div class="text-lg font-semibold text-gray-900">
                    <?php echo (int)$dbOverview['uploads_count']; ?> Dateien - <?php echo htmlspecialchars(format_bytes($dbOverview['uploads_size']), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-sm text-gray-500">Letztes Backup</div>
                <div class="text-lg font-semibold text-gray-900">
                    <?php echo htmlspecialchars($lastBackupLabel, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>

        <!-- Datenbank-Uebersicht eingebunden -->
        <?php include __DIR__ . '/../partials/aufguesse_db_overview.php'; ?>
    </div>
</body>
</html>
<script src="../../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin.js'); ?>"></script>
<script src="../../assets/js/admin-db-overview.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin-db-overview.js'); ?>"></script>
<script>
    (function() {
        const form = document.getElementById('backup-download-form');
        const localStorageInput = document.getElementById('localstorage-json');
        if (form && localStorageInput) {
            form.addEventListener('submit', () => {
                const data = {};
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key) {
                        data[key] = localStorage.getItem(key);
                    }
                }
                const json = JSON.stringify(data);
                localStorageInput.value = btoa(unescape(encodeURIComponent(json)));
            });
        }

        const restorePayload = <?php echo $localStorageRestoreJson ? json_encode($localStorageRestoreJson) : 'null'; ?>;
        if (restorePayload) {
            try {
                const parsed = JSON.parse(restorePayload);
                if (parsed && typeof parsed === 'object') {
                    localStorage.clear();
                    Object.keys(parsed).forEach((key) => {
                        localStorage.setItem(key, String(parsed[key]));
                    });
                    location.reload();
                }
            } catch (error) {
                // Ignore invalid payload.
            }
        }
    })();
</script>
<script>
    (function() {
        const exportBtn = document.getElementById('export-localstorage');
        const importBtn = document.getElementById('import-localstorage');
        const importFile = document.getElementById('import-localstorage-file');

        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                const data = {};
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key) {
                        data[key] = localStorage.getItem(key);
                    }
                }
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `localstorage_backup_${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            });
        }

        if (importBtn) {
            importBtn.addEventListener('click', () => {
                const file = importFile && importFile.files ? importFile.files[0] : null;
                if (!file) {
                    alert('Bitte eine JSON-Datei auswaehlen.');
                    return;
                }
                if (!confirm('LocalStorage importieren und vorhandene Werte überschreiben?')) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    try {
                        const payload = JSON.parse(reader.result || '{}');
                        if (payload && typeof payload === 'object') {
                            localStorage.clear();
                            Object.keys(payload).forEach((key) => {
                                localStorage.setItem(key, String(payload[key]));
                            });
                            alert('LocalStorage importiert. Bitte Seite neu laden.');
                        }
                    } catch (error) {
                        alert('JSON konnte nicht gelesen werden.');
                    }
                };
                reader.readAsText(file);
            });
        }
    })();
</script>

