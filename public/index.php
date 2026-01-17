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
    <div class="screen-lock" aria-hidden="true">
        <div>
            <div class="screen-lock__title">Bildschirm zu klein</div>
            <div class="screen-lock__subtitle">Bitte vergrößern oder ein größeres Gerät nutzen.</div>
        </div>
    </div>
    <?php
    $screenId = isset($screenId) ? (int)$screenId : 0;
    $screenAttr = $screenId > 0 ? ' data-screen-id="' . $screenId . '"' : '';
    $publicBase = '';
    $adminBase = BASE_URL . 'admin/pages/';
    $adminAuthBase = BASE_URL . 'admin/login/';
    $showPublicLinksWhenLoggedOut = false;
    $navId = 'kiosk-admin-nav';
    $navClass = 'kiosk-admin-nav bg-blue-600 text-white p-4';
    include __DIR__ . '/partials/admin_nav.php';
    ?>

    <!-- HAUPTCONTAINER -->
    <div class="w-full p-0">
        <!-- AUFGUSSPLAN-CONTAINER -->
        <!-- Hier wird der dynamische Inhalt über JavaScript geladen -->
        <div id="aufgussplan" class="p-0 min-h-screen" data-hide-plan-header="true"<?php echo $screenAttr; ?>>
            <!-- Platzhalter für JavaScript-Inhalt -->
            <!-- Wird von app.js mit Daten gefüllt -->
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        window.APP_BASE_URL = '<?php echo rtrim(BASE_URL, '/'); ?>/';
        window.APP_UPLOADS_URL = '<?php echo rtrim(BASE_URL, '/'); ?>/uploads/';
    </script>
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

    <!-- NÄCHSTER AUFGUSS POPUP -->
    <div id="next-aufguss-overlay" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Nächster Aufguss</h3>
            </div>
            <div id="next-aufguss-body" class="p-5">
                <div class="text-sm text-gray-500">Lädt...</div>
            </div>
        </div>
    </div>
</body>
</html>
