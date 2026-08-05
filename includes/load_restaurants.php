<?php
require_once __DIR__ . '/config.php';

$sql = "SELECT * FROM restaurants WHERE status=1 ORDER BY rating DESC";
$result = $conn->query($sql);
?>