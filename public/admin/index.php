<?php

/**
 * ADMIN-DASHBOARD - Hauptseite des Admin-Bereichs
 *
 * Diese Seite ist das Herzstück der Verwaltung. Hier können Administratoren:
 * - Neue Aufgüsse erstellen und planen
 * - Übersicht über verschiedene Bereiche sehen
 * - Zu anderen Verwaltungsseiten navigieren
 *
 * Als Anfänger solltest du wissen:
 * - Diese Seite kombiniert PHP-Logik mit HTML-Formularen
 * - Sie verwendet Sessions für Sicherheit (auskommentiert)
 * - Formulare werden mit POST verarbeitet
 * - Daten kommen aus verschiedenen Datenbanktabellen
 *
 * URL: http://localhost/aufgussplan/admin/
 * Sicherheit: Sollte nur für eingeloggte Administratoren zugänglich sein
 */

// PHP-SESSION starten (für Login-Status, Nachrichten, etc.)
session_start();

// Konfiguration laden (Datenbank, Pfade, Sicherheit)
require_once __DIR__ . '/../../src/config/config.php';

/**
 * SICHERHEIT: LOGIN-PRÜFUNG
 *
 * Diese Prüfung ist auskommentiert, damit du die Seite zum Testen verwenden kannst.
 * In Produktion solltest du sie aktivieren:
 *
 * - Prüft, ob der Benutzer eingeloggt ist
 * - Leitet zu login.php um, falls nicht eingeloggt
 * - Schützt den Admin-Bereich vor unbefugtem Zugriff
 */
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

/**
 * FORMULAR-VERARBEITUNG
 *
 * Wenn das Formular abgesendet wird (POST-Request), werden die Daten hier verarbeitet.
 * Dies ist die "Controller"-Logik in MVC-Architektur.
 */

// Variablen für Erfolgs-/Fehlermeldungen initialisieren
$message = '';  // Erfolgsmeldung
$errors = [];   // Array mit Fehlermeldungen

// Prüfen, ob ein POST-Request vorliegt (Formular abgesendet)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AufgussService einbinden (für Geschäftslogik)
    require_once __DIR__ . '/../../src/services/aufgussService.php';

    // Service-Instanz erstellen
    $service = new AufgussService();

    // Formular verarbeiten lassen ($_POST = Formulardaten, $_FILES = hochgeladene Bilder)
    $result = $service->verarbeiteFormular($_POST, $_FILES);

    // Ergebnis prüfen
    if ($result['success']) {
        // ERFOLG: Meldung anzeigen und Formular zurücksetzen
        $message = $result['message'];
        $_POST = []; // Formularfelder leeren (optional)
    } else {
        // FEHLER: Fehlermeldungen sammeln
        $errors = $result['errors'];
    }
}

/**
 * DATEN FÜR SELECT-FELDER LADEN
 *
 * Das Formular hat Dropdown-Menüs für vorhandene Einträge.
 * Diese Daten müssen aus der Datenbank geladen werden.
 */

// Datenbankverbindung herstellen
require_once __DIR__ . '/../../src/db/connection.php';
$db = Database::getInstance()->getConnection();

// MITARBEITER für Dropdown laden (sortiert nach Name)
$mitarbeiter = $db->query("SELECT id, name FROM mitarbeiter ORDER BY name")->fetchAll();

// SAUNEN für Dropdown laden (sortiert nach Name)
$saunen = $db->query("SELECT id, name FROM saunen ORDER BY name")->fetchAll();

// DUFTMITTEL für Dropdown laden (sortiert nach Name)
$duftmittel = $db->query("SELECT id, name FROM duftmittel ORDER BY name")->fetchAll();

// AUFGÜSSE für Dropdown laden (sortiert nach Name)
$aufguesse = $db->query("SELECT id, name FROM aufguesse ORDER BY name")->fetchAll();

// PLÄNE für Übersicht laden (neueste zuerst)
$plaene = $db->query("SELECT id, name, beschreibung, erstellt_am FROM plaene ORDER BY erstellt_am DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aufgussplan</title>
    <!-- Lokale Tailwind CSS -->
    <link rel="stylesheet" href="../dist/style.css">
    <!-- Admin-spezifische Styles -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="bg-gray-100">    <!-- NAVIGATION -->
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- SEITENTITEL -->
        <h2 class="text-2xl font-bold mb-6">Dashboard</h2>

        <!-- ERFOLGS-/FEHLERMELDUNGEN -->
        <!-- Diese werden nur angezeigt, wenn das Formular verarbeitet wurde -->

        <?php if ($message): ?>
            <!-- ERFOLGSMELDUNG (grün) -->
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <!-- FEHLERMELDUNGEN (rot) -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- DASHBOARD-INHALTE -->
        <!-- 3-spaltiges Grid-Layout für verschiedene Bereiche -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <div class="bg-white rounded-lg  p-6">
                <h3 class="text-lg font-semibold mb-2">Mitarbeiter</h3>
                <p class="text-gray-600">Verwalten Sie Ihre Mitarbeiter</p>
                <a href="mitarbeiter.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Verwalten</a>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-2">Statistiken</h3>
                <p class="text-gray-600">Übersicht über Aktivitäten</p>
                <button class="mt-4 bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Anzeigen</button>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-2">Umfrage</h3>
                <p class="text-gray-600">Umfrage Erstellen</p>
                <button class="mt-4 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Anzeigen</button>
            </div>

        </div>


        <div class="bg-white rounded-lg  p-6">
            <h3 class="text-lg font-semibold mb-2">Aufgüsse</h3>
            <p class="text-gray-600">Planen Sie Ihre Aufgüsse</p>
            <a href="aufguesse.php" class="mt-4 inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Verwalten</a>

            <div class="mt-6 border-t border-gray-200 pt-6">
                <?php if (empty($plaene)): ?>
                    <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                        Noch keine Plaene vorhanden. Erstelle zuerst einen Plan in der Planung.
                    </div>
                <?php else: ?>
                    <div id="plan-list" class="flex flex-wrap gap-4">
                        <?php foreach ($plaene as $p): ?>
                            <div class="plan-item flex w-full flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.666rem)]" data-plan-id="<?php echo (int)$p['id']; ?>">
                                <div>
                                    <div class="text-base font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo !empty($p['erstellt_am']) ? htmlspecialchars(date('d.m.Y', strtotime($p['erstellt_am']))) : 'Unbekanntes Datum'; ?>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($p['beschreibung'] ?? 'Keine Beschreibung'); ?>
                                    </div>
                                </div>
                                <button type="button" class="plan-select-btn mt-auto" data-plan-select="<?php echo (int)$p['id']; ?>" aria-pressed="false">
                                    Plan auswaehlen
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/admin-functions.js"></script>
    <script>
                (function() {
                    const planButtons = document.querySelectorAll('[data-plan-select]');
                    if (!planButtons.length) {
                        return;
                    }

                    const storageKey = 'aufgussplanSelectedPlan';
                    const setActive = (planId) => {
                        planButtons.forEach(button => {
                            const isActive = button.getAttribute('data-plan-select') === String(planId);
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        });
                    };

                    const stored = localStorage.getItem(storageKey);
                    if (stored) {
                        setActive(stored);
                    } else {
                        const firstBtn = planButtons[0];
                        if (firstBtn) {
                            setActive(firstBtn.getAttribute('data-plan-select'));
                        }
                    }

                    planButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const planId = button.getAttribute('data-plan-select');
                            if (!planId) return;
                            setActive(planId);
                            localStorage.setItem(storageKey, String(planId));
                        });
                    });
                })();
    </script>
</body>

</html>
