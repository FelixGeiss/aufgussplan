<?php

/**
 * UMFRAGE - Admin-Seite fuer Umfragen
 *
 * Platzhalterseite fuer die Umfrage-Verwaltung.
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umfrage - Aufgussplan</title>
    <link rel="stylesheet" href="../dist/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="bg-gray-100">
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Umfrage</h2>
        <div class="bg-white rounded-lg p-6">
            <p class="text-gray-600">Hier entsteht die Umfrage-Verwaltung.</p>
        </div>
    </div>
</body>

</html>
