<?php

/**
 * UMFRAGE - Admin-Seite fuer Umfragen
 *
 * Anzeige der Aufgüsse aus einem Plan mit Feldern fuer Bewertungs-Kriterien.
 */

session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/models/aufguss.php';
require_once __DIR__ . '/../../../src/db/connection.php';

require_login();
require_permission('umfragen');

$aufgussModel = new Aufguss();
$Pläene = $aufgussModel->getAllPlans();

$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedPlanId = (int)($_POST['plan_id'] ?? 0);
    if ($postedPlanId > 0) {
        $planId = $postedPlanId;
    }
}
if ($planId <= 0 && !empty($Pläene)) {
    $planId = (int)$Pläene[0]['id'];
}

$selectedPlan = null;
foreach ($Pläene as $plan) {
    if ((int)$plan['id'] === $planId) {
        $selectedPlan = $plan;
        break;
    }
}

$fallbackPlan = $selectedPlan;
if ($planId > 0 && !$selectedPlan && !empty($Pläene)) {
    $fallbackPlan = $Pläene[0];
    $planId = (int)$fallbackPlan['id'];
}
if (!$selectedPlan) {
    $selectedPlan = $fallbackPlan;
}

$aufguesse = $planId > 0 ? $aufgussModel->getAufgüsseByPlan($planId) : [];
$criteriaDefaults = [];
for ($i = 1; $i <= 6; $i++) {
    $criteriaDefaults["k{$i}"] = '';
}
$saveMessage = '';
$saveError = '';

$db = Database::getInstance()->getConnection();
$storedCriteria = [];
if ($planId > 0) {
    $stmt = $db->prepare("SELECT k1, k2, k3, k4, k5, k6 FROM umfrage_kriterien WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $storedCriteria = $stmt->fetch() ?: [];
}
foreach ($criteriaDefaults as $key => $value) {
    if (isset($storedCriteria[$key])) {
        $criteriaDefaults[$key] = (string)$storedCriteria[$key];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $planId > 0) {
    $clear = isset($_POST['clear']) && (int)$_POST['clear'] === 1;
    $postedCriteria = [];
    for ($i = 1; $i <= 6; $i++) {
        $key = "k{$i}";
        $postedCriteria[$key] = trim((string)($_POST['criteria'][$key] ?? ''));
    }

    try {
        if ($clear) {
            $stmt = $db->prepare("DELETE FROM umfrage_kriterien WHERE plan_id = ?");
            $stmt->execute([$planId]);
            foreach ($criteriaDefaults as $key => $value) {
                $criteriaDefaults[$key] = '';
            }
            $saveMessage = 'Umfrage wurde geloescht.';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO umfrage_kriterien (plan_id, k1, k2, k3, k4, k5, k6)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    k1 = VALUES(k1),
                    k2 = VALUES(k2),
                    k3 = VALUES(k3),
                    k4 = VALUES(k4),
                    k5 = VALUES(k5),
                    k6 = VALUES(k6),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([
                $planId,
                $postedCriteria['k1'],
                $postedCriteria['k2'],
                $postedCriteria['k3'],
                $postedCriteria['k4'],
                $postedCriteria['k5'],
                $postedCriteria['k6']
            ]);
            $criteriaDefaults = $postedCriteria;
            $saveMessage = 'Umfrage gespeichert.';
        }
    } catch (Exception $e) {
        $saveError = 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
    }

    $redirectUrl = BASE_URL . 'admin/pages/umfragen.php?plan_id=' . $planId;
    if ($saveMessage !== '') {
        $_SESSION['toast_message'] = $saveMessage;
        $_SESSION['toast_type'] = 'success';
        header('Location: ' . $redirectUrl);
        exit;
    }
    if ($saveError !== '') {
        $_SESSION['toast_message'] = $saveError;
        $_SESSION['toast_type'] = 'error';
        header('Location: ' . $redirectUrl);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umfrage - Aufgussplan</title>
    <link rel="stylesheet" href="../../dist/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admin.css'); ?>">
</head>

<body class="bg-gray-100" data-selected-plan="<?php echo $planId > 0 ? (int)$planId : ''; ?>">
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Umfrage erstellen</h2>

        <div class="bg-white rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Plan auswählen</h3>
            <?php if (empty($Pläene)): ?>
                <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                    Noch keine Pläene vorhanden. Erstelle zuerst einen Plan in der Planung.
                </div>
            <?php else: ?>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($Pläene as $plan): ?>
                        <button type="button" class="plan-select-btn" data-plan-id="<?php echo (int)$plan['id']; ?>" data-plan-name="<?php echo htmlspecialchars($plan['name'] ?? 'Plan', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($plan['name'] ?? 'Plan'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg p-6">
            <div class="flex flex-col gap-1 mb-4">
                <h3 class="text-lg font-semibold">Aufgüsse im Plan</h3>
                <?php if ($selectedPlan): ?>
                    <p class="text-sm text-gray-500">Ausgewählt: <?php echo htmlspecialchars($selectedPlan['name'] ?? ''); ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Bitte zuerst einen Plan auswählen.</p>
                <?php endif; ?>
            </div>

            <?php if ($planId <= 0): ?>
                <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                    Es ist noch kein Plan ausgewählt.
                </div>
            <?php elseif (empty($aufguesse)): ?>
                <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                    Dieser Plan enthaelt noch keine Aufgüsse.
                </div>
            <?php else: ?>
                <form action="" method="post" class="space-y-6" id="survey-form">
                    <input type="hidden" name="plan_id" value="<?php echo (int)$planId; ?>">
                    <input type="hidden" name="clear" value="0" id="survey-clear-flag">

                    

                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Kriterien für alle Aufgüsse</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            <?php foreach ($criteriaDefaults as $key => $value): ?>
                                <input
                                    type="text"
                                    name="criteria[<?php echo $key; ?>]"
                                    placeholder="Kriterium <?php echo htmlspecialchars(substr($key, 1)); ?>"
                                    value="<?php echo htmlspecialchars($value); ?>"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                >
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Aufgüsse im Plan</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 text-sm text-gray-700">
                            <?php foreach ($aufguesse as $aufguss): ?>
                                <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                                    <?php echo htmlspecialchars($aufguss['name'] ?? 'Aufguss'); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Umfrage speichern
                        </button>
                        <button type="button" id="survey-delete" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            Umfrage Löschen
                        </button>
                        <span class="text-sm text-gray-500 self-center">Die Umfrage wird beim ausgewählten Plan gespeichert.</span>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        (function() {
            const planButtons = document.querySelectorAll('[data-plan-id]');
            if (!planButtons.length) return;

            const storageKey = 'aufgussplanSelectedPlan';
            const selectedPlan = document.body.getAttribute('data-selected-plan');
            const stored = localStorage.getItem(storageKey);

            if (!selectedPlan && stored) {
                const url = new URL(window.location.href);
                url.searchParams.set('plan_id', stored);
                window.location.href = url.toString();
                return;
            }

            const setActive = (planId) => {
                planButtons.forEach(button => {
                    const isActive = button.getAttribute('data-plan-id') === String(planId);
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            if (selectedPlan) {
                setActive(selectedPlan);
            } else if (stored) {
                setActive(stored);
            }

            planButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const planId = button.getAttribute('data-plan-id');
                    if (!planId) return;
                    localStorage.setItem(storageKey, String(planId));
                    const url = new URL(window.location.href);
                    url.searchParams.set('plan_id', planId);
                    window.location.href = url.toString();
                    if (window.showToast) {
                        const planName = button.getAttribute('data-plan-name') || `Plan ${planId}`;
                        window.showToast(`Ausgewählt: ${planName}`, 'success');
                    }
                });
            });
        })();
    </script>
    <script>
        (function() {
            const form = document.getElementById('survey-form');
            const deleteBtn = document.getElementById('survey-delete');
            const clearFlag = document.getElementById('survey-clear-flag');
            if (!form || !deleteBtn || !clearFlag) return;

            deleteBtn.addEventListener('click', () => {
                const confirmed = window.confirm('Soll die Umfrage wirklich geloescht werden?');
                if (!confirmed) return;
                form.querySelectorAll('input[name^="criteria["]').forEach(input => {
                    input.value = '';
                });
                clearFlag.value = '1';
                form.submit();
            });
        })();
    </script>
    <script src="../../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin.js'); ?>"></script>
</body>

</html>


