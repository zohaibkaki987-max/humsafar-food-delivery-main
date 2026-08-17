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

// The existing Admin page uses the old setting key internally for compatibility,
// but the customer-facing meaning is now a fixed fee per order, not a KM rate.
if (basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'business-management.php') {
    ob_start(function ($html) {
        $html = str_replace('Delivery fee per KM and rider payout are controlled here by Admin and are applied system-wide to all restaurants and deliveries.', 'One fixed delivery fee and rider payout are controlled here by Admin and applied system-wide to all restaurants and deliveries.', $html);
        $html = str_replace('Admin sets one delivery rate per started KM. This is the <b>global rate for every restaurant</b>.', 'Admin sets one fixed delivery fee. The same amount is automatically applied to every restaurant and shown to every customer.', $html);
        $html = str_replace('Delivery Fee per KM (PKR)', 'Delivery Fee per Order (PKR)', $html);
        $html = str_replace('Current:</b> <?=$rate?> PKR per started KM<br><small>Example: 3.2 KM = 4 KM × <?=$rate?> PKR.</small>', 'Current:</b> <?=$rate?> PKR fixed delivery fee per order.<br><small>No KM calculation. This amount is synchronized to all restaurants.</small>', $html);
        $html = str_replace('<b>Delivery fee</b> = CEIL(distance in KM) × Admin Global Delivery Fee/KM', '<b>Delivery fee</b> = Admin Global Fixed Delivery Fee (same for every restaurant)', $html);
        $html = str_replace('<b>Customer total</b> = Marked-up items + Delivery fee − Coupon discount', '<b>Customer total</b> = Marked-up items + Fixed Delivery Fee − Coupon discount', $html);
        return $html;
    });
}

?>