<?php
/**
 * ÖFFENTLICHE ANZEIGE - Aufgussplan für TV-Bildschirme
 *
 * Diese Seite ist die Hauptseite der Anwendung und wird von Gästen/Besuchern
 * der Sauna aufgerufen. Sie zeigt den aktuellen Aufgussplan an.
 *
 * Als Anfänger solltest du wissen:
 * - Diese Datei liegt im "public"-Verzeichnis (direkt über Browser erreichbar)
 * - Sie kombiniert PHP (Server-seitig) mit HTML/CSS/JavaScript (Client-seitig)
 * - Die eigentliche Logik ist in JavaScript (app.js) - hier nur das Grundgerüst
 * - TV-freundliches Design: Groß, klar, automatisch aktualisierend
 *
 * URL: http://localhost/aufgussplan/
 */

// Session starten und Konfiguration laden (Datenbank, Pfade, etc.)
session_start();
require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth.php';

// Hier könnte zukünftig PHP-Logik stehen, z.B.:
// - Direkte Datenbankabfragen für Server-Side Rendering
// - Session-Management
// - Sicherheitstoken
// Aber aktuell wird alles über JavaScript/AJAX geladen
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <!-- Viewport für mobile Geräte und TV-Bildschirme -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aufgussplan</title>

    <!-- CSS STYLESHEETS -->
    <!-- Tailwind CSS (generiert aus input.css) -->
    <link rel="stylesheet" href="dist/style.css">
    <!-- Zusätzliche Styles für die öffentliche Anzeige -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.hide-cursor,
        body.hide-cursor * {
            cursor: none !important;
        }
        /* TODO: Cursor-Ausblendung funktioniert im Kioskmodus noch nicht stabil. */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
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
    <?php
    $loggedIn = is_admin_logged_in();
    $isAdmin = is_admin_user();
    $canAufguesse = has_permission('aufguesse');
    $canStatistik = has_permission('statistik');
    $canUmfragen = has_permission('umfragen');
    ?>
    <nav id="kiosk-admin-nav" class="kiosk-admin-nav bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Aufgussplan Admin</h1>
            <div>
                <?php if (!$loggedIn): ?>
                    <a href="admin/login.php" class="hover:underline">Login</a>
                <?php else: ?>
                    <a href="index.php" class="mr-4 hover:underline">Anzeige</a>
                    <a href="umfrage.php" class="mr-4 hover:underline">Umfrage anzeigen</a>
                    <a href="admin/index.php" class="mr-4 hover:underline">Dashboard</a>
                    <?php if ($isAdmin): ?>
                        <a href="admin/mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                    <?php endif; ?>
                    <?php if ($canAufguesse): ?>
                        <a href="admin/aufguesse.php" class="mr-4 hover:underline">Aufguesse</a>
                    <?php endif; ?>
                    <?php if ($canStatistik): ?>
                        <a href="admin/statistik.php" class="mr-4 hover:underline">Statistiken</a>
                    <?php endif; ?>
                    <?php if ($canUmfragen): ?>
                        <a href="admin/umfragen.php" class="mr-4 hover:underline">Umfrage erstellen</a>
                    <?php endif; ?>
                    <a href="admin/logout.php" class="hover:underline">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HAUPTCONTAINER -->
    <div class="w-full p-0">
        <!-- AUFGUSSPLAN-CONTAINER -->
        <!-- Hier wird der dynamische Inhalt über JavaScript geladen -->
        <div id="aufgussplan" class="p-0 min-h-screen" data-hide-plan-header="true">
            <!-- Platzhalter für JavaScript-Inhalt -->
            <!-- Wird von app.js mit Daten gefüllt -->
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <!-- Haupt-JavaScript für die öffentliche Anzeige -->
    <script src="assets/js/app.js"></script>
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

    <!-- NAECHSTER AUFGUSS POPUP -->
    <div id="next-aufguss-overlay" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Naechster Aufguss</h3>
            </div>
            <div id="next-aufguss-body" class="p-5">
                <div class="text-sm text-gray-500">Laedt...</div>
            </div>
        </div>
    </div>
</body>
</html>
