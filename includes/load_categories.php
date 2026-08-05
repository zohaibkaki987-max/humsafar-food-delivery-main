<?php
require_once __DIR__ . '/config.php';

$sql = "SELECT * FROM categories WHERE status=1 ORDER BY id ASC";
$result = $conn->query($sql);
?>