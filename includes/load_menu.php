<?php
require_once __DIR__ . '/config.php';

$restaurant_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM menu_items WHERE restaurant_id=? AND status=1");
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();

$menu = $stmt->get_result();
?>