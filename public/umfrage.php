<?php
/**
 * OEFFENTLICHE UMFRAGEN - Platzhalterseite
 *
 * Diese Seite ist die oeffentliche Anzeige fuer Umfragen.
 */

require_once __DIR__ . '/../src/config/config.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umfrage</title>
    <link rel="stylesheet" href="dist/style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.hide-cursor,
        body.hide-cursor * {
            cursor: none !important;
        }
        html, body {
            overflow: hidden;
        }
        .kiosk-admin-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            transform: translateY(-100%);
            opacity: 0;
            transition: transform 200ms ease, opacity 200ms ease;
            pointer-events: none;
        }
        .kiosk-admin-nav.is-visible {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>

<body class="bg-gray-100 kiosk-view">
    <nav id="kiosk-admin-nav" class="kiosk-admin-nav bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Aufgussplan Admin</h1>
            <div>
                <a href="index.php" class="mr-4 hover:underline">Anzeige</a>
                <a href="umfrage.php" class="mr-4 hover:underline">Umfrage anzeigen</a>
                <a href="admin/index.php" class="mr-4 hover:underline">Dashboard</a>
                <a href="admin/mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                <a href="admin/aufguesse.php" class="mr-4 hover:underline">Aufguesse</a>
                <a href="admin/statistik.php" class="mr-4 hover:underline">Statistiken</a>
                <a href="admin/umfragen.php" class="mr-4 hover:underline">Umfrage erstellen</a>
                <a href="admin/logout.php" class="hover:underline">Logout</a>
            </div>
        </div>
    </nav>

    <div class="w-full p-0">
        <div class="bg-white rounded-lg shadow-md p-6 mx-4 mt-6">
            <h2 class="text-2xl font-bold mb-2">Umfrage</h2>
            <p class="text-gray-600">Hier entsteht die Umfrage-Anzeige.</p>
        </div>
    </div>

    <script>
        (function() {
            const hideDelay = 2000;
            let cursorTimer = null;

            function resetCursor() {
                document.body.classList.remove('hide-cursor');
                if (cursorTimer) {
                    clearTimeout(cursorTimer);
                }
                cursorTimer = setTimeout(() => {
                    document.body.classList.add('hide-cursor');
                }, hideDelay);
            }

            document.addEventListener('mousemove', resetCursor);
            document.addEventListener('keydown', resetCursor);
            resetCursor();
        })();
    </script>
    <script>
        (function() {
            const nav = document.getElementById('kiosk-admin-nav');
            if (!nav) return;
            let hideTimer = null;
            const show = () => {
                nav.classList.add('is-visible');
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(() => nav.classList.remove('is-visible'), 2000);
            };
            document.addEventListener('mousemove', (event) => {
                if (event.clientY <= 30) {
                    show();
                }
            });
        })();
    </script>
</body>
</html>
