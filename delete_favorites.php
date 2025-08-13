<?php
require 'db.php';

// Delete all favorites from the DB
$conn->query("UPDATE trips SET is_favorite = 0");

// Redirect back to settings with confirmation flag
header("Location: settings.php?favorites_cleared=1");
exit;