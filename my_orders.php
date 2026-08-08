<?php

require_once 'includes/config.php';
require_once 'includes/session.php';


/* =====================================================
   CHECK LOGIN
===================================================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}


$user_id = (int) $_SESSION['user_id'];


/* =====================================================
   HELPER
===================================================== */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =====================================================
   GET ORDERS
===================================================== */

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
AND o.order_status != 'cancelled'

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


$result = $stmt->get_result();


$orders = [];


while (
    $row = $result->fetch_assoc()
) {

    $orders[] = $row;

}


$stmt->close();


/* =====================================================
   STATUS CLASS
===================================================== */

function getStatusClass($status)
{

    switch (strtolower($status)) {

        case 'pending':
            return 'status-pending';

        case 'confirmed':
        case 'accepted':
            return 'status-confirmed';

        case 'preparing':
            return 'status-preparing';

        case 'out_for_delivery':
        case 'out for delivery':
        case 'on_the_way':
            return 'status-delivery';

        case 'delivered':
        case 'completed':
            return 'status-delivered';

        case 'cancelled':
        case 'canceled':
            return 'status-cancelled';

        default:
            return 'status-default';

    }

}


function getStatusIcon($status)
{

    switch (strtolower($status)) {

        case 'pending':
            return 'fa-clock';

        case 'confirmed':
        case 'accepted':
            return 'fa-circle-check';

        case 'preparing':
            return 'fa-kitchen-set';

        case 'out_for_delivery':
        case 'out for delivery':
        case 'on_the_way':
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

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >


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
            background: #f5f6fa;
            color: #333;
            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
        }


        .orders-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }


        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }


        .page-header h1 {
            margin: 0;
            color: #222;
            font-size: 32px;
        }


        .page-header p {
            margin: 7px 0 0;
            color: #777;
        }


        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            background: #fff;
            color: #333;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid #eee;
            font-weight: 600;
        }


        .back-btn:hover {
            background: #f1f1f1;
        }


        /* =================================================
           EMPTY ORDERS
        ================================================= */

        .empty-orders {
            background: #fff;
            border-radius: 20px;
            padding: 65px 25px;
            text-align: center;
            box-shadow:
                0 10px 30px rgba(0,0,0,.06);
        }


        .empty-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #fff1f2;
            color: #E23744;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }


        .empty-orders h2 {
            margin: 0;
            color: #222;
        }


        .empty-orders p {
            color: #777;
            margin: 10px 0 25px;
        }


        .shop-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 22px;
            border-radius: 10px;
            background: #E23744;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
        }


        .shop-btn:hover {
            background: #c91f31;
        }


        /* =================================================
           ORDER CARD
        ================================================= */

        .order-card {
            background: #fff;
            border-radius: 18px;
            margin-bottom: 22px;
            overflow: hidden;
            box-shadow:
                0 8px 25px rgba(0,0,0,.06);
            border: 1px solid #eee;
        }


        .order-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 22px;
            border-bottom: 1px solid #eee;
        }


        .order-info h3 {
            margin: 0;
            color: #222;
            font-size: 18px;
        }


        .order-date {
            margin-top: 6px;
            color: #888;
            font-size: 13px;
        }


        .order-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 13px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
        }


        .status-pending {
            background: #fff5d9;
            color: #a36a00;
        }


        .status-confirmed {
            background: #e8f5ff;
            color: #0874b9;
        }


        .status-preparing {
            background: #fff0df;
            color: #c26400;
        }


        .status-delivery {
            background: #f0eaff;
            color: #6941c6;
        }


        .status-delivered {
            background: #e8f8ef;
            color: #198754;
        }


        .status-cancelled {
            background: #ffe9eb;
            color: #d92d3a;
        }


        .status-default {
            background: #eee;
            color: #555;
        }


        /* =================================================
           RESTAURANT
        ================================================= */

        .restaurant-row {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 18px 22px 10px;
        }


        .restaurant-image {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background: #fff1f2;
            color: #E23744;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            flex-shrink: 0;
        }


        .restaurant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .restaurant-row h4 {
            margin: 0;
            color: #222;
            font-size: 16px;
        }


        .restaurant-row span {
            display: block;
            margin-top: 3px;
            color: #888;
            font-size: 12px;
        }


        /* =================================================
           ORDER ITEMS
        ================================================= */

        .items-container {
            padding: 8px 22px 18px;
        }


        .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }


        .order-item:last-child {
            border-bottom: 0;
        }


        .item-left {
            min-width: 0;
        }


        .item-name {
            color: #222;
            font-weight: 600;
        }


        .item-qty {
            margin-top: 4px;
            color: #888;
            font-size: 13px;
        }


        .item-price {
            color: #222;
            font-weight: 700;
            white-space: nowrap;
        }


        /* =================================================
           ORDER FOOTER
        ================================================= */

        .order-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 22px;
            background: #fafafa;
            border-top: 1px solid #eee;
        }


        .total-label {
            color: #777;
            font-size: 13px;
        }


        .total-price {
            display: block;
            margin-top: 3px;
            color: #E23744;
            font-size: 21px;
            font-weight: 800;
        }


        .view-order-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 9px;
            background: #E23744;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }


        .view-order-btn:hover {
            background: #c91f31;
        }
.cancel-order-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 17px;
    border-radius: 9px;
    background: #dc3545;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
}

.cancel-order-btn:hover {
    background: #bb2d3b;
}

        /* =================================================
           PAYMENT
        ================================================= */

        .payment-method {
            margin-top: 3px;
            color: #777;
            font-size: 12px;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 700px) {

            .orders-container {
                margin-top: 25px;
                padding: 0 12px 35px;
            }


            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }


            .page-header h1 {
                font-size: 27px;
            }


            .order-top {
                align-items: flex-start;
                flex-direction: column;
                padding: 17px;
            }


            .restaurant-row {
                padding-left: 17px;
                padding-right: 17px;
            }


            .items-container {
                padding-left: 17px;
                padding-right: 17px;
            }


            .order-bottom {
                align-items: flex-start;
                flex-direction: column;
                padding: 17px;
            }


            .view-order-btn {
                width: 100%;
                justify-content: center;
            }


            .order-item {
                align-items: flex-start;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="orders-container">


    <!-- =================================================
         PAGE HEADER
    ================================================= -->

    <div class="page-header">


        <div>

            <h1>

                <i class="fas fa-box-open"></i>

                My Orders

            </h1>


            <p>
                View your recent and previous orders.
            </p>

        </div>


        <a
            href="index.php"
            class="back-btn"
        >

            <i class="fas fa-arrow-left"></i>

            Continue Shopping

        </a>


    </div>


    <?php if (empty($orders)) { ?>


        <!-- =================================================
             NO ORDERS
        ================================================= -->

        <div class="empty-orders">


            <div class="empty-icon">

                <i class="fas fa-receipt"></i>

            </div>


            <h2>
                No Orders Yet
            </h2>


            <p>
                You haven't placed any orders yet.
            </p>


            <a
                href="index.php"
                class="shop-btn"
            >

                <i class="fas fa-utensils"></i>

                Start Ordering

            </a>


        </div>


    <?php } else { ?>


        <!-- =================================================
             ORDERS LIST
        ================================================= -->

        <?php foreach (
            $orders as $order
        ) { ?>


            <?php

            $status =
                $order['order_status'];

            $statusClass =
                getStatusClass(
                    $status
                );

            $statusIcon =
                getStatusIcon(
                    $status
                );


            $statusText =
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $status
                    )
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

            ?>


            <div class="order-card">


                <!-- =================================================
                     ORDER HEADER
                ================================================= -->

                <div class="order-top">


                    <div class="order-info">


                        <h3>

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

                            <i class="far fa-calendar"></i>

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
                            <?php
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
                                $statusText
                            );
                        ?>

                    </div>


                </div>


                <!-- =================================================
                     RESTAURANT
                ================================================= -->

                <div class="restaurant-row">


                    <div class="restaurant-image">


                        <?php if (
                            !empty(
                                $order[
                                    'restaurant_image'
                                ]
                            )
                        ) { ?>


                            <img
                                src="
                                    assets/images/restaurants/<?php
                                        echo h(
                                            $order[
                                                'restaurant_image'
                                            ]
                                        );
                                    ?>
                                "
                                alt="<?php
                                    echo h(
                                        $order[
                                            'restaurant_name'
                                        ]
                                    );
                                ?>"
                            >


                        <?php } else { ?>


                            <i class="fas fa-store"></i>


                        <?php } ?>


                    </div>


                    <div>


                        <h4>

                            <?php
                                echo h(
                                    $order[
                                        'restaurant_name'
                                    ] ??
                                    'Restaurant'
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
                ================================================= -->

                <div class="items-container">


                    <?php

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


                    $items = [];


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


                    <?php if (
                        !empty($items)
                    ) { ?>


                        <?php foreach (
                            $items as $item
                        ) { ?>


                            <div class="order-item">


                                <div class="item-left">


                                    <div class="item-name">

                                        <?php
                                            echo h(
                                                $item[
                                                    'item_name'
                                                ]
                                            );
                                        ?>

                                    </div>


                                    <div class="item-qty">

                                        Qty:
                                        <?php
                                            echo (int)
                                                $item[
                                                    'quantity'
                                                ];
                                        ?>

                                        × Rs.

                                        <?php
                                            echo number_format(
                                                (float)
                                                $item[
                                                    'item_price'
                                                ],
                                                2
                                            );
                                        ?>

                                    </div>


                                </div>


                                <div class="item-price">

                                    Rs.

                                    <?php
                                        echo number_format(
                                            (float)
                                            $item[
                                                'subtotal'
                                            ],
                                            2
                                        );
                                    ?>

                                </div>


                            </div>


                        <?php } ?>


                    <?php } else { ?>


                        <div
                            style="
                                padding:12px 0;
                                color:#888;
                            "
                        >

                            Order items not available.

                        </div>


                    <?php } ?>


                </div>


<!-- =================================================
     ORDER FOOTER
================================================= -->

<div class="order-bottom">


    <div>

        <span class="total-label">
            Total Amount
        </span>


        <strong class="total-price">

            Rs.

            <?php
                echo number_format(
                    (float)
                    $order['total'],
                    2
                );
            ?>

        </strong>


        <div class="payment-method">

            <i class="fas fa-credit-card"></i>

            <?php

            if (
                $order['payment_method'] === 'card'
            ) {

                echo 'Card Payment';

            } elseif (
                $order['payment_method'] === 'online'
            ) {

                echo 'Online Payment';

            } else {

                echo 'Cash on Delivery';

            }

            ?>

        </div>

    </div>


    <!-- BUTTONS -->

    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
        "
    >


        <!-- VIEW ORDER -->

        <a
            href="order_success.php?order_id=<?php echo (int)$order['id']; ?>"
            class="view-order-btn"
        >

            <i class="fas fa-eye"></i>

            View Order

        </a>


        <!-- CANCEL ORDER -->

        <?php

        $currentStatus = strtolower(
            trim(
                $order['order_status']
            )
        );


        $canCancel = in_array(
            $currentStatus,
            [
                'pending',
                'confirmed',
                'accepted'
            ],
            true
        );

        ?>


        <?php if ($canCancel) { ?>

            <a
                href="cancel_order.php?order_id=<?php echo (int)$order['id']; ?>"
                class="cancel-order-btn"
                onclick="return confirm('Are you sure you want to cancel this order?');"
            >

                <i class="fas fa-xmark"></i>

                Cancel Order

            </a>

        <?php } ?>


    </div>


</div>

        <?php } ?>


    <?php } ?>


</div>


</body>

</html>