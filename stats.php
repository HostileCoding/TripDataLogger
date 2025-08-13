<?php
require 'db.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Statistics</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .charts-grid{display:grid;grid-template-columns:1fr;gap:20px;align-items:stretch}
    @media (min-width:700px){.charts-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (min-width:1100px){.charts-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    .chart-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.04);min-height:280px}
    .chart-card canvas{width:100% !important;height:100% !important;display:block}
    #totals{display:grid !important;grid-template-columns:1fr;gap:8px;margin-bottom:20px}
    @media (min-width:700px){#totals{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media (min-width:1100px){#totals{grid-template-columns:repeat(6,minmax(0,1fr))}}
  </style>
</head>
<body>
<h1>📊 Statistics</h1>
<div style="margin-bottom: 15px;">
  <a href="index.php" class="nav-link">⬅️ Back to Home</a>
</div>
<div class="filter-bar" style="margin-bottom: 20px;">
  <label>From:</label>
  <input type="datetime-local" id="fromDate" />
  <label>To:</label>
  <input type="datetime-local" id="toDate" />
  <label style="margin-left: 10px;">
    <input type="checkbox" id="onlyFavorites" /> Show only favorites
  </label>
  <button id="clearBtn" class="btn danger" style="margin-left: 10px;">Clear Filters</button>
</div>
<div class="warning-box">
  ⚠️ <strong>Note:</strong> By default, the latest <strong>20 trips</strong> are displayed in the charts and statistics. Use the filters above to customize which trips are included.
</div>
<div id="totals" class="totals-box">
  <div><strong>Current Odometer:</strong> <span id="currentOdometer">0.0</span> km</div>
  <div><strong>Total Duration:</strong> <span id="totalDuration">0</span> min</div>
  <div><strong>Total Distance:</strong> <span id="totalDistance">0</span> km</div>
  <div><strong>Total Battery:</strong> <span id="totalBattery">0</span> kW</div>
  <div><strong>Avg Consumption:</strong> <span id="avgConsumption">0.00</span> kWh/100km</div>
  <div><strong>Avg Speed:</strong> <span id="avgSpeed">0.0</span> km/h</div>
</div>
<div class="charts-grid">
  <div class="chart-card"><canvas id="chart"></canvas></div>
  <div class="chart-card"><canvas id="chartConsumption"></canvas></div>
  <div class="chart-card"><canvas id="scatterChart"></canvas></div>
  <div class="chart-card"><canvas id="radarChart"></canvas></div>
  <div class="chart-card"><canvas id="tripCostChart"></canvas></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartBattery, chartConsumption, chartScatter, chartRadar, chartCost;
async function fetchData() {
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const onlyFav = document.getElementById('onlyFavorites').checked ? 1 : 0;
  const params = new URLSearchParams({ from, to, onlyFav });
  const response = await fetch('stats_data.php?' + params.toString());
  return await response.json();
}
async function updateStats() {
  const data = await fetchData();
  let trips = data.trips;
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const onlyFav = document.getElementById('onlyFavorites').checked;
  const filtersActive = from || to || onlyFav;
  if (!filtersActive && trips.length > 20) { trips = trips.slice(0, 20); }
  const totalDuration = trips.reduce((s,t)=>s+parseFloat(t.duration_min),0);
  const totalDistance = trips.reduce((s,t)=>s+parseFloat(t.distance_km),0);
  const totalBattery = trips.reduce((s,t)=>s+parseFloat(t.battery_consumed_kwh),0);
  const avgConsumption = totalDistance>0?(totalBattery/totalDistance*100):0;
  const avgSpeed = totalDuration>0?(totalDistance/(totalDuration/60)):0;
  document.getElementById("currentOdometer").textContent = parseFloat(data.current_km).toFixed(1);
  document.getElementById("totalDuration").textContent = totalDuration.toFixed(0);
  document.getElementById("totalDistance").textContent = totalDistance.toFixed(1);
  document.getElementById("totalBattery").textContent = totalBattery.toFixed(2);
  document.getElementById("avgConsumption").textContent = avgConsumption.toFixed(2);
  document.getElementById("avgSpeed").textContent = avgSpeed.toFixed(1);
  const tripsOrdered = [...trips].reverse();
  const labels = tripsOrdered.map(r=>r.started_at);
  const batteryData = tripsOrdered.map(r=>parseFloat(r.battery_consumed_kwh));
  const consumptionData = tripsOrdered.map(r=>parseFloat(r.average_consumption));
  const tripCostData = tripsOrdered.map(r=>(parseFloat(r.cost_per_kw)*parseFloat(r.battery_consumed_kwh)).toFixed(2));
  const scatterPoints = tripsOrdered.map(trip=>({x:new Date(trip.started_at).toLocaleDateString(),y:parseFloat(trip.average_consumption)-avgConsumption,backgroundColor:parseFloat(trip.average_consumption)<avgConsumption?'#28a745':'#dc3545'}));
  const radarLabels = tripsOrdered.map(r=>new Date(r.started_at).toLocaleDateString());
  const radarData = tripsOrdered.map(r=>parseFloat(r.avg_speed_kmh));
  [chartBattery, chartConsumption, chartScatter, chartRadar, chartCost].forEach(ch=>{ if(ch) ch.destroy(); });
  const commonOpts = { responsive:true, maintainAspectRatio:false, animation:false };
  const softGrid = { color:'rgba(0,0,0,0.06)' };
  chartBattery = new Chart(document.getElementById('chart').getContext('2d'), {
    type:'bar',
    data:{ labels, datasets:[{ label:'Battery Usage (kW)', data:batteryData, backgroundColor:'#0077cc', borderWidth:0 }] },
    options:{ ...commonOpts, scales:{ x:{ grid:softGrid, border:{ display:false } }, y:{ grid:softGrid, border:{ display:false }, beginAtZero:true } } }
  });
  chartConsumption = new Chart(document.getElementById('chartConsumption').getContext('2d'), {
    type:'line',
    data:{ labels, datasets:[{ label:'Consumption (kWh/100km)', data:consumptionData, borderColor:'#28a745', borderWidth:2, fill:false, tension:0.3 }] },
    options:{ ...commonOpts, plugins:{ title:{ display:true, text:'Average Consumption per Trip' } }, scales:{ x:{ grid:softGrid }, y:{ grid:softGrid, beginAtZero:true } } }
  });
  chartScatter = new Chart(document.getElementById('scatterChart').getContext('2d'), {
    type:'scatter',
    data:{ datasets:[{ label:'Deviation from Avg Consumption', data:scatterPoints, parsing:{ xAxisKey:'x', yAxisKey:'y' }, pointBackgroundColor:scatterPoints.map(p=>p.backgroundColor), pointRadius:5 }] },
    options:{ ...commonOpts, plugins:{ title:{ display:true, text:'Deviation from Average Consumption (kWh/100km)' }, tooltip:{ callbacks:{ label:ctx=>`${ctx.parsed.y.toFixed(2)} kWh/100km deviation` } } }, scales:{ x:{ type:'category', title:{ display:true, text:'Trip Date' }, grid:softGrid }, y:{ title:{ display:true, text:'Deviation (kWh/100km)' }, beginAtZero:false, grid:softGrid } } }
  });
  const topSpeeds = [...radarData].map((v,i)=>({value:v,index:i})).sort((a,b)=>b.value-a.value).slice(0,3).map(item=>item.index);
  chartRadar = new Chart(document.getElementById('radarChart').getContext('2d'), {
    type:'radar',
    data:{ labels:radarLabels, datasets:[{ label:'Avg Speed (km/h)', data:radarData, backgroundColor:'rgba(0, 123, 255, 0.2)', borderColor:'#007bff', pointBackgroundColor:radarData.map((_,i)=>topSpeeds.includes(i)?'#ff6600':'#007bff'), pointRadius:radarData.map((_,i)=>topSpeeds.includes(i)?6:3) }] },
    options:commonOpts
  });
  chartCost = new Chart(document.getElementById('tripCostChart').getContext('2d'), {
    type:'line',
    data:{ labels, datasets:[{ label:'Trip Cost (EUR)', data:tripCostData, borderColor:'#ff9900', borderWidth:2, fill:false, tension:0.3 }] },
    options:{ ...commonOpts, plugins:{ title:{ display:true, text:'Average Cost per Trip' } }, scales:{ x:{ grid:softGrid, border:{ display:false } }, y:{ grid:softGrid, border:{ display:false }, beginAtZero:true } } }
  });
}
document.getElementById('fromDate').addEventListener('change', updateStats);
document.getElementById('toDate').addEventListener('change', updateStats);
document.getElementById('onlyFavorites').addEventListener('change', updateStats);
document.getElementById('clearBtn').addEventListener('click', ()=>{ document.getElementById('fromDate').value=''; document.getElementById('toDate').value=''; document.getElementById('onlyFavorites').checked=false; updateStats(); });
updateStats();
</script>
</body>
</html>