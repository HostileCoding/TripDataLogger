<?php
require 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('CONFIG_FILE', __DIR__ . '/config.json');
$defaultCost = 0.034;

// Load electricity cost
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    $costPerKw = isset($config['cost']) ? floatval($config['cost']) : $defaultCost;
} else {
    $costPerKw = $defaultCost;
}

$errors = [];
$imported = 0;

// === Geocoding function ===
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

// === Handle CSV Upload ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $errors[] = "Upload non valido.";
    } else {
        $csv = fopen($_FILES['csv']['tmp_name'], 'r');
        if ($csv === false) {
            $errors[] = "Impossibile aprire il file CSV.";
        } else {
            $header = fgetcsv($csv, 0, ';', '"');
            $lineNumber = 2;

            // PREPARO le query in anticipo
            // 1) INSERT IGNORE senza geocoding (coordinate a NULL)
            $insertSql = "INSERT IGNORE INTO trips 
                (category, started_at, start_odometer, start_address,
                 ended_at, end_odometer, end_address, duration_min,
                 distance_km, battery_consumed_kwh, user_note,
                 average_consumption, avg_speed_kmh, cost_per_kw,
                 start_lat, start_lng, end_lat, end_lng)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $insertStmt = $conn->prepare($insertSql);
            if (!$insertStmt) {
                $errors[] = "Errore prepare INSERT: " . $conn->error;
            }

            // 2) UPDATE delle sole coordinate, per il record appena inserito
            $updateSql = "UPDATE trips
                          SET start_lat = ?, start_lng = ?, end_lat = ?, end_lng = ?
                          WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                $errors[] = "Errore prepare UPDATE: " . $conn->error;
            }

            while (($row = fgetcsv($csv, 0, ';', '"')) !== false) {
                if (count($row) < 11) {
                    $errors[] = "Linea $lineNumber: riga incompleta (attese 11 colonne).";
                    $lineNumber++;
                    continue;
                }

                // Map CSV columns
                [$category, $started, $start_km, $start_address, $ended, $end_km,
                 $end_address, $duration, $distance, $consumption, $note] = $row;

                // Parse datetime
                $started_ts = strtotime($started);
                $ended_ts = strtotime($ended);
                if (!$started_ts || !$ended_ts) {
                    $errors[] = "Linea $lineNumber: formato data/ora non valido.";
                    $lineNumber++;
                    continue;
                }

                $started_at = date('Y-m-d H:i:s', $started_ts);
                $ended_at   = date('Y-m-d H:i:s', $ended_ts);

                // Parse numeric values
                $start_odometer = floatval(str_replace([' km', ','], ['', '.'], $start_km));
                $end_odometer   = floatval(str_replace([' km', ','], ['', '.'], $end_km));
                $distance_km    = floatval(str_replace([' km', ','], ['', '.'], $distance));
                $battery_kwh    = floatval(str_replace(',', '.', $consumption));
                $duration_min   = intval($duration);

                if ($start_odometer <= 0 || $end_odometer <= 0 || $distance_km <= 0 || $battery_kwh < 0 || $duration_min <= 0) {
                    $errors[] = "Linea $lineNumber: valori numerici non validi.";
                    $lineNumber++;
                    continue;
                }

                // Derived values
                $avg_consumption = $distance_km > 0 ? ($battery_kwh / $distance_km * 100) : null;
                $avg_speed       = $duration_min > 0 ? ($distance_km / ($duration_min / 60)) : null;

                // ========== 1) INSERIMENTO SENZA GEO ==========
                if ($insertStmt) {
                    // Tipi corretti per i 14 parametri:
                    // s,s,d,s,s,d,s,i,d,d,s,d,d,d
                    $insertStmt->bind_param(
                        "ssdssdsiddsddd",
                        $category,
                        $started_at,
                        $start_odometer,
                        $start_address,
                        $ended_at,
                        $end_odometer,
                        $end_address,
                        $duration_min,
                        $distance_km,
                        $battery_kwh,
                        $note,
                        $avg_consumption,
                        $avg_speed,
                        $costPerKw
                    );

                    if (!$insertStmt->execute()) {
                        $errors[] = "Linea $lineNumber: errore INSERT - " . $insertStmt->error;
                        $lineNumber++;
                        continue;
                    }
                } else {
                    // Se non è stato possibile preparare l'INSERT, non possiamo proseguire
                    $lineNumber++;
                    continue;
                }

                // Se affected_rows > 0 significa che è un NUOVO viaggio
                if ($insertStmt->affected_rows > 0) {
                    $imported++;
                    $newTripId = $conn->insert_id;

                    // ========== 2) GEO SOLO PER I NUOVI ==========
                    [$start_lat, $start_lng] = geocodeAddress($start_address);
                    [$end_lat, $end_lng]     = geocodeAddress($end_address);

                    if ($updateStmt) {
                        // Tipi: d d d d i
                        $updateStmt->bind_param(
                            "ddddi",
                            $start_lat,
                            $start_lng,
                            $end_lat,
                            $end_lng,
                            $newTripId
                        );
                        if (!$updateStmt->execute()) {
                            $errors[] = "Linea $lineNumber: errore UPDATE geocoding - " . $updateStmt->error;
                        }
                    } else {
                        $errors[] = "Linea $lineNumber: impossibile preparare UPDATE geocoding.";
                    }
                }
                // Se affected_rows == 0, il record esisteva già -> NIENTE geocoding

                $lineNumber++;
            }

            if ($insertStmt) $insertStmt->close();
            if ($updateStmt) $updateStmt->close();
            fclose($csv);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Trips CSV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>📥 Import Trips CSV</h1>

<div>
    <a href="index.php" class="nav-link">⬅️ Back to Home</a>
</div>

<?php if ($imported > 0): ?>
    <div class="message-box">
        ✅ <?= $imported ?> trip(s) imported successfully.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="error-box">
        <strong>Some rows were skipped / had issues:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <label for="csv"><strong>Select CSV File:</strong></label>
    <input type="file" name="csv" id="csv" accept=".csv" required>
    <button type="submit" class="btn">Upload & Import</button>
</form>

<div style="font-size:14px; margin-bottom:20px;">
    Current electricity cost: <strong><?= number_format($costPerKw, 4) ?> EUR/kWh</strong>
</div>

<!-- ⚠️ Geocoding warning -->
<div class="warning-box">
    ⚠️ <strong>Note:</strong> Geocoding will run <em>only</em> for newly imported trips.  
    If a trip already exists in the DB, no geocoding is performed for it.  
    If geocoding fails, the trip is still imported but won't appear on the map.
</div>

<!-- ⚠️ Cost warning -->
<div class="warning-box">
    ⚠️ <strong>Reminder:</strong> The electricity cost per kWh defined in <em>Settings</em> will 
    only be applied to trips imported from now on.  
    Previously imported trips retain their original value.
</div>

</body>
</html>