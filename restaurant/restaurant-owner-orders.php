<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Database check
|--------------------------------------------------------------------------
*/
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

/*
|--------------------------------------------------------------------------
| Find logged-in restaurant owner
|--------------------------------------------------------------------------
*/
$owner = null;
$ownerId = 0;
$ownerEmail = '';

if (!empty($_SESSION['restaurant_owner_id'])) {
    $ownerId = (int)$_SESSION['restaurant_owner_id'];
}

if ($ownerId <= 0 && !empty($_SESSION['restaurant_user_id'])) {
    $ownerId = (int)$_SESSION['restaurant_user_id'];
}

if ($ownerId <= 0 && !empty($_SESSION['owner_id'])) {
    $ownerId = (int)$_SESSION['owner_id'];
}

if (!empty($_SESSION['restaurant_owner_email'])) {
    $ownerEmail = trim(
        (string)$_SESSION['restaurant_owner_email']
    );
}

if ($ownerEmail === '' && !empty($_SESSION['email'])) {
    $ownerEmail = trim(
        (string)$_SESSION['email']
    );
}

/*
|--------------------------------------------------------------------------
| Get owner by ID
|--------------------------------------------------------------------------
*/
if ($ownerId > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $ownerId);
        $stmt->execute();

        $result = $stmt->get_result();
        $owner = $result->fetch_assoc();

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Get owner by email if ID unavailable
|--------------------------------------------------------------------------
*/
if (!$owner && $ownerEmail !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status
        FROM restaurant_users
        WHERE email = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("s", $ownerEmail);
        $stmt->execute();

        $result = $stmt->get_result();
        $owner = $result->fetch_assoc();

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Owner not found
|--------------------------------------------------------------------------
*/
if (!$owner) {

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Owner data
|--------------------------------------------------------------------------
*/
$ownerId = (int)$owner['id'];

$restaurantName = trim(
    (string)$owner['restaurant_name']
);

$ownerName = trim(
    (string)$owner['full_name']
);

$ownerStatus = strtolower(
    trim(
        (string)$owner['status']
    )
);

/*
|--------------------------------------------------------------------------
| Find restaurant
|--------------------------------------------------------------------------
*/
$restaurant = null;
$restaurantId = 0;

if ($restaurantName !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM restaurants
        WHERE name = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $restaurantName
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $restaurant = $result->fetch_assoc();

        $stmt->close();
    }
}

if ($restaurant) {
    $restaurantId = (int)$restaurant['id'];
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/
$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Update order status
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_order'])
) {

    /*
    |--------------------------------------------------------------------------
    | Only approved owner can manage orders
    |--------------------------------------------------------------------------
    */
    if ($ownerStatus !== 'approved') {

        $errorMessage =
            'Your restaurant owner account is not approved yet.';

    } else {

        $orderId = isset($_POST['order_id'])
            ? (int)$_POST['order_id']
            : 0;

        $newStatus = isset($_POST['order_status'])
            ? trim((string)$_POST['order_status'])
            : '';

        $allowedStatuses = [
            'pending',
            'confirmed',
            'preparing',
            'out_for_delivery',
            'delivered',
            'cancelled'
        ];

        if ($orderId <= 0) {

            $errorMessage =
                'Invalid order selected.';

        } elseif (
            !in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {

            $errorMessage =
                'Invalid order status.';

        } elseif ($restaurantId <= 0) {

            $errorMessage =
                'Your restaurant record was not found.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Security:
            | Owner can update only his own restaurant orders
            |--------------------------------------------------------------------------
            */
            $stmt = $conn->prepare("
                UPDATE orders
                SET order_status = ?
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $errorMessage =
                    'Database error: ' .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    "sii",
                    $newStatus,
                    $orderId,
                    $restaurantId
                );

                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0) {

                        $successMessage =
                            'Order status updated successfully.';

                    } else {

                        $successMessage =
                            'Order status is already ' .
                            str_replace(
                                '_',
                                ' ',
                                $newStatus
                            ) .
                            '.';
                    }

                } else {

                    $errorMessage =
                        'Could not update order status.';
                }

                $stmt->close();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/
$orders = [];

if ($restaurantId > 0) {

    $stmt = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.restaurant_id,
            o.address_id,
            o.payment_method,
            o.subtotal,
            o.delivery_fee,
            o.discount,
            o.total,
            o.order_status,
            o.customer_note,
            o.created_at,

            u.full_name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone

        FROM orders o

        LEFT JOIN users u
            ON o.user_id = u.id

        WHERE o.restaurant_id = ?

        ORDER BY o.id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $restaurantId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $orders[] = $row;
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/
$orderItems = [];

foreach ($orders as $order) {

    $orderId = (int)$order['id'];

    $orderItems[$orderId] = [];

    $stmt = $conn->prepare("
        SELECT
            id,
            item_name,
            item_price,
            quantity,
            subtotal
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $orderId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($item = $result->fetch_assoc()) {

            $orderItems[$orderId][] = $item;
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Addresses
|--------------------------------------------------------------------------
*/
$orderAddresses = [];

foreach ($orders as $order) {

    $orderId = (int)$order['id'];

    $addressId = (int)$order['address_id'];

    $orderAddresses[$orderId] = null;

    if ($addressId <= 0) {
        continue;
    }

    $stmt = $conn->prepare("
        SELECT
            address_title,
            address_line,
            city,
            area,
            phone
        FROM customer_addresses
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $addressId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $orderAddresses[$orderId] =
            $result->fetch_assoc();

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalOrders = count($orders);

$pendingOrders = 0;
$confirmedOrders = 0;
$preparingOrders = 0;
$deliveryOrders = 0;
$completedOrders = 0;
$cancelledOrders = 0;

foreach ($orders as $order) {

    $status = strtolower(
        trim(
            (string)$order['order_status']
        )
    );

    if ($status === 'pending') {

        $pendingOrders++;

    } elseif (
        $status === 'confirmed' ||
        $status === 'accepted'
    ) {

        $confirmedOrders++;

    } elseif ($status === 'preparing') {

        $preparingOrders++;

    } elseif (
        $status === 'out_for_delivery' ||
        $status === 'on_the_way'
    ) {

        $deliveryOrders++;

    } elseif (
        $status === 'delivered' ||
        $status === 'completed'
    ) {

        $completedOrders++;

    } elseif (
        $status === 'cancelled' ||
        $status === 'canceled'
    ) {

        $cancelledOrders++;
    }
}

/*
|--------------------------------------------------------------------------
| Status helper
|--------------------------------------------------------------------------
*/
function statusLabel($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            strtolower(
                trim(
                    (string)$status
                )
            )
        )
    );
}

function statusClass($status)
{
    $status = strtolower(
        trim(
            (string)$status
        )
    );

    switch ($status) {

        case 'pending':
            return 'pending';

        case 'confirmed':
        case 'accepted':
            return 'confirmed';

        case 'preparing':
            return 'preparing';

        case 'out_for_delivery':
        case 'on_the_way':
            return 'delivery';

        case 'delivered':
        case 'completed':
            return 'completed';

        case 'cancelled':
        case 'canceled':
            return 'cancelled';

        default:
            return 'default';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Restaurant Orders - Humsafar
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #fff7fa;
    color: #292929;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 245px;
    height: 100vh;

    background: #ffffff;

    border-right: 1px solid #eeeeee;

    padding: 25px 16px;

    z-index: 1000;
}

.logo {

    font-size: 23px;

    font-weight: 900;

    color: #e00038;

    text-decoration: none;

    display: block;

    padding: 0 12px 28px;
}

.logo span {

    color: #292929;
}

.restaurant-label {

    font-size: 10px;

    color: #999999;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 0 12px 8px;
}

.restaurant-title {

    font-size: 14px;

    font-weight: 800;

    padding: 0 12px 22px;

    color: #333333;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.menu {

    display: flex;

    flex-direction: column;

    gap: 5px;
}

.menu a {

    text-decoration: none;

    color: #555555;

    padding: 12px 13px;

    border-radius: 10px;

    font-size: 13px;

    font-weight: 700;

    transition: .2s;
}

.menu a:hover {

    background: #fff0f3;

    color: #e00038;
}

.menu a.active {

    background: #e00038;

    color: #ffffff;
}

.menu a.disabled {

    color: #aaaaaa;

    cursor: not-allowed;

    background: #fafafa;
}

.sidebar-bottom {

    position: absolute;

    left: 16px;
    right: 16px;
    bottom: 20px;
}

.logout {

    display: block;

    text-align: center;

    text-decoration: none;

    color: #e00038;

    background: #fff0f3;

    padding: 12px;

    border-radius: 10px;

    font-size: 13px;

    font-weight: 800;
}

/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    min-height: 100vh;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 72px;

    background: #ffffff;

    border-bottom: 1px solid #eeeeee;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 35px;
}

.page-title {

    font-size: 20px;

    font-weight: 900;
}

.owner-area {

    display: flex;

    align-items: center;

    gap: 12px;
}

.owner-avatar {

    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #fff0f3;

    color: #e00038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 900;
}

.owner-name {

    font-size: 13px;

    font-weight: 800;
}

.owner-status {

    font-size: 10px;

    color: #18733e;

    margin-top: 3px;

    text-transform: uppercase;

    font-weight: 800;
}

/* =========================================================
   CONTENT
========================================================= */

.content {

    width: 94%;

    max-width: 1350px;

    margin: auto;

    padding: 30px 0 60px;
}

.heading {

    margin-bottom: 24px;
}

.heading h1 {

    margin: 0 0 6px;

    font-size: 30px;
}

.heading p {

    margin: 0;

    color: #777777;

    font-size: 13px;
}

/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 14px 17px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 13px;

    font-weight: 700;
}

.alert-success {

    background: #eaf8ef;

    color: #18733e;
}

.alert-error {

    background: #fff0f0;

    color: #c52323;
}

/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 13px;

    margin-bottom: 25px;
}

.stat {

    background: #ffffff;

    border: 1px solid #eee5e9;

    border-radius: 14px;

    padding: 18px;
}

.stat-number {

    font-size: 25px;

    font-weight: 900;

    color: #e00038;
}

.stat-label {

    color: #777777;

    font-size: 11px;

    margin-top: 6px;

    font-weight: 700;
}

/* =========================================================
   FILTER
========================================================= */

.toolbar {

    background: #ffffff;

    border: 1px solid #eee5e9;

    border-radius: 14px;

    padding: 16px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.search {

    width: 300px;

    border: 1px solid #dddddd;

    border-radius: 9px;

    padding: 11px 13px;

    outline: none;

    font-size: 13px;
}

.search:focus {

    border-color: #e00038;
}

.filter-select {

    border: 1px solid #dddddd;

    border-radius: 9px;

    padding: 11px 13px;

    background: #ffffff;

    outline: none;

    font-size: 13px;
}

/* =========================================================
   ORDER CARD
========================================================= */

.order-card {

    background: #ffffff;

    border: 1px solid #eee5e9;

    border-radius: 17px;

    margin-bottom: 20px;

    overflow: hidden;
}

.order-header {

    padding: 18px 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-bottom: 1px solid #eeeeee;

    gap: 15px;
}

.order-id {

    font-size: 17px;

    font-weight: 900;
}

.order-date {

    font-size: 11px;

    color: #888888;

    margin-top: 5px;
}

.status {

    display: inline-flex;

    padding: 8px 13px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 900;
}

.pending {

    background: #fff4d9;

    color: #956500;
}

.confirmed {

    background: #eaf3ff;

    color: #1769aa;
}

.preparing {

    background: #fff0df;

    color: #a85d00;
}

.delivery {

    background: #f0eaff;

    color: #6641a3;
}

.completed {

    background: #e7f8ed;

    color: #18733e;
}

.cancelled {

    background: #fff0f0;

    color: #c52323;
}

.default {

    background: #eeeeee;

    color: #777777;
}

/* =========================================================
   ORDER BODY
========================================================= */

.order-body {

    padding: 20px;
}

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 13px;

    margin-bottom: 20px;
}

.info-box {

    background: #faf8f9;

    border-radius: 11px;

    padding: 15px;
}

.info-title {

    font-size: 10px;

    color: #999999;

    text-transform: uppercase;

    letter-spacing: .5px;

    font-weight: 800;

    margin-bottom: 7px;
}

.info-main {

    font-size: 13px;

    font-weight: 800;

    line-height: 1.5;
}

.info-small {

    font-size: 11px;

    color: #777777;

    margin-top: 3px;

    line-height: 1.5;
}

/* =========================================================
   ITEMS
========================================================= */

.items {

    border: 1px solid #eeeeee;

    border-radius: 11px;

    overflow: hidden;

    margin-bottom: 20px;
}

.items-heading {

    padding: 12px 15px;

    background: #faf8f9;

    font-size: 11px;

    text-transform: uppercase;

    color: #777777;

    font-weight: 900;
}

.item {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 13px 15px;

    border-top: 1px solid #eeeeee;

    gap: 15px;
}

.item-name {

    font-size: 13px;

    font-weight: 800;
}

.item-meta {

    color: #888888;

    font-size: 11px;

    margin-top: 3px;
}

.item-total {

    color: #e00038;

    font-weight: 900;

    font-size: 13px;
}

/* =========================================================
   TOTAL
========================================================= */

.total-row {

    display: flex;

    justify-content: flex-end;

    margin-bottom: 20px;
}

.total-box {

    min-width: 250px;

    background: #fff0f3;

    border-radius: 12px;

    padding: 15px 18px;
}

.total-line {

    display: flex;

    justify-content: space-between;

    gap: 25px;

    font-size: 12px;

    color: #666666;

    margin-bottom: 6px;
}

.total-final {

    border-top: 1px solid #f1cfd8;

    padding-top: 9px;

    margin-top: 9px;

    display: flex;

    justify-content: space-between;

    font-size: 17px;

    font-weight: 900;

    color: #e00038;
}

/* =========================================================
   ACTIONS
========================================================= */

.order-actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    gap: 9px;

    flex-wrap: wrap;
}

.status-form {

    margin: 0;
}

.status-btn {

    border: 0;

    padding: 10px 14px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 11px;

    font-weight: 900;

    background: #f1f1f1;

    color: #444444;
}

.status-btn:hover {

    background: #e00038;

    color: #ffffff;
}

.status-btn.accept {

    background: #eaf3ff;

    color: #1769aa;
}

.status-btn.prepare {

    background: #fff0df;

    color: #a85d00;
}

.status-btn.delivery-btn {

    background: #f0eaff;

    color: #6641a3;
}

.status-btn.complete {

    background: #e7f8ed;

    color: #18733e;
}

.status-btn.cancel {

    background: #fff0f0;

    color: #c52323;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: #ffffff;

    border: 1px solid #eee5e9;

    border-radius: 17px;

    padding: 70px 25px;

    text-align: center;
}

.empty-icon {

    width: 65px;

    height: 65px;

    border-radius: 50%;

    background: #fff0f3;

    color: #e00038;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 17px;

    font-size: 27px;

    font-weight: 900;
}

.empty h3 {

    margin: 0 0 7px;

    font-size: 19px;
}

.empty p {

    margin: 0;

    color: #888888;

    font-size: 13px;
}

/* =========================================================
   PENDING LOCK
========================================================= */

.lock-box {

    background: #fffaf0;

    border: 1px solid #f2dfae;

    border-radius: 14px;

    padding: 18px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    gap: 15px;
}

.lock-icon {

    width: 42px;
    height: 42px;

    background: #fff0d0;

    color: #956500;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;
}

.lock-text strong {

    display: block;

    font-size: 13px;

    margin-bottom: 4px;
}

.lock-text span {

    color: #777777;

    font-size: 11px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .info-grid {

        grid-template-columns:
            1fr 1fr;
    }
}

@media (max-width: 800px) {

    .sidebar {

        width: 75px;

        padding: 20px 8px;
    }

    .logo {

        font-size: 0;

        text-align: center;
    }

    .logo::after {

        content: "H";

        font-size: 24px;

        color: #e00038;
    }

    .restaurant-label,
    .restaurant-title,
    .menu a span,
    .logout span {

        display: none;
    }

    .menu a {

        text-align: center;

        font-size: 18px;
    }

    .main {

        margin-left: 75px;
    }

    .topbar {

        padding: 0 18px;
    }

    .content {

        width: 92%;
    }

    .stats {

        grid-template-columns:
            1fr 1fr;
    }

    .toolbar {

        flex-direction: column;

        align-items: stretch;
    }

    .search {

        width: 100%;
    }
}

@media (max-width: 600px) {

    .info-grid {

        grid-template-columns: 1fr;
    }

    .stats {

        grid-template-columns: 1fr 1fr;
    }

    .order-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .order-actions {

        justify-content: flex-start;
    }

    .total-row {

        justify-content: stretch;
    }

    .total-box {

        width: 100%;
    }

    .owner-name {

        display: none;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <a
        href="restaurant-owner-dashboard.php"
        class="logo"
    >
        Humsafar <span>Food</span>
    </a>

    <div class="restaurant-label">
        Restaurant
    </div>

    <div
        class="restaurant-title"
        title="<?php echo h($restaurantName); ?>"
    >
        <?php
        echo h(
            $restaurantName !== ''
                ? $restaurantName
                : 'My Restaurant'
        );
        ?>
    </div>

    <nav class="menu">

        <a href="restaurant-owner-dashboard.php">
            <span>🏠 Dashboard</span>
        </a>

        <a href="restaurant-owner-manage.php">
            <span>🍽 Manage Restaurant</span>
        </a>

        <a href="restaurant-owner-menu.php">
            <span>📋 Menu Management</span>
        </a>

        <a
            href="restaurant-owner-orders.php"
            class="active"
        >
            <span>🛒 Orders</span>
        </a>

        <a href="restaurant-owner-settings.php">
            <span>⚙ Restaurant Settings</span>
        </a>

    </nav>

    <div class="sidebar-bottom">

        <a
            href="restaurant-owner-logout.php"
            class="logout"
        >
            <span>Logout</span>
        </a>

    </div>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <!-- TOPBAR -->

    <header class="topbar">

        <div class="page-title">
            Orders
        </div>

        <div class="owner-area">

            <div class="owner-avatar">
                <?php
                echo h(
                    strtoupper(
                        substr(
                            $ownerName !== ''
                                ? $ownerName
                                : 'O',
                            0,
                            1
                        )
                    )
                );
                ?>
            </div>

            <div>

                <div class="owner-name">
                    <?php
                    echo h(
                        $ownerName !== ''
                            ? $ownerName
                            : 'Restaurant Owner'
                    );
                    ?>
                </div>

                <div class="owner-status">
                    <?php
                    echo h($ownerStatus);
                    ?>
                </div>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">

        <div class="heading">

            <h1>
                Restaurant Orders
            </h1>

            <p>
                View and manage orders received by your restaurant.
            </p>

        </div>


        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success">

                <?php
                echo h($successMessage);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ''): ?>

            <div class="alert alert-error">

                <?php
                echo h($errorMessage);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($ownerStatus !== 'approved'): ?>

            <div class="lock-box">

                <div class="lock-icon">
                    🔒
                </div>

                <div class="lock-text">

                    <strong>
                        Restaurant Orders Are Locked
                    </strong>

                    <span>
                        Your account is currently
                        <?php echo h($ownerStatus); ?>.
                        Orders management will become available
                        after admin approval.
                    </span>

                </div>

            </div>

        <?php endif; ?>


        <!-- STATS -->

        <div class="stats">

            <div class="stat">

                <div class="stat-number">
                    <?php echo $totalOrders; ?>
                </div>

                <div class="stat-label">
                    Total Orders
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?php echo $pendingOrders; ?>
                </div>

                <div class="stat-label">
                    Pending
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?php echo $confirmedOrders; ?>
                </div>

                <div class="stat-label">
                    Confirmed
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?php echo $preparingOrders; ?>
                </div>

                <div class="stat-label">
                    Preparing
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?php echo $deliveryOrders; ?>
                </div>

                <div class="stat-label">
                    Out for Delivery
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?php echo $completedOrders; ?>
                </div>

                <div class="stat-label">
                    Completed
                </div>

            </div>

        </div>


        <!-- TOOLBAR -->

        <?php if ($ownerStatus === 'approved'): ?>

            <div class="toolbar">

                <input
                    type="text"
                    id="orderSearch"
                    class="search"
                    placeholder="Search order, customer or phone..."
                    onkeyup="filterOrders()"
                >

                <select
                    id="statusFilter"
                    class="filter-select"
                    onchange="filterOrders()"
                >

                    <option value="all">
                        All Orders
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="confirmed">
                        Confirmed
                    </option>

                    <option value="preparing">
                        Preparing
                    </option>

                    <option value="out_for_delivery">
                        Out for Delivery
                    </option>

                    <option value="delivered">
                        Delivered
                    </option>

                    <option value="cancelled">
                        Cancelled
                    </option>

                </select>

            </div>

        <?php endif; ?>


        <!-- ORDERS -->

        <?php if (empty($orders)): ?>

            <div class="empty">

                <div class="empty-icon">
                    🛒
                </div>

                <h3>
                    No Orders Yet
                </h3>

                <p>
                    When customers place orders
                    from your restaurant, they will
                    appear here.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($orders as $order): ?>

                <?php

                $orderId =
                    (int)$order['id'];

                $status =
                    strtolower(
                        trim(
                            (string)$order['order_status']
                        )
                    );

                $customerName =
                    $order['customer_name']
                    ?? 'Guest Customer';

                $customerEmail =
                    $order['customer_email']
                    ?? '';

                $customerPhone =
                    $order['customer_phone']
                    ?? '';

                $items =
                    $orderItems[$orderId]
                    ?? [];

                $address =
                    $orderAddresses[$orderId]
                    ?? null;

                ?>

                <article
                    class="order-card"
                    data-status="<?php echo h($status); ?>"
                    data-search="<?php
                        echo h(
                            strtolower(
                                $order['order_number']
                                . ' '
                                . $customerName
                                . ' '
                                . $customerPhone
                            )
                        );
                    ?>"
                >

                    <!-- ORDER HEADER -->

                    <div class="order-header">

                        <div>

                            <div class="order-id">

                                Order #
                                <?php

                                echo h(
                                    $order['order_number']
                                    ?: $orderId
                                );

                                ?>

                            </div>

                            <div class="order-date">

                                <?php

                                echo h(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    )
                                );

                                ?>

                            </div>

                        </div>


                        <div>

                            <span
                                class="status <?php
                                    echo h(
                                        statusClass(
                                            $status
                                        )
                                    );
                                ?>"
                            >

                                <?php

                                echo h(
                                    statusLabel(
                                        $status
                                    )
                                );

                                ?>

                            </span>

                        </div>

                    </div>


                    <!-- ORDER BODY -->

                    <div class="order-body">


                        <!-- CUSTOMER / PAYMENT / ADDRESS -->

                        <div class="info-grid">

                            <div class="info-box">

                                <div class="info-title">
                                    Customer
                                </div>

                                <div class="info-main">
                                    <?php
                                    echo h(
                                        $customerName
                                    );
                                    ?>
                                </div>

                                <?php if ($customerPhone !== ''): ?>

                                    <div class="info-small">

                                        📞
                                        <?php
                                        echo h(
                                            $customerPhone
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>


                                <?php if ($customerEmail !== ''): ?>

                                    <div class="info-small">

                                        ✉
                                        <?php
                                        echo h(
                                            $customerEmail
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="info-box">

                                <div class="info-title">
                                    Delivery Address
                                </div>

                                <?php if ($address): ?>

                                    <div class="info-main">

                                        <?php
                                        echo h(
                                            $address['address_title']
                                            ?? 'Delivery Address'
                                        );
                                        ?>

                                    </div>

                                    <div class="info-small">

                                        <?php
                                        echo h(
                                            $address['address_line']
                                            ?? ''
                                        );
                                        ?>

                                        <?php
                                        if (
                                            !empty(
                                                $address['area']
                                            )
                                        ) {
                                            echo ', ' .
                                                h(
                                                    $address['area']
                                                );
                                        }
                                        ?>

                                        <?php
                                        if (
                                            !empty(
                                                $address['city']
                                            )
                                        ) {
                                            echo ', ' .
                                                h(
                                                    $address['city']
                                                );
                                        }
                                        ?>

                                    </div>

                                <?php else: ?>

                                    <div class="info-main">
                                        Address unavailable
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="info-box">

                                <div class="info-title">
                                    Payment
                                </div>

                                <div class="info-main">

                                    <?php
                                    echo h(
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string)
                                                $order['payment_method']
                                            )
                                        )
                                    );
                                    ?>

                                </div>

                                <div class="info-small">

                                    Payment status:
                                    <?php
                                    echo h(
                                        ucfirst(
                                            (string)(
                                                $order['payment_status']
                                                ?? 'pending'
                                            )
                                        )
                                    );
                                    ?>

                                </div>

                            </div>

                        </div>


                        <!-- ITEMS -->

                        <div class="items">

                            <div class="items-heading">
                                Ordered Items
                            </div>


                            <?php if (empty($items)): ?>

                                <div class="item">

                                    <div class="item-name">
                                        No item information available.
                                    </div>

                                </div>

                            <?php else: ?>


                                <?php foreach ($items as $item): ?>

                                    <div class="item">

                                        <div>

                                            <div class="item-name">

                                                <?php
                                                echo h(
                                                    $item['item_name']
                                                );
                                                ?>

                                            </div>

                                            <div class="item-meta">

                                                <?php
                                                echo h(
                                                    $item['quantity']
                                                );
                                                ?>
                                                × Rs.
                                                <?php
                                                echo number_format(
                                                    (float)
                                                    $item['item_price'],
                                                    2
                                                );
                                                ?>

                                            </div>

                                        </div>


                                        <div class="item-total">

                                            Rs.
                                            <?php
                                            echo number_format(
                                                (float)
                                                $item['subtotal'],
                                                2
                                            );
                                            ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>


                            <?php endif; ?>

                        </div>


                        <!-- TOTAL -->

                        <div class="total-row">

                            <div class="total-box">

                                <div class="total-line">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong>

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float)
                                            $order['subtotal'],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </div>


                                <div class="total-line">

                                    <span>
                                        Delivery Fee
                                    </span>

                                    <strong>

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float)
                                            $order['delivery_fee'],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </div>


                                <?php if (
                                    (float)
                                    $order['discount'] > 0
                                ): ?>

                                    <div class="total-line">

                                        <span>
                                            Discount
                                        </span>

                                        <strong>

                                            - Rs.
                                            <?php
                                            echo number_format(
                                                (float)
                                                $order['discount'],
                                                2
                                            );
                                            ?>

                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <div class="total-final">

                                    <span>
                                        Order Total
                                    </span>

                                    <span>

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float)
                                            $order['total'],
                                            2
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- CUSTOMER NOTE -->

                        <?php if (
                            !empty(
                                $order['customer_note']
                            )
                        ): ?>

                            <div
                                class="info-box"
                                style="margin-bottom:20px;"
                            >

                                <div class="info-title">
                                    Customer Note
                                </div>

                                <div class="info-main">

                                    <?php
                                    echo nl2br(
                                        h(
                                            $order['customer_note']
                                        )
                                    );
                                    ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- ACTIONS -->

                        <?php if (
                            $ownerStatus === 'approved'
                        ): ?>

                            <div class="order-actions">


                                <?php if (
                                    $status === 'pending'
                                ): ?>

                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo $orderId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_status"
                                            value="confirmed"
                                        >

                                        <button
                                            type="submit"
                                            name="update_order"
                                            class="status-btn accept"
                                        >
                                            ✓ Accept Order
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo $orderId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_status"
                                            value="cancelled"
                                        >

                                        <button
                                            type="submit"
                                            name="update_order"
                                            class="status-btn cancel"
                                        >
                                            ✕ Cancel
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if (
                                    $status === 'confirmed'
                                    ||
                                    $status === 'accepted'
                                ): ?>

                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo $orderId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_status"
                                            value="preparing"
                                        >

                                        <button
                                            type="submit"
                                            name="update_order"
                                            class="status-btn prepare"
                                        >
                                            🍳 Start Preparing
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if (
                                    $status === 'preparing'
                                ): ?>

                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo $orderId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_status"
                                            value="out_for_delivery"
                                        >

                                        <button
                                            type="submit"
                                            name="update_order"
                                            class="status-btn delivery-btn"
                                        >
                                            🚴 Ready / Out for Delivery
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if (
                                    $status === 'out_for_delivery'
                                    ||
                                    $status === 'on_the_way'
                                ): ?>

                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                                echo $orderId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_status"
                                            value="delivered"
                                        >

                                        <button
                                            type="submit"
                                            name="update_order"
                                            class="status-btn complete"
                                        >
                                            ✓ Mark Delivered
                                        </button>

                                    </form>

                                <?php endif; ?>


                            </div>

                        <?php endif; ?>


                    </div>

                </article>

            <?php endforeach; ?>


        <?php endif; ?>


    </section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| Search + Status Filter
|--------------------------------------------------------------------------
*/

function filterOrders() {

    const searchInput =
        document.getElementById(
            'orderSearch'
        );

    const statusFilter =
        document.getElementById(
            'statusFilter'
        );

    if (!searchInput || !statusFilter) {
        return;
    }

    const search =
        searchInput.value
        .toLowerCase()
        .trim();

    const status =
        statusFilter.value;

    const cards =
        document.querySelectorAll(
            '.order-card'
        );

    cards.forEach(function(card) {

        const cardSearch =
            card
            .getAttribute(
                'data-search'
            )
            .toLowerCase();

        const cardStatus =
            card
            .getAttribute(
                'data-status'
            );

        const matchesSearch =
            cardSearch.includes(
                search
            );

        const matchesStatus =
            status === 'all'
            ||
            cardStatus === status;

        if (
            matchesSearch &&
            matchesStatus
        ) {

            card.style.display =
                '';

        } else {

            card.style.display =
                'none';
        }

    });
}

</script>

</body>

</html>