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

// Konfiguration laden (Datenbank, Pfade, etc.)
require_once __DIR__ . '/../src/config/config.php';

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
    </style>
</head>

<body class="bg-gray-100">
    <!-- HAUPTCONTAINER -->
    <div class="w-full p-0">
        <!-- AUFGUSSPLAN-CONTAINER -->
        <!-- Hier wird der dynamische Inhalt über JavaScript geladen -->
        <div id="aufgussplan" class="bg-white rounded-lg shadow-md p-0" data-hide-plan-header="true">
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
