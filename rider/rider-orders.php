<?php

session_start();

require_once '../includes/config.php';


/*
|--------------------------------------------------------------------------
| RIDER AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['rider_logged_in']) ||
    $_SESSION['rider_logged_in'] !== true
) {
    header('Location: rider-login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| RIDER ID
|--------------------------------------------------------------------------
*/

$riderId = isset($_SESSION['rider_id'])
    ? (int) $_SESSION['rider_id']
    : 0;


if ($riderId <= 0) {

    session_unset();
    session_destroy();

    header('Location: rider-login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function riderOrderEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| GET CURRENT RIDER
|--------------------------------------------------------------------------
*/

$rider = [
    'id' => $riderId,
    'full_name' => $_SESSION['rider_name'] ?? 'Rider',
    'status' => 'pending'
];


$riderStmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        vehicle_type,
        status
    FROM riders
    WHERE id = ?
    LIMIT 1
");


if ($riderStmt) {

    $riderStmt->bind_param(
        'i',
        $riderId
    );

    $riderStmt->execute();

    $riderResult =
        $riderStmt->get_result();

    $databaseRider =
        $riderResult->fetch_assoc();

    $riderStmt->close();


    if ($databaseRider) {

        $rider = $databaseRider;

        $_SESSION['rider_name'] =
            $rider['full_name'];

        $_SESSION['rider_status'] =
            $rider['status'];

    }

}


/*
|--------------------------------------------------------------------------
| APPROVAL CHECK
|--------------------------------------------------------------------------
*/

$riderStatus = strtolower(
    trim(
        (string)$rider['status']
    )
);


$isApproved = in_array(
    $riderStatus,
    [
        'approved',
        'active'
    ],
    true
);


/*
|--------------------------------------------------------------------------
| ORDER DATA
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This page reads existing orders.
|
| The actual automatic rider assignment will be connected
| from the restaurant Accept Order action in the next step.
|
|--------------------------------------------------------------------------
*/


$orders = [];

$queryError = '';


/*
|--------------------------------------------------------------------------
| CHECK EXISTING ORDER COLUMNS
|--------------------------------------------------------------------------
|
| We first get the columns that actually exist in orders table.
| This prevents the page from breaking if your existing schema
| uses a slightly different column structure.
|
|--------------------------------------------------------------------------
*/

$orderColumns = [];

$columnsResult = $conn->query("
    SHOW COLUMNS FROM orders
");


if ($columnsResult) {

    while (
        $column =
        $columnsResult->fetch_assoc()
    ) {

        $orderColumns[] =
            $column['Field'];

    }

}


/*
|--------------------------------------------------------------------------
| COLUMN HELPER
|--------------------------------------------------------------------------
*/

$hasOrderColumn = function ($column) use ($orderColumns) {

    return in_array(
        $column,
        $orderColumns,
        true
    );

};


/*
|--------------------------------------------------------------------------
| FIND RIDER ASSIGNMENT COLUMN
|--------------------------------------------------------------------------
*/

$riderColumn = null;


$possibleRiderColumns = [
    'rider_id',
    'delivery_rider_id',
    'assigned_rider_id'
];


foreach (
    $possibleRiderColumns
    as $possibleColumn
) {

    if (
        $hasOrderColumn(
            $possibleColumn
        )
    ) {

        $riderColumn =
            $possibleColumn;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| FIND ORDER STATUS COLUMN
|--------------------------------------------------------------------------
*/

$statusColumn = null;


$possibleStatusColumns = [
    'order_status',
    'status'
];


foreach (
    $possibleStatusColumns
    as $possibleColumn
) {

    if (
        $hasOrderColumn(
            $possibleColumn
        )
    ) {

        $statusColumn =
            $possibleColumn;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| LOAD ORDERS
|--------------------------------------------------------------------------
*/

if ($isApproved) {

    if ($riderColumn !== null) {

        /*
        |--------------------------------------------------------------------------
        | Orders already assigned to this rider
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                o.*
            FROM orders o
            WHERE o.`$riderColumn` = ?
        ";


        /*
        |--------------------------------------------------------------------------
        | Only active delivery-related orders
        |--------------------------------------------------------------------------
        */

        if ($statusColumn !== null) {

            $sql .= "
                AND LOWER(
                    TRIM(
                        o.`$statusColumn`
                    )
                ) NOT IN (
                    'delivered',
                    'cancelled',
                    'completed'
                )
            ";

        }


        $sql .= "
            ORDER BY
                o.id DESC
        ";


        $stmt =
            $conn->prepare($sql);


        if ($stmt) {

            $stmt->bind_param(
                'i',
                $riderId
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

        } else {

            $queryError =
                'Unable to load rider orders.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| ORDER DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

$getOrderValue = function (
    $order,
    $possibleColumns,
    $default = ''
) {

    foreach (
        $possibleColumns
        as $column
    ) {

        if (
            isset(
                $order[$column]
            ) &&
            $order[$column] !== ''
        ) {

            return $order[$column];

        }

    }

    return $default;

};


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Available Orders - Humsafar';


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
        <?= riderOrderEscape($pageTitle) ?>
    </title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f7f7f9;

            color: #252525;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN CONTENT
        |--------------------------------------------------------------------------
        */

        .rider-page-content {

            margin-left: 223px;

            min-height: 100vh;

            padding: 30px;
        }


        /*
        |--------------------------------------------------------------------------
        | TOP HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap: 20px;

            margin-bottom: 25px;
        }


        .page-title h1 {

            margin: 0 0 7px;

            font-size: 27px;

            font-weight: 800;
        }


        .page-title p {

            margin: 0;

            color: #777;

            font-size: 13px;

            line-height: 1.5;
        }


        .refresh-btn {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 11px 16px;

            border-radius: 9px;

            background: #fff;

            border:
                1px solid #e6e6e6;

            color: #e00038;

            text-decoration: none;

            font-size: 12px;

            font-weight: 700;

            transition: .18s ease;
        }


        .refresh-btn:hover {

            background: #fff0f3;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS NOTICE
        |--------------------------------------------------------------------------
        */

        .notice {

            padding: 16px 18px;

            border-radius: 12px;

            margin-bottom: 22px;

            display: flex;

            align-items: flex-start;

            gap: 12px;

            font-size: 13px;

            line-height: 1.5;
        }


        .notice i {

            margin-top: 2px;

            font-size: 16px;
        }


        .notice.pending {

            background: #fff7df;

            color: #765b00;

            border:
                1px solid #f4df9b;
        }


        .notice.blocked {

            background: #fff0f2;

            color: #a40028;

            border:
                1px solid #ffd0da;
        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 16px;

            margin-bottom: 22px;
        }


        .summary-card {

            background: #fff;

            border:
                1px solid #eeeeee;

            border-radius: 13px;

            padding: 19px;
        }


        .summary-label {

            color: #888;

            font-size: 11px;

            margin-bottom: 9px;
        }


        .summary-value {

            font-size: 22px;

            font-weight: 800;

            color: #252525;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .empty-card {

            background: #fff;

            border:
                1px solid #eeeeee;

            border-radius: 14px;

            padding: 60px 25px;

            text-align: center;
        }


        .empty-icon {

            width: 70px;

            height: 70px;

            margin:
                0 auto 18px;

            border-radius: 50%;

            background: #fff0f3;

            color: #e00038;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 27px;
        }


        .empty-card h2 {

            margin:
                0 0 8px;

            font-size: 18px;
        }


        .empty-card p {

            margin: 0 auto;

            max-width: 470px;

            color: #888;

            font-size: 13px;

            line-height: 1.6;
        }


        /*
        |--------------------------------------------------------------------------
        | ORDER CARD
        |--------------------------------------------------------------------------
        */

        .orders-list {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }


        .order-card {

            background: #fff;

            border:
                1px solid #eeeeee;

            border-radius: 14px;

            overflow: hidden;

            transition:
                transform .18s ease,
                box-shadow .18s ease;
        }


        .order-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.06);
        }


        .order-card-header {

            padding:
                16px 18px;

            border-bottom:
                1px solid #f0f0f0;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 15px;
        }


        .order-number {

            font-size: 15px;

            font-weight: 800;
        }


        .order-status {

            padding:
                6px 10px;

            border-radius: 20px;

            background: #fff0f3;

            color: #e00038;

            font-size: 10px;

            font-weight: 800;

            text-transform:
                uppercase;
        }


        .order-body {

            padding: 18px;
        }


        .order-info-row {

            display: flex;

            align-items: flex-start;

            gap: 11px;

            margin-bottom: 14px;
        }


        .order-info-row:last-child {

            margin-bottom: 0;
        }


        .order-info-icon {

            width: 31px;

            height: 31px;

            flex-shrink: 0;

            border-radius: 8px;

            background: #fff0f3;

            color: #e00038;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;
        }


        .order-info-content {

            min-width: 0;

            flex: 1;
        }


        .order-info-label {

            display: block;

            margin-bottom: 3px;

            color: #999;

            font-size: 10px;

            font-weight: 700;

            text-transform:
                uppercase;
        }


        .order-info-value {

            color: #333;

            font-size: 12px;

            font-weight: 600;

            line-height: 1.45;

            word-break: break-word;
        }


        .order-footer {

            padding:
                15px 18px;

            background: #fafafa;

            border-top:
                1px solid #eeeeee;

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 15px;
        }


        .order-amount-label {

            color: #999;

            font-size: 10px;

            display: block;

            margin-bottom: 3px;
        }


        .order-amount {

            font-size: 17px;

            font-weight: 800;

            color: #222;
        }


        .view-btn {

            display: inline-flex;

            align-items: center;

            justify-content:
                center;

            gap: 7px;

            min-width: 110px;

            padding:
                10px 14px;

            border: 0;

            border-radius: 8px;

            background: #e00038;

            color: #fff;

            font-size: 11px;

            font-weight: 800;

            text-decoration: none;

            cursor: pointer;
        }


        .view-btn:hover {

            background: #c80032;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .orders-list {

                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 900px) {

            .rider-page-content {

                margin-left: 0;

                padding: 22px 18px;
            }


            .summary-grid {

                grid-template-columns:
                    1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .rider-page-content {

                padding: 20px 14px;
            }


            .page-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .summary-grid {

                grid-template-columns:
                    1fr;
            }


            .order-footer {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .view-btn {

                width: 100%;
            }

        }

    </style>

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| EXISTING RIDER SIDEBAR
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/rider-sidebar.php';

?>


<main class="rider-page-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Available Orders
            </h1>

            <p>
                View delivery orders assigned to your rider account.
            </p>

        </div>


        <a
            href="rider-orders.php"
            class="refresh-btn"
        >

            <i class="fas fa-rotate-right"></i>

            Refresh

        </a>

    </div>


    <!-- =====================================================
         ACCOUNT STATUS
    ====================================================== -->

    <?php if (!$isApproved): ?>


        <div
            class="notice
            <?= $riderStatus === 'blocked'
                ? 'blocked'
                : 'pending' ?>"
        >

            <i
                class="fas
                <?= $riderStatus === 'blocked'
                    ? 'fa-ban'
                    : 'fa-clock' ?>"
            ></i>


            <div>

                <?php if (
                    $riderStatus === 'blocked'
                ): ?>

                    <strong>
                        Rider Account Blocked
                    </strong>

                    <br>

                    Your rider account is currently blocked.
                    Please contact Humsafar administration.

                <?php else: ?>

                    <strong>
                        Waiting for Admin Approval
                    </strong>

                    <br>

                    Available delivery orders will appear
                    after your rider account is approved.

                <?php endif; ?>

            </div>

        </div>


    <?php endif; ?>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-label">
                Rider
            </div>

            <div class="summary-value">

                <?= riderOrderEscape(
                    $rider['full_name']
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Current Requests
            </div>

            <div class="summary-value">
                <?= count($orders) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Account Status
            </div>

            <div class="summary-value">

                <?= riderOrderEscape(
                    ucfirst($riderStatus)
                ) ?>

            </div>

        </div>


    </div>


    <!-- =====================================================
         ORDERS
    ====================================================== -->

    <?php if (!$isApproved): ?>


        <div class="empty-card">

            <div class="empty-icon">

                <i class="fas fa-lock"></i>

            </div>


            <h2>
                Orders Locked
            </h2>


            <p>
                Your rider account must be approved by
                Humsafar administration before delivery
                orders can be received.
            </p>

        </div>


    <?php elseif ($riderColumn === null): ?>


        <div class="empty-card">

            <div class="empty-icon">

                <i class="fas fa-link"></i>

            </div>


            <h2>
                Rider Assignment Is Not Connected Yet
            </h2>


            <p>
                The rider order page is ready, but the
                automatic restaurant-to-rider assignment
                needs to be connected to the orders system.
            </p>

        </div>


    <?php elseif (!empty($queryError)): ?>


        <div class="empty-card">

            <div class="empty-icon">

                <i class="fas fa-triangle-exclamation"></i>

            </div>


            <h2>
                Unable to Load Orders
            </h2>


            <p>
                <?= riderOrderEscape(
                    $queryError
                ) ?>
            </p>

        </div>


    <?php elseif (empty($orders)): ?>


        <div class="empty-card">

            <div class="empty-icon">

                <i class="fas fa-motorcycle"></i>

            </div>


            <h2>
                No Delivery Orders
            </h2>


            <p>
                You currently have no delivery orders assigned.
                When the restaurant accepts an order and the
                system assigns it to you, the order will appear
                here.
            </p>

        </div>


    <?php else: ?>


        <div class="orders-list">


            <?php foreach (
                $orders
                as $order
            ): ?>


                <?php

                /*
                |--------------------------------------------------------------------------
                | ORDER VALUES
                |--------------------------------------------------------------------------
                */

                $orderId =
                    $getOrderValue(
                        $order,
                        [
                            'id',
                            'order_id'
                        ],
                        '—'
                    );


                $status =
                    $getOrderValue(
                        $order,
                        [
                            'order_status',
                            'status'
                        ],
                        'Assigned'
                    );


                $restaurantName =
                    $getOrderValue(
                        $order,
                        [
                            'restaurant_name'
                        ],
                        'Restaurant'
                    );


                $pickupAddress =
                    $getOrderValue(
                        $order,
                        [
                            'pickup_address',
                            'restaurant_address'
                        ],
                        'Pickup address available in order details'
                    );


                $deliveryAddress =
                    $getOrderValue(
                        $order,
                        [
                            'delivery_address',
                            'address',
                            'customer_address'
                        ],
                        'Customer delivery address'
                    );


                $totalAmount =
                    $getOrderValue(
                        $order,
                        [
                            'total_amount',
                            'total',
                            'grand_total',
                            'amount'
                        ],
                        '0.00'
                    );


                ?>


                <article
                    class="order-card"
                >


                    <div class="order-card-header">


                        <div class="order-number">

                            Order
                            #<?= riderOrderEscape(
                                $orderId
                            ) ?>

                        </div>


                        <span
                            class="order-status"
                        >

                            <?= riderOrderEscape(
                                str_replace(
                                    '_',
                                    ' ',
                                    $status
                                )
                            ) ?>

                        </span>


                    </div>


                    <div class="order-body">


                        <!-- RESTAURANT -->

                        <div class="order-info-row">

                            <div
                                class="order-info-icon"
                            >

                                <i
                                    class="fas fa-store"
                                ></i>

                            </div>


                            <div
                                class="order-info-content"
                            >

                                <span
                                    class="order-info-label"
                                >
                                    Restaurant
                                </span>


                                <div
                                    class="order-info-value"
                                >

                                    <?= riderOrderEscape(
                                        $restaurantName
                                    ) ?>

                                </div>

                            </div>

                        </div>


                        <!-- PICKUP -->

                        <div class="order-info-row">

                            <div
                                class="order-info-icon"
                            >

                                <i
                                    class="fas fa-location-dot"
                                ></i>

                            </div>


                            <div
                                class="order-info-content"
                            >

                                <span
                                    class="order-info-label"
                                >
                                    Pickup
                                </span>


                                <div
                                    class="order-info-value"
                                >

                                    <?= riderOrderEscape(
                                        $pickupAddress
                                    ) ?>

                                </div>

                            </div>

                        </div>


                        <!-- DELIVERY -->

                        <div class="order-info-row">

                            <div
                                class="order-info-icon"
                            >

                                <i
                                    class="fas fa-house"
                                ></i>

                            </div>


                            <div
                                class="order-info-content"
                            >

                                <span
                                    class="order-info-label"
                                >
                                    Delivery
                                </span>


                                <div
                                    class="order-info-value"
                                >

                                    <?= riderOrderEscape(
                                        $deliveryAddress
                                    ) ?>

                                </div>

                            </div>

                        </div>


                    </div>


                    <div class="order-footer">


                        <div>

                            <span
                                class="order-amount-label"
                            >
                                Order Amount
                            </span>


                            <span
                                class="order-amount"
                            >

                                Rs.
                                <?= riderOrderEscape(
                                    number_format(
                                        (float)$totalAmount,
                                        2
                                    )
                                ) ?>

                            </span>

                        </div>


                        <a
                            href="rider-delivery.php?order_id=<?= urlencode(
                                $orderId
                            ) ?>"
                            class="view-btn"
                        >

                            <i
                                class="fas fa-arrow-right"
                            ></i>

                            View Order

                        </a>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


</body>

</html>