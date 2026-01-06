<?php

/**
 * ADMIN-DASHBOARD - Hauptseite des Admin-Bereichs
 *
 * Diese Seite ist das Herzstueck der Verwaltung. Hier koennen Administratoren:
 * - Aufgüsse planen
 * - Uebersicht ueber Bereiche sehen
 * - Zu anderen Verwaltungsseiten navigieren
 *
 * Hinweise fuer Einsteiger:
 * - Seite kombiniert PHP-Logik und HTML
 * - Session-Login ist optional (auskommentiert)
 * - Daten kommen aus der Datenbank
 *
 * URL: http://localhost/aufgussplan/admin/
 * Sicherheit: Nur fuer eingeloggte Administratoren gedacht
 */

// Session starten (Login-Status, Nachrichten, etc.)
session_start();

// Konfiguration laden (Datenbank, Pfade, Sicherheit)
require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/auth.php';

/**
 * SICHERHEIT: LOGIN-PRUEFUNG
 *
 * Diese Pruefung ist auskommentiert, damit du die Seite testen kannst.
 * In Produktion solltest du sie aktivieren:
 *
 * - Prueft, ob der Benutzer eingeloggt ist
 * - Leitet zu login.php um, falls nicht eingeloggt
 * - Schuetzt den Admin-Bereich vor unbefugtem Zugriff
 */
require_login();

/**
 * DATEN FUER DAS DASHBOARD LADEN
 *
 * Aktuell braucht die Seite nur die Pläene fuer die Uebersicht.
 */

// Datenbankverbindung herstellen
require_once __DIR__ . '/../../src/db/connection.php';
$db = Database::getInstance()->getConnection();

// Pläene fuer die Uebersicht laden (neueste zuerst)
$Pläene = $db->query("SELECT id, name, beschreibung, erstellt_am FROM plaene ORDER BY erstellt_am DESC")->fetchAll();

$canMitarbeiter = has_permission('mitarbeiter');
$canAufguesse = has_permission('aufguesse');
$canStatistik = has_permission('statistik');
$canUmfragen = has_permission('umfragen');
$canBildschirme = has_permission('bildschirme');
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

<body class="bg-gray-100">
    <!-- NAVIGATION -->
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- SEITENTITEL -->
        <h2 class="text-2xl font-bold mb-6">Dashboard</h2>

        <!-- DASHBOARD-INHALTE -->
        <!-- 3-spaltiges Grid-Layout fuer verschiedene Bereiche -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <?php if ($canMitarbeiter): ?>
                <div class="bg-white rounded-lg  p-6">
                    <h3 class="text-lg font-semibold mb-2">Mitarbeiter</h3>
                    <p class="text-gray-600">Verwalten Sie Ihre Mitarbeiter</p>
                    <a href="mitarbeiter.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Verwalten</a>
                </div>
            <?php endif; ?>

            <?php if ($canStatistik): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-2">Statistiken</h3>
                    <p class="text-gray-600">Uebersicht ueber Aktivitaeten</p>
                    <a href="statistik.php" class="mt-4 inline-block bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Anzeigen</a>
                </div>
            <?php endif; ?>

            <?php if ($canUmfragen): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-2">Umfrage</h3>
                    <p class="text-gray-600">Umfrage Erstellen</p>
                    <a href="umfragen.php" class="mt-4 inline-block bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Anzeigen</a>
                </div>
            <?php endif; ?>
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">Aufgüsse</h3>
                <p class="text-gray-600">Planen Sie Ihre Aufgüsse</p>
                <?php if ($canAufguesse): ?>
                    <a href="aufguesse.php" class="mt-4 inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Verwalten</a>
                <?php endif; ?>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <?php if (empty($Pläene)): ?>
                        <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                            Noch keine Pläene vorhanden. Erstelle zuerst einen Plan in der Planung.
                        </div>
                    <?php else: ?>
                        <div id="plan-list" class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($Pläene as $p): ?>
                                <div class="plan-item flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3" data-plan-id="<?php echo (int)$p['id']; ?>">
                                    <div>
                                        <div class="text-base font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo !empty($p['erstellt_am']) ? htmlspecialchars(date('d.m.Y', strtotime($p['erstellt_am']))) : 'Unbekanntes Datum'; ?>
                                        </div>
                                        <div class="text-sm text-gray-600 mt-1 break-words">
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

            <?php if ($canBildschirme): ?>
                <div class="bg-white rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-2">Bildschirme</h3>
                    <p class="text-gray-600">Verwalten Sie die TV-Bildschirme</p>
                    <a href="bildschirme.php" class="mt-4 inline-block bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">Verwalten</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
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
