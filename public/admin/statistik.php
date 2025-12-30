<?php
/**
 * STATISTIKEN
 *
 * Platzhalterseite fuer Statistik-Ansichten.
 *
 * URL: http://localhost/aufgussplan/admin/statistik.php
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/db/connection.php';

// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

$db = Database::getInstance()->getConnection();

function buildBarItems(array $labels, array $counts) {
    $items = [];
    foreach ($labels as $index => $label) {
        $items[] = [
            'label' => $label,
            'value' => (int)($counts[$index] ?? 0),
        ];
    }
    return $items;
}

function renderBarList(array $items, $barClass) {
    $maxValue = 0;
    foreach ($items as $item) {
        if ($item['value'] > $maxValue) {
            $maxValue = $item['value'];
        }
    }
    if ($maxValue <= 0) {
        $maxValue = 1;
    }

    echo '<div class="space-y-3">';
    foreach ($items as $item) {
        $label = htmlspecialchars($item['label']);
        $value = (int)$item['value'];
        $width = ($value / $maxValue) * 100;
        $widthStyle = number_format($width, 2, '.', '');
        echo '<div>';
        echo '<div class="flex justify-between text-xs text-gray-500 mb-1">';
        echo '<span class="truncate max-w-[70%]">' . $label . '</span>';
        echo '<span>' . $value . '</span>';
        echo '</div>';
        echo '<div class="h-3 w-full rounded bg-gray-100">';
        echo '<div class="h-3 rounded ' . $barClass . '" style="width:' . $widthStyle . '%"></div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

function renderLineChart(array $items, $strokeClass, array $options = []) {
    $values = array_map(function ($item) {
        return (int)$item['value'];
    }, $items);

    $maxValue = max(1, max($values));
    $count = count($items);
    $pointWidth = $options['pointWidth'] ?? 48;
    $width = max(320, $count * $pointWidth);
    $height = 140;
    $paddingLeft = 28;
    $paddingRight = 10;
    $paddingY = 14;
    $plotWidth = $width - $paddingLeft - $paddingRight;
    $plotHeight = $height - ($paddingY * 2);
    $gridLines = 4;
    $showArea = $options['area'] ?? false;
    $fillClass = $options['fillClass'] ?? 'fill-blue-100/70';

    $points = [];
    foreach ($items as $index => $item) {
        $x = $paddingLeft;
        if ($count > 1) {
            $x += ($plotWidth * $index) / ($count - 1);
        }
        $value = (int)$item['value'];
        $y = $paddingY + ($plotHeight * (1 - ($value / $maxValue)));
        $points[] = [
            'x' => number_format($x, 2, '.', ''),
            'y' => number_format($y, 2, '.', ''),
            'label' => $item['label'],
            'value' => $value
        ];
    }

    $polyPoints = [];
    foreach ($points as $point) {
        $polyPoints[] = $point['x'] . ',' . $point['y'];
    }

    $areaPath = '';
    if ($showArea && !empty($points)) {
        $first = $points[0];
        $last = $points[count($points) - 1];
        $bottom = number_format($paddingY + $plotHeight, 2, '.', '');
        $areaPath = 'M ' . $first['x'] . ' ' . $first['y'];
        foreach ($points as $point) {
            $areaPath .= ' L ' . $point['x'] . ' ' . $point['y'];
        }
        $areaPath .= ' L ' . $last['x'] . ' ' . $bottom . ' L ' . $first['x'] . ' ' . $bottom . ' Z';
    }

    echo '<div class="w-full overflow-x-auto">';
    echo '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="h-36" style="min-width:' . $width . 'px">';
    for ($i = 0; $i <= $gridLines; $i++) {
        $y = $paddingY + ($plotHeight * $i / $gridLines);
        $yPos = number_format($y, 2, '.', '');
        echo '<line x1="' . $paddingLeft . '" y1="' . $yPos . '" x2="' . ($width - $paddingRight) . '" y2="' . $yPos . '" class="stroke-gray-200" stroke-width="1" />';
    }
    echo '<text x="2" y="' . ($paddingY + 6) . '" class="fill-gray-400 text-[10px]">' . $maxValue . '</text>';
    echo '<text x="2" y="' . ($paddingY + $plotHeight + 4) . '" class="fill-gray-400 text-[10px]">0</text>';

    if ($showArea && $areaPath !== '') {
        echo '<path d="' . $areaPath . '" class="' . $fillClass . '"></path>';
    }

    if (!empty($polyPoints)) {
        echo '<polyline fill="none" stroke-width="3" class="' . $strokeClass . '" points="' . implode(' ', $polyPoints) . '"></polyline>';
    }

    foreach ($points as $point) {
        $label = htmlspecialchars($point['label']);
        $value = (int)$point['value'];
        echo '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="3.5" class="fill-white stroke-2 ' . $strokeClass . '"></circle>';
        echo '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="10" class="chart-point fill-transparent stroke-transparent" data-label="' . $label . '" data-value="' . $value . '"></circle>';
    }

    echo '</svg>';
    echo '<div class="mt-3 flex text-xs text-gray-500" style="min-width:' . $width . 'px">';
    foreach ($items as $item) {
        echo '<span class="flex-1 text-center truncate">' . htmlspecialchars($item['label']) . '</span>';
    }
    echo '</div>';
    echo '</div>';
}

function renderAreaChart(array $items, $strokeClass, $fillClass) {
    renderLineChart($items, $strokeClass, [
        'area' => true,
        'fillClass' => $fillClass
    ]);
}

function mapCountsByKey(array $rows, $keyField, $valueField) {
    $map = [];
    foreach ($rows as $row) {
        $map[$row[$keyField]] = (int)$row[$valueField];
    }
    return $map;
}

// Aufguesse pro Tag (letzte 7 Tage)
$byDayRows = $db->prepare(
    "SELECT DATE(datum) AS label, COUNT(*) AS cnt
     FROM aufguesse
     WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(datum)
     ORDER BY label ASC"
);
$byDayRows->execute();
$byDayMap = mapCountsByKey($byDayRows->fetchAll(), 'label', 'cnt');
$dayLabels = [];
$dayCounts = [];
$dayStart = new DateTime('today');
$dayStart->modify('-6 days');
for ($i = 0; $i < 7; $i++) {
    $label = $dayStart->format('d.m');
    $key = $dayStart->format('Y-m-d');
    $dayLabels[] = $label;
    $dayCounts[] = $byDayMap[$key] ?? 0;
    $dayStart->modify('+1 day');
}
$byDayItems = buildBarItems($dayLabels, $dayCounts);

// Aufguesse pro Woche (letzte 8 Wochen)
$byWeekRows = $db->prepare(
    "SELECT YEARWEEK(datum, 3) AS yw, COUNT(*) AS cnt
     FROM aufguesse
     WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 WEEK)
     GROUP BY YEARWEEK(datum, 3)
     ORDER BY yw ASC"
);
$byWeekRows->execute();
$byWeekMap = mapCountsByKey($byWeekRows->fetchAll(), 'yw', 'cnt');
$weekLabels = [];
$weekCounts = [];
$weekStart = new DateTime('monday this week');
$weekStart->modify('-7 weeks');
for ($i = 0; $i < 8; $i++) {
    $key = $weekStart->format('oW');
    $weekLabels[] = 'KW ' . $weekStart->format('W');
    $weekCounts[] = $byWeekMap[$key] ?? 0;
    $weekStart->modify('+1 week');
}
$byWeekItems = buildBarItems($weekLabels, $weekCounts);

// Aufguesse pro Monat (letzte 12 Monate)
$byMonthRows = $db->prepare(
    "SELECT DATE_FORMAT(datum, '%Y-%m') AS ym, COUNT(*) AS cnt
     FROM aufguesse
     WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY DATE_FORMAT(datum, '%Y-%m')
     ORDER BY ym ASC"
);
$byMonthRows->execute();
$byMonthMap = mapCountsByKey($byMonthRows->fetchAll(), 'ym', 'cnt');
$monthLabels = [];
$monthCounts = [];
$monthStart = new DateTime('first day of this month');
$monthStart->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $key = $monthStart->format('Y-m');
    $monthLabels[] = $monthStart->format('m/Y');
    $monthCounts[] = $byMonthMap[$key] ?? 0;
    $monthStart->modify('+1 month');
}
$byMonthItems = buildBarItems($monthLabels, $monthCounts);

// Aufguesse pro Jahr
$byYearRows = $db->prepare(
    "SELECT YEAR(datum) AS y, COUNT(*) AS cnt
     FROM aufguesse
     GROUP BY YEAR(datum)
     ORDER BY y ASC"
);
$byYearRows->execute();
$yearLabels = [];
$yearCounts = [];
foreach ($byYearRows->fetchAll() as $row) {
    $yearLabels[] = (string)$row['y'];
    $yearCounts[] = (int)$row['cnt'];
}
$byYearItems = buildBarItems($yearLabels, $yearCounts);

// Wie oft welcher Aufguss
$aufgussNameRows = $db->query(
    "SELECT COALESCE(name, 'Ohne Name') AS label, COUNT(*) AS cnt
     FROM aufguesse
     GROUP BY COALESCE(name, 'Ohne Name')
     ORDER BY cnt DESC, label ASC"
)->fetchAll();
$aufgussNameItems = [];
foreach ($aufgussNameRows as $row) {
    $aufgussNameItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}

// Wie oft ein Duftmittel verwendet wurde
$duftmittelRows = $db->query(
    "SELECT COALESCE(d.name, 'Ohne Duftmittel') AS label, COUNT(*) AS cnt
     FROM aufguesse a
     LEFT JOIN duftmittel d ON a.duftmittel_id = d.id
     GROUP BY COALESCE(d.name, 'Ohne Duftmittel')
     ORDER BY cnt DESC, label ASC"
)->fetchAll();
$duftmittelItems = [];
foreach ($duftmittelRows as $row) {
    $duftmittelItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}

// Wie oft welche Sauna genutzt wurde
$saunaRows = $db->query(
    "SELECT COALESCE(s.name, 'Ohne Sauna') AS label, COUNT(*) AS cnt
     FROM aufguesse a
     LEFT JOIN saunen s ON a.sauna_id = s.id
     GROUP BY COALESCE(s.name, 'Ohne Sauna')
     ORDER BY cnt DESC, label ASC"
)->fetchAll();
$saunaItems = [];
foreach ($saunaRows as $row) {
    $saunaItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}

// Aufguesse nach Staerke
$staerkeRows = $db->query(
    "SELECT staerke, COUNT(*) AS cnt
     FROM aufguesse
     GROUP BY staerke"
)->fetchAll();
$staerkeMap = [];
$ohneStaerkeCount = 0;
foreach ($staerkeRows as $row) {
    if ($row['staerke'] === null) {
        $ohneStaerkeCount = (int)$row['cnt'];
        continue;
    }
    $staerkeMap[(int)$row['staerke']] = (int)$row['cnt'];
}
$staerkeItems = [];
for ($s = 0; $s <= 6; $s++) {
    $staerkeItems[] = ['label' => 'St ' . $s, 'value' => $staerkeMap[$s] ?? 0];
}
if ($ohneStaerkeCount > 0) {
    $staerkeItems[] = ['label' => 'Ohne Staerke', 'value' => $ohneStaerkeCount];
}

$datasets = [
    'days' => ['title' => 'Aufguesse pro Tag (7 Tage)', 'items' => $byDayItems],
    'weeks' => ['title' => 'Aufguesse pro Woche (8 Wochen)', 'items' => $byWeekItems],
    'months' => ['title' => 'Aufguesse pro Monat (12 Monate)', 'items' => $byMonthItems],
    'years' => ['title' => 'Aufguesse pro Jahr', 'items' => $byYearItems],
    'staerke' => ['title' => 'Aufguesse nach Staerke', 'items' => $staerkeItems],
    'aufguss' => ['title' => 'Wie oft welcher Aufguss', 'items' => $aufgussNameItems],
    'duftmittel' => ['title' => 'Wie oft Duftmittel verwendet', 'items' => $duftmittelItems],
    'sauna' => ['title' => 'Wie oft welche Sauna', 'items' => $saunaItems],
];

if (isset($_GET['download'])) {
    $key = preg_replace('/[^a-z0-9_-]/i', '', $_GET['download']);
    if (!isset($datasets[$key])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unbekannter Download-Schluessel.';
        exit;
    }

    $filename = 'statistik_' . $key . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Label', 'Wert']);
    foreach ($datasets[$key]['items'] as $item) {
        fputcsv($output, [$item['label'], $item['value']]);
    }
    fclose($output);
    exit;
}

function renderDataAccordion($id, $title, array $items) {
    $panelId = 'accordion-' . $id;
    echo '<div class="relative">';
    echo '<button type="button" class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm" data-accordion-toggle="' . htmlspecialchars($panelId) . '" aria-expanded="false">';
    echo '<span>' . htmlspecialchars($title) . '</span>';
    echo '<span class="text-gray-400 transition" data-accordion-icon="' . htmlspecialchars($panelId) . '">▼</span>';
    echo '</button>';
    echo '<div id="' . htmlspecialchars($panelId) . '" class="absolute left-0 right-0 z-50 mt-0 hidden w-full rounded-lg border border-gray-200 bg-white p-4 shadow-lg">';
    echo '<div class="flex justify-end">';
    echo '<a href="statistik.php?download=' . htmlspecialchars($id) . '" class="text-sm text-blue-600 hover:underline">CSV herunterladen</a>';
    echo '</div>';
    echo '<div class="mt-3 max-h-72 overflow-auto">';
    echo '<table class="min-w-full text-sm">';
    echo '<thead>';
    echo '<tr class="text-left text-gray-500">';
    echo '<th class="py-2 pr-4">Label</th>';
    echo '<th class="py-2">Wert</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="divide-y divide-gray-100">';
    foreach ($items as $item) {
        echo '<tr>';
        echo '<td class="py-2 pr-4 text-gray-700">' . htmlspecialchars($item['label']) . '</td>';
        echo '<td class="py-2 text-gray-700">' . (int)$item['value'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiken - Aufgussplan</title>
    <link rel="stylesheet" href="../dist/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Statistiken</h2>

        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Zeitreihen (ein-/ausblenden)</h3>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm font-semibold text-gray-700">Zeitreihen anzeigen:</span>
                <button type="button" class="plan-select-btn" data-toggle-target="chart-days" aria-pressed="true">
                    Tage
                </button>
                <button type="button" class="plan-select-btn" data-toggle-target="chart-weeks" aria-pressed="true">
                    Wochen
                </button>
                <button type="button" class="plan-select-btn" data-toggle-target="chart-months" aria-pressed="true">
                    Monate
                </button>
                <button type="button" class="plan-select-btn" data-toggle-target="chart-years" aria-pressed="true">
                    Jahre
                </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div id="chart-days" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufguesse pro Tag (7 Tage)</h3>
                <?php renderLineChart($byDayItems, 'stroke-blue-500'); ?>
                <div class="mt-4">
                    <?php renderDataAccordion('days', $datasets['days']['title'], $datasets['days']['items']); ?>
                </div>
            </div>
            <div id="chart-weeks" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufguesse pro Woche (8 Wochen)</h3>
                <?php renderLineChart($byWeekItems, 'stroke-indigo-500'); ?>
                <div class="mt-4">
                    <?php renderDataAccordion('weeks', $datasets['weeks']['title'], $datasets['weeks']['items']); ?>
                </div>
            </div>
            <div id="chart-months" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufguesse pro Monat (12 Monate)</h3>
                <?php renderLineChart($byMonthItems, 'stroke-teal-500'); ?>
                <div class="mt-4">
                    <?php renderDataAccordion('months', $datasets['months']['title'], $datasets['months']['items']); ?>
                </div>
            </div>
            <div id="chart-years" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufguesse pro Jahr</h3>
                <?php renderLineChart($byYearItems, 'stroke-emerald-500'); ?>
                <div class="mt-4">
                    <?php renderDataAccordion('years', $datasets['years']['title'], $datasets['years']['items']); ?>
                </div>
            </div>
        </div>

        <div class="my-8 border-t border-gray-200"></div>

        <h3 class="text-lg font-semibold text-gray-900 mb-4">Weitere Statistiken (immer sichtbar)</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufguesse nach Staerke</h3>
                <div class="max-h-96 overflow-y-auto pr-2">
                    <?php renderAreaChart($staerkeItems, 'stroke-slate-600', 'fill-slate-200/70'); ?>
                </div>
                <div class="mt-4">
                    <?php renderDataAccordion('staerke', $datasets['staerke']['title'], $datasets['staerke']['items']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft welcher Aufguss</h3>
                <div class="max-h-96 overflow-y-auto pr-2">
                    <?php renderAreaChart($aufgussNameItems, 'stroke-orange-500', 'fill-orange-200/70'); ?>
                </div>
                <div class="mt-4">
                    <?php renderDataAccordion('aufguss', $datasets['aufguss']['title'], $datasets['aufguss']['items']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft Duftmittel verwendet</h3>
                <div class="max-h-96 overflow-y-auto pr-2">
                    <?php renderAreaChart($duftmittelItems, 'stroke-amber-500', 'fill-amber-200/70'); ?>
                </div>
                <div class="mt-4">
                    <?php renderDataAccordion('duftmittel', $datasets['duftmittel']['title'], $datasets['duftmittel']['items']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft welche Sauna</h3>
                <div class="max-h-96 overflow-y-auto pr-2">
                    <?php renderAreaChart($saunaItems, 'stroke-rose-500', 'fill-rose-200/70'); ?>
                </div>
                <div class="mt-4">
                    <?php renderDataAccordion('sauna', $datasets['sauna']['title'], $datasets['sauna']['items']); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="chart-tooltip" class="fixed z-50 hidden rounded bg-gray-900 text-white text-xs px-2 py-1 shadow pointer-events-none"></div>

    <script>
        document.querySelectorAll('[data-toggle-target]').forEach((button) => {
            const targetId = button.getAttribute('data-toggle-target');
            const target = document.getElementById(targetId);
            if (!target) return;

            const apply = () => {
                const isActive = button.getAttribute('aria-pressed') === 'true';
                target.classList.toggle('hidden', !isActive);
                button.classList.toggle('is-active', isActive);
            };

            button.addEventListener('click', () => {
                const isActive = button.getAttribute('aria-pressed') === 'true';
                button.setAttribute('aria-pressed', isActive ? 'false' : 'true');
                apply();
            });

            apply();
        });

        const tooltip = document.getElementById('chart-tooltip');
        if (tooltip) {
            const showTooltip = (event, label, value) => {
                tooltip.textContent = `${label}: ${value}`;
                tooltip.classList.remove('hidden');
                tooltip.style.left = `${event.clientX + 8}px`;
                tooltip.style.top = `${event.clientY - 8}px`;
            };

            const moveTooltip = (event) => {
                tooltip.style.left = `${event.clientX + 8}px`;
                tooltip.style.top = `${event.clientY - 8}px`;
            };

            document.querySelectorAll('.chart-point').forEach((point) => {
                point.addEventListener('mouseenter', (event) => {
                    showTooltip(event, point.dataset.label || '-', point.dataset.value || '0');
                });
                point.addEventListener('mousemove', moveTooltip);
                point.addEventListener('mouseleave', () => {
                    tooltip.classList.add('hidden');
                });
            });
        }

        document.querySelectorAll('[data-accordion-toggle]').forEach((button) => {
            const targetId = button.getAttribute('data-accordion-toggle');
            const target = document.getElementById(targetId);
            const icon = document.querySelector(`[data-accordion-icon="${targetId}"]`);
            if (!target) return;

            button.addEventListener('click', () => {
                const isOpen = !target.classList.contains('hidden');
                target.classList.toggle('hidden', isOpen);
                button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                if (icon) {
                    icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            });
        });
    </script>
</body>
</html>
