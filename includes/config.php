<?php

$host = "localhost";
$dbname = "humsafar";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Safe additive customer features integration.
require_once __DIR__ . '/customer-feature-injector.php';
// Customer cancellation UI guard. Server-side enforcement is in cancel_order.php.
require_once __DIR__ . '/customer-cancellation-injector.php';

?>