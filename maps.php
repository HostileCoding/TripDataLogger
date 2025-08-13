<?php
require 'db.php';

// === Filtri ===
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$onlyFav = isset($_GET['onlyFavorites']) && $_GET['onlyFavorites'] == '1';

if (empty($from) && empty($to)) {
    $to = date('Y-m-d\TH:i');
    $from = date('Y-m-d\TH:i', strtotime('-10 days'));
}

$from_sql = date('Y-m-d H:i:s', strtotime($from));
$to_sql = date('Y-m-d H:i:s', strtotime($to));

$sql = "SELECT * FROM trips 
        WHERE started_at BETWEEN ? AND ? ";
if ($onlyFav) $sql .= "AND is_favorite = 1 ";
$sql .= "ORDER BY started_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_sql, $to_sql);
$stmt->execute();
$result = $stmt->get_result();
$trips = $result->fetch_all(MYSQLI_ASSOC);

// === Prepara marker (solo se coordinate valide) ===
$markers = [];
foreach ($trips as $trip) {
    if (!empty($trip['start_lat']) && !empty($trip['start_lng'])) {
        $markers[] = [
            'lat' => (float) $trip['start_lat'],
            'lng' => (float) $trip['start_lng'],
            'popup' => "🚗 Start: " . $trip['start_address'],
            'type' => 'start'
        ];
    }
    if (!empty($trip['end_lat']) && !empty($trip['end_lng'])) {
        $markers[] = [
            'lat' => (float) $trip['end_lat'],
            'lng' => (float) $trip['end_lng'],
            'popup' => "🏁 End: " . $trip['end_address'],
            'type' => 'end'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Trips Map</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
  <style>
    #map { height: 600px; margin-top: 20px; border-radius: 10px; }
  </style>
</head>
<body>

<h1>🗺️ Trips Map</h1>
<a href="index.php" class="nav-link">⬅️ Back to Home</a>

<div class="warning-box" style="margin-top: 15px;">
  ⚠️ <strong>Note:</strong> Trips with unresolved coordinates are excluded from the map.
</div>

<!-- Filters -->
<div class="filter-box" style="margin-top: 15px;">
  <form method="GET">
    <label>From:</label>
    <input type="datetime-local" name="from" value="<?= htmlspecialchars($from) ?>">
    <label>To:</label>
    <input type="datetime-local" name="to" value="<?= htmlspecialchars($to) ?>">
    <label style="margin-left: 10px;">
      <input type="checkbox" name="onlyFavorites" value="1" <?= $onlyFav ? 'checked' : '' ?>> Show only favorites
    </label>
    <button type="submit" class="btn" style="margin-left: 10px;">Apply</button>
  </form>
</div>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script>
const map = L.map('map').setView([46.0, 8.9], 7);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// === Custom icons
const startIcon = L.icon({
  iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png',
  iconSize: [25, 25]
});
const endIcon = L.icon({
  iconUrl: 'https://cdn-icons-png.flaticon.com/512/149/149059.png',
  iconSize: [25, 25]
});

// === Cluster + heat
const markerCluster = L.markerClusterGroup();
const heatData = [];

const markers = <?= json_encode($markers) ?>;

markers.forEach(m => {
  const marker = L.marker([m.lat, m.lng], {
    icon: m.type === 'start' ? startIcon : endIcon
  }).bindPopup(m.popup);

  markerCluster.addLayer(marker);
  heatData.push([m.lat, m.lng, 0.5]); // weight 0.5
});

map.addLayer(markerCluster);

L.heatLayer(heatData, {
  radius: 20,
  blur: 15,
  maxZoom: 10
}).addTo(map);

// Auto-fit bounds
if (markerCluster.getLayers().length > 0) {
  map.fitBounds(markerCluster.getBounds().pad(0.1));
}
</script>

</body>
</html>