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

// Rider Available Orders must follow the same online rule as the rider sidebar:
// a rider is ONLINE only while a booked availability session is currently active.
// Offline riders must not see available orders and cannot accept one by POST either.
if (basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'rider-orders.php' && !empty($_SESSION['rider_id'])) {
    $riderOrdersId = (int)$_SESSION['rider_id'];
    $riderOrdersOnline = false;
    $riderOrdersApproved = false;

    $roStatus = $conn->prepare("SELECT status FROM riders WHERE id=? LIMIT 1");
    if ($roStatus) {
        $roStatus->bind_param('i', $riderOrdersId);
        $roStatus->execute();
        $roStatusRow = $roStatus->get_result()->fetch_assoc();
        $roStatus->close();
        $riderOrdersApproved = in_array(strtolower(trim((string)($roStatusRow['status'] ?? ''))), ['active','approved'], true);
    }

    if ($riderOrdersApproved) {
        $roSession = $conn->prepare("SELECT id FROM rider_availability WHERE rider_id=? AND available_date=CURDATE() AND start_time<=CURTIME() AND (end_time>CURTIME() OR end_time='00:00:00') LIMIT 1");
        if ($roSession) {
            $roSession->bind_param('i', $riderOrdersId);
            $roSession->execute();
            $riderOrdersOnline = $roSession->get_result()->num_rows > 0;
            $roSession->close();
        }
    }

    // Block direct/manual attempts to accept an order while offline.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_action']) && (string)$_POST['delivery_action'] === 'accept' && !$riderOrdersOnline) {
        header('Location: rider-orders.php');
        exit;
    }

    if (!$riderOrdersOnline) {
        ob_start(function ($html) {
            // Remove the complete New Deliveries section while offline.
            $html = preg_replace('/<section class="section"><h2>New Deliveries<\/h2>.*?<\/section><section class="section"><h2>Active Deliveries<\/h2>/s', '<section class="section"><h2>New Deliveries</h2><div class="empty">You are offline. Go online during an active booked session to see available orders.</div></section><section class="section"><h2>Active Deliveries</h2>', $html, 1);
            // The New Deliveries counter should also be zero while offline.
            $html = preg_replace('/(<div class="stat"><small>New Deliveries<\/small><strong>)\d+(<\/strong>)/', '$10$2', $html, 1);
            return $html;
        });
    }
}

?>