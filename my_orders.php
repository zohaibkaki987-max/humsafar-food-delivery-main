<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';


/*
|--------------------------------------------------------------------------
| CUSTOMER LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}

$user_id = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| GET CUSTOMER ORDERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Project uses mysqli connection: $conn
|
| Cancelled orders are intentionally included.
|
*/

$orders = [];

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_number,
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

        r.name AS restaurant_name,
        r.image AS restaurant_image

    FROM orders o

    LEFT JOIN restaurants r
        ON o.restaurant_id = r.id

    WHERE o.user_id = ?

    ORDER BY o.id DESC
");


if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}


$stmt->bind_param(
    "i",
    $user_id
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

/* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER */
$customerTracking = [];
foreach ($orders as $trackingOrder) {
    $trackingOrderId = (int)($trackingOrder['id'] ?? 0);
    if ($trackingOrderId < 1) continue;
    $trackingStmt = $conn->prepare("SELECT rd.status AS delivery_status,r.id AS rider_id,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at
        FROM rider_deliveries rd
        INNER JOIN riders r ON r.id=rd.rider_id
        LEFT JOIN rider_locations rl ON rl.id=(SELECT x.id FROM rider_locations x WHERE x.rider_id=r.id ORDER BY x.id DESC LIMIT 1)
        WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1");
    if ($trackingStmt) {
        $trackingStmt->bind_param('i', $trackingOrderId);
        $trackingStmt->execute();
        $customerTracking[$trackingOrderId] = $trackingStmt->get_result()->fetch_assoc();
        $trackingStmt->close();
    }
}



/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
*/

function getStatusLabel($status)
{

    $status =
        strtolower(
            trim(
                (string) $status
            )
        );


    switch ($status) {

        case 'pending':
            return 'Open';

        case 'confirmed':
            return 'Confirmed';

        case 'accepted':
            return 'Accepted';

        case 'preparing':
            return 'Preparing';

        case 'ready':
            return 'Ready';

        case 'ready_for_pickup':
            return 'Ready for Pickup';

        case 'out_for_delivery':
            return 'Out for Delivery';

        case 'on_the_way':
            return 'Out for Delivery';

        case 'picked_up':
            return 'Out for Delivery';

        case 'delivered':
            return 'Delivered';

        case 'completed':
            return 'Delivered';

        case 'cancelled':
            return 'Cancelled';

        case 'canceled':
            return 'Cancelled';

        default:

            return ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $status
                )
            );

    }

}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function getStatusClass($status)
{

    $status =
        strtolower(
            trim(
                (string) $status
            )
        );


    switch ($status) {

        case 'pending':
            return 'pending';

        case 'confirmed':
        case 'accepted':
            return 'accepted';

        case 'preparing':
            return 'preparing';

        case 'ready':
        case 'ready_for_pickup':
            return 'ready';

        case 'out_for_delivery':
        case 'on_the_way':
        case 'picked_up':
            return 'delivery';

        case 'delivered':
        case 'completed':
            return 'delivered';

        case 'cancelled':
        case 'canceled':
            return 'cancelled';

        default:
            return 'default';

    }

}


/*
|--------------------------------------------------------------------------
| STATUS ICON
|--------------------------------------------------------------------------
*/

function getStatusIcon($status)
{

    $status =
        strtolower(
            trim(
                (string) $status
            )
        );


    switch ($status) {

        case 'pending':
            return 'fa-clock';

        case 'confirmed':
        case 'accepted':
            return 'fa-circle-check';

        case 'preparing':
            return 'fa-kitchen-set';

        case 'ready':
        case 'ready_for_pickup':
            return 'fa-box';

        case 'out_for_delivery':
        case 'on_the_way':
        case 'picked_up':
            return 'fa-motorcycle';

        case 'delivered':
        case 'completed':
            return 'fa-check-double';

        case 'cancelled':
        case 'canceled':
            return 'fa-circle-xmark';

        default:
            return 'fa-circle-info';

    }

}


/*
|--------------------------------------------------------------------------
| STATUS PROGRESS
|--------------------------------------------------------------------------
*/

function getProgressStep($status)
{

    $status =
        strtolower(
            trim(
                (string) $status
            )
        );


    switch ($status) {

        case 'pending':
            return 1;

        case 'confirmed':
        case 'accepted':
            return 2;

        case 'preparing':
            return 3;

        case 'ready':
        case 'ready_for_pickup':
            return 4;

        case 'out_for_delivery':
        case 'on_the_way':
        case 'picked_up':
            return 5;

        case 'delivered':
        case 'completed':
            return 6;

        default:
            return 1;

    }

}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalOrders =
    count($orders);


$openOrders = 0;

$deliveredOrders = 0;

$cancelledOrders = 0;


foreach (
    $orders as $order
) {

    $status =
        strtolower(
            trim(
                (string)
                ($order['order_status'] ?? '')
            )
        );


    if (
        in_array(
            $status,
            [
                'delivered',
                'completed'
            ],
            true
        )
    ) {

        $deliveredOrders++;

    }

    elseif (
        in_array(
            $status,
            [
                'cancelled',
                'canceled'
            ],
            true
        )
    ) {

        $cancelledOrders++;

    }

    else {

        $openOrders++;

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
        My Orders - Humsafar
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">


    <style>

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background:
                #f5f6fa;

            color:
                #333;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

        }


        .orders-container {

            max-width:
                1100px;

            margin:
                38px auto;

            padding:
                0 20px 60px;

        }


        /*
        |--------------------------------------------------------------------------
        | PAGE HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                25px;

        }


        .page-heading h1 {

            margin:
                0;

            color:
                #fff;

            font-size:
                32px;

            font-weight:
                800;

        }


        .page-heading p {

            margin:
                7px 0 0;

            color:
                #fff;

            font-size:
                14px;

        }


        .continue-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                11px 17px;

            background:
                #fff;

            color:
                #333;

            border:
                1px solid #eee;

            border-radius:
                10px;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                700;

        }


        .continue-btn:hover {

            background:
                #fff1f5;

            color:
                #ed0038;

            border-color:
                #ed0038;

        }


        /*
        |--------------------------------------------------------------------------
        | STAT CARDS
        |--------------------------------------------------------------------------
        */

        .order-stats {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                15px;

            margin-bottom:
                25px;

        }


        .stat-card {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;

            padding:
                17px;

            background:
                #fff;

            border:
                1px solid #eee;

            border-radius:
                15px;

            box-shadow:
                0 7px 22px
                rgba(0,0,0,.04);

        }


        .stat-icon {

            width:
                45px;

            height:
                45px;

            border-radius:
                12px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #fff1f5;

            color:
                #ed0038;

            font-size:
                18px;

        }


        .stat-number {

            font-size:
                21px;

            font-weight:
                800;

            color:
                #222;

        }


        .stat-label {

            margin-top:
                2px;

            color:
                #888;

            font-size:
                11px;

        }


        /*
        |--------------------------------------------------------------------------
        | ORDER CARD
        |--------------------------------------------------------------------------
        */

        .order-card {

            margin-bottom:
                20px;

            overflow:
                hidden;

            background:
                #fff;

            border:
                1px solid #eee;

            border-radius:
                18px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.06);

        }


        /*
        |--------------------------------------------------------------------------
        | ORDER TOP
        |--------------------------------------------------------------------------
        */

        .order-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                19px 22px;

            border-bottom:
                1px solid #eee;

        }


        .order-number {

            margin:
                0;

            color:
                #222;

            font-size:
                17px;

            font-weight:
                800;

        }


        .order-date {

            margin-top:
                6px;

            color:
                #888;

            font-size:
                12px;

        }


        .order-date i {

            margin-right:
                5px;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .order-status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                8px 13px;

            border-radius:
                25px;

            font-size:
                11px;

            font-weight:
                800;

            white-space:
                nowrap;

        }


        .status-pending {

            background:
                #fff5df;

            color:
                #b87800;

        }


        .status-accepted {

            background:
                #eaf4ff;

            color:
                #1672b8;

        }


        .status-preparing {

            background:
                #fff0e5;

            color:
                #d15c1c;

        }


        .status-ready {

            background:
                #f1ebff;

            color:
                #6944c1;

        }


        .status-delivery {

            background:
                #e9f8ef;

            color:
                #168249;

        }


        .status-delivered {

            background:
                #e5f8eb;

            color:
                #13803f;

        }


        .status-cancelled {

            background:
                #ffebed;

            color:
                #d22b3a;

        }


        .status-default {

            background:
                #f0f0f0;

            color:
                #666;

        }


        /*
        |--------------------------------------------------------------------------
        | RESTAURANT
        |--------------------------------------------------------------------------
        */

        .restaurant-row {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;

            padding:
                17px 22px;

        }


        .restaurant-image {

            width:
                58px;

            height:
                58px;

            overflow:
                hidden;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-shrink:
                0;

            background:
                #fff1f5;

            color:
                #ed0038;

            border-radius:
                13px;

        }


        .restaurant-image img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

        }


        .restaurant-row h4 {

            margin:
                0;

            color:
                #222;

            font-size:
                16px;

        }


        .restaurant-row span {

            display:
                block;

            margin-top:
                3px;

            color:
                #888;

            font-size:
                12px;

        }


        /*
        |--------------------------------------------------------------------------
        | ITEMS
        |--------------------------------------------------------------------------
        */

        .items-container {

            padding:
                5px 22px 17px;

        }


        .items-title {

            margin-bottom:
                5px;

            color:
                #555;

            font-size:
                12px;

            font-weight:
                800;

            text-transform:
                uppercase;

        }


        .order-item {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                12px 0;

            border-bottom:
                1px solid #f0f0f0;

        }


        .order-item:last-child {

            border-bottom:
                0;

        }


        .item-left {

            min-width:
                0;

        }


        .item-name {

            color:
                #222;

            font-size:
                13px;

            font-weight:
                650;

        }


        .item-qty {

            margin-top:
                4px;

            color:
                #888;

            font-size:
                12px;

        }


        .item-price {

            color:
                #222;

            font-size:
                13px;

            font-weight:
                750;

            white-space:
                nowrap;

        }


        /*
        |--------------------------------------------------------------------------
        | TRACKING
        |--------------------------------------------------------------------------
        */

        .tracking {

            margin:
                5px 22px 20px;

            padding:
                17px;

            background:
                #fafafa;

            border:
                1px solid #eee;

            border-radius:
                13px;

        }


        .tracking-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin-bottom:
                18px;

        }


        .tracking-title {

            color:
                #333;

            font-size:
                13px;

            font-weight:
                800;

        }


        .tracking-current {

            color:
                #ed0038;

            font-size:
                11px;

            font-weight:
                800;

        }


        .progress {

            position:
                relative;

            display:
                flex;

            justify-content:
                space-between;

        }


        .progress-line {

            position:
                absolute;

            top:
                13px;

            left:
                8%;

            right:
                8%;

            height:
                3px;

            background:
                #ddd;

            z-index:
                0;

        }


        .progress-fill {

            height:
                100%;

            background:
                #ed0038;

            transition:
                width .3s ease;

        }


        .progress-step {

            position:
                relative;

            z-index:
                1;

            width:
                16.66%;

            text-align:
                center;

        }


        .progress-dot {

            width:
                28px;

            height:
                28px;

            margin:
                0 auto 7px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                3px solid #fafafa;

            border-radius:
                50%;

            background:
                #ddd;

            color:
                #999;

            font-size:
                10px;

        }


        .progress-step.active
        .progress-dot {

            background:
                #ed0038;

            color:
                #fff;

        }


        .progress-step.completed
        .progress-dot {

            background:
                #28a745;

            color:
                #fff;

        }


        .progress-step span {

            display:
                block;

            color:
                #999;

            font-size:
                8px;

            line-height:
                1.2;

        }


        .progress-step.active span,
        .progress-step.completed span {

            color:
                #333;

            font-weight:
                700;

        }


        /*
        |--------------------------------------------------------------------------
        | CANCELLED
        |--------------------------------------------------------------------------
        */

        .cancelled-box {

            margin:
                5px 22px 20px;

            padding:
                14px 16px;

            background:
                #fff0f1;

            border:
                1px solid #ffd4d8;

            border-radius:
                11px;

            color:
                #c72a38;

            font-size:
                12px;

        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .order-bottom {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                18px 22px;

            background:
                #fafafa;

            border-top:
                1px solid #eee;

        }


        .total-label {

            color:
                #777;

            font-size:
                12px;

        }


        .total-price {

            display:
                block;

            margin-top:
                3px;

            color:
                #ed0038;

            font-size:
                21px;

            font-weight:
                800;

        }


        .payment-method {

            margin-top:
                4px;

            color:
                #888;

            font-size:
                11px;

        }


        .view-order-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                11px 17px;

            background:
                #ed0038;

            color:
                #fff;

            border-radius:
                9px;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                750;

        }


        .view-order-btn:hover {

            background:
                #d90035;

            color:
                #fff;

        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .empty-orders {

            padding:
                70px 25px;

            text-align:
                center;

            background:
                #fff;

            border:
                1px solid #eee;

            border-radius:
                20px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.05);

        }


        .empty-icon {

            width:
                90px;

            height:
                90px;

            margin:
                0 auto 20px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #fff1f5;

            color:
                #ed0038;

            border-radius:
                50%;

            font-size:
                38px;

        }


        .empty-orders h2 {

            margin:
                0;

            color:
                #222;

            font-size:
                22px;

        }


        .empty-orders p {

            margin:
                9px 0 23px;

            color:
                #777;

            font-size:
                13px;

        }


        .shop-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                12px 21px;

            background:
                #ed0038;

            color:
                #fff;

            border-radius:
                10px;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                750;

        }


        .shop-btn:hover {

            background:
                #d90035;

            color:
                #fff;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 750px) {

            .orders-container {

                margin-top:
                    25px;

                padding:
                    0 12px 40px;

            }


            .page-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .continue-btn {

                width:
                    100%;

                justify-content:
                    center;

            }


            .order-stats {

                grid-template-columns:
                    1fr;

            }


            .order-top {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                padding:
                    17px;

            }


            .restaurant-row {

                padding-left:
                    17px;

                padding-right:
                    17px;

            }


            .items-container {

                padding-left:
                    17px;

                padding-right:
                    17px;

            }


            .tracking {

                margin-left:
                    17px;

                margin-right:
                    17px;

                overflow-x:
                    auto;

            }


            .progress {

                min-width:
                    520px;

            }


            .order-bottom {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                padding:
                    17px;

            }


            .view-order-btn {

                width:
                    100%;

            }

        }


        @media (max-width: 450px) {

            .page-heading h1 {

                font-size:
                    27px;

            }


            .order-status {

                font-size:
                    10px;

            }


            .restaurant-image {

                width:
                    50px;

                height:
                    50px;

            }

        }
        .order-actions {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
        }
        .cancel-order-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            background: #dc3545;
            transition: opacity .2s ease;
        }
        .cancel-order-btn:hover {
            opacity: .9;
        }

    
        /* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER_CSS */
        .live-map-card{margin:5px 22px 20px;padding:14px;background:#f8fbff;border:1px solid #dcecf6;border-radius:13px}
        .live-map-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;color:#333;font-size:13px;font-weight:800}
        .live-map-status{color:#1672b8;font-size:11px;font-weight:800}
        .live-map{height:260px;border-radius:11px;overflow:hidden;border:1px solid #dbe8ef}
        .live-map-meta{margin-top:8px;color:#777;font-size:11px}
        @media (max-width:750px){.live-map-card{margin-left:17px;margin-right:17px}.live-map{height:230px}}
</style>

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| EXISTING HUMSAFAR CUSTOMER HEADER
|--------------------------------------------------------------------------
|
| IMPORTANT:
| customer-header.php is inside includes/
|
*/

require_once __DIR__ . '/includes/customer-header.php';

?>


<!-- =========================================================
     MY ORDERS
========================================================= -->

<main class="orders-container">


    <!-- PAGE HEADER -->

    <div class="page-header">


        <div class="page-heading">

            <h1>

                <i
                    class="fas fa-box-open"
                ></i>

                My Orders

            </h1>


            <p>

                View your recent and previous orders.

            </p>

        </div>


        <a
            href="restaurants.php"
            class="continue-btn"
        >

            <i
                class="fas fa-arrow-left"
            ></i>

            Continue Shopping

        </a>


    </div>



    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="order-stats">


        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-receipt"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $totalOrders;
                    ?>

                </div>


                <div class="stat-label">

                    Total Orders

                </div>

            </div>

        </div>



        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-clock"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $openOrders;
                    ?>

                </div>


                <div class="stat-label">

                    Open Orders

                </div>

            </div>

        </div>



        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-circle-check"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $deliveredOrders;
                    ?>

                </div>


                <div class="stat-label">

                    Delivered

                </div>

            </div>

        </div>


    </div>



    <?php if (empty($orders)): ?>


        <!-- =================================================
             EMPTY ORDERS
        ================================================== -->

        <div class="empty-orders">


            <div class="empty-icon">

                <i
                    class="fas fa-receipt"
                ></i>

            </div>


            <h2>

                No Orders Yet

            </h2>


            <p>

                You haven't placed any orders yet.

            </p>


            <a
                href="restaurants.php"
                class="shop-btn"
            >

                <i
                    class="fas fa-utensils"
                ></i>

                Start Ordering

            </a>


        </div>


    <?php else: ?>


        <!-- =================================================
             ORDERS
        ================================================== -->

        <?php foreach (
            $orders as $order
        ): ?>


            <?php

            $status =
                $order['order_status']
                ?? 'pending';


            $statusLabel =
                getStatusLabel(
                    $status
                );


            $statusClass =
                getStatusClass(
                    $status
                );


            $statusIcon =
                getStatusIcon(
                    $status
                );


            $progressStep =
                getProgressStep(
                    $status
                );


            $orderDate = '';

            if (
                !empty(
                    $order['created_at']
                )
            ) {

                $timestamp =
                    strtotime(
                        $order['created_at']
                    );


                if (
                    $timestamp !== false
                ) {

                    $orderDate =
                        date(
                            'd M Y, h:i A',
                            $timestamp
                        );

                }

            }


            $restaurantName =
                $order['restaurant_name']
                ?? 'Restaurant';


            $restaurantImage =
                $order['restaurant_image']
                ?? '';


            $total =
                (float)
                (
                    $order['total']
                    ?? 0
                );


            $payment =
                $order['payment_method']
                ?? 'Cash on Delivery';


            $isCancelled =
                in_array(
                    strtolower(
                        trim(
                            (string)
                            $status
                        )
                    ),
                    [
                        'cancelled',
                        'canceled'
                    ],
                    true
                );

            ?>


            <div class="order-card">


                <!-- =================================================
                     ORDER HEADER
                ================================================== -->

                <div class="order-top">


                    <div>


                        <h3 class="order-number">

                            Order #

                            <?php
                            echo h(
                                $order[
                                    'order_number'
                                ]
                            );
                            ?>

                        </h3>


                        <div class="order-date">

                            <i
                                class="far fa-calendar"
                            ></i>

                            <?php
                            echo h(
                                $orderDate
                            );
                            ?>

                        </div>


                    </div>


                    <div
                        class="
                            order-status
                            status-<?php
                            echo h(
                                $statusClass
                            );
                            ?>
                        "
                    >

                        <i
                            class="
                                fas
                                <?php
                                echo h(
                                    $statusIcon
                                );
                                ?>
                            "
                        ></i>


                        <?php
                        echo h(
                            $statusLabel
                        );
                        ?>

                    </div>


                </div>



                <!-- =================================================
                     RESTAURANT
                ================================================== -->

                <div class="restaurant-row">


                    <div class="restaurant-image">


                        <?php if (
                            !empty(
                                $restaurantImage
                            )
                        ): ?>


                            <img
                                src="
                                    assets/images/restaurants/<?php
                                    echo h(
                                        $restaurantImage
                                    );
                                    ?>
                                "
                                alt="<?php
                                echo h(
                                    $restaurantName
                                );
                                ?>"
                                onerror="
                                    this.style.display='none';
                                    this.parentElement.innerHTML='<i class=&quot;fas fa-store&quot;></i>';
                                "
                            >


                        <?php else: ?>


                            <i
                                class="
                                    fas
                                    fa-store
                                "
                            ></i>


                        <?php endif; ?>


                    </div>


                    <div>


                        <h4>

                            <?php
                            echo h(
                                $restaurantName
                            );
                            ?>

                        </h4>


                        <span>

                            Order details

                        </span>


                    </div>


                </div>



                <!-- =================================================
                     ORDER ITEMS
                ================================================== -->

                <?php

                $items = [];


                $itemStmt =
                    $conn->prepare("
                        SELECT
                            item_name,
                            item_price,
                            quantity,
                            subtotal

                        FROM order_items

                        WHERE order_id = ?

                        ORDER BY id ASC
                    ");


                if ($itemStmt) {

                    $itemStmt->bind_param(
                        "i",
                        $order['id']
                    );


                    $itemStmt->execute();


                    $itemResult =
                        $itemStmt->get_result();


                    while (
                        $item =
                        $itemResult->fetch_assoc()
                    ) {

                        $items[] =
                            $item;

                    }


                    $itemStmt->close();

                }

                ?>


                <div class="items-container">


                    <?php if (
                        !empty($items)
                    ): ?>


                        <div class="items-title">

                            Ordered Items

                        </div>


                        <?php foreach (
                            $items as $item
                        ): ?>


                            <div class="order-item">


                                <div class="item-left">


                                    <div class="item-name">

                                        <?php
                                        echo h(
                                            $item[
                                                'item_name'
                                            ]
                                            ?? 'Item'
                                        );
                                        ?>

                                    </div>


                                    <div class="item-qty">

                                        Quantity:

                                        <?php
                                        echo h(
                                            $item[
                                                'quantity'
                                            ]
                                            ?? 1
                                        );
                                        ?>

                                    </div>


                                </div>


                                <div class="item-price">

                                    Rs.

                                    <?php

                                    $itemSubtotal =
                                        $item['subtotal']
                                        ??
                                        (
                                            (
                                                $item[
                                                    'item_price'
                                                ]
                                                ?? 0
                                            )
                                            *
                                            (
                                                $item[
                                                    'quantity'
                                                ]
                                                ?? 1
                                            )
                                        );


                                    echo number_format(
                                        (float)
                                        $itemSubtotal,
                                        2
                                    );

                                    ?>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div
                            style="
                                padding:10px 0;
                                color:#888;
                                font-size:12px;
                            "
                        >

                            Order items are not available.

                        </div>


                    <?php endif; ?>


                </div>


                <?php if (strtolower(trim((string)$status)) === 'pending'): ?>
                    <div class="order-actions">
                    <a
                    href="cancel_order.php?order_id=<?php echo (int)$order['id']; ?>"
                    class="cancel-order-btn"
                    onclick="return confirm('Are you sure you want to cancel this order?');"
                    >
                    <i class="fas fa-times-circle"></i>
                    Cancel Order
                </a>
            </div>
            <?php endif; ?>

                <?php if (!$isCancelled):
                    $isDelivered =
    in_array(
        strtolower(
            trim(
                (string)$status
            )
            ),
            [
                'delivered',
                'completed'
                ],
                true
                ); ?>


                    <!-- =================================================
                         ORDER TRACKING
                    ================================================== -->

                    <div class="tracking">


                        <div
                            class="tracking-header"
                        >


                            <div class="tracking-title">

                                Order Tracking

                            </div>


                            <div class="tracking-current">

                                <?php
                                echo h(
                                    $statusLabel
                                );
                                ?>

                            </div>


                        </div>



                        <div class="progress">


                            <div
                                class="progress-line"
                            >

                                <div
                                    class="progress-fill"
                                    style="
                                        width:
                                        <?php

                                        $progressWidth =
                                            (
                                                (
                                                    $progressStep - 1
                                                )
                                                /
                                                5
                                            )
                                            * 100;

                                        echo
                                            $progressWidth
                                            . '%';

                                        ?>
                                    "
                                ></div>

                            </div>



                            <!-- PLACED -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 1
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-receipt
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Placed
                                </span>

                            </div>



                            <!-- ACCEPTED -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 2
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-circle-check
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Accepted
                                </span>

                            </div>



                            <!-- PREPARING -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 3
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-kitchen-set
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Preparing
                                </span>

                            </div>



                            <!-- READY -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 4
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-box
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Ready
                                </span>

                            </div>



                            <!-- OUT FOR DELIVERY -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 5
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-motorcycle
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Out for Delivery
                                </span>

                            </div>



                            <!-- DELIVERED -->

                            <div
                                class="
                                    progress-step
                                    <?php
                                    echo
                                        $progressStep >= 6
                                        ? 'completed'
                                        : '';
                                    ?>
                                "
                            >

                                <div
                                    class="progress-dot"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-house
                                        "
                                    ></i>

                                </div>


                                <span>
                                    Delivered
                                </span>

                            </div>


                        </div>


                    </div>


                

                <?php $liveTracking = $customerTracking[(int)$order['id']] ?? null; ?>
                <?php if (!$isDelivered && $liveTracking): ?>
                <div class="live-map-card" data-live-order="<?php echo (int)$order['id']; ?>">
                    <div class="live-map-header">
                        <span><i class="fas fa-location-dot"></i> Live Delivery Location</span>
                        <span class="live-map-status" data-live-status>Waiting for rider GPS...</span>
                    </div>
                    <div class="live-map" id="customer-live-map-<?php echo (int)$order['id']; ?>"></div>
                    <div class="live-map-meta" data-live-meta>Map will update automatically when the rider sends a location.</div>
                </div>
                <?php endif; ?>

<?php else: ?>


                    <!-- =================================================
                         CANCELLED
                    ================================================== -->

                    <div class="cancelled-box">


                        <i
                            class="
                                fas
                                fa-circle-xmark
                            "
                        ></i>


                        <strong>
                            Order Cancelled
                        </strong>


                        <br>


                        This order has been cancelled.

                    </div>


                <?php endif; ?>



                <!-- =================================================
                     ORDER FOOTER
                ================================================== -->

                <div class="order-bottom">


                    <div>


                        <span class="total-label">

                            Total Amount

                        </span>


                        <strong
                            class="total-price"
                        >

                            Rs.

                            <?php
                            echo number_format(
                                $total,
                                2
                            );
                            ?>

                        </strong>


                        <div
                            class="payment-method"
                        >

                            Payment:

                            <?php

                            echo h(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $payment
                                    )
                                )
                            );

                            ?>

                        </div>


                    </div>


                    <?php if (
                        file_exists(
                            __DIR__
                            . '/order-details.php'
                        )
                    ): ?>


                        <a
                            href="
                                order-details.php?id=<?php
                                echo (int)
                                $order['id'];
                                ?>
                            "
                            class="view-order-btn"
                        >

                            <i
                                class="
                                    fas
                                    fa-eye
                                "
                            ></i>

                            View Order

                        </a>


                    <?php endif; ?>


                </div>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</main>




<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER_JS */
(function(){
    const maps = {};
    const defaultCenter = [25.3960, 68.3578];
    function initCustomerMap(orderId, lat, lng){
        const el = document.getElementById('customer-live-map-'+orderId);
        if(!el) return;
        const valid = Number.isFinite(lat) && Number.isFinite(lng);
        if(!maps[orderId]){
            maps[orderId] = L.map(el).setView(valid ? [lat,lng] : defaultCenter, 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(maps[orderId]);
        }
        if(valid){
            if(!maps[orderId].marker) maps[orderId].marker = L.marker([lat,lng]).addTo(maps[orderId]);
            else maps[orderId].marker.setLatLng([lat,lng]);
            maps[orderId].setView([lat,lng],15);
        }
    }
    async function poll(orderId){
        try{
            const r = await fetch('live-tracking-data.php?order_id='+encodeURIComponent(orderId),{cache:'no-store'});
            const d = await r.json();
            const card = document.querySelector('[data-live-order="'+orderId+'"]');
            if(!card) return;
            const status = card.querySelector('[data-live-status]');
            const meta = card.querySelector('[data-live-meta]');
            if(d.rider){
            const deliveryStatus = String(d.rider.delivery_status || '').toLowerCase().trim();
            if(deliveryStatus === 'delivered' || deliveryStatus === 'completed'){
                const mapCard = document.querySelector('[data-live-order="'+orderId+'"]');
                if(mapCard){
                    mapCard.remove();
                }
                if(maps[orderId]){
                    maps[orderId].remove();
                    delete maps[orderId];
                }
                
                return true;
            }
                const lat = parseFloat(d.rider.latitude), lng = parseFloat(d.rider.longitude);
                initCustomerMap(orderId, Number.isFinite(lat)?lat:NaN, Number.isFinite(lng)?lng:NaN);
                if(Number.isFinite(lat) && Number.isFinite(lng)){
                    if(status) status.textContent = 'Rider location is live';
                    if(meta) meta.textContent = 'Rider: '+(d.rider.rider_name||'Rider')+' · Last update: '+(d.rider.location_updated_at||'just now');
                }else if(status){
                    status.textContent = 'Waiting for rider GPS...';
                }
            }
        }catch(e){}
    }
    document.querySelectorAll('[data-live-order]').forEach(function(el){
        const id = el.getAttribute('data-live-order');
        initCustomerMap(id,NaN,NaN);
        poll(id);
        const timer = setInterval(async function(){
        const removed = await poll(id);
        if(removed){
            clearInterval(timer);
        }
    },5000);
});
})();
</script>
</body>

</html>