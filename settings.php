<?php
define('CONFIG_FILE', __DIR__ . '/config.json');

// Default electricity cost if no config file exists
$defaultCost = 0.34;
$message = "";

// --- Show a success message if redirected from delete_favorites.php ---
if (isset($_GET['favorites_cleared']) && $_GET['favorites_cleared'] == 1) {
    $message = "⚠️ All favorites have been removed.";
}

// --- Handle POST for electricity cost only ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['electricity_cost'])) {
    $newCost = floatval($_POST['electricity_cost']);
    file_put_contents(CONFIG_FILE, json_encode(['cost' => $newCost], JSON_PRETTY_PRINT));
    $message = "✅ Electricity cost saved successfully.";
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
    <title>Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>⚙️ Settings</h1>

<div style="margin-bottom: 15px;">
    <a href="index.php" class="nav-link">⬅️ Back to Home</a>
</div>

<?php if (!empty($message)): ?>
    <div class="message-box" style="background-color: #28a745; margin-bottom: 15px; padding: 10px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- === Electricity Cost Form === -->
<form method="POST" style="max-width: 400px; margin-bottom: 30px;">
    <label for="electricity_cost"><strong>Average Electricity Cost (EUR/kW):</strong></label>
    <input type="number" step="0.0001" name="electricity_cost" id="electricity_cost" 
           value="<?= htmlspecialchars($currentCost) ?>" required 
           style="width: 100%; margin-top: 5px; margin-bottom: 10px;">
    <button type="submit" class="btn">💾 Save Cost</button>
</form>

<!-- === Remove All Favorites Form === -->
<form method="POST" action="delete_favorites.php" 
      onsubmit="return confirm('Are you sure you want to remove ALL favorites? This cannot be undone.');">
    <button type="submit" class="btn danger">❌ Remove All Favorites</button>
</form>

</body>
</html>