// === Gestione eventi tabella principale ===
function setupHandlers() {
  // === Espansione righe ===
  document.querySelectorAll(".clickable-row").forEach(r => {
    r.addEventListener("click", () => {
      const idx = r.dataset.index;
      const det = document.querySelector(`.details-row[data-index="${idx}"]`);
      const icon = r.querySelector(".toggle-icon");
      const isVisible = det.style.display === "table-row";

      document.querySelectorAll(".details-row").forEach(d => d.style.display = "none");
      document.querySelectorAll(".toggle-icon").forEach(i => i.textContent = "▶");

      det.style.display = isVisible ? "none" : "table-row";
      icon.textContent = isVisible ? "▶" : "▼";
    });
  });

  // === Blocca propagazione click sui controlli interattivi ===
  document.querySelectorAll(".edit-icon, .cost-edit-icon, .favorite-icon, .note-edit-icon").forEach(icon => {
    icon.addEventListener("click", e => e.stopPropagation());
  });

  // === Categoria ===
  document.querySelectorAll(".edit-icon").forEach(icon => {
    icon.addEventListener("click", () => {
      const td = icon.closest("td");
      td.querySelector(".category-label").style.display = "none";
      const input = td.querySelector(".category-input");
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

  // === Costo per kW ===
  document.querySelectorAll(".cost-edit-icon").forEach(icon => {
    icon.addEventListener("click", () => {
      const td = icon.closest("td");
      td.querySelector(".cost-label").style.display = "none";
      const input = td.querySelector(".cost-input");
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

  // === Preferiti ===
  document.querySelectorAll(".favorite-icon").forEach(icon => {
    icon.addEventListener("click", () => {
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

  // === Note ===
  document.querySelectorAll(".note-edit-icon").forEach(icon => {
    icon.addEventListener("click", e => {
      e.stopPropagation();
      const td = icon.closest("td");
      td.querySelector(".note-label").style.display = "none";
      const textarea = td.querySelector(".note-input");
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
}

// === Salvataggio categoria ===
function saveCategory(e) {
  const input = e.target;
  const id = input.dataset.id;
  const value = input.value.trim();
  const td = input.closest("td");
  const label = td.querySelector(".category-label");

  fetch("update_category.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `id=${encodeURIComponent(id)}&category=${encodeURIComponent(value)}`
  })
  .then(r => r.json())
  .then(() => {
    label.textContent = value;
    label.style.display = "inline";
    input.style.display = "none";
  });
}

// === Salvataggio costo ===
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
  .then(r => r.json())
  .then(() => {
    label.textContent = value;
    label.style.display = "inline";
    input.style.display = "none";
  });
}

// === Salvataggio note ===
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
      label.textContent = value;
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