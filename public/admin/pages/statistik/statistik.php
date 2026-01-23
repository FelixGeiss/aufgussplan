<?php
/**
 * STATISTIKEN
 *
 * Platzhalterseite fuer Statistik-Ansichten.
 *
 * URL: http://localhost/aufgussplan/admin/statistik/statistik.php
 */

session_start();

require_once __DIR__ . '/../../../../src/config/config.php';
require_once __DIR__ . '/../../../../src/auth.php';
require_once __DIR__ . '/../../../../src/db/connection.php';

require_login();
require_permission('statistik');

$db = Database::getInstance()->getConnection();

// Kombiniert Labels und Werte fuer Balkendiagramm-Daten.
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

// Mappt Ergebniszeilen auf key => count.
function mapCountsByKey(array $rows, $keyField, $valueField) {
    $map = [];
    foreach ($rows as $row) {
        $map[$row[$keyField]] = (int)$row[$valueField];
    }
    return $map;
}

// Baut SQL-Filter fuer ausgewaehlte Plaene.
function buildPlanFilter(array $selectedPlanIds, $alias = 'a') {
    if (empty($selectedPlanIds)) {
        return ['sql' => '', 'params' => []];
    }
    $placeholders = implode(',', array_fill(0, count($selectedPlanIds), '?'));
    return ['sql' => $alias . '.plan_id IN (' . $placeholders . ')', 'params' => $selectedPlanIds];
}

// Setzt WHERE-Teile fuer Statistikabfragen zusammen.
function buildStatsWhere($periodCondition, $planFilterSql) {
    $parts = [];
    if ($periodCondition) {
        $parts[] = $periodCondition;
    }
    if ($planFilterSql) {
        $parts[] = $planFilterSql;
    }
    return implode(' AND ', $parts);
}

// Laedt Statistikzeilen (Label/Count) mit optionalen Filtern.
function fetchStatsItems(PDO $db, $labelExpr, $joinSql, $whereSql, array $params) {
    $sql = "SELECT " . $labelExpr . " AS label, COALESCE(SUM(s.anzahl), 0) AS cnt
            FROM statistik s " . $joinSql;
    if ($whereSql) {
        $sql .= " WHERE " . $whereSql;
    }
    $sql .= " GROUP BY " . $labelExpr . " ORDER BY cnt DESC, label ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $items[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
    }
    return $items;
}

// Liefert Staerke-Verteilung als Diagramm-Items.
function fetchStaerkeItems(PDO $db, $whereSql, array $params) {
    $sql = "SELECT s.staerke, COALESCE(SUM(s.anzahl), 0) AS cnt
            FROM statistik s";
    if ($whereSql) {
        $sql .= " WHERE " . $whereSql;
    }
    $sql .= " GROUP BY s.staerke";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $map = [];
    $ohne = 0;
    foreach ($rows as $row) {
        if ($row['staerke'] === null) {
            $ohne = (int)$row['cnt'];
            continue;
        }
        $map[(int)$row['staerke']] = (int)$row['cnt'];
    }
    $items = [];
    for ($s = 1; $s <= 6; $s++) {
        $items[] = ['label' => 'St ' . $s, 'value' => $map[$s] ?? 0];
    }
    if ($ohne > 0) {
        $items[] = ['label' => 'Ohne Staerke', 'value' => $ohne];
    }
    return $items;
}

// Holt Zeitreihen-Counts als Map.
function fetchTimeSeriesMap(PDO $db, $labelExpr, $whereSql, array $params) {
    $sql = "SELECT " . $labelExpr . " AS label, COALESCE(SUM(s.anzahl), 0) AS cnt
            FROM statistik s";
    if ($whereSql) {
        $sql .= " WHERE " . $whereSql;
    }
    $sql .= " GROUP BY " . $labelExpr;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return mapCountsByKey($stmt->fetchAll(), 'label', 'cnt');
}

// Baut Zeitreihen-Items aus Labels und Map.
function buildTimeSeriesItems(array $labels, array $keys, array $map) {
    $counts = [];
    foreach ($keys as $key) {
        $counts[] = $map[$key] ?? 0;
    }
    return buildBarItems($labels, $counts);
}

$planRows = $db->query("SELECT id, name FROM plaene ORDER BY name ASC")->fetchAll();
$allPlanIds = [];
foreach ($planRows as $planRow) {
    $allPlanIds[] = (int)$planRow['id'];
}

$selectedPlanIds = [];
$noPlansSelected = false;
if (isset($_GET['plans']) && $_GET['plans'] !== '' && $_GET['plans'] !== 'all') {
    if ($_GET['plans'] === 'none') {
        $noPlansSelected = true;
    } else {
        $rawPlanIds = array_filter(array_map('trim', explode(',', $_GET['plans'])));
        foreach ($rawPlanIds as $rawPlanId) {
            if (ctype_digit($rawPlanId)) {
                $selectedPlanIds[] = (int)$rawPlanId;
            }
        }
        if (!empty($allPlanIds)) {
            $selectedPlanIds = array_values(array_intersect($selectedPlanIds, $allPlanIds));
        }
    }
}

if ($noPlansSelected) {
    $selectedPlanIds = [];
    $planFilter = ['sql' => '1=0', 'params' => []];
    $planFilterStats = ['sql' => '1=0', 'params' => []];
} elseif (!empty($allPlanIds) && (!empty($selectedPlanIds) && count($selectedPlanIds) < count($allPlanIds))) {
    $planFilter = buildPlanFilter($selectedPlanIds, 'a');
    $planFilterStats = buildPlanFilter($selectedPlanIds, 's');
} else {
    $selectedPlanIds = [];
    $planFilter = buildPlanFilter([]);
    $planFilterStats = buildPlanFilter([], 's');
}

// Aufgüsse pro Tag (letzte 7 Tage)
$byDayRows = $db->prepare(
    "SELECT DATE(datum) AS label, COALESCE(SUM(anzahl), 0) AS cnt
     FROM statistik s
     WHERE s.datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)"
     . ($planFilterStats['sql'] ? " AND " . $planFilterStats['sql'] : "") . "
     GROUP BY DATE(datum)
     ORDER BY label ASC"
);
$byDayRows->execute($planFilterStats['params']);
$byDayMap = mapCountsByKey($byDayRows->fetchAll(), 'label', 'cnt');
$dayLabels = [];
$dayCounts = [];
$dayKeys = [];
$dayStart = new DateTime('today');
$dayStart->modify('-6 days');
for ($i = 0; $i < 7; $i++) {
    $label = $dayStart->format('d.m.Y');
    $key = $dayStart->format('Y-m-d');
    $dayLabels[] = $label;
    $dayKeys[] = $key;
    $dayCounts[] = $byDayMap[$key] ?? 0;
    $dayStart->modify('+1 day');
}
$byDayItems = buildBarItems($dayLabels, $dayCounts);

// Aufgüsse pro Woche (letzte 8 Wochen)
$byWeekRows = $db->prepare(
    "SELECT YEARWEEK(datum, 3) AS yw, COALESCE(SUM(anzahl), 0) AS cnt
     FROM statistik s
     WHERE s.datum >= DATE_SUB(CURDATE(), INTERVAL 7 WEEK)"
     . ($planFilterStats['sql'] ? " AND " . $planFilterStats['sql'] : "") . "
     GROUP BY YEARWEEK(datum, 3)
     ORDER BY yw ASC"
);
$byWeekRows->execute($planFilterStats['params']);
$byWeekMap = mapCountsByKey($byWeekRows->fetchAll(), 'yw', 'cnt');
$weekLabels = [];
$weekCounts = [];
$weekKeys = [];
$weekStart = new DateTime('monday this week');
$weekStart->modify('-7 weeks');
for ($i = 0; $i < 8; $i++) {
    $key = $weekStart->format('oW');
    $weekLabels[] = 'KW ' . $weekStart->format('W') . '/' . $weekStart->format('o');
    $weekKeys[] = $key;
    $weekCounts[] = $byWeekMap[$key] ?? 0;
    $weekStart->modify('+1 week');
}
$byWeekItems = buildBarItems($weekLabels, $weekCounts);

// Aufgüsse pro Monat (letzte 12 Monate)
$byMonthRows = $db->prepare(
    "SELECT DATE_FORMAT(datum, '%Y-%m') AS ym, COALESCE(SUM(anzahl), 0) AS cnt
     FROM statistik s
     WHERE s.datum >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)"
     . ($planFilterStats['sql'] ? " AND " . $planFilterStats['sql'] : "") . "
     GROUP BY DATE_FORMAT(datum, '%Y-%m')
     ORDER BY ym ASC"
);
$byMonthRows->execute($planFilterStats['params']);
$byMonthMap = mapCountsByKey($byMonthRows->fetchAll(), 'ym', 'cnt');
$monthLabels = [];
$monthCounts = [];
$monthKeys = [];
$monthStart = new DateTime('first day of this month');
$monthStart->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $key = $monthStart->format('Y-m');
    $monthLabels[] = $monthStart->format('m/Y');
    $monthKeys[] = $key;
    $monthCounts[] = $byMonthMap[$key] ?? 0;
    $monthStart->modify('+1 month');
}
$byMonthItems = buildBarItems($monthLabels, $monthCounts);

// Aufgüsse pro Jahr
$byYearRows = $db->prepare(
    "SELECT YEAR(datum) AS y, COALESCE(SUM(anzahl), 0) AS cnt
     FROM statistik s"
     . ($planFilterStats['sql'] ? " WHERE " . $planFilterStats['sql'] : "") . "
     GROUP BY YEAR(datum)
     ORDER BY y ASC"
);
$byYearRows->execute($planFilterStats['params']);
$yearLabels = [];
$yearCounts = [];
foreach ($byYearRows->fetchAll() as $row) {
    $yearLabels[] = (string)$row['y'];
    $yearCounts[] = (int)$row['cnt'];
}
$yearKeys = $yearLabels;
$byYearItems = buildBarItems($yearLabels, $yearCounts);

// Wie oft welcher Aufguss (wie oft im Plan)
$aufgussNameStmt = $db->prepare(
    "SELECT COALESCE(an.name, 'Ohne Name') AS label, COUNT(*) AS cnt
     FROM aufguesse a
     LEFT JOIN aufguss_namen an ON a.aufguss_name_id = an.id"
    . ($planFilter['sql'] ? " WHERE " . $planFilter['sql'] : "") . "
     GROUP BY COALESCE(an.name, 'Ohne Name')
     ORDER BY cnt DESC, label ASC"
);
$aufgussNameStmt->execute($planFilter['params']);
$aufgussNameRows = $aufgussNameStmt->fetchAll();
$aufgussNameItems = [];
foreach ($aufgussNameRows as $row) {
    $aufgussNameItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}
if ($noPlansSelected) {
    $aufgussNameItems = [];
}

// Wie oft ein Duftmittel verwendet wurde (wie oft im Plan)
$duftmittelStmt = $db->prepare(
    "SELECT COALESCE(d.name, 'Ohne Duftmittel') AS label, COUNT(*) AS cnt
     FROM aufguesse a
     LEFT JOIN duftmittel d ON a.duftmittel_id = d.id
     " . ($planFilter['sql'] ? "WHERE " . $planFilter['sql'] : "") . "
     GROUP BY COALESCE(d.name, 'Ohne Duftmittel')
     ORDER BY cnt DESC, label ASC"
);
$duftmittelStmt->execute($planFilter['params']);
$duftmittelRows = $duftmittelStmt->fetchAll();
$duftmittelItems = [];
foreach ($duftmittelRows as $row) {
    $duftmittelItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}
if ($noPlansSelected) {
    $duftmittelItems = [];
}

// Wie oft welche Sauna genutzt wurde (wie oft im Plan)
$saunaStmt = $db->prepare(
    "SELECT COALESCE(s.name, 'Ohne Sauna') AS label, COUNT(*) AS cnt
     FROM aufguesse a
     LEFT JOIN saunen s ON a.sauna_id = s.id
     " . ($planFilter['sql'] ? "WHERE " . $planFilter['sql'] : "") . "
     GROUP BY COALESCE(s.name, 'Ohne Sauna')
     ORDER BY cnt DESC, label ASC"
);
$saunaStmt->execute($planFilter['params']);
$saunaRows = $saunaStmt->fetchAll();
$saunaItems = [];
foreach ($saunaRows as $row) {
    $saunaItems[] = ['label' => $row['label'], 'value' => (int)$row['cnt']];
}
if ($noPlansSelected) {
    $saunaItems = [];
}

// Aufgüsse nach Staerke (wie oft im Plan)
$staerkeStmt = $db->prepare(
    "SELECT a.staerke, COUNT(*) AS cnt
     FROM aufguesse a"
     . ($planFilter['sql'] ? " WHERE " . $planFilter['sql'] : "") . "
     GROUP BY a.staerke"
);
$staerkeStmt->execute($planFilter['params']);
$staerkeRows = $staerkeStmt->fetchAll();
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
for ($s = 1; $s <= 6; $s++) {
    $staerkeItems[] = ['label' => 'St ' . $s, 'value' => $staerkeMap[$s] ?? 0];
}
if ($ohneStaerkeCount > 0) {
    $staerkeItems[] = ['label' => 'Ohne Staerke', 'value' => $ohneStaerkeCount];
}
if ($noPlansSelected) {
    $staerkeItems = [];
}

// Umfrage-Bewertungen nach Kriterium (Durchschnitt)
$ratingItems = [];
try {
    $planFilterRatings = $noPlansSelected ? ['sql' => '1=0', 'params' => []] : buildPlanFilter($selectedPlanIds, 'r');
    $ratingStmt = $db->prepare(
        "SELECT r.kriterium AS label,
                r.aufguss_name_id AS aufguss_name_id,
                COALESCE(an.name, 'Unbekannter Aufguss') AS aufguss,
                AVG(r.rating) AS avg_rating,
                COUNT(*) AS cnt
         FROM umfrage_bewertungen r
         LEFT JOIN aufguss_namen an ON r.aufguss_name_id = an.id"
        . ($planFilterRatings['sql'] ? " WHERE " . $planFilterRatings['sql'] : "") . "
         GROUP BY r.kriterium, r.aufguss_name_id
         ORDER BY avg_rating DESC, label ASC"
    );
    $ratingStmt->execute($planFilterRatings['params']);
    foreach ($ratingStmt->fetchAll() as $row) {
        $ratingItems[] = [
            'label' => $row['label'],
            'aufguss_name_id' => $row['aufguss_name_id'] !== null ? (int)$row['aufguss_name_id'] : null,
            'aufguss' => $row['aufguss'],
            'value' => round((float)$row['avg_rating'], 2),
            'count' => (int)$row['cnt']
        ];
    }
    if ($noPlansSelected) {
        $ratingItems = [];
    }
} catch (Exception $e) {
    $ratingItems = [];
}

$ratingAufguesse = [];
$ratingKriterien = [];
if (!empty($ratingItems)) {
    foreach ($ratingItems as $item) {
        $aufgussLabel = trim((string)($item['aufguss'] ?? ''));
        $kriteriumLabel = trim((string)($item['label'] ?? ''));
        if ($aufgussLabel !== '') {
            $ratingAufguesse[$aufgussLabel] = true;
        }
        if ($kriteriumLabel !== '') {
            $ratingKriterien[$kriteriumLabel] = true;
        }
    }
    $ratingAufguesse = array_keys($ratingAufguesse);
    $ratingKriterien = array_keys($ratingKriterien);
    sort($ratingAufguesse, SORT_NATURAL | SORT_FLAG_CASE);
    sort($ratingKriterien, SORT_NATURAL | SORT_FLAG_CASE);
}


// Statistik nach Zeitraum (aus statistik-Tabelle)
$periodDay = "s.datum = CURDATE()";
$periodWeek = "s.datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
$periodMonth = "s.datum >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
$periodYear = "s.datum >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";

$duftmittelList = $db->query("SELECT id, name FROM duftmittel ORDER BY name ASC")->fetchAll();
$saunaList = $db->query("SELECT id, name FROM saunen ORDER BY name ASC")->fetchAll();

$duftDayMap = fetchTimeSeriesMap($db, "DATE(datum)", buildStatsWhere($periodDay . " AND s.duftmittel_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$duftWeekMap = fetchTimeSeriesMap($db, "YEARWEEK(datum, 3)", buildStatsWhere($periodWeek . " AND s.duftmittel_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$duftMonthMap = fetchTimeSeriesMap($db, "DATE_FORMAT(datum, '%Y-%m')", buildStatsWhere($periodMonth . " AND s.duftmittel_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$duftYearMap = fetchTimeSeriesMap($db, "YEAR(datum)", buildStatsWhere("s.duftmittel_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);

$saunaDayMap = fetchTimeSeriesMap($db, "DATE(datum)", buildStatsWhere($periodDay . " AND s.sauna_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$saunaWeekMap = fetchTimeSeriesMap($db, "YEARWEEK(datum, 3)", buildStatsWhere($periodWeek . " AND s.sauna_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$saunaMonthMap = fetchTimeSeriesMap($db, "DATE_FORMAT(datum, '%Y-%m')", buildStatsWhere($periodMonth . " AND s.sauna_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$saunaYearMap = fetchTimeSeriesMap($db, "YEAR(datum)", buildStatsWhere("s.sauna_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);

$aufgussDayMap = fetchTimeSeriesMap($db, "DATE(datum)", buildStatsWhere($periodDay . " AND s.aufguss_name_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$aufgussWeekMap = fetchTimeSeriesMap($db, "YEARWEEK(datum, 3)", buildStatsWhere($periodWeek . " AND s.aufguss_name_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$aufgussMonthMap = fetchTimeSeriesMap($db, "DATE_FORMAT(datum, '%Y-%m')", buildStatsWhere($periodMonth . " AND s.aufguss_name_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);
$aufgussYearMap = fetchTimeSeriesMap($db, "YEAR(datum)", buildStatsWhere("s.aufguss_name_id IS NOT NULL", $planFilterStats['sql']), $planFilterStats['params']);

$staerkeDayItemsByLevel = [];
$staerkeWeekItemsByLevel = [];
$staerkeMonthItemsByLevel = [];
$staerkeYearItemsByLevel = [];
for ($level = 1; $level <= 6; $level++) {
    $staerkeDayMap = fetchTimeSeriesMap(
        $db,
        "DATE(datum)",
        buildStatsWhere($periodDay . " AND s.staerke = " . $level, $planFilterStats['sql']),
        $planFilterStats['params']
    );
    $staerkeWeekMap = fetchTimeSeriesMap(
        $db,
        "YEARWEEK(datum, 3)",
        buildStatsWhere($periodWeek . " AND s.staerke = " . $level, $planFilterStats['sql']),
        $planFilterStats['params']
    );
    $staerkeMonthMap = fetchTimeSeriesMap(
        $db,
        "DATE_FORMAT(datum, '%Y-%m')",
        buildStatsWhere($periodMonth . " AND s.staerke = " . $level, $planFilterStats['sql']),
        $planFilterStats['params']
    );
    $staerkeYearMap = fetchTimeSeriesMap(
        $db,
        "YEAR(datum)",
        buildStatsWhere("s.staerke = " . $level, $planFilterStats['sql']),
        $planFilterStats['params']
    );

    $staerkeDayItemsByLevel[$level] = buildTimeSeriesItems($dayLabels, $dayKeys, $staerkeDayMap);
    $staerkeWeekItemsByLevel[$level] = buildTimeSeriesItems($weekLabels, $weekKeys, $staerkeWeekMap);
    $staerkeMonthItemsByLevel[$level] = buildTimeSeriesItems($monthLabels, $monthKeys, $staerkeMonthMap);
    $staerkeYearItemsByLevel[$level] = buildTimeSeriesItems($yearLabels, $yearKeys, $staerkeYearMap);
}

$duftDayItems = buildTimeSeriesItems($dayLabels, $dayKeys, $duftDayMap);
$duftWeekItems = buildTimeSeriesItems($weekLabels, $weekKeys, $duftWeekMap);
$duftMonthItems = buildTimeSeriesItems($monthLabels, $monthKeys, $duftMonthMap);
$duftYearItems = buildTimeSeriesItems($yearLabels, $yearKeys, $duftYearMap);

$saunaDayItems = buildTimeSeriesItems($dayLabels, $dayKeys, $saunaDayMap);
$saunaWeekItems = buildTimeSeriesItems($weekLabels, $weekKeys, $saunaWeekMap);
$saunaMonthItems = buildTimeSeriesItems($monthLabels, $monthKeys, $saunaMonthMap);
$saunaYearItems = buildTimeSeriesItems($yearLabels, $yearKeys, $saunaYearMap);

$aufgussDayItems = buildTimeSeriesItems($dayLabels, $dayKeys, $aufgussDayMap);
$aufgussWeekItems = buildTimeSeriesItems($weekLabels, $weekKeys, $aufgussWeekMap);
$aufgussMonthItems = buildTimeSeriesItems($monthLabels, $monthKeys, $aufgussMonthMap);
$aufgussYearItems = buildTimeSeriesItems($yearLabels, $yearKeys, $aufgussYearMap);

$duftItemsById = [
    'day' => [],
    'week' => [],
    'month' => [],
    'year' => []
];
$saunaItemsById = [
    'day' => [],
    'week' => [],
    'month' => [],
    'year' => []
];
foreach ($duftmittelList as $duft) {
    $id = (int)$duft['id'];
    $duftDayMapId = fetchTimeSeriesMap($db, "DATE(datum)", buildStatsWhere($periodDay . " AND s.duftmittel_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $duftWeekMapId = fetchTimeSeriesMap($db, "YEARWEEK(datum, 3)", buildStatsWhere($periodWeek . " AND s.duftmittel_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $duftMonthMapId = fetchTimeSeriesMap($db, "DATE_FORMAT(datum, '%Y-%m')", buildStatsWhere($periodMonth . " AND s.duftmittel_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $duftYearMapId = fetchTimeSeriesMap($db, "YEAR(datum)", buildStatsWhere("s.duftmittel_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);

    $duftItemsById['day'][$id] = buildTimeSeriesItems($dayLabels, $dayKeys, $duftDayMapId);
    $duftItemsById['week'][$id] = buildTimeSeriesItems($weekLabels, $weekKeys, $duftWeekMapId);
    $duftItemsById['month'][$id] = buildTimeSeriesItems($monthLabels, $monthKeys, $duftMonthMapId);
    $duftItemsById['year'][$id] = buildTimeSeriesItems($yearLabels, $yearKeys, $duftYearMapId);
}
foreach ($saunaList as $sauna) {
    $id = (int)$sauna['id'];
    $saunaDayMapId = fetchTimeSeriesMap($db, "DATE(datum)", buildStatsWhere($periodDay . " AND s.sauna_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $saunaWeekMapId = fetchTimeSeriesMap($db, "YEARWEEK(datum, 3)", buildStatsWhere($periodWeek . " AND s.sauna_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $saunaMonthMapId = fetchTimeSeriesMap($db, "DATE_FORMAT(datum, '%Y-%m')", buildStatsWhere($periodMonth . " AND s.sauna_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);
    $saunaYearMapId = fetchTimeSeriesMap($db, "YEAR(datum)", buildStatsWhere("s.sauna_id = " . $id, $planFilterStats['sql']), $planFilterStats['params']);

    $saunaItemsById['day'][$id] = buildTimeSeriesItems($dayLabels, $dayKeys, $saunaDayMapId);
    $saunaItemsById['week'][$id] = buildTimeSeriesItems($weekLabels, $weekKeys, $saunaWeekMapId);
    $saunaItemsById['month'][$id] = buildTimeSeriesItems($monthLabels, $monthKeys, $saunaMonthMapId);
    $saunaItemsById['year'][$id] = buildTimeSeriesItems($yearLabels, $yearKeys, $saunaYearMapId);
}

$seriesDays = [
    ['key' => 'base', 'label' => 'Eingetragene Aufgüsse', 'items' => $byDayItems, 'strokeClass' => 'stroke-blue-500'],
    ['key' => 'aufguss', 'label' => 'Gemachte Aufgüsse', 'items' => $aufgussDayItems, 'strokeClass' => 'stroke-orange-500'],
];
foreach ($duftmittelList as $duft) {
    $seriesDays[] = [
        'key' => 'duft-' . $duft['id'],
        'label' => 'Duftmittel: ' . $duft['name'],
        'items' => $duftItemsById['day'][(int)$duft['id']] ?? [],
        'strokeClass' => 'stroke-sky-500'
    ];
}
foreach ($saunaList as $sauna) {
    $seriesDays[] = [
        'key' => 'sauna-' . $sauna['id'],
        'label' => 'Sauna: ' . $sauna['name'],
        'items' => $saunaItemsById['day'][(int)$sauna['id']] ?? [],
        'strokeClass' => 'stroke-emerald-500'
    ];
}
for ($level = 1; $level <= 6; $level++) {
    $seriesDays[] = [
        'key' => 'staerke-' . $level,
        'label' => 'Staerke ' . $level,
        'items' => $staerkeDayItemsByLevel[$level],
        'strokeClass' => 'stroke-slate-' . (300 + ($level * 100))
    ];
}

$seriesWeeks = [
    ['key' => 'base', 'label' => 'Eingetragene Aufgüsse', 'items' => $byWeekItems, 'strokeClass' => 'stroke-indigo-500'],
    ['key' => 'aufguss', 'label' => 'Gemachte Aufgüsse', 'items' => $aufgussWeekItems, 'strokeClass' => 'stroke-orange-600'],
];
foreach ($duftmittelList as $duft) {
    $seriesWeeks[] = [
        'key' => 'duft-' . $duft['id'],
        'label' => 'Duftmittel: ' . $duft['name'],
        'items' => $duftItemsById['week'][(int)$duft['id']] ?? [],
        'strokeClass' => 'stroke-sky-600'
    ];
}
foreach ($saunaList as $sauna) {
    $seriesWeeks[] = [
        'key' => 'sauna-' . $sauna['id'],
        'label' => 'Sauna: ' . $sauna['name'],
        'items' => $saunaItemsById['week'][(int)$sauna['id']] ?? [],
        'strokeClass' => 'stroke-emerald-600'
    ];
}
for ($level = 1; $level <= 6; $level++) {
    $seriesWeeks[] = [
        'key' => 'staerke-' . $level,
        'label' => 'Staerke ' . $level,
        'items' => $staerkeWeekItemsByLevel[$level],
        'strokeClass' => 'stroke-slate-' . (300 + ($level * 100))
    ];
}

$seriesMonths = [
    ['key' => 'base', 'label' => 'Eingetragene Aufgüsse', 'items' => $byMonthItems, 'strokeClass' => 'stroke-teal-500'],
    ['key' => 'aufguss', 'label' => 'Gemachte Aufgüsse', 'items' => $aufgussMonthItems, 'strokeClass' => 'stroke-orange-700'],
];
foreach ($duftmittelList as $duft) {
    $seriesMonths[] = [
        'key' => 'duft-' . $duft['id'],
        'label' => 'Duftmittel: ' . $duft['name'],
        'items' => $duftItemsById['month'][(int)$duft['id']] ?? [],
        'strokeClass' => 'stroke-sky-700'
    ];
}
foreach ($saunaList as $sauna) {
    $seriesMonths[] = [
        'key' => 'sauna-' . $sauna['id'],
        'label' => 'Sauna: ' . $sauna['name'],
        'items' => $saunaItemsById['month'][(int)$sauna['id']] ?? [],
        'strokeClass' => 'stroke-emerald-700'
    ];
}
for ($level = 1; $level <= 6; $level++) {
    $seriesMonths[] = [
        'key' => 'staerke-' . $level,
        'label' => 'Staerke ' . $level,
        'items' => $staerkeMonthItemsByLevel[$level],
        'strokeClass' => 'stroke-slate-' . (300 + ($level * 100))
    ];
}

$seriesYears = [
    ['key' => 'base', 'label' => 'Eingetragene Aufgüsse', 'items' => $byYearItems, 'strokeClass' => 'stroke-emerald-500'],
    ['key' => 'aufguss', 'label' => 'Gemachte Aufgüsse', 'items' => $aufgussYearItems, 'strokeClass' => 'stroke-orange-800'],
];
foreach ($duftmittelList as $duft) {
    $seriesYears[] = [
        'key' => 'duft-' . $duft['id'],
        'label' => 'Duftmittel: ' . $duft['name'],
        'items' => $duftItemsById['year'][(int)$duft['id']] ?? [],
        'strokeClass' => 'stroke-sky-800'
    ];
}
foreach ($saunaList as $sauna) {
    $seriesYears[] = [
        'key' => 'sauna-' . $sauna['id'],
        'label' => 'Sauna: ' . $sauna['name'],
        'items' => $saunaItemsById['year'][(int)$sauna['id']] ?? [],
        'strokeClass' => 'stroke-emerald-800'
    ];
}
for ($level = 1; $level <= 6; $level++) {
    $seriesYears[] = [
        'key' => 'staerke-' . $level,
        'label' => 'Staerke ' . $level,
        'items' => $staerkeYearItemsByLevel[$level],
        'strokeClass' => 'stroke-slate-' . (300 + ($level * 100))
    ];
}



if (defined('STATISTIK_JSON')) {
    $chartDataPayload = [
        'days' => [
            'categories' => $dayLabels,
            'series' => array_map(function ($set) {
                return [
                    'key' => $set['key'],
                    'name' => $set['label'],
                    'data' => array_map(function ($item) {
                        return (int)$item['value'];
                    }, $set['items'] ?? [])
                ];
            }, $seriesDays)
        ],
        'weeks' => [
            'categories' => $weekLabels,
            'series' => array_map(function ($set) {
                return [
                    'key' => $set['key'],
                    'name' => $set['label'],
                    'data' => array_map(function ($item) {
                        return (int)$item['value'];
                    }, $set['items'] ?? [])
                ];
            }, $seriesWeeks)
        ],
        'months' => [
            'categories' => $monthLabels,
            'series' => array_map(function ($set) {
                return [
                    'key' => $set['key'],
                    'name' => $set['label'],
                    'data' => array_map(function ($item) {
                        return (int)$item['value'];
                    }, $set['items'] ?? [])
                ];
            }, $seriesMonths)
        ],
        'years' => [
            'categories' => $yearLabels,
            'series' => array_map(function ($set) {
                return [
                    'key' => $set['key'],
                    'name' => $set['label'],
                    'data' => array_map(function ($item) {
                        return (int)$item['value'];
                    }, $set['items'] ?? [])
                ];
            }, $seriesYears)
        ]
    ];

    $barChartPayload = [
        'staerke' => [
            'categories' => array_map(function ($item) { return $item['label']; }, $staerkeItems),
            'data' => array_map(function ($item) { return (int)$item['value']; }, $staerkeItems)
        ],
        'aufguss' => [
            'categories' => array_map(function ($item) { return $item['label']; }, $aufgussNameItems),
            'data' => array_map(function ($item) { return (int)$item['value']; }, $aufgussNameItems)
        ],
        'duftmittel' => [
            'categories' => array_map(function ($item) { return $item['label']; }, $duftmittelItems),
            'data' => array_map(function ($item) { return (int)$item['value']; }, $duftmittelItems)
        ],
        'sauna' => [
            'categories' => array_map(function ($item) { return $item['label']; }, $saunaItems),
            'data' => array_map(function ($item) { return (int)$item['value']; }, $saunaItems)
        ]
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'chartData' => $chartDataPayload,
        'barChartData' => $barChartPayload
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <link rel="icon" href="/AufgussManager/branding/favicon/favicon.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiken - Aufgussplan</title>
    <link rel="stylesheet" href="../../../dist/style.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../../assets/css/admin.css'); ?>">
</head>
<body class="bg-gray-100">
    <?php
        $publicBase = BASE_URL;
        $adminBase = BASE_URL . 'admin/pages/';
        $adminAuthBase = BASE_URL . 'admin/login/';
        include __DIR__ . '/../../partials/navbar.php';
    ?>

    <div class="container mx-auto px-4 py-8">
        <h2 class="text-2xl font-bold mb-6">Statistiken</h2>

        <div class="mb-6"></div>

        <div class="mb-8">
            <div class="mb-6 sticky top-4 z-30">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Legende filtern</h3>
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-700">
                    <label class="plan-select-btn legend-filter">
                        <input type="checkbox" class="rounded border-gray-300" data-legend-group="aufguss" checked>
                        <span>Aufgüsse</span>
                    </label>
                    <label class="plan-select-btn legend-filter">
                        <input type="checkbox" class="rounded border-gray-300" data-legend-group="duft">
                        <span>Duftmittel</span>
                    </label>
                    <label class="plan-select-btn legend-filter">
                        <input type="checkbox" class="rounded border-gray-300" data-legend-group="sauna">
                        <span>Sauna</span>
                    </label>
                    <label class="plan-select-btn legend-filter">
                        <input type="checkbox" class="rounded border-gray-300" data-legend-group="stärke">
                        <span>Stärke</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="period-days" class="mb-8" data-period="days">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tage</h3>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>
                <div id="apex-chart-days" class="apex-chart apex-chart-line"></div>
            </div>
        </div>

        <div id="period-weeks" class="mb-8" data-period="weeks">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Wochen</h3>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>
                <div id="apex-chart-weeks" class="apex-chart apex-chart-line"></div>
            </div>
        </div>

        <div id="period-months" class="mb-8" data-period="months">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Monate</h3>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>
                <div id="apex-chart-months" class="apex-chart apex-chart-line"></div>
            </div>
        </div>

        <div id="period-years" class="mb-8" data-period="years">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Jahre</h3>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>
                <div id="apex-chart-years" class="apex-chart apex-chart-line"></div>
            </div>
        </div>

        </div>

        <div class="my-8 border-t border-gray-200"></div>

        <h3 id="more-stats" class="text-lg font-semibold text-gray-900 mb-4">Weitere Statistiken</h3>
        <?php if (!empty($planRows)) : ?>
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Pläne (ein-/ausblenden)</h4>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm font-semibold text-gray-700">Pläne anzeigen:</span>
                    <?php foreach ($planRows as $planRow) :
                        $planId = (int)$planRow['id'];
                        $isActive = empty($selectedPlanIds) || in_array($planId, $selectedPlanIds, true);
                    ?>
                        <button type="button" class="plan-select-btn<?php echo $isActive ? ' is-active' : ''; ?>" data-plan-id="<?php echo $planId; ?>" aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>">
                            <?php echo htmlspecialchars($planRow['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Aufgüsse nach Stärke</h3>
                <div id="apex-bar-staerke" class="apex-chart apex-chart-bar"></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft welcher Aufguss</h3>
                <div id="apex-bar-aufguss" class="apex-chart apex-chart-bar"></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft Duftmittel verwendet</h3>
                <div id="apex-bar-duftmittel" class="apex-chart apex-chart-bar"></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Wie oft welche Sauna</h3>
                <div id="apex-bar-sauna" class="apex-chart apex-chart-bar"></div>
            </div>
        </div>

        <div class="my-8 border-t border-gray-200"></div>
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Umfrage</h3>
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Umfragepunkte</h4>
            <?php if (!empty($ratingItems)) : ?>
                <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label for="umfrage-filter-aufguss" class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Aufguss filtern</label>
                            <select id="umfrage-filter-aufguss" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Alle Aufgüsse</option>
                                <?php foreach ($ratingAufguesse as $aufgussOption) : ?>
                                    <option value="<?php echo htmlspecialchars(strtolower($aufgussOption)); ?>">
                                        <?php echo htmlspecialchars($aufgussOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="umfrage-filter-kriterium" class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Umfragepunkt filtern</label>
                            <select id="umfrage-filter-kriterium" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Alle Umfragepunkte</option>
                                <?php foreach ($ratingKriterien as $kriteriumOption) : ?>
                                    <option value="<?php echo htmlspecialchars(strtolower($kriteriumOption)); ?>">
                                        <?php echo htmlspecialchars($kriteriumOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="umfrage-filter-avg" class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Ø Sterne filtern</label>
                            <select id="umfrage-filter-avg" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Alle Bewertungen</option>
                                <option value="4">Ab 4.0</option>
                                <option value="3">Ab 3.0</option>
                                <option value="2">Ab 2.0</option>
                                <option value="1">Ab 1.0</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <div class="flex w-full gap-2">
                                <button type="button" id="umfrage-filter-reset" class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                                    Filter zuruecksetzen
                                </button>
                                <button type="button" id="umfrage-download-csv" class="w-full rounded-md border border-blue-500 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                    CSV Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (empty($ratingItems)) : ?>
                <div class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                    Noch keine Bewertungen vorhanden.
                </div>
            <?php else : ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" id="umfrage-karten">
                    <?php foreach ($ratingItems as $item) :
                        $avg = (float)($item['value'] ?? 0);
                        $count = (int)($item['count'] ?? 0);
                        if ($avg >= 4.0) {
                            $cardClass = 'border-emerald-200 bg-emerald-50';
                            $badgeClass = 'bg-emerald-600';
                        } elseif ($avg >= 2.5) {
                            $cardClass = 'border-amber-200 bg-amber-50';
                            $badgeClass = 'bg-amber-600';
                        } else {
                            $cardClass = 'border-rose-200 bg-rose-50';
                            $badgeClass = 'bg-rose-600';
                        }
                    ?>
                        <div class="rounded-lg border <?php echo $cardClass; ?> shadow-sm px-5 py-4" data-aufguss="<?php echo htmlspecialchars(strtolower($item['aufguss'] ?? '')); ?>" data-aufguss-id="<?php echo htmlspecialchars((string)($item['aufguss_name_id'] ?? '')); ?>" data-kriterium="<?php echo htmlspecialchars(strtolower($item['label'] ?? '')); ?>" data-kriterium-label="<?php echo htmlspecialchars($item['label'] ?? ''); ?>" data-avg="<?php echo number_format($avg, 1, '.', ''); ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Umfragepunkt</div>
                                    <div class="text-lg font-semibold text-gray-900 mt-1 font-display">
                                        <?php echo htmlspecialchars($item['label']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($item['aufguss'] ?? 'Unbekannter Aufguss'); ?>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="text-xs text-white px-2 py-1 rounded-full <?php echo $badgeClass; ?>">
                                        <?php echo number_format($avg, 1, '.', ''); ?> / 5
                                    </span>
                                    <button type="button" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600" data-umfrage-delete>
                                        Löschen
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 text-sm text-gray-700">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="flex items-center gap-1 text-amber-500">
                                        <?php
                                        $fullStars = (int)floor($avg);
                                        $emptyStars = 5 - $fullStars;
                                        for ($i = 0; $i < $fullStars; $i++) {
                                            echo '<span aria-hidden="true">★</span>';
                                        }
                                        for ($i = 0; $i < $emptyStars; $i++) {
                                            echo '<span aria-hidden="true" class="text-gray-300">★</span>';
                                        }
                                        ?>
                                    </div>
                                    <span class="text-gray-600"><?php echo number_format($avg, 1, '.', ''); ?> Ø Sterne</span>
                                </div>
                                <div><span class="font-semibold text-gray-900"><?php echo $count; ?></span> Bewertungen</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        </div>
    </div>


    <script src="../../../assets/vendor/apexcharts.min.js"></script>
    <script>
        let chartData = <?php echo json_encode([
            'days' => [
                'categories' => $dayLabels,
                'series' => array_map(function ($set) {
                    return [
                        'key' => $set['key'],
                        'name' => $set['label'],
                        'data' => array_map(function ($item) {
                            return (int)$item['value'];
                        }, $set['items'] ?? [])
                    ];
                }, $seriesDays)
            ],
            'weeks' => [
                'categories' => $weekLabels,
                'series' => array_map(function ($set) {
                    return [
                        'key' => $set['key'],
                        'name' => $set['label'],
                        'data' => array_map(function ($item) {
                            return (int)$item['value'];
                        }, $set['items'] ?? [])
                    ];
                }, $seriesWeeks)
            ],
            'months' => [
                'categories' => $monthLabels,
                'series' => array_map(function ($set) {
                    return [
                        'key' => $set['key'],
                        'name' => $set['label'],
                        'data' => array_map(function ($item) {
                            return (int)$item['value'];
                        }, $set['items'] ?? [])
                    ];
                }, $seriesMonths)
            ],
            'years' => [
                'categories' => $yearLabels,
                'series' => array_map(function ($set) {
                    return [
                        'key' => $set['key'],
                        'name' => $set['label'],
                        'data' => array_map(function ($item) {
                            return (int)$item['value'];
                        }, $set['items'] ?? [])
                    ];
                }, $seriesYears)
            ]
        ], JSON_UNESCAPED_UNICODE); ?>;
        let barChartData = <?php echo json_encode([
            'staerke' => [
                'categories' => array_map(function ($item) { return $item['label']; }, $staerkeItems),
                'data' => array_map(function ($item) { return (int)$item['value']; }, $staerkeItems)
            ],
            'aufguss' => [
                'categories' => array_map(function ($item) { return $item['label']; }, $aufgussNameItems),
                'data' => array_map(function ($item) { return (int)$item['value']; }, $aufgussNameItems)
            ],
            'duftmittel' => [
                'categories' => array_map(function ($item) { return $item['label']; }, $duftmittelItems),
                'data' => array_map(function ($item) { return (int)$item['value']; }, $duftmittelItems)
            ],
            'sauna' => [
                'categories' => array_map(function ($item) { return $item['label']; }, $saunaItems),
                'data' => array_map(function ($item) { return (int)$item['value']; }, $saunaItems)
            ]
        ], JSON_UNESCAPED_UNICODE); ?>;
        const chartInstances = {};
        const barChartInstances = {};
        const seriesNameByKey = {};
        const seriesKeyByName = {};
        const chartPeriods = Object.keys(chartData).filter((period) => {
            return document.getElementById(`apex-chart-${period}`);
        });
        const legendGroupInputs = Array.from(document.querySelectorAll('[data-legend-group]'));

        document.querySelectorAll('[data-toggle-target]').forEach((button) => {
            const targetId = button.getAttribute('data-toggle-target');
            const group = button.getAttribute('data-toggle-group');
            const target = document.getElementById(targetId);
            if (!target) return;

            const apply = () => {
                const isActive = button.getAttribute('aria-pressed') === 'true';
                target.classList.toggle('hidden', !isActive);
                button.classList.toggle('is-active', isActive);
            };

            button.addEventListener('click', () => {
                if (group) {
                    document.querySelectorAll(`[data-toggle-group="${group}"]`).forEach((btn) => {
                        btn.setAttribute('aria-pressed', btn === button ? 'true' : 'false');
                        const otherTargetId = btn.getAttribute('data-toggle-target');
                        const otherTarget = otherTargetId ? document.getElementById(otherTargetId) : null;
                        if (otherTarget) {
                            const isActive = btn === button;
                            otherTarget.classList.toggle('hidden', !isActive);
                            btn.classList.toggle('is-active', isActive);
                        }
                    });
                    return;
                }

                const isActive = button.getAttribute('aria-pressed') === 'true';
                button.setAttribute('aria-pressed', isActive ? 'false' : 'true');
                apply();
            });

            apply();
        });

        const getLegendGroupForKey = (key) => {
            if (!key) return '';
            if (key === 'base' || key === 'aufguss') return 'aufguss';
            if (key.startsWith('duft-')) return 'duft';
            if (key.startsWith('sauna-')) return 'sauna';
            if (key.startsWith('staerke-')) return 'staerke';
            return '';
        };

        const getEnabledLegendGroups = () => {
            if (legendGroupInputs.length === 0) return new Set(['aufguss', 'duft', 'sauna', 'staerke']);
            return new Set(
                legendGroupInputs
                    .filter((input) => input.checked)
                    .map((input) => input.getAttribute('data-legend-group'))
                    .filter(Boolean)
            );
        };

        const getFilteredSeries = (period) => {
            const data = chartData[period];
            if (!data) return [];
            const enabledGroups = getEnabledLegendGroups();
            return data.series.filter((item) => {
                const group = getLegendGroupForKey(item.key);
                if (!group) return false;
                return enabledGroups.has(group);
            });
        };

        const buildChartOptions = (period, series) => {
            const data = chartData[period];
            return {
                chart: {
                    type: 'line',
                    height: 420,
                    toolbar: {
                        show: true,
                        offsetY: 0,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true
                        },
                        export: {
                            csv: {
                                filename: `statistik_${period}`,
                                headerCategory: 'Label',
                                headerValue: 'Wert'
                            },
                            svg: {
                                filename: `statistik_${period}`
                            },
                            png: {
                                filename: `statistik_${period}`
                            }
                        }
                    },
                    zoom: {
                        enabled: true,
                        type: 'x',
                        autoScaleYaxis: true
                    },
                    animations: { enabled: true }
                },
                series: series.map((item) => ({ name: item.name, data: item.data })),
                xaxis: {
                    categories: data.categories,
                    labels: { rotate: -35 }
                },
                stroke: { width: 3, curve: 'straight' },
                markers: { size: 3 },
                dataLabels: { enabled: false },
                grid: { strokeDashArray: 3 },
                legend: {
                    show: true,
                    position: 'top',
                    offsetY: 22,
                    onItemClick: { toggleDataSeries: true }
                },
                tooltip: { shared: true, intersect: false }
            };
        };

        const initCharts = () => {
            let readyCount = 0;
            const total = chartPeriods.length;
            chartPeriods.forEach((period) => {
                const container = document.getElementById(`apex-chart-${period}`);
                const data = chartData[period];
                if (!container || !data) return;
                seriesNameByKey[period] = {};
                seriesKeyByName[period] = {};
                data.series.forEach((item) => {
                    seriesNameByKey[period][item.key] = item.name;
                    seriesKeyByName[period][item.name] = item.key;
                });
                const filteredSeries = getFilteredSeries(period);
                if (filteredSeries.length === 0) {
                    container.innerHTML = '<div class="text-sm text-gray-500">Keine Daten vorhanden.</div>';
                    readyCount += 1;
                    if (readyCount === total) {
                    }
                    return;
                }
                container.innerHTML = '';
                const chart = new ApexCharts(container, buildChartOptions(period, filteredSeries));
                chartInstances[period] = chart;
                chart.render().then(() => {
                    readyCount += 1;
                    if (readyCount === total) {
                    }
                });
            });
        };

        const buildBarOptions = (data, color) => {
            const maxValue = Math.max(0, ...data.data);
            const tickAmount = maxValue <= 10 ? Math.max(1, maxValue) : 5;
            return {
                chart: {
                    type: 'bar',
                    height: 260,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: false,
                            zoom: false,
                            zoomin: false,
                            zoomout: false,
                            pan: false,
                            reset: false
                        },
                        export: {
                            csv: {
                                filename: 'statistik_bar',
                                headerCategory: 'Label',
                                headerValue: 'Wert'
                            },
                            svg: {
                                filename: 'statistik_bar'
                            },
                            png: {
                                filename: 'statistik_bar'
                            }
                        }
                    }
                },
                series: [
                    {
                        name: 'Anzahl',
                        data: data.data
                    }
                ],
                yaxis: {
                    min: 0,
                    max: maxValue <= 10 ? maxValue : undefined,
                    tickAmount,
                    forceNiceScale: true,
                    decimalsInFloat: 0,
                    labels: {
                        formatter: (value) => {
                            const rounded = Math.round(value);
                            return Number.isInteger(value) ? rounded : '';
                        }
                    }
                },
                xaxis: {
                    categories: data.categories,
                    labels: { rotate: -35, trim: true }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '55%'
                    }
                },
                dataLabels: { enabled: false },
                colors: [color],
                grid: { strokeDashArray: 3 },
                tooltip: {
                    y: {
                        formatter: (value) => Math.round(value)
                    }
                }
            };
        };

        const initBarCharts = () => {
            const configs = [
                { key: 'staerke', id: 'apex-bar-staerke', color: '#2563eb' },
                { key: 'aufguss', id: 'apex-bar-aufguss', color: '#f97316' },
                { key: 'duftmittel', id: 'apex-bar-duftmittel', color: '#f59e0b' },
                { key: 'sauna', id: 'apex-bar-sauna', color: '#f43f5e' }
            ];
            configs.forEach((config) => {
                const container = document.getElementById(config.id);
                const data = barChartData[config.key];
                if (!container || !data) return;
                if (!data.categories || data.categories.length === 0) {
                    container.innerHTML = '<div class="text-sm text-gray-500">Keine Daten vorhanden.</div>';
                    return;
                }
                const chart = new ApexCharts(container, buildBarOptions(data, config.color));
                barChartInstances[config.id] = chart;
                chart.render();
            });
        };

        legendGroupInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const savedScroll = window.scrollY;
                destroyLineCharts();
                initCharts();
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.scrollTo({ top: savedScroll, behavior: 'auto' });
                    });
                });
            });
        });

        function destroyLineCharts() {
            chartPeriods.forEach((period) => {
                if (chartInstances[period]) {
                    chartInstances[period].destroy();
                    delete chartInstances[period];
                }
                const container = document.getElementById(`apex-chart-${period}`);
                if (container) {
                    container.innerHTML = '';
                }
            });
        }

        function destroyBarCharts() {
            Object.keys(barChartInstances).forEach((key) => {
                barChartInstances[key].destroy();
                delete barChartInstances[key];
            });
            ['apex-bar-staerke', 'apex-bar-aufguss', 'apex-bar-duftmittel', 'apex-bar-sauna'].forEach((id) => {
                const container = document.getElementById(id);
                if (container) {
                    container.innerHTML = '';
                }
            });
        }

        function reloadAllCharts() {
            destroyLineCharts();
            destroyBarCharts();
            initCharts();
            initBarCharts();
        }

        initCharts();
        initBarCharts();

        const planButtons = Array.from(document.querySelectorAll('[data-plan-id]'));
        if (planButtons.length > 0) {
            const allIds = Array.from(planButtons).map((btn) => btn.getAttribute('data-plan-id'));

            const getSelectedPlanIds = () => {
                return planButtons
                    .filter((btn) => btn.getAttribute('aria-pressed') === 'true')
                    .map((btn) => btn.getAttribute('data-plan-id'));
            };

            const updateUrl = (selected) => {
                const params = new URLSearchParams(window.location.search);
                if (selected.length === 0) {
                    params.set('plans', 'none');
                } else if (selected.length === allIds.length) {
                    params.delete('plans');
                } else {
                    params.set('plans', selected.join(','));
                }
                const next = params.toString();
                const base = window.location.pathname;
                const target = next ? `${base}?${next}` : base;
                window.history.replaceState({}, '', target);
            };

            const loadPlanData = async (selected) => {
                const savedScroll = window.scrollY;
                const params = new URLSearchParams();
                if (selected.length === 0) {
                    params.set('plans', 'none');
                } else if (selected.length < allIds.length) {
                    params.set('plans', selected.join(','));
                }
                const url = params.toString() ? `statistik_data.php?${params}` : 'statistik_data.php';
                try {
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const payload = await response.json();
                    if (!payload || !payload.chartData || !payload.barChartData) return;
                    chartData = payload.chartData;
                    barChartData = payload.barChartData;
                    reloadAllCharts();
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            window.scrollTo({ top: savedScroll, behavior: 'auto' });
                        });
                    });
                } catch (error) {
                    console.error('Fehler beim Laden der Statistikdaten', error);
                }
            };

            planButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const isActive = button.getAttribute('aria-pressed') === 'true';
                    button.setAttribute('aria-pressed', isActive ? 'false' : 'true');
                    button.classList.toggle('is-active', !isActive);
                    const selected = getSelectedPlanIds();
                    updateUrl(selected);
                    loadPlanData(selected);
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

        (function() {
            const aufgussInput = document.getElementById('umfrage-filter-aufguss');
            const kriteriumInput = document.getElementById('umfrage-filter-kriterium');
            const avgInput = document.getElementById('umfrage-filter-avg');
            const resetButton = document.getElementById('umfrage-filter-reset');
            const downloadButton = document.getElementById('umfrage-download-csv');
            const cards = Array.from(document.querySelectorAll('#umfrage-karten > div'));
            if (!aufgussInput || !kriteriumInput || !avgInput || cards.length === 0) return;

            const normalize = (value) => (value || '').toString().trim().toLowerCase();
            const applyFilters = () => {
                const aufgussValue = normalize(aufgussInput.value);
                const kriteriumValue = normalize(kriteriumInput.value);
                const avgValue = parseFloat(avgInput.value || '0');
                cards.forEach((card) => {
                    const aufguss = normalize(card.getAttribute('data-aufguss'));
                    const kriterium = normalize(card.getAttribute('data-kriterium'));
                    const avg = parseFloat(card.getAttribute('data-avg') || '0');
                    const matchAufguss = !aufgussValue || aufguss.includes(aufgussValue);
                    const matchKriterium = !kriteriumValue || kriterium.includes(kriteriumValue);
                    const matchAvg = !avgValue || avg >= avgValue;
                    card.classList.toggle('hidden', !(matchAufguss && matchKriterium && matchAvg));
                });
            };

            aufgussInput.addEventListener('change', applyFilters);
            kriteriumInput.addEventListener('change', applyFilters);
            avgInput.addEventListener('change', applyFilters);
            if (resetButton) {
                resetButton.addEventListener('click', () => {
                    aufgussInput.value = '';
                    kriteriumInput.value = '';
                    avgInput.value = '';
                    applyFilters();
                });
            }

            if (downloadButton) {
                downloadButton.addEventListener('click', () => {
                    const rows = [['Aufguss', 'Umfragepunkt', 'Avg_Sterne', 'Bewertungen']];
                    cards.forEach((card) => {
                        if (card.classList.contains('hidden')) return;
                        const aufguss = card.querySelector('.text-sm.text-gray-600')?.textContent?.trim() || '';
                        const kriterium = card.querySelector('.text-lg.font-semibold')?.textContent?.trim() || '';
                        const avg = card.getAttribute('data-avg') || '';
                        const countMatch = card.querySelector('.mt-4 .font-semibold')?.textContent?.trim() || '';
                        rows.push([aufguss, kriterium, avg, countMatch]);
                    });
                    const csv = rows.map((row) => row.map((cell) => {
                        const safe = (cell || '').toString().replace(/"/g, '""');
                        return `"${safe}"`;
                    }).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'umfrage_kacheln.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });
            }

            const getPlanIdsFromUrl = () => {
                const params = new URLSearchParams(window.location.search);
                const raw = params.get('plans');
                if (!raw || raw === 'all') return null;
                if (raw === 'none') return [];
                return raw.split(',').map((value) => value.trim()).filter((value) => value !== '' && /^\d+$/.test(value)).map((value) => Number(value));
            };

            const handleDelete = async (card) => {
                const kriterium = card.getAttribute('data-kriterium-label') || '';
                const aufgussIdRaw = card.getAttribute('data-aufguss-id') || '';
                const aufgussNameId = aufgussIdRaw !== '' && /^\d+$/.test(aufgussIdRaw) ? Number(aufgussIdRaw) : null;
                if (!kriterium) return;

                const confirmText = `Umfrage "${kriterium}" wirklich Löschen?`;
                if (!window.confirm(confirmText)) return;

                const payload = { kriterium, aufguss_name_id: aufgussNameId };
                const planIds = getPlanIdsFromUrl();
                if (Array.isArray(planIds) && planIds.length > 0) {
                    payload.plan_ids = planIds;
                }

                try {
                    const response = await fetch('../../../api/umfrage_bewertungen.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data || data.success !== true) {
                        const message = data && data.message ? data.message : 'Löschen fehlgeschlagen.';
                        alert(message);
                        return;
                    }
                    if (window.showToast) {
                        window.showToast('Gelöscht', 'success');
                    }
                    setTimeout(() => window.location.reload(), 700);
                } catch (error) {
                    console.error('Fehler beim Löschen der Umfrage', error);
                    alert('Löschen fehlgeschlagen.');
                }
            };

            cards.forEach((card) => {
                const deleteButton = card.querySelector('[data-umfrage-delete]');
                if (!deleteButton) return;
                deleteButton.addEventListener('click', () => {
                    handleDelete(card);
                });
            });
        })();
    </script>
    <script src="../../../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/admin.js'); ?>"></script>
</body>
</html>


