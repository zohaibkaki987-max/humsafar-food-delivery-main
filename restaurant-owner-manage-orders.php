<?php

require_once 'includes/config.php';
require_once 'includes/session.php';


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}


/* =========================================================
   FIND RESTAURANT OWNER
========================================================= */

$owner = null;

$ownerId = 0;

$ownerEmail = '';


if (!empty($_SESSION['restaurant_owner_id'])) {

    $ownerId = (int)$_SESSION['restaurant_owner_id'];

}


if (
    $ownerId <= 0 &&
    !empty($_SESSION['restaurant_user_id'])
) {

    $ownerId = (int)$_SESSION['restaurant_user_id'];

}


if (
    $ownerId <= 0 &&
    !empty($_SESSION['owner_id'])
) {

    $ownerId = (int)$_SESSION['owner_id'];

}


if (!empty($_SESSION['restaurant_owner_email'])) {

    $ownerEmail =
        trim(
            (string)$_SESSION['restaurant_owner_email']
        );

}


if (
    $ownerEmail === '' &&
    !empty($_SESSION['email'])
) {

    $ownerEmail =
        trim(
            (string)$_SESSION['email']
        );

}


/* =========================================================
   OWNER BY ID
========================================================= */

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

        $stmt->bind_param(
            "i",
            $ownerId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $owner =
            $result->fetch_assoc();

        $stmt->close();

    }

}


/* =========================================================
   OWNER BY EMAIL
========================================================= */

if (
    !$owner &&
    $ownerEmail !== ''
) {

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

        $stmt->bind_param(
            "s",
            $ownerEmail
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $owner =
            $result->fetch_assoc();

        $stmt->close();

    }

}


/* =========================================================
   OWNER NOT FOUND
========================================================= */

if (!$owner) {

    die(
        '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Owner Login Required</title>

            <style>

            body {
                margin:0;
                background:#fff7fa;
                font-family:Arial,sans-serif;
                display:flex;
                align-items:center;
                justify-content:center;
                min-height:100vh;
            }

            .box {
                width:90%;
                max-width:450px;
                background:#fff;
                padding:40px;
                border-radius:16px;
                text-align:center;
                box-shadow:0 10px 40px rgba(0,0,0,.08);
            }

            h2 {
                color:#e00038;
            }

            a {
                display:inline-block;
                margin-top:15px;
                padding:12px 20px;
                background:#e00038;
                color:white;
                text-decoration:none;
                border-radius:8px;
            }

            </style>
        </head>

        <body>

            <div class="box">

                <h2>Restaurant Owner Login Required</h2>

                <p>
                    Please login through the restaurant owner account.
                </p>

                <a href="restaurant-owner-login.php">
                    Owner Login
                </a>

            </div>

        </body>
        </html>'
    );

}


$ownerId =
    (int)$owner['id'];


$restaurantName =
    trim(
        (string)$owner['restaurant_name']
    );


/* =========================================================
   FIND RESTAURANT
========================================================= */

$restaurant = null;

$restaurantId = 0;


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

    $result =
        $stmt->get_result();

    $restaurant =
        $result->fetch_assoc();

    $stmt->close();

}


if ($restaurant) {

    $restaurantId =
        (int)$restaurant['id'];

}


/* =========================================================
   MESSAGE
========================================================= */

$successMessage = '';

$errorMessage = '';


/* =========================================================
   UPDATE ORDER STATUS
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_order'])
) {

    $orderId =
        (int)(
            isset($_POST['order_id'])
                ? $_POST['order_id']
                : 0
        );


    $newStatus =
        trim(
            (string)(
                isset($_POST['order_status'])
                    ? $_POST['order_status']
                    : ''
            )
        );


    $allowedStatuses =
        array(
            'pending',
            'confirmed',
            'preparing',
            'out_for_delivery',
            'delivered',
            'cancelled'
        );


    if ($orderId <= 0) {

        $errorMessage =
            'Invalid order.';

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
            'Restaurant not found.';

    } else {


        /*
         * IMPORTANT:
         * restaurant_id is checked here.
         * Owner can therefore update only
         * orders belonging to his restaurant.
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
                    'Could not update order status: ' .
                    $stmt->error;

            }


            $stmt->close();

        }

    }

}


/* =========================================================
   GET RESTAURANT ORDERS
========================================================= */

$orders = array();


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


        $result =
            $stmt->get_result();


        while (
            $row =
                $result->fetch_assoc()
        ) {

            $orders[] =
                $row;

        }


        $stmt->close();

    }

}


/* =========================================================
   GET ORDER ITEMS
========================================================= */

$orderItems = array();


foreach ($orders as $order) {


    $orderId =
        (int)$order['id'];


    $orderItems[$orderId] =
        array();


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


        $result =
            $stmt->get_result();


        while (
            $item =
                $result->fetch_assoc()
        ) {

            $orderItems[$orderId][] =
                $item;

        }


        $stmt->close();

    }

}


/* =========================================================
   GET CUSTOMER ADDRESSES
========================================================= */

$orderAddresses = array();


foreach ($orders as $order) {


    $addressId =
        (int)$order['address_id'];


    if ($addressId <= 0) {

        $orderAddresses[$order['id']] =
            null;

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


        $result =
            $stmt->get_result();


        $address =
            $result->fetch_assoc();


        $orderAddresses[$order['id']] =
            $address;


        $stmt->close();

    }

}


/* =========================================================
   COUNTERS
========================================================= */

$totalOrders =
    count($orders);

$pendingOrders =
    0;

$confirmedOrders =
    0;

$preparingOrders =
    0;

$deliveryOrders =
    0;

$completedOrders =
    0;


foreach ($orders as $order) {


    $status =
        strtolower(
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

    }

}


/* =========================================================
   STATUS LABEL
========================================================= */

function orderStatusLabel($status)
{

    $status =
        strtolower(
            trim(
                (string)$status
            )
        );


    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );

}


/* =========================================================
   STATUS CLASS
========================================================= */

function orderStatusClass($status)
{

    $status =
        strtolower(
            trim(
                (string)$status
            )
        );


    if ($status === 'pending') {

        return 'status-pending';

    }


    if (
        $status === 'confirmed' ||
        $status === 'accepted'
    ) {

        return 'status-confirmed';

    }


    if ($status === 'preparing') {

        return 'status-preparing';

    }


    if (
        $status === 'out_for_delivery' ||
        $status === 'on_the_way'
    ) {

        return 'status-delivery';

    }


    if (
        $status === 'delivered' ||
        $status === 'completed'
    ) {

        return 'status-delivered';

    }


    if (
        $status === 'cancelled' ||
        $status === 'canceled'
    ) {

        return 'status-cancelled';

    }


    return 'status-default';

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
    Manage Orders - Humsafar
</title>


<style>

* {
    box-sizing: border-box;
}


body {
    margin: 0;
    background: #fff7fa;
    color: #292929;
    font-family: Arial, sans-serif;
}


.topbar {
    background: #ffffff;
    border-bottom: 1px solid #eeeeee;
    padding: 16px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}


.logo {
    color: #e00038;
    text-decoration: none;
    font-size: 22px;
    font-weight: 800;
}


.nav {
    display: flex;
    gap: 10px;
}


.nav a {
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
}


.dashboard {
    background: #eeeeee;
    color: #333333;
}


.logout {
    background: #fff0f3;
    color: #e00038;
}


.container {
    width: 94%;
    max-width: 1200px;
    margin: auto;
    padding: 35px 0 60px;
}


.heading {
    margin-bottom: 25px;
}


.restaurant-name {
    display: inline-block;
    background: #fff0f3;
    color: #e00038;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 12px;
}


.heading h1 {
    margin: 0 0 8px;
    font-size: 30px;
}


.heading p {
    margin: 0;
    color: #777777;
}


.message {
    padding: 14px 17px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: bold;
}


.success {
    background: #eaf8ef;
    color: #18733e;
}


.error {
    background: #fff0f0;
    color: #c52323;
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;
    grid-template-columns:
        repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 25px;
}


.stat {
    background: #ffffff;
    border: 1px solid #eee5e9;
    border-radius: 13px;
    padding: 18px;
}


.stat-number {
    font-size: 25px;
    font-weight: 800;
    color: #e00038;
}


.stat-label {
    margin-top: 5px;
    color: #777777;
    font-size: 11px;
}


/* =========================================================
   ORDER CARD
========================================================= */

.order-card {
    background: #ffffff;
    border: 1px solid #eee5e9;
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
}


.order-header {
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid #eeeeee;
}


.order-number {
    font-size: 18px;
    font-weight: 800;
}


.order-date {
    color: #888888;
    font-size: 11px;
    margin-top: 5px;
}


.status {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 18px;
    font-size: 11px;
    font-weight: bold;
}


.status-pending {
    background: #fff4d9;
    color: #956500;
}


.status-confirmed {
    background: #eaf3ff;
    color: #1769aa;
}


.status-preparing {
    background: #fff0df;
    color: #a85d00;
}


.status-delivery {
    background: #f0eaff;
    color: #6641a3;
}


.status-delivered {
    background: #e7f8ed;
    color: #18733e;
}


.status-cancelled {
    background: #fff0f0;
    color: #c52323;
}


.status-default {
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
        1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}


.info-box {
    background: #faf8f9;
    border-radius: 10px;
    padding: 15px;
}


.info-title {
    color: #888888;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 7px;
}


.info-main {
    font-weight: bold;
    font-size: 14px;
}


.info-small {
    color: #777777;
    font-size: 11px;
    margin-top: 4px;
    line-height: 1.5;
}


/* =========================================================
   ITEMS
========================================================= */

.items {
    border: 1px solid #eeeeee;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
}


.item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 13px 15px;
    border-bottom: 1px solid #eeeeee;
}


.item:last-child {
    border-bottom: 0;
}


.item-name {
    font-weight: bold;
    font-size: 13px;
}


.item-meta {
    color: #888888;
    font-size: 11px;
    margin-top: 4px;
}


.item-total {
    color: #e00038;
    font-weight: bold;
    white-space: nowrap;
}


/* =========================================================
   TOTAL
========================================================= */

.total-box {
    background: #fff7fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}


.total-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 12px;
}


.total-row.grand {
    border-top: 1px solid #eadfe4;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 17px;
    font-weight: 800;
    color: #e00038;
}


/* =========================================================
   STATUS FORM
========================================================= */

.status-form {
    display: flex;
    gap: 10px;
    align-items: center;
}


.status-form select {
    flex: 1;
    min-width: 180px;
    height: 42px;
    border: 1px solid #dddddd;
    border-radius: 8px;
    padding: 0 10px;
    background: #ffffff;
}


.update-button {
    height: 42px;
    padding: 0 18px;
    border: 0;
    border-radius: 8px;
    background: #e00038;
    color: #ffffff;
    font-weight: bold;
    cursor: pointer;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    background: #ffffff;
    border: 1px solid #eee5e9;
    border-radius: 16px;
    padding: 70px 20px;
    text-align: center;
}


.empty-icon {
    font-size: 45px;
    margin-bottom: 15px;
}


.empty h2 {
    margin: 0 0 8px;
}


.empty p {
    color: #888888;
    margin: 0;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .stats {
        grid-template-columns:
            repeat(3, 1fr);
    }

}


@media (max-width: 650px) {

    .topbar {
        padding: 14px 4%;
    }


    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }


    .order-header {
        align-items: flex-start;
        flex-direction: column;
    }


    .info-grid {
        grid-template-columns: 1fr;
    }


    .status-form {
        flex-direction: column;
        align-items: stretch;
    }


    .status-form select {
        width: 100%;
    }


    .update-button {
        width: 100%;
    }

}

</style>

</head>


<body>


<header class="topbar">

    <a
        href="restaurant-owner-dashboard.php"
        class="logo"
    >
        Humsafar
    </a>


    <nav class="nav">

        <a
            href="restaurant-owner-dashboard.php"
            class="dashboard"
        >
            Dashboard
        </a>


        <a
            href="restaurant-owner-login.php"
            class="logout"
        >
            Logout
        </a>

    </nav>

</header>


<main class="container">


    <section class="heading">

        <div class="restaurant-name">

            Restaurant:
            <?= h($restaurantName) ?>

        </div>


        <h1>
            Manage Orders
        </h1>


        <p>
            View customer orders and update their delivery status.
        </p>

    </section>


    <?php if ($successMessage !== ''): ?>

        <div class="message success">

            <?= h($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="message error">

            <?= h($errorMessage) ?>

        </div>

    <?php endif; ?>


    <?php if (!$restaurant): ?>


        <div class="message error">

            Your restaurant
            <strong>
                <?= h($restaurantName) ?>
            </strong>
            was not found in the restaurants table.

        </div>


    <?php else: ?>


        <!-- =====================================================
             STATS
        ====================================================== -->

        <div class="stats">


            <div class="stat">

                <div class="stat-number">
                    <?= $totalOrders ?>
                </div>

                <div class="stat-label">
                    Total Orders
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= $pendingOrders ?>
                </div>

                <div class="stat-label">
                    Pending
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= $confirmedOrders ?>
                </div>

                <div class="stat-label">
                    Confirmed
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= $preparingOrders ?>
                </div>

                <div class="stat-label">
                    Preparing
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= $completedOrders ?>
                </div>

                <div class="stat-label">
                    Delivered
                </div>

            </div>


        </div>


        <?php if (!empty($orders)): ?>


            <?php foreach ($orders as $order): ?>


                <?php

                $currentStatus =
                    strtolower(
                        trim(
                            (string)$order['order_status']
                        )
                    );


                $orderId =
                    (int)$order['id'];


                $customerName =
                    trim(
                        (string)(
                            $order['customer_name']
                            ?? ''
                        )
                    );


                if ($customerName === '') {

                    $customerName =
                        'Customer';

                }


                $orderNumber =
                    $order['order_number'];


                $items =
                    isset($orderItems[$orderId])
                        ? $orderItems[$orderId]
                        : array();


                $address =
                    isset($orderAddresses[$orderId])
                        ? $orderAddresses[$orderId]
                        : null;


                ?>


                <article class="order-card">


                    <!-- ORDER HEADER -->

                    <div class="order-header">


                        <div>

                            <div class="order-number">

                                Order #
                                <?= h($orderNumber) ?>

                            </div>


                            <div class="order-date">

                                <?php

                                if (
                                    !empty(
                                        $order['created_at']
                                    )
                                ) {

                                    echo h(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime(
                                                $order['created_at']
                                            )
                                        )
                                    );

                                } else {

                                    echo '-';

                                }

                                ?>

                            </div>

                        </div>


                        <span
                            class="status
                            <?= h(
                                orderStatusClass(
                                    $currentStatus
                                )
                            ) ?>"
                        >

                            <?= h(
                                orderStatusLabel(
                                    $currentStatus
                                )
                            ) ?>

                        </span>


                    </div>


                    <!-- ORDER BODY -->

                    <div class="order-body">


                        <!-- CUSTOMER -->

                        <div class="info-grid">


                            <div class="info-box">


                                <div class="info-title">
                                    Customer
                                </div>


                                <div class="info-main">

                                    <?= h(
                                        $customerName
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $order['customer_phone']
                                    )
                                ): ?>

                                    <div class="info-small">

                                        Phone:
                                        <?= h(
                                            $order['customer_phone']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $order['customer_email']
                                    )
                                ): ?>

                                    <div class="info-small">

                                        <?= h(
                                            $order['customer_email']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                            <!-- ADDRESS -->

                            <div class="info-box">


                                <div class="info-title">
                                    Delivery Address
                                </div>


                                <?php if ($address): ?>


                                    <div class="info-main">

                                        <?= h(
                                            $address['address_title']
                                            ?? 'Delivery Address'
                                        ) ?>

                                    </div>


                                    <div class="info-small">

                                        <?= h(
                                            $address['address_line']
                                            ?? ''
                                        ) ?>


                                        <?php if (
                                            !empty(
                                                $address['area']
                                            )
                                        ): ?>

                                            <br>

                                            <?= h(
                                                $address['area']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $address['city']
                                            )
                                        ): ?>

                                            <br>

                                            <?= h(
                                                $address['city']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $address['phone']
                                            )
                                        ): ?>

                                            <br>

                                            Phone:
                                            <?= h(
                                                $address['phone']
                                            ) ?>

                                        <?php endif; ?>

                                    </div>


                                <?php else: ?>


                                    <div class="info-small">

                                        Address information
                                        not available.

                                    </div>


                                <?php endif; ?>


                            </div>


                        </div>


                        <!-- ITEMS -->

                        <div class="items">


                            <?php if (!empty($items)): ?>


                                <?php foreach ($items as $item): ?>


                                    <div class="item">


                                        <div>

                                            <div class="item-name">

                                                <?= h(
                                                    $item['item_name']
                                                ) ?>

                                            </div>


                                            <div class="item-meta">

                                                Rs.
                                                <?= number_format(
                                                    (float)$item['item_price'],
                                                    2
                                                ) ?>

                                                ×

                                                <?= (int)$item['quantity'] ?>

                                            </div>

                                        </div>


                                        <div class="item-total">

                                            Rs.
                                            <?= number_format(
                                                (float)$item['subtotal'],
                                                2
                                            ) ?>

                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <div class="item">

                                    <div class="item-meta">

                                        No order items found.

                                    </div>

                                </div>


                            <?php endif; ?>


                        </div>


                        <!-- TOTAL -->

                        <div class="total-box">


                            <div class="total-row">

                                <span>
                                    Subtotal
                                </span>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        (float)$order['subtotal'],
                                        2
                                    ) ?>
                                </strong>

                            </div>


                            <div class="total-row">

                                <span>
                                    Delivery Fee
                                </span>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        (float)$order['delivery_fee'],
                                        2
                                    ) ?>
                                </strong>

                            </div>


                            <div class="total-row">

                                <span>
                                    Discount
                                </span>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        (float)$order['discount'],
                                        2
                                    ) ?>
                                </strong>

                            </div>


                            <div class="total-row grand">

                                <span>
                                    Total
                                </span>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        (float)$order['total'],
                                        2
                                    ) ?>
                                </strong>

                            </div>


                        </div>


                        <!-- PAYMENT -->

                        <div class="info-box">

                            <div class="info-title">
                                Payment Method
                            </div>

                            <div class="info-main">

                                <?= h(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $order['payment_method']
                                            ?? 'Not specified'
                                        )
                                    )
                                ) ?>

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
                                style="margin-top:15px;"
                            >

                                <div class="info-title">
                                    Customer Note
                                </div>

                                <div class="info-small">

                                    <?= nl2br(
                                        h(
                                            $order['customer_note']
                                        )
                                    ) ?>

                                </div>

                            </div>


                        <?php endif; ?>


                        <!-- UPDATE STATUS -->

                        <form
                            method="POST"
                            class="status-form"
                            style="margin-top:20px;"
                        >


                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $orderId ?>"
                            >


                            <select
                                name="order_status"
                            >

                                <option
                                    value="pending"
                                    <?= $currentStatus === 'pending'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Pending
                                </option>


                                <option
                                    value="confirmed"
                                    <?= (
                                        $currentStatus === 'confirmed' ||
                                        $currentStatus === 'accepted'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Confirmed
                                </option>


                                <option
                                    value="preparing"
                                    <?= $currentStatus === 'preparing'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Preparing
                                </option>


                                <option
                                    value="out_for_delivery"
                                    <?= (
                                        $currentStatus === 'out_for_delivery' ||
                                        $currentStatus === 'on_the_way'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Out for Delivery
                                </option>


                                <option
                                    value="delivered"
                                    <?= (
                                        $currentStatus === 'delivered' ||
                                        $currentStatus === 'completed'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Delivered
                                </option>


                                <option
                                    value="cancelled"
                                    <?= (
                                        $currentStatus === 'cancelled' ||
                                        $currentStatus === 'canceled'
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Cancelled
                                </option>


                            </select>


                            <button
                                type="submit"
                                name="update_order"
                                value="1"
                                class="update-button"
                            >

                                Update Order

                            </button>


                        </form>


                    </div>


                </article>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty">


                <div class="empty-icon">
                    🍽️
                </div>


                <h2>
                    No Orders Yet
                </h2>


                <p>
                    When customers place orders from your restaurant,
                    they will appear here.
                </p>


            </div>


        <?php endif; ?>


    <?php endif; ?>


</main>


</body>

</html>