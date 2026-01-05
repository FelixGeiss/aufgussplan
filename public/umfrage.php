<?php
/**
 * OEFFENTLICHE UMFRAGEN
 *
 * Anzeige der ausgewaehlten Aufguesse mit Bewertungs-Kriterien und Sterneauswahl.
 */

session_start();
require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/models/aufguss.php';
require_once __DIR__ . '/../src/db/connection.php';

$planId = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : (int)($_GET['plan_id'] ?? 0);
$clearSurvey = isset($_POST['clear']) && (int)$_POST['clear'] === 1;
$rawBewertungen = $_POST['bewertungen'] ?? [];
$rawCriteria = $_POST['criteria'] ?? ($_POST['criteria_labels'] ?? []);
$isSubmit = isset($_POST['submit_ratings']) && $_POST['submit_ratings'] === '1';

$aufgussModel = new Aufguss();
if ($planId <= 0) {
    $plaene = $aufgussModel->getAllPlans();
    if (!empty($plaene)) {
        $planId = (int)$plaene[0]['id'];
    }
}

$db = Database::getInstance()->getConnection();
$planBackground = '';
if ($planId > 0) {
    $stmt = $db->prepare("SELECT hintergrund_bild FROM plaene WHERE id = ?");
    $stmt->execute([$planId]);
    $planBackground = (string)($stmt->fetchColumn() ?: '');
}
if (!$clearSurvey && empty($rawCriteria) && $planId > 0) {
    $stmt = $db->prepare("SELECT k1, k2, k3, k4, k5, k6 FROM umfrage_kriterien WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $row = $stmt->fetch();
    if ($row) {
        $rawCriteria = $row;
    }
}

$criteriaByAufguss = [];
$globalCriteria = [];
if (!$clearSurvey && is_array($rawCriteria)) {
    foreach ($rawCriteria as $key => $label) {
        $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
        $cleanLabel = trim((string)$label);
        if ($cleanLabel === '') {
            continue;
        }
        $globalCriteria[$cleanKey] = $cleanLabel;
    }
}

if (!$clearSurvey && is_array($rawBewertungen)) {
    foreach ($rawBewertungen as $aufgussId => $criteria) {
        if (!is_array($criteria)) {
            continue;
        }
        $id = (int)$aufgussId;
        if ($id <= 0) {
            continue;
        }
        foreach ($criteria as $key => $label) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
            $cleanLabel = trim((string)$label);
            if ($cleanLabel === '') {
                continue;
            }
            $criteriaByAufguss[$id][$cleanKey] = $cleanLabel;
        }
    }
}

$aufgussMap = [];
if ($planId > 0) {
    foreach ($aufgussModel->getAufgüsseByPlan($planId) as $aufguss) {
        $aufgussMap[(int)$aufguss['id']] = $aufguss;
    }
} elseif (!empty($criteriaByAufguss) || !empty($globalCriteria)) {
    foreach ($aufgussModel->getAll() as $aufguss) {
        $aufgussId = (int)$aufguss['id'];
        if (isset($criteriaByAufguss[$aufgussId])) {
            $aufgussMap[$aufgussId] = $aufguss;
        }
    }
}

$surveyItems = [];
if ($clearSurvey) {
    $surveyItems = [];
} elseif (!empty($globalCriteria) && !empty($aufgussMap)) {
    foreach ($aufgussMap as $aufgussId => $aufguss) {
        $surveyItems[] = [
            'id' => (int)$aufgussId,
            'name' => $aufguss['name'] ?? 'Aufguss',
            'criteria' => $globalCriteria
        ];
    }
} else {
    foreach ($criteriaByAufguss as $aufgussId => $criteria) {
        $name = $aufgussMap[$aufgussId]['name'] ?? 'Aufguss';
        $surveyItems[] = [
            'id' => $aufgussId,
            'name' => $name,
            'criteria' => $criteria
        ];
    }
}

$saveMessage = '';
$saveError = '';
if ($isSubmit && !$clearSurvey) {
    $ratings = $_POST['ratings'] ?? [];
    $criteriaLabels = $globalCriteria;
    $today = date('Y-m-d');
    $inserted = 0;

    if (!empty($ratings) && !empty($criteriaLabels) && !empty($aufgussMap)) {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare(
                "INSERT INTO umfrage_bewertungen (aufguss_id, plan_id, aufguss_name_id, kriterium, rating, datum)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($ratings as $aufgussId => $criteriaRatings) {
                if (!is_array($criteriaRatings)) {
                    continue;
                }
                $id = (int)$aufgussId;
                if ($id <= 0 || !isset($aufgussMap[$id])) {
                    continue;
                }
                $aufguss = $aufgussMap[$id];
                $aufgussNameId = isset($aufguss['aufguss_name_id']) ? (int)$aufguss['aufguss_name_id'] : null;
                $planValue = isset($aufguss['plan_id']) ? (int)$aufguss['plan_id'] : $planId;

                foreach ($criteriaRatings as $key => $ratingValue) {
                    $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
                    if (!isset($criteriaLabels[$cleanKey])) {
                        continue;
                    }
                    $rating = (int)$ratingValue;
                    if ($rating < 1 || $rating > 5) {
                        continue;
                    }
                    $stmt->execute([
                        $id,
                        $planValue > 0 ? $planValue : null,
                        $aufgussNameId > 0 ? $aufgussNameId : null,
                        $criteriaLabels[$cleanKey],
                        $rating,
                        $today
                    ]);
                    $inserted += 1;
                }
            }

            $db->commit();
            $saveMessage = $inserted > 0
                ? 'Danke! Deine Bewertungen wurden gespeichert.'
                : 'Es wurden keine Bewertungen gespeichert.';
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $saveError = 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
        }
    } else {
        $saveError = 'Keine Bewertungen gefunden.';
    }
}
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
            overflow: auto;
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
        .survey-screen {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .survey-content {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .survey-card {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }
        .survey-grid {
            display: grid;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .survey-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .criterion-row {
            display: grid;
            gap: 0.5rem;
            align-items: center;
            padding: 0.75rem 0;
            border-top: 1px dashed #e5e7eb;
        }
        .criterion-row:first-child {
            border-top: none;
        }
        @media (min-width: 768px) {
            .criterion-row {
                grid-template-columns: 1fr auto;
                gap: 1.5rem;
            }
        }
        .rating-stars {
            display: inline-flex;
            gap: 0.4rem;
        }
        .star-btn {
            width: 42px;
            height: 42px;
            border-radius: 9999px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
        }
        .star-btn svg {
            width: 24px;
            height: 24px;
            fill: #94a3b8;
            transition: fill 0.15s ease;
        }
        .star-btn.is-active {
            background: #fef3c7;
            border-color: #f59e0b;
            transform: translateY(-1px);
        }
        .star-btn.is-active svg {
            fill: #f59e0b;
        }
        .star-btn:active {
            transform: translateY(1px);
        }
        .survey-footer {
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }
    </style>
</head>

<?php
$backgroundStyle = '';
if ($planBackground !== '') {
    $normalizedPath = str_replace('\\', '/', $planBackground);
    $backgroundUrl = stripos($normalizedPath, 'uploads/') === 0
        ? $normalizedPath
        : 'uploads/' . ltrim($normalizedPath, '/');
    $backgroundStyle = "background-image: url('" . htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8') . "');"
        . "background-size: cover;"
        . "background-position: center;"
        . "background-repeat: no-repeat;";
}
?>
<body class="bg-gray-100 kiosk-view" style="<?php echo $backgroundStyle; ?>">
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

    <div class="survey-screen">
        <main class="survey-content px-4 md:px-10 py-6">
            <div class="max-w-6xl mx-auto space-y-6">
                <?php if ($saveMessage): ?>
                    <div class="survey-card p-4 border border-green-200 bg-green-50 text-green-800">
                        <?php echo htmlspecialchars($saveMessage); ?>
                    </div>
                <?php elseif ($saveError): ?>
                    <div class="survey-card p-4 border border-red-200 bg-red-50 text-red-700">
                        <?php echo htmlspecialchars($saveError); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($surveyItems)): ?>
                    <div class="survey-card p-6">
                        <p class="text-gray-600">Es wurden keine Kriterien uebergeben. Bitte die Umfrage im Admin-Bereich vorbereiten.</p>
                    </div>
                <?php else: ?>
                    <form action="#" method="post" class="space-y-6">
                        <input type="hidden" name="plan_id" value="<?php echo (int)$planId; ?>">
                        <input type="hidden" name="submit_ratings" value="1">
                        <?php foreach ($globalCriteria as $key => $label): ?>
                            <input type="hidden" name="criteria_labels[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($label); ?>">
                        <?php endforeach; ?>

                        <div class="survey-grid">
                            <?php foreach ($surveyItems as $item): ?>
                                <section class="survey-card p-6">
                                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500 mb-4">Tippe die Sterne, um die Kriterien zu bewerten.</p>

                                    <?php foreach ($item['criteria'] as $key => $label): ?>
                                        <div class="criterion-row">
                                            <div class="text-sm md:text-base font-medium text-gray-800">
                                                <?php echo htmlspecialchars($label); ?>
                                            </div>
                                            <div class="rating-stars" data-rating>
                                                <input type="hidden" name="ratings[<?php echo (int)$item['id']; ?>][<?php echo htmlspecialchars($key); ?>]" value="0">
                                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                                    <button type="button" class="star-btn" data-value="<?php echo $star; ?>" aria-label="<?php echo $star; ?> von 5">
                                                        <svg viewBox="0 0 20 20" aria-hidden="true">
                                                            <path d="M10 1.5l2.47 5.01 5.53.8-4 3.9.94 5.52L10 14.4 5.06 16.73 6 11.21 2 7.31l5.53-.8L10 1.5z"></path>
                                                        </svg>
                                                    </button>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </section>
                            <?php endforeach; ?>
                        </div>

                        <div class="survey-footer rounded-2xl px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <span class="text-sm text-gray-600">Bewertungen werden nach dem Absenden gespeichert.</span>
                            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-semibold">
                                Bewertung abschliessen
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
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
    <script>
        (function() {
            const ratingGroups = document.querySelectorAll('[data-rating]');
            ratingGroups.forEach((group) => {
                const input = group.querySelector('input[type="hidden"]');
                const buttons = Array.from(group.querySelectorAll('.star-btn'));
                const setRating = (value) => {
                    if (input) input.value = String(value);
                    buttons.forEach((btn) => {
                        const btnValue = Number(btn.getAttribute('data-value'));
                        btn.classList.toggle('is-active', btnValue <= value);
                        btn.setAttribute('aria-pressed', btnValue <= value ? 'true' : 'false');
                    });
                };
                buttons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const value = Number(btn.getAttribute('data-value'));
                        setRating(value);
                    });
                });
            });
        })();
    </script>
</body>
</html>
