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

// Safe additive integration for Customer Favorites, Reviews, Reorder and Notifications.
// The injector is read-only until a customer action is clicked and is disabled automatically
// on admin, restaurant-owner, rider and delivery routes.
require_once __DIR__ . '/customer-feature-injector.php';

?>