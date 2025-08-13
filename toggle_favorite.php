<?php
require 'db.php';
$id = $_POST['id'] ?? null;
if ($id) {
  $res = $conn->query("SELECT is_favorite FROM trips WHERE id = $id");
  $row = $res->fetch_assoc();
  $new = $row['is_favorite'] ? 0 : 1;
  $conn->query("UPDATE trips SET is_favorite = $new WHERE id = $id");
  echo $new;
}