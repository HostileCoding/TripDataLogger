<?php
require 'db.php';
$id = $_POST['id'];
$note = trim($_POST['note']);
$stmt = $conn->prepare("UPDATE trips SET user_note = ? WHERE id = ?");
$stmt->bind_param("si", $note, $id);
$stmt->execute();
echo "Note saved";
?>