<?php
require 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Custom Chart Builder</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
    }
    h1 {
      margin-bottom: 10px;
    }
    .container {
      display: flex;
      flex-direction: row;
      gap: 20px;
      flex-wrap: wrap;
    }
    .attributes, .drop-zone {
      flex: 1;
      border: 2px dashed #ccc;
      padding: 15px;
      min-height: 200px;
      border-radius: 8px;
    }
    .attributes {
      background-color: #f8f9fa;
    }
    .attribute {
      background-color: #007bff;
      color: #fff;
      padding: 8px;
      margin-bottom: 8px;
      border-radius: 4px;
      cursor: pointer;
      user-select: none;
      text-align: center;
    }
    .attribute:hover {
      background-color: #0056b3;
    }
    .drop-zone {
      background-color: #fdfdfd;
      transition: background-color 0.2s ease;
    }
    #chartContainer {
      margin-top: 20px;
    }
    select, button {
      margin-top: 10px;
      padding: 6px;
    }
    .filters {
      margin: 15px 0;
      padding: 10px;
      background-color: #f4f4f4;
      border-radius: 6px;
    }
    .filters label {
      margin-right: 8px;
    }
    #resetDropZone {
      background-color: #dc3545;
      color: #fff;
      border: none;
      padding: 6px 10px;
      margin-top: 10px;
      border-radius: 4px;
      cursor: pointer;
    }
    .selected-attr {
      background-color: #28a745;
      color: white;
      padding: 6px;
      margin: 5px 0;
      border-radius: 4px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .remove-btn {
      background: none;
      border: none;
      color: white;
      font-weight: bold;
      cursor: pointer;
      margin-left: 8px;
    }
  </style>
</head>
<body>

<h1>🛠️ Stats Builder</h1>

<!-- Back to Home button (same style as others) -->
<div>
    <a href="index.php" class="nav-link">⬅️ Back to Home</a>
</div>

<p>Tap on the attributes to add them to your chart. Select the chart type and click "Generate Chart".</p>

<!-- 🔽 Filters -->
<div class="filter-bar">
  <label for="fromDate">From:</label>
  <input type="datetime-local" id="fromDate">
  <label for="toDate">To:</label>
  <input type="datetime-local" id="toDate">
  <label style="margin-left: 10px;">
    <input type="checkbox" id="onlyFavorites"> Show only favorites
  </label>
  <button id="clearFilters" class="btn danger" style="margin-left: 10px;">Clear Filters</button>
</div>

<div class="container">
  <!-- Attributes List -->
  <div class="attributes">
    <h3>Available Attributes</h3>
    <div class="attribute" data-key="distance_km">Total Distance (km)</div>
    <div class="attribute" data-key="battery_consumed_kwh">Total Battery (kWh)</div>
    <div class="attribute" data-key="average_consumption">Average Consumption (kWh/100km)</div>
    <div class="attribute" data-key="avg_speed_kmh">Average Speed (km/h)</div>
    <div class="attribute" data-key="duration_min">Trip Duration (min)</div>
    <!-- ✅ NEW ATTRIBUTE -->
    <div class="attribute" data-key="cost_per_kw">Cost per KW (EUR)</div>
  </div>

  <!-- Drop zone -->
  <div class="drop-zone" id="dropZone">
    <h3>Selected Attributes</h3>
    <p style="font-size: 14px; color: #777;">Tap attributes to add them here.</p>
    <button id="resetDropZone">Reset Drop Zone</button>
  </div>
</div>

<!-- Chart Type -->
<div style="margin-top: 15px;">
  <label for="chartType">Chart Type:</label>
  <select id="chartType">
    <option value="bar">Bar</option>
    <option value="line">Line</option>
    <option value="scatter">Scatter</option>
  </select>
  <button id="generateBtn">Generate Chart</button>
</div>

<!-- Chart Canvas -->
<div id="chartContainer">
  <canvas id="customChart" width="800" height="400"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dropZone = document.getElementById('dropZone');

// === Add attributes to drop zone ===
document.querySelectorAll('.attribute').forEach(attr => {
  attr.addEventListener('click', () => {
    const key = attr.dataset.key;
    const label = attr.textContent;

    // Avoid duplicates
    if (dropZone.querySelector('[data-key="' + key + '"]')) return;

    // Add to drop zone
    const wrapper = document.createElement('div');
    wrapper.classList.add('selected-attr');
    wrapper.dataset.key = key;
    wrapper.innerHTML = `${label} <button class="remove-btn">&times;</button>`;

    wrapper.querySelector('.remove-btn').addEventListener('click', () => wrapper.remove());

    dropZone.appendChild(wrapper);
  });
});

// === Reset Drop Zone ===
function resetDropZone() {
  dropZone.innerHTML = `
    <h3>Selected Attributes</h3>
    <p style="font-size: 14px; color: #777;">Tap attributes to add them here.</p>
    <button id="resetDropZone">Reset Drop Zone</button>`;
  document.getElementById('resetDropZone').addEventListener('click', resetDropZone);
}
document.getElementById('resetDropZone').addEventListener('click', resetDropZone);

// === Chart.js ===
let customChart;

document.getElementById('generateBtn').addEventListener('click', async () => {
  const attributes = Array.from(dropZone.querySelectorAll('.selected-attr'))
    .map(el => el.dataset.key);

  if (attributes.length === 0) {
    alert('Please select at least one attribute.');
    return;
  }

  const chartType = document.getElementById('chartType').value;

  // Filters
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const onlyFav = document.getElementById('onlyFavorites').checked ? 1 : 0;

  const params = new URLSearchParams({ from, to, onlyFav });

  // Fetch data from DB
  const response = await fetch('stats_data.php?' + params.toString());
  const data = await response.json();
  const trips = [...data.trips].reverse();

  const labels = trips.map(t => t.started_at);

  // Build datasets dynamically
  const datasets = attributes.map(attr => ({
    label: attr,
    data: trips.map(t => parseFloat(t[attr])),
    borderColor: randomColor(),
    backgroundColor: randomColor(0.5),
    fill: chartType === 'line' ? false : true
  }));

  // Destroy old chart if exists
  if (customChart) customChart.destroy();

  // Create new chart
  customChart = new Chart(document.getElementById('customChart').getContext('2d'), {
    type: chartType,
    data: { labels, datasets },
    options: {
      responsive: true,
      plugins: { title: { display: true, text: 'Custom Chart' } },
      scales: chartType === 'scatter' ? {
        x: { type: 'category', title: { display: true, text: 'Trip Date' } },
        y: { title: { display: true, text: 'Value' } }
      } : {}
    }
  });
});

// === Clear Filters ===
document.getElementById('clearFilters').addEventListener('click', () => {
  document.getElementById('fromDate').value = '';
  document.getElementById('toDate').value = '';
  document.getElementById('onlyFavorites').checked = false;
});

// === Random Color Generator ===
function randomColor(alpha=1) {
  const r = Math.floor(Math.random() * 255);
  const g = Math.floor(Math.random() * 255);
  const b = Math.floor(Math.random() * 255);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
</script>

</body>
</html>