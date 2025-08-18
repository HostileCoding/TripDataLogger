<?php
require 'db.php';

define('CONFIG_FILE', __DIR__ . '/config.json');

// Default electricity cost if no config file exists
$defaultCost = 0.34;
$message = "";
$details  = [];

// --- Show a success message if redirected from delete_favorites.php ---
if (isset($_GET['favorites_cleared']) && $_GET['favorites_cleared'] == 1) {
    $message = "⚠️ All favorites have been removed.";
}

// === Geocoding function (riuso da import.php) ===
// Restituisce [lat, lng] oppure [null, null]
function geocodeAddress($address) {
    if ($address === null || $address === '') {
        return [null, null];
    }
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address);
    $opts = ["http" => ["header" => "User-Agent: trip-logger/1.0 (+contact:youremail@example.com)\r\n"]];
    $context = stream_context_create($opts);

    // Piccola pausa per rispetto rate-limit Nominatim
    usleep(300000); // 0.3s

    $response = @file_get_contents($url, false, $context);

    if ($response) {
        $json = json_decode($response, true);
        if (!empty($json[0])) {
            return [floatval($json[0]['lat']), floatval($json[0]['lon'])];
        }
    }
    return [null, null];
}

// --- Handle POST for electricity cost only ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['electricity_cost'])) {
    $newCost = floatval($_POST['electricity_cost']);
    file_put_contents(CONFIG_FILE, json_encode(['cost' => $newCost], JSON_PRETTY_PRINT));
    $message = "✅ Electricity cost saved successfully.";
}

// --- Handle POST for force geocoding by date/time range ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['range_start']) && isset($_POST['range_end'])) {
    $rawStart = trim($_POST['range_start']);
    $rawEnd   = trim($_POST['range_end']);

    if ($rawStart === '' || $rawEnd === '') {
        $message = "❌ Please provide both start and end date/time for the range.";
    } else {
        // Input dai campi datetime-local: 'YYYY-MM-DDTHH:MM'
        // Convertiamo in 'Y-m-d H:i:s'
        $startTs = strtotime($rawStart);
        $endTs   = strtotime($rawEnd);

        if (!$startTs || !$endTs) {
            $message = "❌ Invalid date/time format.";
        } elseif ($endTs < $startTs) {
            $message = "❌ End date/time must be after start date/time.";
        } else {
            $startAt = date('Y-m-d H:i:s', $startTs);
            $endAt   = date('Y-m-d H:i:s', $endTs);

            // Seleziona i viaggi nell'intervallo
            $sel = $conn->prepare("SELECT id, start_address, end_address FROM trips WHERE started_at BETWEEN ? AND ? ORDER BY started_at ASC");
            if (!$sel) {
                $message = "❌ Error preparing SELECT: " . $conn->error;
            } else {
                $sel->bind_param("ss", $startAt, $endAt);
                if ($sel->execute()) {
                    $result = $sel->get_result();
                    $processed = 0;
                    $updated   = 0;
                    $failedIds = [];

                    // Prepare update statement una volta
                    $upd = $conn->prepare("UPDATE trips SET start_lat = ?, start_lng = ?, end_lat = ?, end_lng = ? WHERE id = ?");
                    if (!$upd) {
                        $message = "❌ Error preparing UPDATE: " . $conn->error;
                    } else {
                        while ($row = $result->fetch_assoc()) {
                            $processed++;
                            $tripId = (int)$row['id'];
                            $startAddr = $row['start_address'];
                            $endAddr   = $row['end_address'];

                            // Se non ci sono indirizzi, salta
                            if (($startAddr === null || $startAddr === '') && ($endAddr === null || $endAddr === '')) {
                                $details[] = "ℹ️ Trip ID $tripId skipped (no addresses).";
                                continue;
                            }

                            [$startLat, $startLng] = geocodeAddress($startAddr);
                            [$endLat, $endLng]     = geocodeAddress($endAddr);

                            // Aggiorna anche se uno dei due è null: sovrascriviamo i campi con ciò che abbiamo (potrebbero restare null)
                            $upd->bind_param("ddddi", $startLat, $startLng, $endLat, $endLng, $tripId);
                            if ($upd->execute()) {
                                $updated++;
                            } else {
                                $failedIds[] = $tripId;
                            }
                        }
                        $upd->close();

                        $message = "🌍 Geocoding completed for range {$startAt} → {$endAt}. Processed: {$processed}, Updated: {$updated}" .
                                   (count($failedIds) ? ", Failed: " . count($failedIds) : "") . ".";

                        if (!empty($failedIds)) {
                            $details[] = "❌ Update failed for Trip IDs: " . implode(', ', $failedIds);
                        }
                    }
                } else {
                    $message = "❌ Error executing SELECT: " . $sel->error;
                }
                $sel->close();
            }
        }
    }
}

// --- Load current electricity cost ---
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    $currentCost = isset($config['cost']) ? $config['cost'] : $defaultCost;
} else {
    $currentCost = $defaultCost;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings & Geocoding Tools</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>⚙️ Settings</h1>

<div style="margin-bottom: 15px;">
    <a href="index.php" class="nav-link">⬅️ Back to Home</a>
</div>

<?php if (!empty($message)): ?>
    <div class="message-box" style="background-color: #28a745; margin-bottom: 10px; padding: 10px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (!empty($details)): ?>
    <div class="warning-box" style="margin-bottom: 15px;">
        <ul style="margin:0; padding-left: 18px;">
            <?php foreach ($details as $d): ?>
                <li><?= htmlspecialchars($d) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- === Electricity Cost Form === -->
<form method="POST" style="max-width: 480px; margin-bottom: 30px;">
    <label for="electricity_cost"><strong>Average Electricity Cost (EUR/kW):</strong></label>
    <input type="number" step="0.0001" name="electricity_cost" id="electricity_cost"
           value="<?= htmlspecialchars($currentCost) ?>" required
           style="width: 100%; margin-top: 5px; margin-bottom: 10px;">
    <button type="submit" class="btn">💾 Save Cost</button>
</form>

<!-- === Remove All Favorites Form === -->
<form method="POST" action="delete_favorites.php"
      onsubmit="return confirm('Are you sure you want to remove ALL favorites? This cannot be undone.');"
      style="max-width: 480px; margin-bottom: 30px;">
    <button type="submit" class="btn danger">❌ Remove All Favorites</button>
</form>

<hr style="margin: 25px 0; opacity: .2;">

<h2>🌍 Recalculate Geocoding by Date/Time Range</h2>

<div class="warning-box" style="margin-bottom: 12px;">
    ⚠️ To comply with Nominatim’s rate limit, the process adds a small delay between requests.
    If the selected range includes many trips, the operation may take some time.
</div>

<form method="POST" style="max-width: 480px;">
    <label for="range_start"><strong>Start (based on <code>started_at</code>):</strong></label>
    <input type="datetime-local" name="range_start" id="range_start"
           style="width: 100%; margin-top: 5px; margin-bottom: 10px;" required>

    <label for="range_end"><strong>End (based on <code>started_at</code>):</strong></label>
    <input type="datetime-local" name="range_end" id="range_end"
           style="width: 100%; margin-top: 5px; margin-bottom: 10px;" required>

    <button type="submit" class="btn">🔁 Recalculate Geocoding</button>
</form>

</body>
</html>
