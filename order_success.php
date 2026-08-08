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
   CHECK ORDER ID
===================================================== */

if (
    !isset($_GET['order_id']) ||
    (int)$_GET['order_id'] <= 0
) {

    header("Location: cart.php");
    exit;

}


$order_id = (int) $_GET['order_id'];


/* =====================================================
   GET ORDER
===================================================== */

$orderStmt = $conn->prepare("
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

        r.name AS restaurant_name,
        r.image AS restaurant_image

    FROM orders o

    LEFT JOIN restaurants r
        ON o.restaurant_id = r.id

    WHERE o.id = ?
    AND o.user_id = ?

    LIMIT 1
");


if (!$orderStmt) {
    die("Database error: " . $conn->error);
}


$orderStmt->bind_param(
    "ii",
    $order_id,
    $user_id
);


$orderStmt->execute();

$orderResult =
    $orderStmt->get_result();


$order =
    $orderResult->fetch_assoc();


$orderStmt->close();


/* =====================================================
   CHECK ORDER
===================================================== */

if (!$order) {

    header("Location: cart.php");
    exit;

}


/* =====================================================
   GET ORDER ITEMS
===================================================== */

$itemStmt = $conn->prepare("
    SELECT
        oi.id,
        oi.menu_item_id,
        oi.item_name,
        oi.item_price,
        oi.quantity,
        oi.subtotal

    FROM order_items oi

    WHERE oi.order_id = ?

    ORDER BY oi.id ASC
");


if (!$itemStmt) {
    die("Database error: " . $conn->error);
}


$itemStmt->bind_param(
    "i",
    $order_id
);


$itemStmt->execute();

$itemResult =
    $itemStmt->get_result();


$orderItems = array();


while (
    $item = $itemResult->fetch_assoc()
) {

    $orderItems[] = $item;

}


$itemStmt->close();


/* =====================================================
   GET DELIVERY ADDRESS
===================================================== */

$address = null;


if (
    !empty($order['address_id'])
) {

    $addressStmt = $conn->prepare("
        SELECT
            id,
            address_type,
            label,
            full_name,
            phone,
            address,
            city,
            area,
            landmark

        FROM addresses

        WHERE id = ?
        AND user_id = ?

        LIMIT 1
    ");


    if ($addressStmt) {

        $addressStmt->bind_param(
            "ii",
            $order['address_id'],
            $user_id
        );


        $addressStmt->execute();

        $addressResult =
            $addressStmt->get_result();


        $address =
            $addressResult->fetch_assoc();


        $addressStmt->close();

    }

}


/* =====================================================
   PAYMENT TEXT
===================================================== */

$paymentText =
    'Cash on Delivery';


if (
    $order['payment_method'] === 'card'
) {

    $paymentText =
        'Card Payment';

} elseif (
    $order['payment_method'] === 'online'
) {

    $paymentText =
        'Online Payment';

}


/* =====================================================
   STATUS TEXT
===================================================== */

$statusText =
    ucfirst(
        str_replace(
            '_',
            ' ',
            $order['order_status']
        )
    );


/* =====================================================
   FORMAT DATE
===================================================== */

$orderDate = '';

if (!empty($order['created_at'])) {

    $timestamp =
        strtotime(
            $order['created_at']
        );


    if ($timestamp !== false) {

        $orderDate =
            date(
                'd M Y, h:i A',
                $timestamp
            );

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
        Order Confirmed - Humsafar
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


        .success-container {
            max-width: 1000px;
            margin: 45px auto;
            padding: 0 20px 50px;
        }


        .success-card {
            background: #fff;
            border-radius: 22px;
            padding: 40px;
            box-shadow:
                0 12px 35px rgba(0,0,0,.08);
        }


        .success-header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }


        .success-icon {
            width: 85px;
            height: 85px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #e9f8ef;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }


        .success-header h1 {
            margin: 0;
            font-size: 34px;
            color: #222;
        }


        .success-header p {
            margin: 10px 0 0;
            color: #777;
            font-size: 16px;
        }


        .order-number {
            display: inline-block;
            margin-top: 18px;
            padding: 10px 18px;
            border-radius: 25px;
            background: #fff4f4;
            color: #E23744;
            font-weight: 700;
            font-size: 16px;
        }


        .order-meta {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 15px;
            margin: 30px 0;
        }


        .meta-box {
            background: #f8f9fa;
            border-radius: 14px;
            padding: 18px;
            text-align: center;
        }


        .meta-box i {
            color: #E23744;
            font-size: 21px;
            margin-bottom: 8px;
        }


        .meta-box strong {
            display: block;
            color: #222;
            margin-top: 5px;
        }


        .meta-box span {
            display: block;
            margin-top: 4px;
            color: #777;
            font-size: 13px;
        }


        .section {
            margin-top: 30px;
        }


        .section h2 {
            margin: 0 0 18px;
            color: #222;
            font-size: 22px;
        }


        .restaurant-box {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px;
            border: 1px solid #eee;
            border-radius: 14px;
        }


        .restaurant-image {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            overflow: hidden;
            background: #fff4f4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #E23744;
            font-size: 27px;
            flex-shrink: 0;
        }


        .restaurant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .restaurant-box h3 {
            margin: 0;
            color: #222;
        }


        .items-list {
            border: 1px solid #eee;
            border-radius: 14px;
            overflow: hidden;
        }


        .order-item {
            display: grid;
            grid-template-columns:
                1fr auto;
            gap: 20px;
            padding: 16px 18px;
            border-bottom: 1px solid #eee;
        }


        .order-item:last-child {
            border-bottom: 0;
        }


        .item-name {
            font-weight: 700;
            color: #222;
        }


        .item-details {
            margin-top: 5px;
            color: #777;
            font-size: 14px;
        }


        .item-total {
            font-weight: 700;
            color: #222;
            white-space: nowrap;
        }


        .address-box {
            padding: 20px;
            border-radius: 14px;
            background: #f8f9fa;
            line-height: 1.7;
        }


        .address-box h3 {
            margin: 0 0 8px;
            color: #222;
        }


        .address-box p {
            margin: 3px 0;
            color: #666;
        }


        .summary {
            margin-top: 20px;
            padding: 20px;
            border-radius: 14px;
            background: #f8f9fa;
        }


        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 12px;
            color: #666;
        }


        .summary-row strong {
            color: #222;
        }


        .summary-divider {
            height: 1px;
            background: #ddd;
            margin: 18px 0;
        }


        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 23px;
            font-weight: 700;
            color: #222;
        }


        .summary-total span:last-child {
            color: #E23744;
        }


        .note {
            padding: 17px;
            background: #fffaf0;
            border-left: 4px solid #f0ad00;
            border-radius: 8px;
            color: #666;
        }


        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 35px;
        }


        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px 20px;
            border-radius: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: .25s;
        }


        .btn-primary {
            background: #E23744;
            color: #fff;
        }


        .btn-primary:hover {
            background: #c91f31;
        }
        .btn-cancel {
         background: #dc3545;
         color: #fff;
        }

        .btn-cancel:hover {
          background: #bb2d3b;
        }


        .btn-secondary {
            background: #eee;
            color: #333;
        }


        .btn-secondary:hover {
            background: #ddd;
        }


        .secure-text {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 13px;
        }


        @media (max-width: 700px) {

            .success-container {
                margin-top: 25px;
                padding: 0 12px 30px;
            }


            .success-card {
                padding: 22px;
                border-radius: 16px;
            }


            .success-header h1 {
                font-size: 27px;
            }


            .order-meta {
                grid-template-columns: 1fr;
            }


            .action-buttons {
                flex-direction: column;
            }


            .order-item {
                grid-template-columns: 1fr;
            }


            .item-total {
                text-align: left;
            }

        }

    </style>

</head>


<body>


<div class="success-container">


    <div class="success-card">


        <!-- =================================================
             SUCCESS HEADER
        ================================================= -->

        <div class="success-header">


            <div class="success-icon">

                <i class="fas fa-check"></i>

            </div>


            <h1>
                Order Placed Successfully!
            </h1>


            <p>
                Thank you for your order.
                Your order has been received.
            </p>


            <div class="order-number">

                <i class="fas fa-receipt"></i>

                Order #
                <?php
                    echo h(
                        $order['order_number']
                    );
                ?>

            </div>


        </div>


        <!-- =================================================
             ORDER META
        ================================================= -->

        <div class="order-meta">


            <div class="meta-box">

                <i class="fas fa-calendar-check"></i>

                <strong>
                    <?php
                        echo h($orderDate);
                    ?>
                </strong>

                <span>
                    Order Date
                </span>

            </div>


            <div class="meta-box">

                <i class="fas fa-credit-card"></i>

                <strong>
                    <?php
                        echo h($paymentText);
                    ?>
                </strong>

                <span>
                    Payment Method
                </span>

            </div>


            <div class="meta-box">

                <i class="fas fa-clock"></i>

                <strong>
                    <?php
                        echo h($statusText);
                    ?>
                </strong>

                <span>
                    Order Status
                </span>

            </div>


        </div>


        <!-- =================================================
             RESTAURANT
        ================================================= -->

        <div class="section">


            <h2>

                <i class="fas fa-store"></i>

                Restaurant

            </h2>


            <div class="restaurant-box">


                <div class="restaurant-image">

                    <?php if (
                        !empty(
                            $order['restaurant_image']
                        )
                    ) { ?>

                        <img
                            src="assets/images/restaurants/<?php
                                echo h(
                                    $order[
                                        'restaurant_image'
                                    ]
                                );
                            ?>"
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

                    <h3>

                        <?php
                            echo h(
                                $order[
                                    'restaurant_name'
                                ]
                            );
                        ?>

                    </h3>

                </div>


            </div>

        </div>


        <!-- =================================================
             ORDER ITEMS
        ================================================= -->

        <div class="section">


            <h2>

                <i class="fas fa-bag-shopping"></i>

                Ordered Items

            </h2>


            <div class="items-list">


                <?php if (
                    !empty($orderItems)
                ) { ?>


                    <?php foreach (
                        $orderItems as $item
                    ) { ?>


                        <div class="order-item">


                            <div>

                                <div class="item-name">

                                    <?php
                                        echo h(
                                            $item[
                                                'item_name'
                                            ]
                                        );
                                    ?>

                                </div>


                                <div class="item-details">

                                    Qty:
                                    <?php
                                        echo (int)
                                            $item[
                                                'quantity'
                                            ];
                                    ?>

                                    ×

                                    Rs.
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


                            <div class="item-total">

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
                            padding:20px;
                            color:#777;
                        "
                    >

                        No order items found.

                    </div>


                <?php } ?>


            </div>


        </div>


        <!-- =================================================
             DELIVERY ADDRESS
        ================================================= -->

        <?php if ($address) { ?>


            <div class="section">


                <h2>

                    <i class="fas fa-location-dot"></i>

                    Delivery Address

                </h2>


                <div class="address-box">


                    <h3>

                        <?php

                        if (
                            !empty(
                                $address['label']
                            )
                        ) {

                            echo h(
                                $address['label']
                            );

                        } else {

                            echo ucfirst(
                                h(
                                    $address[
                                        'address_type'
                                    ]
                                )
                            );

                        }

                        ?>

                    </h3>


                    <p>

                        <strong>

                            <?php
                                echo h(
                                    $address[
                                        'full_name'
                                    ]
                                );
                            ?>

                        </strong>

                    </p>


                    <p>

                        <i class="fas fa-phone"></i>

                        <?php
                            echo h(
                                $address['phone']
                            );
                        ?>

                    </p>


                    <p>

                        <i class="fas fa-location-dot"></i>

                        <?php
                            echo h(
                                $address['address']
                            );
                        ?>


                        <?php if (
                            !empty(
                                $address['area']
                            )
                        ) { ?>

                            ,
                            <?php
                                echo h(
                                    $address['area']
                                );
                            ?>

                        <?php } ?>


                        ,
                        <?php
                            echo h(
                                $address['city']
                            );
                        ?>

                    </p>


                    <?php if (
                        !empty(
                            $address['landmark']
                        )
                    ) { ?>

                        <p>

                            <i class="fas fa-map-pin"></i>

                            Landmark:
                            <?php
                                echo h(
                                    $address[
                                        'landmark'
                                    ]
                                );
                            ?>

                        </p>

                    <?php } ?>


                </div>

            </div>


        <?php } ?>


        <!-- =================================================
             ORDER NOTE
        ================================================= -->

        <?php if (
            !empty(
                $order['customer_note']
            )
        ) { ?>


            <div class="section">


                <h2>

                    <i class="fas fa-note-sticky"></i>

                    Order Note

                </h2>


                <div class="note">

                    <?php
                        echo nl2br(
                            h(
                                $order[
                                    'customer_note'
                                ]
                            )
                        );
                    ?>

                </div>


            </div>


        <?php } ?>


        <!-- =================================================
             PRICE SUMMARY
        ================================================= -->

        <div class="section">


            <h2>

                <i class="fas fa-receipt"></i>

                Payment Summary

            </h2>


            <div class="summary">


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <strong>

                        Rs.
                        <?php
                            echo number_format(
                                (float)
                                $order[
                                    'subtotal'
                                ],
                                2
                            );
                        ?>

                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Delivery Fee
                    </span>

                    <strong>

                        Rs.
                        <?php
                            echo number_format(
                                (float)
                                $order[
                                    'delivery_fee'
                                ],
                                2
                            );
                        ?>

                    </strong>

                </div>


                <?php if (
                    (float)$order['discount'] > 0
                ) { ?>


                    <div class="summary-row">

                        <span>
                            Discount
                        </span>

                        <strong
                            style="color:#198754;"
                        >

                            − Rs.
                            <?php
                                echo number_format(
                                    (float)
                                    $order[
                                        'discount'
                                    ],
                                    2
                                );
                            ?>

                        </strong>

                    </div>


                <?php } ?>


                <div class="summary-divider"></div>


                <div class="summary-total">

                    <span>
                        Total
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


<!-- =================================================
     BUTTONS
================================================= -->

<div class="action-buttons">


    <!-- CONTINUE SHOPPING -->

    <a
        href="index.php"
        class="btn btn-secondary"
    >

        <i class="fas fa-house"></i>

        Continue Shopping

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
            class="btn btn-cancel"
            onclick="return confirm('Are you sure you want to cancel this order?');"
        >

            <i class="fas fa-xmark"></i>

            Cancel Order

        </a>

    <?php } ?>


    <!-- MY ORDERS -->

    <a
        href="my_orders.php"
        class="btn btn-primary"
    >

        <i class="fas fa-box"></i>

        My Orders

    </a>


</div>


<div class="secure-text">

    <i class="fas fa-shield-halved"></i>

    Thank you for choosing Humsafar.

</div>

</body>
</html>
