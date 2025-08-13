<?php
require 'db.php';

$id = $_POST['id'] ?? null;
$category = trim($_POST['category'] ?? '');

if ($id && $category !== '') {
    // Aggiorna la categoria
    $stmt = $conn->prepare("UPDATE trips SET category = ? WHERE id = ?");
    $stmt->bind_param("si", $category, $id);
    $stmt->execute();

    // Recupera la lista aggiornata delle categorie distinte
    $result = $conn->query("SELECT DISTINCT category FROM trips ORDER BY category ASC");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }

    // Risposta JSON con tutte le categorie
    header('Content-Type: application/json');
    echo json_encode($categories);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
}