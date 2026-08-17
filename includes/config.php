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

// Global fixed delivery fee: whenever business_settings exists,
// keep every restaurant's delivery_fee synchronized to Admin's value.
// Customer and checkout pages already read restaurants.delivery_fee,
// so this removes any per-restaurant/per-KM customer delivery pricing.
$globalFeeTable = $conn->query("SHOW TABLES LIKE 'business_settings'");
if ($globalFeeTable && $globalFeeTable->num_rows > 0) {
    $globalFeeResult = $conn->query("SELECT setting_value FROM business_settings WHERE setting_key = 'delivery_fee_per_km' LIMIT 1");
    if ($globalFeeResult && ($globalFeeRow = $globalFeeResult->fetch_assoc())) {
        $globalDeliveryFee = (float)$globalFeeRow['setting_value'];
        $syncStmt = $conn->prepare("UPDATE restaurants SET delivery_fee = ? WHERE delivery_fee <> ? OR delivery_fee IS NULL");
        if ($syncStmt) {
            $syncStmt->bind_param('dd', $globalDeliveryFee, $globalDeliveryFee);
            $syncStmt->execute();
            $syncStmt->close();
        }
    }
}

?>