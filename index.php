<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Trip Data Logger</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
  <img src="assets/tdl.png" alt="App Logo" style="width:50px;height:auto" />
  <h1>Trip Data Logger</h1>
</div>

<nav style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px">
  <a href="import.php" class="nav-link">📥 Import Trips CSV</a>
  <a href="stats.php" class="nav-link">📊 Statistics</a>
  <a href="statsbuilder.php" class="nav-link">🛠️ Stats Builder</a>
  <a href="maps.php" class="nav-link">🗺️ Trips Map</a>
  <a href="settings.php" class="nav-link">⚙️ Settings</a>
</nav>

<div class="filter-bar">
  <form id="filterForm">
    <label for="from">From:</label>
    <input type="datetime-local" name="from" id="from" />
    <label for="to">To:</label>
    <input type="datetime-local" name="to" id="to" />
    <label for="category">Category:</label>
    <select name="category" id="category">
      <option value="">All</option>
      <?php
        $catResult = $conn->query("SELECT DISTINCT category FROM trips ORDER BY category ASC");
        while ($catRow = $catResult->fetch_assoc()) {
            $cat = htmlspecialchars($catRow['category']);
            echo "<option value=\"{$cat}\">{$cat}</option>";
        }
      ?>
    </select>
    <label>
      <input type="checkbox" name="onlyFavorites" id="onlyFavorites" value="1" /> Only Favorites
    </label>
    <button type="button" id="clearFilters" class="btn danger">Clear Filters</button>
  </form>
</div>

<div id="tableContainer" class="table-container"></div>

<div class="table-footer">
  <label for="rowLimit">Show rows:</label>
  <select id="rowLimit">
    <option value="10">10</option>
    <option value="20" selected>20</option>
    <option value="50">50</option>
    <option value="100">100</option>
  </select>
</div>

<div id="pagination" class="pagination"></div>

<script>
let currentPage = 1;
function fetchTrips(page) {
  if (page) currentPage = page;
  const params = new URLSearchParams();
  const from = document.getElementById("from").value;
  const to = document.getElementById("to").value;
  const category = document.getElementById("category").value;
  const onlyFav = document.getElementById("onlyFavorites").checked;
  const limit = document.getElementById("rowLimit").value;
  if (from) params.append("from", from);
  if (to) params.append("to", to);
  if (category) params.append("category", category);
  if (onlyFav) params.append("onlyFavorites", 1);
  params.append("limit", limit);
  params.append("page", currentPage);
  fetch("ajax_load_table.php?" + params.toString())
    .then(res => res.text())
    .then(html => {
      document.getElementById("tableContainer").innerHTML = html;
      setupHandlers();
      renderPaginationFromData();
    });
}
function setupHandlers() {
  document.querySelectorAll(".clickable-row").forEach(row => {
    row.addEventListener("click", () => {
      const idx = row.dataset.index;
      const detail = document.querySelector(`.details-row[data-index="${idx}"]`);
      const icon = row.querySelector(".toggle-icon");
      const isVisible = detail.style.display === "table-row";
      document.querySelectorAll(".details-row").forEach(d => d.style.display = "none");
      document.querySelectorAll(".toggle-icon").forEach(i => i.textContent = "▶");
      if (!isVisible) {
        detail.style.display = "table-row";
        icon.textContent = "▼";
      }
    });
  });
  document.querySelectorAll(".favorite-icon").forEach(icon => {
    icon.addEventListener("click", (e) => {
      e.stopPropagation();
      const id = icon.dataset.id;
      fetch("toggle_favorite.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${encodeURIComponent(id)}`
      })
      .then(r => r.text())
      .then(newState => {
        icon.textContent = newState === "1" ? "★" : "☆";
      });
    });
  });
  document.querySelectorAll(".edit-icon").forEach(icon => {
    icon.addEventListener("click", (e) => {
      e.stopPropagation();
      const td = icon.closest("td");
      const label = td.querySelector(".category-label");
      const input = td.querySelector(".category-input");
      label.style.display = "none";
      input.style.display = "inline-block";
      input.focus();
    });
  });
  document.querySelectorAll(".category-input").forEach(input => {
    input.addEventListener("blur", saveCategory);
    input.addEventListener("keydown", e => {
      if (e.key === "Enter") {
        e.preventDefault();
        input.blur();
      }
    });
  });
  function saveCategory(e) {
    const input = e.target;
    const id = input.dataset.id;
    const value = input.value.trim();
    const label = input.closest("td").querySelector(".category-label");
    fetch("update_category.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `id=${encodeURIComponent(id)}&category=${encodeURIComponent(value)}`
    })
    .then(() => {
      label.textContent = value || "";
      label.style.display = "inline";
      input.style.display = "none";
    });
  }
  document.querySelectorAll(".cost-edit-icon").forEach(icon => {
    icon.addEventListener("click", (e) => {
      e.stopPropagation();
      const td = icon.closest("td");
      const label = td.querySelector(".cost-label");
      const input = td.querySelector(".cost-input");
      label.style.display = "none";
      input.style.display = "inline-block";
      input.focus();
    });
  });
  document.querySelectorAll(".cost-input").forEach(input => {
    input.addEventListener("blur", saveCost);
    input.addEventListener("keydown", e => {
      if (e.key === "Enter") {
        e.preventDefault();
        input.blur();
      }
    });
  });
  function saveCost(e) {
    const input = e.target;
    const id = input.dataset.id;
    const value = input.value.trim();
    const label = input.closest("td").querySelector(".cost-label");
    fetch("update_cost.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `id=${encodeURIComponent(id)}&cost_per_kw=${encodeURIComponent(value)}`
    })
    .then(() => {
      label.textContent = value || "";
      label.style.display = "inline";
      input.style.display = "none";
    });
  }
  document.querySelectorAll(".note-edit-icon").forEach(icon => {
    icon.addEventListener("click", (e) => {
      e.stopPropagation();
      const td = icon.closest("td");
      const label = td.querySelector(".note-label");
      const textarea = td.querySelector(".note-input");
      label.style.display = "none";
      textarea.style.display = "block";
      textarea.focus();
    });
  });
  document.querySelectorAll(".note-input").forEach(input => {
    input.addEventListener("blur", saveNote);
    input.addEventListener("keydown", e => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        input.blur();
      }
    });
  });
  function saveNote(e) {
    const input = e.target;
    const id = input.dataset.id;
    const value = input.value.trim();
    const label = input.closest("td").querySelector(".note-label");
    const status = input.closest("td").querySelector(".note-status");
    fetch("update_note.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `id=${encodeURIComponent(id)}&note=${encodeURIComponent(value)}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        label.textContent = value || "";
        label.style.display = "inline";
        input.style.display = "none";
        status.textContent = "✅ Saved";
        status.style.color = "green";
      } else {
        status.textContent = "❌ Error";
        status.style.color = "red";
      }
      setTimeout(() => status.textContent = "", 2500);
    })
    .catch(() => {
      status.textContent = "❌ Server error";
      status.style.color = "red";
      setTimeout(() => status.textContent = "", 2500);
    });
  }
}
function renderPaginationFromData() {
  const dataEl = document.getElementById("paginationData");
  const container = document.getElementById("pagination");
  if (!dataEl) { container.innerHTML = ""; return; }
  const totalPages = parseInt(dataEl.dataset.totalPages || "1", 10);
  const current = parseInt(dataEl.dataset.currentPage || "1", 10);
  if (totalPages <= 1) { container.innerHTML = ""; return; }
  container.innerHTML = "";
  const makeBtn = (label, page, disabled = false, active = false) => {
    const a = document.createElement("button");
    a.type = "button";
    a.className = "page-btn" + (active ? " active" : "");
    a.textContent = label;
    if (disabled) a.setAttribute("disabled", "disabled");
    else a.addEventListener("click", () => fetchTrips(page));
    return a;
  };
  container.appendChild(makeBtn("« First", 1, current === 1));
  container.appendChild(makeBtn("‹ Prev", Math.max(1, current - 1), current === 1));
  const windowSize = 2;
  const addEllipsis = () => {
    const span = document.createElement("span");
    span.className = "page-ellipsis";
    span.textContent = "…";
    container.appendChild(span);
  };
  const addPage = (p) => { container.appendChild(makeBtn(String(p), p, false, p === current)); };
  if (totalPages <= 7) {
    for (let p = 1; p <= totalPages; p++) addPage(p);
  } else {
    const start = Math.max(2, current - windowSize);
    const end = Math.min(totalPages - 1, current + windowSize);
    addPage(1);
    if (start > 2) addEllipsis();
    for (let p = start; p <= end; p++) addPage(p);
    if (end < totalPages - 1) addEllipsis();
    addPage(totalPages);
  }
  container.appendChild(makeBtn("Next ›", Math.min(totalPages, current + 1), current === totalPages));
  container.appendChild(makeBtn("Last »", totalPages, current === totalPages));
}
["from", "to", "category", "onlyFavorites", "rowLimit"].forEach(id =>
  document.getElementById(id).addEventListener("change", () => {
    currentPage = 1;
    fetchTrips(1);
  })
);
document.getElementById("clearFilters").addEventListener("click", () => {
  document.getElementById("from").value = "";
  document.getElementById("to").value = "";
  document.getElementById("category").value = "";
  document.getElementById("onlyFavorites").checked = false;
  currentPage = 1;
  fetchTrips(1);
});
fetchTrips(1);
</script>

</body>
</html>