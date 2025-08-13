<?php
require 'db.php';
header('Content-Type: application/json');

if (!isset($_POST['id']) || !isset($_POST['note'])) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$id = intval($_POST['id']);
$note = trim($_POST['note']);

$stmt = $conn->prepare("UPDATE trips SET user_note = ? WHERE id = ?");
$stmt->bind_param("si", $note, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}