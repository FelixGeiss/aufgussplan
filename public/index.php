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
</head>

<body class="bg-gray-100">
    <!-- HAUPTCONTAINER -->
    <div class="w-full px-6 py-8">
        <!-- AUFGUSSPLAN-CONTAINER -->
        <!-- Hier wird der dynamische Inhalt über JavaScript geladen -->
        <div id="aufgussplan" class="bg-white rounded-lg shadow-md p-6">
            <!-- Platzhalter für JavaScript-Inhalt -->
            <!-- Wird von app.js mit Daten gefüllt -->
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <!-- Haupt-JavaScript für die öffentliche Anzeige -->
    <script src="assets/js/app.js"></script>

    <!-- NAECHSTER AUFGUSS POPUP -->
    <div id="next-aufguss-overlay" class="next-aufguss-overlay hidden">
        <div class="next-aufguss-card">
            <div class="next-aufguss-header">Naechster Aufguss</div>
            <div id="next-aufguss-title" class="next-aufguss-title">--</div>
            <div id="next-aufguss-time" class="next-aufguss-time">--:--</div>
            <div id="next-aufguss-sauna" class="next-aufguss-sauna">--</div>
            <div id="next-aufguss-countdown" class="next-aufguss-countdown">--</div>
        </div>
    </div>
</body>
</html>
