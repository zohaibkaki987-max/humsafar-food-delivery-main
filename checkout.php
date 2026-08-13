<?php

require_once 'includes/config.php';
require_once 'includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;
}


$user_id = (int) $_SESSION['user_id'];


/* =====================================================
   HELPER
===================================================== */

if (!function_exists('h')) {

    function h($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/* =====================================================
   SUCCESS STATE
===================================================== */

$orderSuccess = false;

$successOrderNumber = '';

$successOrderId = 0;


/* =====================================================
   ERROR
===================================================== */

$errorMessage = '';


/* =====================================================
   PAYMENT FROM CART
===================================================== */

$paymentFromCart =
    isset($_GET['payment'])
    ? strtolower(
        trim(
            $_GET['payment']
        )
    )
    : 'cod';


if (
    $paymentFromCart !== 'cod'
    &&
    $paymentFromCart !== 'card'
) {

    $paymentFromCart = 'cod';
}


/* =====================================================
   ADDRESS FROM CART
===================================================== */

$addressFromCart =
    isset($_GET['address'])
    ? trim(
        $_GET['address']
    )
    : '';


/* =====================================================
   CART DATA
===================================================== */

$cartItems = [];

$totalItems = 0;

$subTotal = 0;

$deliveryFee = 0;

$grandTotal = 0;

$restaurantID = 0;

$restaurantName = '';

$restaurantImage = '';

$deliveryTime = '';


$cartSql = "
    SELECT

        cart.id AS cart_id,
        cart.quantity,

        menu_items.id AS menu_id,
        menu_items.restaurant_id,
        menu_items.name AS item_name,
        menu_items.description AS item_description,
        menu_items.price AS item_price,
        menu_items.image AS item_image,

        restaurants.name AS restaurant_name,
        restaurants.image AS restaurant_image,
        restaurants.delivery_time,
        restaurants.delivery_fee

    FROM cart

    INNER JOIN menu_items
        ON cart.menu_item_id = menu_items.id

    INNER JOIN restaurants
        ON menu_items.restaurant_id = restaurants.id

    WHERE cart.user_id = ?

    ORDER BY cart.id DESC
";


$cartStmt =
    $conn->prepare(
        $cartSql
    );


if (!$cartStmt) {

    die(
        "Cart query error: "
        .
        $conn->error
    );
}


$cartStmt->bind_param(
    "i",
    $user_id
);


$cartStmt->execute();


$cartResult =
    $cartStmt->get_result();


while (
    $row =
    $cartResult->fetch_assoc()
) {


    if (
        $restaurantID === 0
    ) {

        $restaurantID =
            (int)
            $row['restaurant_id'];

        $restaurantName =
            $row['restaurant_name']
            ??
            '';

        $restaurantImage =
            $row['restaurant_image']
            ??
            '';

        $deliveryTime =
            $row['delivery_time']
            ??
            '';

        $deliveryFee =
            (float)
            (
                $row['delivery_fee']
                ??
                0
            );
    }


    $quantity =
        (int)
        $row['quantity'];


    $itemPrice =
        (float)
        $row['item_price'];


    $itemSubtotal =
        $itemPrice
        *
        $quantity;


    $row['quantity'] =
        $quantity;


    $row['item_price'] =
        $itemPrice;


    $row['item_subtotal'] =
        $itemSubtotal;


    $totalItems +=
        $quantity;


    $subTotal +=
        $itemSubtotal;


    $cartItems[] =
        $row;
}


$cartStmt->close();


/* =====================================================
   EMPTY CART
===================================================== */

if (
    empty($cartItems)
    &&
    !isset($_GET['success'])
) {

    header("Location: cart.php");
    exit;
}


/* =====================================================
   TOTAL
===================================================== */

$grandTotal =
    $subTotal
    +
    $deliveryFee;


/* =====================================================
   SAVED ADDRESSES
===================================================== */

$addresses = [];


$addressSql = "
    SELECT

        id,
        address_title,
        full_name,
        phone,
        address,
        area,
        city,
        delivery_instructions,
        latitude,
        longitude,
        is_default

    FROM customer_addresses

    WHERE user_id = ?

    ORDER BY
        is_default DESC,
        id DESC
";


$addressStmt =
    $conn->prepare(
        $addressSql
    );


if ($addressStmt) {

    $addressStmt->bind_param(
        "i",
        $user_id
    );


    $addressStmt->execute();


    $addressResult =
        $addressStmt->get_result();


    while (
        $address =
        $addressResult->fetch_assoc()
    ) {

        $addresses[] =
            $address;
    }


    $addressStmt->close();
}


/* =====================================================
   SELECTED ADDRESS
===================================================== */

$selectedAddressId = 0;


/* =====================================================
   FIRST: ADDRESS SENT FROM CART
===================================================== */

if (
    $addressFromCart !== ''
) {


    foreach (
        $addresses
        as $address
    ) {


        $fullAddress =
            trim(
                $address['address']
            );


        if (
            !empty(
                $address['area']
            )
        ) {

            $fullAddress .=
                ', '
                .
                $address['area'];
        }


        if (
            !empty(
                $address['city']
            )
        ) {

            $fullAddress .=
                ', '
                .
                $address['city'];
        }


        if (
            !empty(
                $address['phone']
            )
        ) {

            $fullAddress .=
                ' | '
                .
                $address['phone'];
        }


        if (
            strcasecmp(
                $fullAddress,
                $addressFromCart
            ) === 0
        ) {

            $selectedAddressId =
                (int)
                $address['id'];

            break;
        }
    }
}


/* =====================================================
   SECOND: DEFAULT ADDRESS
===================================================== */

if (
    $selectedAddressId === 0
) {


    foreach (
        $addresses
        as $address
    ) {

        if (
            (int)
            $address['is_default']
            ===
            1
        ) {

            $selectedAddressId =
                (int)
                $address['id'];

            break;
        }
    }
}


/* =====================================================
   SUCCESS PAGE
===================================================== */

if (
    isset(
        $_GET['success']
    )
    &&
    $_GET['success'] === '1'
) {

    $orderSuccess = true;

    $successOrderNumber =
        isset(
            $_GET['order']
        )
        ?
        trim(
            $_GET['order']
        )
        :
        '';

    $successOrderId =
        isset(
            $_GET['order_id']
        )
        ?
        (int)
        $_GET['order_id']
        :
        0;
}


/* =====================================================
   PLACE ORDER
===================================================== */

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'
) {


    $selectedAddressId =
        isset(
            $_POST['address_id']
        )
        ?
        (int)
        $_POST['address_id']
        :
        0;


    $selectedPayment =
        isset(
            $_POST['payment_method']
        )
        ?
        strtolower(
            trim(
                $_POST['payment_method']
            )
        )
        :
        '';


    /* =================================================
       VALIDATE ADDRESS
    ================================================= */

    if (
        $selectedAddressId <= 0
    ) {

        $errorMessage =
            "Please select a delivery address.";
    }


    /* =================================================
       VALIDATE PAYMENT
    ================================================= */

    if (
        $errorMessage === ''
        &&
        (
            $selectedPayment !== 'cod'
            &&
            $selectedPayment !== 'card'
        )
    ) {

        $errorMessage =
            "Please select a payment method.";
    }


    /* =================================================
       VERIFY ADDRESS
    ================================================= */

    if (
        $errorMessage === ''
    ) {


        $verifyAddressSql = "
            SELECT id

            FROM customer_addresses

            WHERE id = ?

            AND user_id = ?

            LIMIT 1
        ";


        $verifyAddressStmt =
            $conn->prepare(
                $verifyAddressSql
            );


        if (
            !$verifyAddressStmt
        ) {

            $errorMessage =
                "Unable to verify delivery address.";

        } else {


            $verifyAddressStmt->bind_param(
                "ii",
                $selectedAddressId,
                $user_id
            );


            $verifyAddressStmt->execute();


            $verifyAddressResult =
                $verifyAddressStmt->get_result();


            if (
                $verifyAddressResult->num_rows
                ===
                0
            ) {

                $errorMessage =
                    "Selected address is invalid.";
            }


            $verifyAddressStmt->close();
        }
    }


    /* =================================================
       PAYMENT NAME
    ================================================= */

    if (
        $selectedPayment === 'cod'
    ) {

        $paymentMethod =
            'Cash on Delivery';

    } else {

        $paymentMethod =
            'Card Payment';
    }


    /* =================================================
       CREATE ORDER NUMBER
    ================================================= */

    if (
        $errorMessage === ''
    ) {

        $orderNumber =
            'HS'
            .
            date('YmdHis')
            .
            rand(
                100,
                999
            );
    }


    /* =================================================
       ORDER STATUS
    ================================================= */

    $orderStatus =
        'pending';


    $discount = 0;


    /* =================================================
       START TRANSACTION
    ================================================= */

    if (
        $errorMessage === ''
    ) {

        $conn->begin_transaction();


        try {


            /* =========================================
               RE-CHECK CART INSIDE TRANSACTION
            ========================================= */

            $orderCartSql = "
                SELECT

                    cart.id AS cart_id,
                    cart.quantity,

                    menu_items.id AS menu_id,
                    menu_items.restaurant_id,
                    menu_items.name AS item_name,
                    menu_items.price AS item_price,
                    menu_items.image AS item_image,

                    restaurants.name AS restaurant_name,
                    restaurants.image AS restaurant_image,
                    restaurants.delivery_time,
                    restaurants.delivery_fee

                FROM cart

                INNER JOIN menu_items
                    ON cart.menu_item_id =
                       menu_items.id

                INNER JOIN restaurants
                    ON menu_items.restaurant_id =
                       restaurants.id

                WHERE cart.user_id = ?

                ORDER BY cart.id ASC
            ";


            $orderCartStmt =
                $conn->prepare(
                    $orderCartSql
                );


            if (
                !$orderCartStmt
            ) {

                throw new Exception(
                    "Unable to load cart."
                );
            }


            $orderCartStmt->bind_param(
                "i",
                $user_id
            );


            $orderCartStmt->execute();


            $orderCartResult =
                $orderCartStmt->get_result();


            $orderCartItems = [];

            $orderSubTotal = 0;

            $orderDeliveryFee = 0;

            $orderRestaurantId = 0;


            while (
                $cartRow =
                $orderCartResult->fetch_assoc()
            ) {


                if (
                    $orderRestaurantId === 0
                ) {

                    $orderRestaurantId =
                        (int)
                        $cartRow[
                            'restaurant_id'
                        ];


                    $orderDeliveryFee =
                        (float)
                        (
                            $cartRow[
                                'delivery_fee'
                            ]
                            ??
                            0
                        );
                }


                $cartQuantity =
                    (int)
                    $cartRow[
                        'quantity'
                    ];


                $cartPrice =
                    (float)
                    $cartRow[
                        'item_price'
                    ];


                $cartSubtotal =
                    $cartPrice
                    *
                    $cartQuantity;


                $cartRow[
                    'quantity'
                ] =
                    $cartQuantity;


                $cartRow[
                    'item_price'
                ] =
                    $cartPrice;


                $cartRow[
                    'item_subtotal'
                ] =
                    $cartSubtotal;


                $orderSubTotal +=
                    $cartSubtotal;


                $orderCartItems[] =
                    $cartRow;
            }


            $orderCartStmt->close();


            if (
                empty(
                    $orderCartItems
                )
            ) {

                throw new Exception(
                    "Your cart is empty."
                );
            }


            /* =========================================
               CALCULATE FINAL TOTAL
            ========================================= */

            $orderTotal =
                $orderSubTotal
                +
                $orderDeliveryFee
                -
                $discount;


            /* =========================================
               INSERT ORDER
            ========================================= */

            $insertOrderSql = "
                INSERT INTO orders
                (
                    user_id,
                    order_number,
                    restaurant_id,
                    address_id,
                    payment_method,
                    subtotal,
                    delivery_fee,
                    discount,
                    total,
                    order_status
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";


            $insertOrderStmt =
                $conn->prepare(
                    $insertOrderSql
                );


            if (
                !$insertOrderStmt
            ) {

                throw new Exception(
                    "Order query error: "
                    .
                    $conn->error
                );
            }


            /*
             * i = user_id
             * s = order_number
             * i = restaurant_id
             * i = address_id
             * s = payment_method
             * d = subtotal
             * d = delivery_fee
             * d = discount
             * d = total
             * s = order_status
             */

            $insertOrderStmt->bind_param(
                "isiisdddds",
                $user_id,
                $orderNumber,
                $orderRestaurantId,
                $selectedAddressId,
                $paymentMethod,
                $orderSubTotal,
                $orderDeliveryFee,
                $discount,
                $orderTotal,
                $orderStatus
            );


            if (
                !$insertOrderStmt->execute()
            ) {

                throw new Exception(
                    "Unable to create order: "
                    .
                    $insertOrderStmt->error
                );
            }


            $newOrderId =
                (int)
                $insertOrderStmt->insert_id;


            $insertOrderStmt->close();


            /* =========================================
               INSERT ORDER ITEMS
            ========================================= */

            foreach (
                $orderCartItems
                as $orderItem
            ) {


                $orderMenuItemId =
                    (int)
                    $orderItem[
                        'menu_id'
                    ];


                $orderItemName =
                    $orderItem[
                        'item_name'
                    ];


                $orderItemPrice =
                    (float)
                    $orderItem[
                        'item_price'
                    ];


                $orderQuantity =
                    (int)
                    $orderItem[
                        'quantity'
                    ];


                $orderItemSubtotal =
                    (float)
                    $orderItem[
                        'item_subtotal'
                    ];


                $insertItemSql = "
                    INSERT INTO order_items
                    (
                        order_id,
                        menu_item_id,
                        item_name,
                        item_price,
                        quantity,
                        subtotal
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ";


                $insertItemStmt =
                    $conn->prepare(
                        $insertItemSql
                    );


                if (
                    !$insertItemStmt
                ) {

                    throw new Exception(
                        "Order item query error: "
                        .
                        $conn->error
                    );
                }


                $insertItemStmt->bind_param(
                    "iisddi",
                    $newOrderId,
                    $orderMenuItemId,
                    $orderItemName,
                    $orderItemPrice,
                    $orderQuantity,
                    $orderItemSubtotal
                );


                /*
                 * Correct bind types for:
                 *
                 * order_id       integer
                 * menu_item_id   integer
                 * item_name      string
                 * item_price     double
                 * quantity       integer
                 * subtotal       double
                 */

                $insertItemStmt->close();


                /*
                 * Re-run with the correct bind string.
                 */

                $insertItemStmt =
                    $conn->prepare(
                        $insertItemSql
                    );


                if (
                    !$insertItemStmt
                ) {

                    throw new Exception(
                        "Unable to prepare order item."
                    );
                }


                $insertItemStmt->bind_param(
                    "iisdid",
                    $newOrderId,
                    $orderMenuItemId,
                    $orderItemName,
                    $orderItemPrice,
                    $orderQuantity,
                    $orderItemSubtotal
                );


                if (
                    !$insertItemStmt->execute()
                ) {

                    throw new Exception(
                        "Unable to save order item: "
                        .
                        $insertItemStmt->error
                    );
                }


                $insertItemStmt->close();
            }


            /* =========================================
               CLEAR CUSTOMER CART
            ========================================= */

            $clearCartSql = "
                DELETE FROM cart

                WHERE user_id = ?
            ";


            $clearCartStmt =
                $conn->prepare(
                    $clearCartSql
                );


            if (
                !$clearCartStmt
            ) {

                throw new Exception(
                    "Unable to clear cart."
                );
            }


            $clearCartStmt->bind_param(
                "i",
                $user_id
            );


            if (
                !$clearCartStmt->execute()
            ) {

                throw new Exception(
                    "Unable to clear cart."
                );
            }


            $clearCartStmt->close();


            /* =========================================
               COMMIT
            ========================================= */

            $conn->commit();


            /* =========================================
               REDIRECT
               PREVENT DOUBLE ORDER ON REFRESH
            ========================================= */

            header(
                "Location: checkout.php?success=1"
                .
                "&order="
                .
                urlencode(
                    $orderNumber
                )
                .
                "&order_id="
                .
                $newOrderId
            );

            exit;


        } catch (
            Throwable $exception
        ) {


            $conn->rollback();


            $errorMessage =
                $exception->getMessage();
        }
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
        Checkout - Humsafar
    </title>


    <!-- MAIN WEBSITE CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <!-- HEADER CSS -->

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        /* =====================================================
           CHECKOUT
        ===================================================== */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background:
                #f6f7fb;

            color:
                #292929;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
        }


        .checkout-page {

            width: 100%;

            max-width: 1250px;

            margin:
                32px auto 0;

            padding:
                0 20px 55px;
        }


        /* =====================================================
           PAGE TITLE
        ===================================================== */

        .checkout-title {

            position: relative;

            overflow: hidden;

            margin-bottom: 24px;

            padding:
                29px 34px;

            border-radius: 17px;

            background:
                linear-gradient(
                    105deg,
                    #ed0640 0%,
                    #f52d69 52%,
                    #ff6190 100%
                );

            box-shadow:
                0 10px 28px
                rgba(
                    239,
                    30,
                    84,
                    .16
                );
        }


        .checkout-title::before {

            content: "";

            position: absolute;

            width: 170px;

            height: 170px;

            right: -55px;

            top: -85px;

            border-radius: 50%;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .09
                );
        }


        .checkout-title h1 {

            position: relative;

            z-index: 2;

            margin: 0 0 7px;

            color:
                #fff;

            font-size:
                31px;

            font-weight:
                800;
        }


        .checkout-title p {

            position: relative;

            z-index: 2;

            margin: 0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .9
                );

            font-size:
                14px;
        }


        /* =====================================================
           LAYOUT
        ===================================================== */

        .checkout-grid {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                390px;

            gap:
                24px;

            align-items:
                start;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .checkout-card {

            background:
                #fff;

            border:
                1px solid #ececec;

            border-radius:
                13px;

            padding:
                22px;

            margin-bottom:
                18px;

            box-shadow:
                0 5px 18px
                rgba(
                    0,
                    0,
                    0,
                    .045
                );
        }


        .card-heading {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            margin-bottom:
                18px;
        }


        .card-heading i {

            color:
                #ed1748;

            font-size:
                19px;
        }


        .card-heading h2 {

            margin:
                0;

            color:
                #292929;

            font-size:
                19px;

            font-weight:
                600;
        }


        /* =====================================================
           ADDRESS
        ===================================================== */

        .address-option {

            position:
                relative;

            display:
                block;

            margin-bottom:
                12px;

            padding:
                16px;

            border:
                1px solid #e4e4e4;

            border-radius:
                10px;

            background:
                #fff;

            cursor:
                pointer;

            transition:
                .2s;
        }


        .address-option:hover {

            border-color:
                #f42a65;
        }


        .address-option.selected {

            border-color:
                #ed1748;

            background:
                #fff7f9;

            box-shadow:
                0 0 0 1px
                rgba(
                    237,
                    23,
                    72,
                    .05
                );
        }


        .address-option input {

            position:
                absolute;

            opacity:
                0;
        }


        .address-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            margin-bottom:
                9px;
        }


        .address-title {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            color:
                #333;

            font-size:
                15px;

            font-weight:
                600;
        }


        .radio-circle {

            width:
                18px;

            height:
                18px;

            flex-shrink:
                0;

            border:
                2px solid #bbb;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;
        }


        .address-option.selected
        .radio-circle {

            border-color:
                #ed1748;
        }


        .address-option.selected
        .radio-circle::after {

            content:
                "";

            width:
                8px;

            height:
                8px;

            border-radius:
                50%;

            background:
                #ed1748;
        }


        .default-badge {

            padding:
                4px 9px;

            border-radius:
                20px;

            background:
                #ffe9ef;

            color:
                #ed1748;

            font-size:
                11px;

            white-space:
                nowrap;
        }


        .address-name {

            margin-bottom:
                5px;

            color:
                #333;

            font-size:
                14px;
        }


        .address-phone {

            margin-bottom:
                6px;

            color:
                #777;

            font-size:
                13px;
        }


        .address-text {

            color:
                #666;

            font-size:
                13px;

            line-height:
                1.55;
        }


        .address-note {

            margin-top:
                8px;

            color:
                #888;

            font-size:
                12px;
        }


        .no-address {

            padding:
                16px;

            border-radius:
                9px;

            background:
                #fff5f7;

            color:
                #777;

            font-size:
                13px;

            line-height:
                1.6;
        }


        .manage-address {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            margin-top:
                14px;

            color:
                #ed1748;

            font-size:
                13px;

            font-weight:
                600;
        }


        .manage-address:hover {

            color:
                #c9123d;
        }


        /* =====================================================
           PAYMENT
        ===================================================== */

        .payment-option {

            position:
                relative;

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            margin-bottom:
                11px;

            padding:
                14px;

            border:
                1px solid #e4e4e4;

            border-radius:
                10px;

            cursor:
                pointer;

            transition:
                .2s;
        }


        .payment-option:hover {

            border-color:
                #f42a65;
        }


        .payment-option.selected {

            border-color:
                #ed1748;

            background:
                #fff7f9;
        }


        .payment-option input {

            width:
                17px;

            height:
                17px;

            margin:
                0;

            accent-color:
                #ed1748;
        }


        .payment-icon {

            width:
                40px;

            height:
                40px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-shrink:
                0;

            border-radius:
                8px;

            background:
                #fff0f4;

            color:
                #ed1748;

            font-size:
                17px;
        }


        .payment-info {

            display:
                flex;

            flex-direction:
                column;

            gap:
                3px;
        }


        .payment-name {

            color:
                #333;

            font-size:
                14px;

            font-weight:
                600;
        }


        .payment-description {

            color:
                #888;

            font-size:
                12px;
        }


        /* =====================================================
           RESTAURANT
        ===================================================== */

        .restaurant-box {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding-bottom:
                15px;

            margin-bottom:
                4px;

            border-bottom:
                1px solid #eee;
        }


        .restaurant-image {

            width:
                55px;

            height:
                55px;

            flex-shrink:
                0;

            object-fit:
                cover;

            border-radius:
                50%;

            border:
                1px solid #eee;
        }


        .restaurant-placeholder {

            width:
                55px;

            height:
                55px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                #fff0f4;

            color:
                #ed1748;

            font-size:
                20px;
        }


        .restaurant-name {

            margin-bottom:
                4px;

            color:
                #333;

            font-size:
                15px;

            font-weight:
                600;
        }


        .restaurant-time {

            color:
                #777;

            font-size:
                12px;
        }


        /* =====================================================
           ITEMS
        ===================================================== */

        .checkout-item {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;

            padding:
                13px 0;

            border-bottom:
                1px solid #eee;
        }


        .checkout-item:last-child {

            border-bottom:
                0;
        }


        .item-image {

            width:
                62px;

            height:
                62px;

            flex-shrink:
                0;

            object-fit:
                cover;

            border-radius:
                9px;

            background:
                #f2f2f2;
        }


        .item-placeholder {

            width:
                62px;

            height:
                62px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                9px;

            background:
                #fff0f4;

            color:
                #ed1748;

            font-size:
                19px;
        }


        .item-info {

            min-width:
                0;

            flex:
                1;
        }


        .item-name {

            margin-bottom:
                4px;

            color:
                #333;

            font-size:
                14px;

            font-weight:
                500;

            line-height:
                1.35;
        }


        .item-quantity {

            color:
                #888;

            font-size:
                12px;
        }


        .item-total {

            color:
                #ed1748;

            font-size:
                13px;

            white-space:
                nowrap;
        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                8px 0;

            color:
                #666;

            font-size:
                13px;
        }


        .summary-divider {

            height:
                1px;

            margin:
                8px 0;

            background:
                #eee;
        }


        .summary-total {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding-top:
                10px;

            color:
                #333;

            font-size:
                17px;

            font-weight:
                600;
        }


        .summary-total strong {

            color:
                #ed1748;
        }


        .free {

            color:
                #ed1748;
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .place-order-btn {

            width:
                100%;

            margin-top:
                17px;

            padding:
                13px 18px;

            border:
                0;

            border-radius:
                8px;

            background:
                #ed1748;

            color:
                #fff;

            font-size:
                14px;

            font-weight:
                600;

            cursor:
                pointer;

            transition:
                .2s;
        }


        .place-order-btn:hover {

            background:
                #d91442;
        }


        .place-order-btn:disabled {

            opacity:
                .55;

            cursor:
                not-allowed;
        }


        /* =====================================================
           ERROR
        ===================================================== */

        .checkout-error {

            margin-bottom:
                20px;

            padding:
                13px 15px;

            border:
                1px solid #ffd0d7;

            border-radius:
                9px;

            background:
                #fff1f3;

            color:
                #c92347;

            font-size:
                13px;
        }


        /* =====================================================
           SUCCESS
        ===================================================== */

        .success-wrapper {

            max-width:
                650px;

            margin:
                45px auto;

            text-align:
                center;
        }


        .success-card {

            padding:
                42px 30px;

            border:
                1px solid #eee;

            border-radius:
                16px;

            background:
                #fff;

            box-shadow:
                0 10px 30px
                rgba(
                    0,
                    0,
                    0,
                    .06
                );
        }


        .success-icon {

            width:
                72px;

            height:
                72px;

            margin:
                0 auto 18px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                #eafaf0;

            color:
                #25a65a;

            font-size:
                31px;
        }


        .success-card h1 {

            margin:
                0 0 9px;

            color:
                #333;

            font-size:
                28px;

            font-weight:
                700;
        }


        .success-card p {

            margin:
                0 0 10px;

            color:
                #777;

            font-size:
                14px;

            line-height:
                1.6;
        }


        .order-number {

            display:
                inline-block;

            margin:
                10px 0 20px;

            padding:
                8px 14px;

            border-radius:
                20px;

            background:
                #fff0f4;

            color:
                #ed1748;

            font-size:
                14px;

            font-weight:
                600;
        }


        .success-actions {

            display:
                flex;

            justify-content:
                center;

            gap:
                10px;

            flex-wrap:
                wrap;
        }


        .success-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            min-width:
                145px;

            padding:
                11px 16px;

            border-radius:
                8px;

            font-size:
                13px;

            font-weight:
                600;

            text-decoration:
                none;
        }


        .success-btn.primary {

            background:
                #ed1748;

            color:
                #fff;
        }


        .success-btn.secondary {

            border:
                1px solid #ed1748;

            background:
                #fff;

            color:
                #ed1748;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (
            max-width: 850px
        ) {

            .checkout-grid {

                grid-template-columns:
                    1fr;
            }
        }


        @media (
            max-width: 600px
        ) {

            .checkout-page {

                margin-top:
                    20px;

                padding:
                    0 14px 40px;
            }


            .checkout-title {

                padding:
                    24px 22px;
            }


            .checkout-title h1 {

                font-size:
                    26px;
            }


            .checkout-card {

                padding:
                    18px;
            }


            .item-image,
            .item-placeholder {

                width:
                    57px;

                height:
                    57px;
            }
        }

    </style>

</head>


<body>


<?php
/*
|--------------------------------------------------------------------------
| EXISTING CUSTOMER HEADER
|--------------------------------------------------------------------------
|
| The repo's customer-facing pages use includes/header.php.
| This keeps the same Humsafar header/navigation instead of creating
| another different header.
|
*/
require_once 'includes/header.php';
?>


<!-- =====================================================
     CHECKOUT PAGE
===================================================== -->

<main class="checkout-page">


<?php if (
    $orderSuccess
) { ?>


    <!-- =================================================
         ORDER SUCCESS
    ================================================== -->

    <div
        class="success-wrapper"
    >

        <div
            class="success-card"
        >

            <div
                class="success-icon"
            >

                <i
                    class="fas fa-check"
                ></i>

            </div>


            <h1>
                Order Placed Successfully
            </h1>


            <p>

                Thank you for ordering
                from Humsafar.

            </p>


            <?php if (
                $successOrderNumber !== ''
            ) { ?>

                <div
                    class="order-number"
                >

                    Order #
                    <?php
                        echo h(
                            $successOrderNumber
                        );
                    ?>

                </div>

            <?php } ?>


            <p>

                Your order has been received
                and is now pending confirmation.

            </p>


            <div
                class="success-actions"
            >


                <a
                    href="my_orders.php"
                    class="
                        success-btn
                        primary
                    "
                >

                    <i
                        class="
                            fas
                            fa-receipt
                        "
                    ></i>

                    My Orders

                </a>


                <a
                    href="restaurants.php"
                    class="
                        success-btn
                        secondary
                    "
                >

                    <i
                        class="
                            fas
                            fa-store
                        "
                    ></i>

                    Continue Shopping

                </a>


            </div>


        </div>

    </div>


<?php } else { ?>


    <!-- =================================================
         TITLE
    ================================================== -->

    <section
        class="checkout-title"
    >

        <h1>

            <i
                class="
                    fas
                    fa-credit-card
                "
            ></i>

            Checkout

        </h1>


        <p>

            Confirm your saved delivery address
            and payment method before placing your order.

        </p>

    </section>


    <?php if (
        $errorMessage !== ''
    ) { ?>


        <div
            class="checkout-error"
        >

            <i
                class="
                    fas
                    fa-circle-exclamation
                "
            ></i>

            &nbsp;

            <?php
                echo h(
                    $errorMessage
                );
            ?>

        </div>


    <?php } ?>


    <!-- =================================================
         FORM
    ================================================== -->

    <form
        method="POST"
        action="checkout.php"
        id="checkout-form"
    >


        <div
            class="checkout-grid"
        >


            <!-- =============================================
                 LEFT SIDE
            ============================================== -->

            <div>


                <!-- =========================================
                     DELIVERY ADDRESS
                ========================================== -->

                <section
                    class="checkout-card"
                >


                    <div
                        class="card-heading"
                    >

                        <i
                            class="
                                fas
                                fa-location-dot
                            "
                        ></i>


                        <h2>
                            Delivery Address
                        </h2>

                    </div>


                    <?php if (
                        empty($addresses)
                    ) { ?>


                        <div
                            class="no-address"
                        >

                            <i
                                class="
                                    fas
                                    fa-location-dot
                                "
                            ></i>

                            &nbsp;

                            No saved delivery address
                            was found.

                            Please add an address
                            from My Address first.

                            <br>


                            <a
                                href="customer/manage-addresses.php"
                                class="manage-address"
                            >

                                <i
                                    class="
                                        fas
                                        fa-arrow-right
                                    "
                                ></i>

                                Manage Addresses

                            </a>

                        </div>


                    <?php } else { ?>


                        <?php foreach (
                            $addresses
                            as $address
                        ) { ?>


                            <?php

                            $addressId =
                                (int)
                                $address['id'];


                            $isSelected =
                                (
                                    $addressId
                                    ===
                                    $selectedAddressId
                                );


                            $fullAddress =
                                trim(
                                    $address[
                                        'address'
                                    ]
                                );


                            if (
                                !empty(
                                    $address[
                                        'area'
                                    ]
                                )
                            ) {

                                $fullAddress .=
                                    ', '
                                    .
                                    $address[
                                        'area'
                                    ];
                            }


                            if (
                                !empty(
                                    $address[
                                        'city'
                                    ]
                                )
                            ) {

                                $fullAddress .=
                                    ', '
                                    .
                                    $address[
                                        'city'
                                    ];
                            }

                            ?>


                            <label
                                class="
                                    address-option
                                    <?php
                                    echo $isSelected
                                        ? 'selected'
                                        : '';
                                    ?>
                                "
                            >


                                <input
                                    type="radio"
                                    name="address_id"
                                    value="<?php
                                        echo $addressId;
                                    ?>"
                                    <?php
                                    echo $isSelected
                                        ? 'checked'
                                        : '';
                                    ?>
                                    required
                                >


                                <div
                                    class="
                                        address-top
                                    "
                                >


                                    <span
                                        class="
                                            address-title
                                        "
                                    >

                                        <span
                                            class="
                                                radio-circle
                                            "
                                        ></span>


                                        <?php
                                            echo h(
                                                $address[
                                                    'address_title'
                                                ]
                                            );
                                        ?>

                                    </span>


                                    <?php if (
                                        (int)
                                        $address[
                                            'is_default'
                                        ] === 1
                                    ) { ?>


                                        <span
                                            class="
                                                default-badge
                                            "
                                        >

                                            Default

                                        </span>


                                    <?php } ?>


                                </div>


                                <div
                                    class="
                                        address-name
                                    "
                                >

                                    <?php
                                        echo h(
                                            $address[
                                                'full_name'
                                            ]
                                        );
                                    ?>

                                </div>


                                <div
                                    class="
                                        address-phone
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-phone
                                        "
                                    ></i>

                                    &nbsp;

                                    <?php
                                        echo h(
                                            $address[
                                                'phone'
                                            ]
                                        );
                                    ?>

                                </div>


                                <div
                                    class="
                                        address-text
                                    "
                                >

                                    <?php
                                        echo h(
                                            $fullAddress
                                        );
                                    ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $address[
                                            'delivery_instructions'
                                        ]
                                    )
                                ) { ?>


                                    <div
                                        class="
                                            address-note
                                        "
                                    >

                                        <i
                                            class="
                                                fas
                                                fa-circle-info
                                            "
                                        ></i>

                                        &nbsp;

                                        <?php
                                            echo h(
                                                $address[
                                                    'delivery_instructions'
                                                ]
                                            );
                                        ?>

                                    </div>


                                <?php } ?>


                            </label>


                        <?php } ?>


                        <a
                            href="customer/manage-addresses.php"
                            class="manage-address"
                        >

                            <i
                                class="
                                    fas
                                    fa-pen
                                "
                            ></i>

                            Manage Addresses

                        </a>


                    <?php } ?>


                </section>


                <!-- =========================================
                     PAYMENT METHOD
                ========================================== -->

                <section
                    class="checkout-card"
                >


                    <div
                        class="card-heading"
                    >

                        <i
                            class="
                                fas
                                fa-wallet
                            "
                        ></i>


                        <h2>
                            Payment Method
                        </h2>

                    </div>


                    <!-- COD -->

                    <label
                        class="
                            payment-option
                            <?php
                            echo
                            $paymentFromCart
                            ===
                            'cod'
                                ?
                                'selected'
                                :
                                '';
                            ?>
                        "
                    >


                        <input
                            type="radio"
                            name="payment_method"
                            value="cod"
                            <?php
                            echo
                            $paymentFromCart
                            ===
                            'cod'
                                ?
                                'checked'
                                :
                                '';
                            ?>
                            required
                        >


                        <span
                            class="
                                payment-icon
                            "
                        >

                            <i
                                class="
                                    fas
                                    fa-money-bill-wave
                                "
                            ></i>

                        </span>


                        <span
                            class="
                                payment-info
                            "
                        >

                            <span
                                class="
                                    payment-name
                                "
                            >

                                Cash on Delivery

                            </span>


                            <span
                                class="
                                    payment-description
                                "
                            >

                                Pay when your order arrives.

                            </span>

                        </span>


                    </label>


                    <!-- CARD -->

                    <label
                        class="
                            payment-option
                            <?php
                            echo
                            $paymentFromCart
                            ===
                            'card'
                                ?
                                'selected'
                                :
                                '';
                            ?>
                        "
                    >


                        <input
                            type="radio"
                            name="payment_method"
                            value="card"
                            <?php
                            echo
                            $paymentFromCart
                            ===
                            'card'
                                ?
                                'checked'
                                :
                                '';
                            ?>
                            required
                        >


                        <span
                            class="
                                payment-icon
                            "
                        >

                            <i
                                class="
                                    fas
                                    fa-credit-card
                                "
                            ></i>

                        </span>


                        <span
                            class="
                                payment-info
                            "
                        >

                            <span
                                class="
                                    payment-name
                                "
                            >

                                Card Payment

                            </span>


                            <span
                                class="
                                    payment-description
                                "
                            >

                                Pay securely using your card.

                            </span>

                        </span>


                    </label>


                </section>


            </div>


            <!-- =============================================
                 RIGHT SIDE
            ============================================== -->

            <aside>


                <section
                    class="checkout-card"
                >


                    <div
                        class="card-heading"
                    >

                        <i
                            class="
                                fas
                                fa-bag-shopping
                            "
                        ></i>


                        <h2>
                            Order Summary
                        </h2>

                    </div>


                    <!-- =====================================
                         RESTAURANT
                    ====================================== -->

                    <div
                        class="
                            restaurant-box
                        "
                    >


                        <?php if (
                            !empty(
                                $restaurantImage
                            )
                        ) { ?>


                            <img
                                src="
                                    assets/images/restaurants/<?php
                                        echo h(
                                            $restaurantImage
                                        );
                                    ?>
                                "
                                class="
                                    restaurant-image
                                "
                                alt="<?php
                                    echo h(
                                        $restaurantName
                                    );
                                ?>"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="
                                    restaurant-placeholder
                                "
                                style="
                                    display:none;
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-store
                                    "
                                ></i>

                            </div>


                        <?php } else { ?>


                            <div
                                class="
                                    restaurant-placeholder
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-store
                                    "
                                ></i>

                            </div>


                        <?php } ?>


                        <div>

                            <div
                                class="
                                    restaurant-name
                                "
                            >

                                <?php
                                    echo h(
                                        $restaurantName
                                    );
                                ?>

                            </div>


                            <?php if (
                                !empty(
                                    $deliveryTime
                                )
                            ) { ?>


                                <div
                                    class="
                                        restaurant-time
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-clock
                                        "
                                    ></i>

                                    &nbsp;

                                    <?php
                                        echo h(
                                            $deliveryTime
                                        );
                                    ?>

                                </div>


                            <?php } ?>


                        </div>


                    </div>


                    <!-- =====================================
                         ITEMS
                    ====================================== -->

                    <?php foreach (
                        $cartItems
                        as $item
                    ) { ?>


                        <div
                            class="
                                checkout-item
                            "
                        >


                            <?php if (
                                !empty(
                                    $item[
                                        'item_image'
                                    ]
                                )
                            ) { ?>


                                <img
                                    src="
                                        assets/images/menu/<?php
                                            echo h(
                                                $item[
                                                    'item_image'
                                                ]
                                            );
                                        ?>
                                    "
                                    class="
                                        item-image
                                    "
                                    alt="<?php
                                        echo h(
                                            $item[
                                                'item_name'
                                            ]
                                        );
                                    ?>"
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='flex';
                                    "
                                >


                                <div
                                    class="
                                        item-placeholder
                                    "
                                    style="
                                        display:none;
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-utensils
                                        "
                                    ></i>

                                </div>


                            <?php } else { ?>


                                <div
                                    class="
                                        item-placeholder
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-utensils
                                        "
                                    ></i>

                                </div>


                            <?php } ?>


                            <div
                                class="
                                    item-info
                                "
                            >

                                <div
                                    class="
                                        item-name
                                    "
                                >

                                    <?php
                                        echo h(
                                            $item[
                                                'item_name'
                                            ]
                                        );
                                    ?>

                                </div>


                                <div
                                    class="
                                        item-quantity
                                    "
                                >

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
                                            $item[
                                                'item_price'
                                            ],
                                            2
                                        );
                                    ?>

                                </div>

                            </div>


                            <div
                                class="
                                    item-total
                                "
                            >

                                Rs.
                                <?php
                                    echo number_format(
                                        $item[
                                            'item_subtotal'
                                        ],
                                        2
                                    );
                                ?>

                            </div>


                        </div>


                    <?php } ?>


                    <!-- =====================================
                         SUBTOTAL
                    ====================================== -->

                    <div
                        class="
                            summary-row
                        "
                    >

                        <span>
                            Subtotal
                        </span>


                        <span>

                            Rs.
                            <?php
                                echo number_format(
                                    $subTotal,
                                    2
                                );
                            ?>

                        </span>

                    </div>


                    <!-- =====================================
                         DELIVERY FEE
                    ====================================== -->

                    <div
                        class="
                            summary-row
                        "
                    >

                        <span>
                            Delivery Fee
                        </span>


                        <?php if (
                            $deliveryFee > 0
                        ) { ?>


                            <span>

                                Rs.
                                <?php
                                    echo number_format(
                                        $deliveryFee,
                                        2
                                    );
                                ?>

                            </span>


                        <?php } else { ?>


                            <span
                                class="free"
                            >

                                FREE

                            </span>


                        <?php } ?>


                    </div>


                    <div
                        class="
                            summary-divider
                        "
                    ></div>


                    <!-- =====================================
                         TOTAL
                    ====================================== -->

                    <div
                        class="
                            summary-total
                        "
                    >

                        <span>
                            Total
                        </span>


                        <strong>

                            Rs.
                            <?php
                                echo number_format(
                                    $grandTotal,
                                    2
                                );
                            ?>

                        </strong>

                    </div>


                    <!-- =====================================
                         PLACE ORDER
                    ====================================== -->

                    <button
                        type="submit"
                        class="
                            place-order-btn
                        "
                        <?php
                        echo empty(
                            $addresses
                        )
                            ?
                            'disabled'
                            :
                            '';
                        ?>
                    >

                        <i
                            class="
                                fas
                                fa-check
                            "
                        ></i>

                        &nbsp;

                        Place Order

                    </button>


                </section>


            </aside>


        </div>


    </form>


<?php } ?>


</main>


<script>

/* =====================================================
   ADDRESS SELECTION
===================================================== */

document
    .querySelectorAll(
        '.address-option'
    )
    .forEach(
        function(option) {

            option.addEventListener(
                'click',
                function() {


                    document
                        .querySelectorAll(
                            '.address-option'
                        )
                        .forEach(
                            function(item) {

                                item.classList
                                    .remove(
                                        'selected'
                                    );

                            }
                        );


                    option.classList.add(
                        'selected'
                    );


                    var radio =
                        option.querySelector(
                            'input[type="radio"]'
                        );


                    if (radio) {

                        radio.checked =
                            true;
                    }

                }
            );

        }
    );


/* =====================================================
   PAYMENT SELECTION
===================================================== */

document
    .querySelectorAll(
        '.payment-option'
    )
    .forEach(
        function(option) {

            option.addEventListener(
                'click',
                function() {


                    document
                        .querySelectorAll(
                            '.payment-option'
                        )
                        .forEach(
                            function(item) {

                                item.classList
                                    .remove(
                                        'selected'
                                    );

                            }
                        );


                    option.classList.add(
                        'selected'
                    );


                    var radio =
                        option.querySelector(
                            'input[type="radio"]'
                        );


                    if (radio) {

                        radio.checked =
                            true;
                    }

                }
            );

        }
    );


/* =====================================================
   FORM VALIDATION
===================================================== */

var checkoutForm =
    document.getElementById(
        'checkout-form'
    );


if (checkoutForm) {


    checkoutForm.addEventListener(
        'submit',
        function(event) {


            var address =
                document.querySelector(
                    'input[name="address_id"]:checked'
                );


            var payment =
                document.querySelector(
                    'input[name="payment_method"]:checked'
                );


            if (!address) {

                event.preventDefault();

                alert(
                    'Please select a delivery address.'
                );

                return;
            }


            if (!payment) {

                event.preventDefault();

                alert(
                    'Please select a payment method.'
                );

                return;
            }


        }
    );

}

</script>


<?php

/*
|--------------------------------------------------------------------------
| EXISTING FOOTER
|--------------------------------------------------------------------------
*/

require_once 'includes/footer.php';

?>