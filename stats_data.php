<?php
require 'db.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$onlyFav = isset($_GET['onlyFav']) && $_GET['onlyFav'] == 1;

// Se nessun filtro, ultimi 10 giorni
if (empty($from) && empty($to)) {
    $to = date('Y-m-d\TH:i');
    $from = date('Y-m-d\TH:i', strtotime('-10 days'));
}

$from_sql = date('Y-m-d H:i:s', strtotime($from));
$to_sql = date('Y-m-d H:i:s', strtotime($to));

$sql = "SELECT * FROM trips WHERE started_at BETWEEN ? AND ?";
if ($onlyFav) {
    $sql .= " AND is_favorite = 1";
}
$sql .= " ORDER BY ended_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_sql, $to_sql);
$stmt->execute();
$result = $stmt->get_result();
$trips = $result->fetch_all(MYSQLI_ASSOC);

// Calcolo statistiche
$current_km = null;
$totalDuration = 0;
$totalDistance = 0;
$totalBattery = 0;

foreach ($trips as $row) {
    if (!empty($row['end_odometer']) && $current_km === null) {
        $current_km = $row['end_odometer'];
    }
    $totalDuration += $row['duration_min'];
    $totalDistance += $row['distance_km'];
    $totalBattery += $row['battery_consumed_kwh'];
}

$avgConsumption = $totalDistance > 0 ? ($totalBattery / $totalDistance * 100) : 0;
$avgSpeed = $totalDuration > 0 ? ($totalDistance / ($totalDuration / 60)) : 0;

header('Content-Type: application/json');
echo json_encode([
    'trips' => $trips,
    'current_km' => $current_km ?? 0,
    'totalDuration' => $totalDuration,
    'totalDistance' => $totalDistance,
    'totalBattery' => $totalBattery,
    'avgConsumption' => $avgConsumption,
    'avgSpeed' => $avgSpeed
]);