<?php
require 'db.php';

if (isset($_POST['id']) && isset($_POST['cost_per_kw'])) {
    $id = intval($_POST['id']);
    $cost = floatval($_POST['cost_per_kw']);

    $stmt = $conn->prepare("UPDATE trips SET cost_per_kw = ? WHERE id = ?");
    $stmt->bind_param("di", $cost, $id);
    $stmt->execute();

    echo json_encode(["success" => true, "cost_per_kw" => $cost]);
} else {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
}