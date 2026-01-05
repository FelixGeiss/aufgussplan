<?php
/**
 * BILDSCHIRME-VERWALTUNG
 *
 * Platzhalterseite fuer die Verwaltung der TV-Bildschirme.
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildschirme verwalten - Aufgussplan</title>
    <link rel="stylesheet" href="../dist/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-4">Bildschirme verwalten</h2>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-6">
                <p class="text-gray-600">
                    Waehle pro Bildschirm, ob ein Plan oder ein Bild angezeigt werden soll.
                    Optional kannst du einen Hintergrund definieren.
                </p>
                <a href="../index.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Zur Anzeige</a>
            </div>
            <div id="screen-list" class="grid grid-cols-1 lg:grid-cols-2 gap-6"></div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/bildschirme.js"></script>
</body>
</html>
