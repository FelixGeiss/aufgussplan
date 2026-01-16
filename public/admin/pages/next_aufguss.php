<?php
session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/db/connection.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'invalid_id'
    ]);
    exit;
}

$sql = "SELECT
            a.id,
            an.name AS name,
            a.staerke,
            aa_list.aufgieser_namen,
            aa_list.aufgieser_items,
            a.zeit_anfang,
            a.zeit,
            s.name AS sauna_name,
            s.bild AS sauna_bild,
            s.temperatur AS sauna_temperatur,
            d.name AS duftmittel_name,
            m.name AS mitarbeiter_name,
            m.bild AS mitarbeiter_bild
        FROM aufguesse a
        LEFT JOIN (
            SELECT aa.aufguss_id,
                   GROUP_CONCAT(COALESCE(m2.name, aa.name) ORDER BY aa.id SEPARATOR ', ') as aufgieser_namen,
                   GROUP_CONCAT(CONCAT(COALESCE(m2.name, aa.name), '||', IFNULL(m2.bild, '')) ORDER BY aa.id SEPARATOR ';;') as aufgieser_items
            FROM aufguss_aufgieser aa
            LEFT JOIN mitarbeiter m2 ON aa.mitarbeiter_id = m2.id
            GROUP BY aa.aufguss_id
        ) aa_list ON aa_list.aufguss_id = a.id
        LEFT JOIN aufguss_namen an ON an.id = a.aufguss_name_id
        LEFT JOIN saunen s ON s.id = a.sauna_id
        LEFT JOIN duftmittel d ON d.id = a.duftmittel_id
        LEFT JOIN mitarbeiter m ON m.id = a.mitarbeiter_id
        WHERE a.id = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode([
        'success' => false,
        'error' => 'not_found'
    ]);
    exit;
}

$aufgieser = $row['aufgieser_namen'] ?: ($row['mitarbeiter_name'] ?: null);

echo json_encode([
    'success' => true,
    'data' => [
        'id' => $row['id'],
        'name' => $row['name'],
        'staerke' => $row['staerke'],
        'aufgieser_name' => $aufgieser,
        'aufgieser_items' => $row['aufgieser_items'],
        'sauna_name' => $row['sauna_name'],
        'sauna_bild' => $row['sauna_bild'],
        'sauna_temperatur' => $row['sauna_temperatur'],
        'mitarbeiter_bild' => $row['mitarbeiter_bild'],
        'duftmittel_name' => $row['duftmittel_name']
    ]
]);
