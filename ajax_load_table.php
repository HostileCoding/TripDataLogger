<?php
require 'db.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$category = $_GET['category'] ?? '';
$onlyFav = isset($_GET['onlyFavorites']) && $_GET['onlyFavorites'] == '1';

$limit = intval($_GET['limit'] ?? 20);
if ($limit <= 0) $limit = 20;

$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

/**
 * IMPORTANT CHANGE:
 * - No default date window. We only filter by date if From/To are provided.
 */
$conditions = [];
$params = [];
$types = "";

// Date filters (optional)
if (!empty($from)) {
    $from_sql = date('Y-m-d H:i:s', strtotime($from));
    $conditions[] = "started_at >= ?";
    $params[] = $from_sql;
    $types .= "s";
}
if (!empty($to)) {
    $to_sql = date('Y-m-d H:i:s', strtotime($to));
    $conditions[] = "started_at <= ?";
    $params[] = $to_sql;
    $types .= "s";
}

// Other filters
if (!empty($category)) {
    $conditions[] = "category LIKE ?";
    $params[] = "%$category%";
    $types .= "s";
}
if ($onlyFav) {
    $conditions[] = "is_favorite = 1";
}

$where = "";
if (!empty($conditions)) {
    $where = " WHERE " . implode(" AND ", $conditions);
}

/* ===== Total count ===== */
$countSql = "SELECT COUNT(*) AS cnt FROM trips" . $where;
$stmtCount = $conn->prepare($countSql);
if (!empty($types)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$totalRows = (int)($resCount->fetch_assoc()['cnt'] ?? 0);
$stmtCount->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

/* ===== Paged data ===== */
$dataSql = "SELECT * FROM trips" . $where . " ORDER BY started_at DESC LIMIT ? OFFSET ?";
$paramsData = $params;
$typesData = $types . "ii";
$paramsData[] = $limit;
$paramsData[] = $offset;

$stmt = $conn->prepare($dataSql);
$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Hidden pagination info for the front-end -->
<div id="paginationData"
     data-total-rows="<?= htmlspecialchars($totalRows) ?>"
     data-total-pages="<?= htmlspecialchars($totalPages) ?>"
     data-current-page="<?= htmlspecialchars($page) ?>"
     style="display:none;"></div>

<table id="tripTable">
  <thead>
    <tr>
      <th></th>
      <th>Date</th>
      <th>Category</th>
      <th>Duration</th>
      <th>Distance</th>
      <th>Battery</th>
      <th>Avg Speed</th>
      <th>Consumption</th>
      <th>Favorite</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $i => $row): ?>
      <tr class="clickable-row" data-index="<?= $i ?>" data-id="<?= $row['id'] ?>" data-category="<?= htmlspecialchars($row['category']) ?>" data-favorite="<?= $row['is_favorite'] ?>">
        <td class="toggle-icon">▶</td>
        <td><?= date('d/m/Y H:i', strtotime($row['started_at'])) ?></td>
        <td>
          <span class="category-label"><?= htmlspecialchars($row['category']) ?></span>
          <input type="text" class="category-input" data-id="<?= $row['id'] ?>" 
                 value="<?= htmlspecialchars($row['category']) ?>" style="display:none;" />
          <span class="edit-icon" title="Edit category">✏️</span>
        </td>
        <td><?= (int)$row['duration_min'] ?> min</td>
        <td><?= (float)$row['distance_km'] ?> km</td>
        <td><?= (float)$row['battery_consumed_kwh'] ?> kW</td>
        <td><?= (float)$row['avg_speed_kmh'] ?> km/h</td>
        <td><?= (float)$row['average_consumption'] ?> kWh/100km</td>
        <td><span class="favorite-icon" data-id="<?= $row['id'] ?>"><?= $row['is_favorite'] ? '★' : '☆' ?></span></td>
      </tr>

      <tr class="details-row" data-index="<?= $i ?>" style="display: none;">
        <td colspan="9">
          <div class="details-section">
            <strong>From:</strong> <?= htmlspecialchars($row['start_address']) ?> (<?= (float)$row['start_odometer'] ?> km)<br>
            <strong>To:</strong> <?= htmlspecialchars($row['end_address']) ?> (<?= (float)$row['end_odometer'] ?> km)<br>
            <strong>Started:</strong> <?= htmlspecialchars($row['started_at']) ?><br>
            <strong>Ended:</strong> <?= htmlspecialchars($row['ended_at']) ?><br>
            <strong>Distance:</strong> <?= (float)$row['distance_km'] ?> km<br>
            <strong>Duration:</strong> <?= (int)$row['duration_min'] ?> min<br>
            <strong>Battery:</strong> <?= (float)$row['battery_consumed_kwh'] ?> kW<br>
            <strong>Avg Speed:</strong> <?= (float)$row['avg_speed_kmh'] ?> km/h<br>
            <strong>Consumption:</strong> <?= (float)$row['average_consumption'] ?> kWh/100km<br>

            <strong>Cost per KW:</strong>
            <span class="cost-label"><?= htmlspecialchars($row['cost_per_kw']) ?></span>
            <input type="number" class="cost-input" data-id="<?= $row['id'] ?>"
                   value="<?= htmlspecialchars($row['cost_per_kw']) ?>" step="0.0001"
                   style="display:none; width:80px;" />
            <span class="cost-edit-icon" title="Edit cost per KW">✏️</span><br>
            
            <strong>Note:</strong>
            <span class="note-label"><?= htmlspecialchars($row['user_note']) ?></span>
            <textarea class="note-input" data-id="<?= $row['id'] ?>" maxlength="500"
                      style="display:none; width: 90%; height: 60px;"><?= htmlspecialchars($row['user_note']) ?></textarea>
            <span class="note-edit-icon" title="Edit note">✏️</span>
            <span class="note-status" id="status-<?= (int)$row['id'] ?>"></span>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>