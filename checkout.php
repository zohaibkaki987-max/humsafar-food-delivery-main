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
   HELPER FUNCTION
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
   GET CURRENT USER
===================================================== */

$userStmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone
    FROM users
    WHERE id = ?
    LIMIT 1
");


if (!$userStmt) {
    die("Database error: " . $conn->error);
}


$userStmt->bind_param(
    "i",
    $user_id
);

$userStmt->execute();

$userResult = $userStmt->get_result();

$user = $userResult->fetch_assoc();

$userStmt->close();


if (!$user) {

    session_destroy();

    header("Location: login.php");
    exit;

}


/* =====================================================
   SAVE NEW ADDRESS
===================================================== */

$addressError = "";

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_address'])
) {

    $addressType = isset($_POST['address_type'])
        ? trim($_POST['address_type'])
        : 'home';

    $label = isset($_POST['label'])
        ? trim($_POST['label'])
        : '';

    $fullName = isset($_POST['full_name'])
        ? trim($_POST['full_name'])
        : '';

    $phone = isset($_POST['phone'])
        ? trim($_POST['phone'])
        : '';

    $address = isset($_POST['address'])
        ? trim($_POST['address'])
        : '';

    $city = isset($_POST['city'])
        ? trim($_POST['city'])
        : 'Hyderabad';

    $area = isset($_POST['area'])
        ? trim($_POST['area'])
        : '';

    $landmark = isset($_POST['landmark'])
        ? trim($_POST['landmark'])
        : '';

    $makeDefault = isset($_POST['is_default'])
        ? 1
        : 0;


    /* ================================================
       VALIDATE ADDRESS
    ================================================= */

    if (
        !in_array(
            $addressType,
            array('home', 'work', 'other'),
            true
        )
    ) {

        $addressType = 'home';

    }


    if ($fullName === '') {

        $addressError =
            "Please enter your full name.";

    } elseif ($phone === '') {

        $addressError =
            "Please enter your phone number.";

    } elseif ($address === '') {

        $addressError =
            "Please enter your complete address.";

    } elseif ($city === '') {

        $addressError =
            "Please enter your city.";

    }


    /* ================================================
       SAVE ADDRESS
    ================================================= */

    if ($addressError === '') {

        $conn->begin_transaction();

        try {

            /*
             * If this address is selected as default,
             * remove default from existing addresses.
             */

            if ($makeDefault === 1) {

                $resetStmt = $conn->prepare("
                    UPDATE addresses
                    SET is_default = 0
                    WHERE user_id = ?
                ");

                if (!$resetStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }

                $resetStmt->bind_param(
                    "i",
                    $user_id
                );

                if (!$resetStmt->execute()) {
                    throw new Exception(
                        $resetStmt->error
                    );
                }

                $resetStmt->close();

            }


            /*
             * If user has no address yet,
             * make the first address default.
             */

            if ($makeDefault === 0) {

                $countStmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM addresses
                    WHERE user_id = ?
                ");

                if (!$countStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }

                $countStmt->bind_param(
                    "i",
                    $user_id
                );

                $countStmt->execute();

                $countResult =
                    $countStmt->get_result();

                $countRow =
                    $countResult->fetch_assoc();

                $countStmt->close();


                if (
                    isset($countRow['total']) &&
                    (int)$countRow['total'] === 0
                ) {

                    $makeDefault = 1;

                }

            }


            $addressStmt = $conn->prepare("
                INSERT INTO addresses
                (
                    user_id,
                    address_type,
                    label,
                    full_name,
                    phone,
                    address,
                    city,
                    area,
                    landmark,
                    is_default
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
            ");


            if (!$addressStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $addressStmt->bind_param(
                "issssssssi",
                $user_id,
                $addressType,
                $label,
                $fullName,
                $phone,
                $address,
                $city,
                $area,
                $landmark,
                $makeDefault
            );


            if (!$addressStmt->execute()) {
                throw new Exception(
                    $addressStmt->error
                );
            }


            $newAddressId =
                $conn->insert_id;


            $addressStmt->close();


            $conn->commit();


            /*
             * Redirect to prevent duplicate
             * form submission on refresh.
             */

            header(
                "Location: checkout.php?address_id=" .
                $newAddressId .
                "&saved=1"
            );

            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $addressError =
                "Unable to save address. Please try again.";

        }

    }

}


/* =====================================================
   GET CART ITEMS
===================================================== */

$cartStmt = $conn->prepare("
    SELECT
        c.id AS cart_id,
        c.menu_item_id,
        c.quantity,

        m.name,
        m.description,
        m.price,
        m.image,
        m.restaurant_id,

        r.name AS restaurant_name,
        r.image AS restaurant_image,
        r.address AS restaurant_address,
        r.phone AS restaurant_phone,
        r.delivery_time,
        r.delivery_fee

    FROM cart c

    INNER JOIN menu_items m
        ON c.menu_item_id = m.id

    INNER JOIN restaurants r
        ON m.restaurant_id = r.id

    WHERE c.user_id = ?

    ORDER BY c.id ASC
");


if (!$cartStmt) {
    die("Database error: " . $conn->error);
}


$cartStmt->bind_param(
    "i",
    $user_id
);

$cartStmt->execute();

$cartResult =
    $cartStmt->get_result();


$cartItems = array();


while ($row = $cartResult->fetch_assoc()) {

    $row['quantity'] =
        (int)$row['quantity'];

    $row['price'] =
        (float)$row['price'];

    $row['subtotal'] =
        $row['price'] *
        $row['quantity'];

    $cartItems[] = $row;

}


$cartStmt->close();


/* =====================================================
   CHECK EMPTY CART
===================================================== */

if (empty($cartItems)) {

    header("Location: cart.php");
    exit;

}


/* =====================================================
   CHECK RESTAURANT
===================================================== */

$restaurantIds = array();

foreach ($cartItems as $item) {

    $restaurantIds[] =
        (int)$item['restaurant_id'];

}

$restaurantIds =
    array_unique($restaurantIds);


/*
 * One cart should contain items from
 * one restaurant.
 */

if (count($restaurantIds) !== 1) {

    die(
        "Your cart contains items from multiple restaurants. " .
        "Please keep items from one restaurant in the cart."
    );

}


$restaurantId =
    (int)$restaurantIds[0];


/* =====================================================
   RESTAURANT INFORMATION
===================================================== */

$restaurantName =
    $cartItems[0]['restaurant_name'];

$restaurantImage =
    $cartItems[0]['restaurant_image'];

$restaurantAddress =
    $cartItems[0]['restaurant_address'];

$restaurantPhone =
    $cartItems[0]['restaurant_phone'];

$deliveryTime =
    $cartItems[0]['delivery_time'];

$deliveryFee =
    (float)$cartItems[0]['delivery_fee'];


/* =====================================================
   CALCULATE SUBTOTAL
===================================================== */

$subtotal = 0;

$totalItems = 0;


foreach ($cartItems as $item) {

    $subtotal +=
        $item['subtotal'];

    $totalItems +=
        $item['quantity'];

}


$discount = 0;

$total = $subtotal +
         $deliveryFee -
         $discount;


/* =====================================================
   GET USER ADDRESSES
===================================================== */

$addresses = array();


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
        landmark,
        is_default
    FROM addresses
    WHERE user_id = ?
    ORDER BY
        is_default DESC,
        id DESC
");


if (!$addressStmt) {
    die("Database error: " . $conn->error);
}


$addressStmt->bind_param(
    "i",
    $user_id
);

$addressStmt->execute();

$addressResult =
    $addressStmt->get_result();


while ($row = $addressResult->fetch_assoc()) {

    $addresses[] = $row;

}


$addressStmt->close();


/* =====================================================
   SELECT ADDRESS
===================================================== */

$selectedAddressId = 0;


/*
 * Address coming from cart.php
 */

if (isset($_POST['address_id'])) {

    $selectedAddressId =
        (int)$_POST['address_id'];

}


/*
 * Address coming from URL after
 * adding a new address.
 */

if (
    isset($_GET['address_id']) &&
    (int)$_GET['address_id'] > 0
) {

    $selectedAddressId =
        (int)$_GET['address_id'];

}


/*
 * Find default address if nothing
 * has been selected.
 */

if ($selectedAddressId <= 0) {

    foreach ($addresses as $addressRow) {

        if (
            (int)$addressRow['is_default'] === 1
        ) {

            $selectedAddressId =
                (int)$addressRow['id'];

            break;

        }

    }

}


/*
 * If there is still no selected address,
 * select the first address.
 */

if ($selectedAddressId <= 0 && !empty($addresses)) {

    $selectedAddressId =
        (int)$addresses[0]['id'];

}


/* =====================================================
   PAYMENT METHOD
===================================================== */

$paymentMethod = 'cash_on_delivery';


if (isset($_POST['payment_method'])) {

    $paymentMethod =
        trim($_POST['payment_method']);

}


$allowedPayments = array(
    'cash_on_delivery',
    'card',
    'online'
);


if (
    !in_array(
        $paymentMethod,
        $allowedPayments,
        true
    )
) {

    $paymentMethod =
        'cash_on_delivery';

}


/* =====================================================
   PLACE ORDER
===================================================== */

$orderError = "";


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['place_order'])
) {

    $selectedAddressId =
        isset($_POST['address_id'])
        ? (int)$_POST['address_id']
        : 0;


    $paymentMethod =
        isset($_POST['payment_method'])
        ? trim($_POST['payment_method'])
        : 'cash_on_delivery';


    $customerNote =
        isset($_POST['customer_note'])
        ? trim($_POST['customer_note'])
        : '';


    /* ================================================
       VALIDATE PAYMENT
    ================================================= */

    if (
        !in_array(
            $paymentMethod,
            $allowedPayments,
            true
        )
    ) {

        $orderError =
            "Please select a valid payment method.";

    }


    /* ================================================
       VALIDATE ADDRESS
    ================================================= */

    if ($orderError === '') {

        if ($selectedAddressId <= 0) {

            $orderError =
                "Please select a delivery address.";

        } else {

            $verifyAddress =
                $conn->prepare("
                    SELECT id
                    FROM addresses
                    WHERE id = ?
                    AND user_id = ?
                    LIMIT 1
                ");


            if (!$verifyAddress) {

                $orderError =
                    "Unable to verify address.";

            } else {

                $verifyAddress->bind_param(
                    "ii",
                    $selectedAddressId,
                    $user_id
                );

                $verifyAddress->execute();

                $verifyResult =
                    $verifyAddress->get_result();


                if (
                    $verifyResult->num_rows === 0
                ) {

                    $orderError =
                        "Please select a valid delivery address.";

                }


                $verifyAddress->close();

            }

        }

    }


    /* ================================================
       RE-CHECK CART
    ================================================= */

    if ($orderError === '') {

        $cartCheckStmt =
            $conn->prepare("
                SELECT
                    c.id AS cart_id,
                    c.menu_item_id,
                    c.quantity,
                    m.name,
                    m.price,
                    m.restaurant_id,
                    r.delivery_fee

                FROM cart c

                INNER JOIN menu_items m
                    ON c.menu_item_id = m.id

                INNER JOIN restaurants r
                    ON m.restaurant_id = r.id

                WHERE c.user_id = ?
            ");


        if (!$cartCheckStmt) {

            $orderError =
                "Unable to verify cart.";

        } else {

            $cartCheckStmt->bind_param(
                "i",
                $user_id
            );

            $cartCheckStmt->execute();

            $cartCheckResult =
                $cartCheckStmt->get_result();


            $freshCart = array();

            $freshSubtotal = 0;

            $freshRestaurantId = 0;


            while (
                $freshItem =
                $cartCheckResult->fetch_assoc()
            ) {

                $freshItem['quantity'] =
                    (int)$freshItem['quantity'];

                $freshItem['price'] =
                    (float)$freshItem['price'];

                $freshItem['subtotal'] =
                    $freshItem['price'] *
                    $freshItem['quantity'];

                $freshCart[] =
                    $freshItem;

                $freshSubtotal +=
                    $freshItem['subtotal'];


                if ($freshRestaurantId === 0) {

                    $freshRestaurantId =
                        (int)$freshItem['restaurant_id'];

                }

            }


            $cartCheckStmt->close();


            if (empty($freshCart)) {

                $orderError =
                    "Your cart is empty.";

            } elseif (
                $freshRestaurantId !==
                $restaurantId
            ) {

                $orderError =
                    "Your cart has changed. Please refresh the page.";

            } else {

                /*
                 * Use fresh database prices
                 * while placing the order.
                 */

                $cartItems =
                    $freshCart;

                $subtotal =
                    $freshSubtotal;

                $deliveryFee =
                    isset(
                        $freshCart[0]['delivery_fee']
                    )
                    ? (float)$freshCart[0]['delivery_fee']
                    : $deliveryFee;

                $total =
                    $subtotal +
                    $deliveryFee -
                    $discount;

            }

        }

    }


    /* ================================================
       CREATE ORDER
    ================================================= */

    if ($orderError === '') {

        $conn->begin_transaction();


        try {

            /*
             * Generate unique order number.
             */

            $orderNumber =
                'HUM-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);


            /*
             * Make sure generated order number
             * does not already exist.
             */

            $orderCheck =
                $conn->prepare("
                    SELECT id
                    FROM orders
                    WHERE order_number = ?
                    LIMIT 1
                ");


            if (!$orderCheck) {
                throw new Exception(
                    $conn->error
                );
            }


            $orderCheck->bind_param(
                "s",
                $orderNumber
            );

            $orderCheck->execute();

            $orderCheckResult =
                $orderCheck->get_result();


            while (
                $orderCheckResult->num_rows > 0
            ) {

                $orderNumber =
                    'HUM-' .
                    date('YmdHis') .
                    '-' .
                    random_int(100, 999);

                $orderCheck->bind_param(
                    "s",
                    $orderNumber
                );

                $orderCheck->execute();

                $orderCheckResult =
                    $orderCheck->get_result();

            }


            $orderCheck->close();


            /* ========================================
               INSERT ORDER
            ======================================== */

            $orderStmt =
                $conn->prepare("
                    INSERT INTO orders
                    (
                        order_number,
                        user_id,
                        restaurant_id,
                        address_id,
                        payment_method,
                        subtotal,
                        delivery_fee,
                        discount,
                        total,
                        order_status,
                        customer_note
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
                        'pending',
                        ?
                    )
                ");


            if (!$orderStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $orderStmt->bind_param(
                "siiisdddds",
                $orderNumber,
                $user_id,
                $restaurantId,
                $selectedAddressId,
                $paymentMethod,
                $subtotal,
                $deliveryFee,
                $discount,
                $total,
                $customerNote
            );


            if (!$orderStmt->execute()) {

                throw new Exception(
                    $orderStmt->error
                );

            }


            $orderId =
                $conn->insert_id;


            $orderStmt->close();


            /* ========================================
               INSERT ORDER ITEMS
            ======================================== */

            $itemStmt =
                $conn->prepare("
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
                ");


            if (!$itemStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            foreach ($cartItems as $item) {

                $menuItemId =
                    (int)$item['menu_item_id'];

                $itemName =
                    $item['name'];

                $itemPrice =
                    (float)$item['price'];

                $quantity =
                    (int)$item['quantity'];

                $itemSubtotal =
                    $itemPrice *
                    $quantity;


                $itemStmt->bind_param(
                    "iisdid",
                    $orderId,
                    $menuItemId,
                    $itemName,
                    $itemPrice,
                    $quantity,
                    $itemSubtotal
                );


                if (!$itemStmt->execute()) {

                    throw new Exception(
                        $itemStmt->error
                    );

                }

            }


            $itemStmt->close();


            /* ========================================
               EMPTY CART
            ======================================== */

            $deleteCart =
                $conn->prepare("
                    DELETE FROM cart
                    WHERE user_id = ?
                ");


            if (!$deleteCart) {
                throw new Exception(
                    $conn->error
                );
            }


            $deleteCart->bind_param(
                "i",
                $user_id
            );


            if (!$deleteCart->execute()) {

                throw new Exception(
                    $deleteCart->error
                );

            }


            $deleteCart->close();


            /* ========================================
               COMMIT
            ======================================== */

            $conn->commit();


            /*
             * Redirect to success page.
             */

            header(
                "Location: order_success.php?order_id=" .
                $orderId
            );

            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $orderError =
                "Unable to place your order. Please try again.";

        }

    }

}


/* =====================================================
   PAGE DATA
===================================================== */

$selectedAddress = null;


foreach ($addresses as $addressRow) {

    if (
        (int)$addressRow['id'] ===
        $selectedAddressId
    ) {

        $selectedAddress =
            $addressRow;

        break;

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


        a {
            text-decoration: none;
            color: inherit;
        }


        .checkout-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }


        .checkout-title {
            margin-bottom: 30px;
        }


        .checkout-title h1 {
            margin: 0;
            font-size: 38px;
            color: #222;
        }


        .checkout-title p {
            margin: 8px 0 0;
            color: #777;
        }


        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: start;
        }


        .checkout-card {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow:
                0 10px 25px rgba(0,0,0,.07);
        }


        .checkout-card h2 {
            margin: 0 0 22px;
            font-size: 24px;
            color: #222;
        }


        .restaurant-box {
            display: flex;
            gap: 18px;
            align-items: center;
        }


        .restaurant-image {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #E23744;
            flex-shrink: 0;
        }


        .restaurant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .restaurant-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff4f4;
            color: #E23744;
            font-size: 30px;
        }


        .restaurant-info h3 {
            margin: 0 0 7px;
            font-size: 21px;
        }


        .restaurant-info p {
            margin: 5px 0;
            color: #666;
        }


        .checkout-item {
            display: grid;
            grid-template-columns: 70px 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }


        .checkout-item:last-child {
            border-bottom: 0;
        }


        .checkout-item-image {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            overflow: hidden;
            background: #f5f5f5;
        }


        .checkout-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .checkout-item-details h4 {
            margin: 0 0 5px;
            font-size: 17px;
        }


        .checkout-item-details p {
            margin: 3px 0;
            color: #777;
            font-size: 14px;
        }


        .checkout-item-price {
            text-align: right;
            font-weight: 700;
            color: #222;
            white-space: nowrap;
        }


        .address-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        .address-option {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 17px;
            border: 2px solid #eee;
            border-radius: 14px;
            cursor: pointer;
            transition: .25s;
        }


        .address-option:hover {
            border-color: #E23744;
            background: #fff8f8;
        }


        .address-option.selected {
            border-color: #E23744;
            background: #fff4f4;
        }


        .address-option input {
            margin-top: 4px;
            accent-color: #E23744;
        }


        .address-details {
            flex: 1;
        }


        .address-details h4 {
            margin: 0 0 7px;
            color: #222;
            font-size: 17px;
        }


        .address-details p {
            margin: 4px 0;
            color: #666;
            line-height: 1.5;
            font-size: 14px;
        }


        .address-badge {
            display: inline-block;
            margin-left: 8px;
            padding: 4px 9px;
            border-radius: 20px;
            background: #E23744;
            color: #fff;
            font-size: 11px;
        }


        .new-address-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            border-radius: 15px;
            background: #f8f9fa;
            border: 1px solid #eee;
        }


        .new-address-form.show {
            display: block;
        }


        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }


        .form-group {
            margin-bottom: 15px;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }


        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px 13px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            background: #fff;
        }


        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }


        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #E23744;
            box-shadow:
                0 0 0 3px
                rgba(226,55,68,.10);
        }


        .address-type-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        .type-option {
            position: relative;
        }


        .type-option input {
            position: absolute;
            opacity: 0;
        }


        .type-option label {
            display: block;
            padding: 10px 18px;
            border: 1px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            background: #fff;
        }


        .type-option input:checked + label {
            border-color: #E23744;
            color: #E23744;
            background: #fff4f4;
            font-weight: 700;
        }


        .checkbox-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin: 5px 0 15px;
            font-size: 14px;
        }


        .checkbox-row input {
            accent-color: #E23744;
        }


        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        .payment-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border: 2px solid #eee;
            border-radius: 13px;
            cursor: pointer;
            transition: .25s;
        }


        .payment-option:hover,
        .payment-option.selected {
            border-color: #E23744;
            background: #fff8f8;
        }


        .payment-option input {
            accent-color: #E23744;
        }


        .payment-option i {
            color: #E23744;
            font-size: 19px;
            width: 25px;
            text-align: center;
        }


        .payment-option strong {
            display: block;
            color: #222;
        }


        .payment-option small {
            display: block;
            margin-top: 3px;
            color: #777;
        }


        .summary-card {
            position: sticky;
            top: 25px;
        }


        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 15px;
            color: #666;
        }


        .summary-row span:last-child {
            color: #222;
            font-weight: 600;
        }


        .summary-divider {
            height: 1px;
            background: #eee;
            margin: 20px 0;
        }


        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 22px;
            font-weight: 700;
            color: #222;
        }


        .summary-total span:last-child {
            color: #E23744;
        }


        .note-box {
            margin-top: 20px;
        }


        .note-box label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }


        .note-box textarea {
            width: 100%;
            min-height: 80px;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px;
            resize: vertical;
            font-family: inherit;
        }


        .btn {
            border: 0;
            cursor: pointer;
            border-radius: 11px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .25s;
        }


        .btn-primary {
            background: #E23744;
            color: #fff;
        }


        .btn-primary:hover {
            background: #c91f31;
            transform: translateY(-1px);
        }


        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }


        .btn-secondary:hover {
            background: #e5e5e5;
        }


        .save-address-btn {
            width: 100%;
            margin-top: 5px;
        }


        .place-order-btn {
            width: 100%;
            margin-top: 25px;
            padding: 17px;
            font-size: 17px;
        }


        .back-cart {
            display: inline-flex;
            margin-top: 15px;
            color: #E23744;
            font-weight: 600;
        }


        .message {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }


        .error-message {
            background: #fdeaea;
            color: #b4232d;
            border-left: 4px solid #E23744;
        }


        .success-message {
            background: #eaf8ee;
            color: #198754;
            border-left: 4px solid #198754;
        }


        .security-note {
            text-align: center;
            margin-top: 15px;
            color: #777;
            font-size: 13px;
        }


        @media (max-width: 992px) {

            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: relative;
                top: 0;
            }

        }


        @media (max-width: 600px) {

            .checkout-container {
                padding: 15px;
                margin: 25px auto;
            }

            .checkout-card {
                padding: 20px;
                border-radius: 15px;
            }

            .checkout-title h1 {
                font-size: 30px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .checkout-item {
                grid-template-columns: 60px 1fr;
            }

            .checkout-item-price {
                grid-column: 2;
                text-align: left;
            }

        }

    </style>

</head>


<body>


<?php
/*
 * If your project already has a header include,
 * you can include it here.
 *
 * Uncomment only if your project uses it:
 *
 * require_once 'includes/header.php';
 */
?>


<div class="checkout-container">


    <!-- =================================================
         PAGE TITLE
    ================================================= -->

    <div class="checkout-title">

        <h1>
            Checkout
        </h1>

        <p>
            Complete your order and delivery details.
        </p>

    </div>


    <!-- =================================================
         ERROR / SUCCESS MESSAGE
    ================================================= -->

    <?php if ($orderError !== '') { ?>

        <div class="message error-message">

            <i class="fas fa-circle-exclamation"></i>

            <?php echo h($orderError); ?>

        </div>

    <?php } ?>


    <?php if ($addressError !== '') { ?>

        <div class="message error-message">

            <i class="fas fa-circle-exclamation"></i>

            <?php echo h($addressError); ?>

        </div>

    <?php } ?>


    <?php if (
        isset($_GET['saved']) &&
        $_GET['saved'] == '1'
    ) { ?>

        <div class="message success-message">

            <i class="fas fa-check-circle"></i>

            New address saved successfully.

        </div>

    <?php } ?>


    <div class="checkout-grid">


        <!-- =================================================
             LEFT SIDE
        ================================================= -->

        <div>


            <!-- =================================================
                 RESTAURANT
            ================================================= -->

            <div class="checkout-card">

                <h2>
                    <i class="fas fa-store"></i>
                    Restaurant
                </h2>


                <div class="restaurant-box">


                    <div class="restaurant-image">

                        <?php if (
                            !empty($restaurantImage)
                        ) { ?>

                            <img
                                src="assets/images/restaurants/<?php
                                    echo h($restaurantImage);
                                ?>"
                                alt="<?php
                                    echo h($restaurantName);
                                ?>"
                            >

                        <?php } else { ?>

                            <div class="restaurant-placeholder">

                                <i class="fas fa-store"></i>

                            </div>

                        <?php } ?>

                    </div>


                    <div class="restaurant-info">

                        <h3>
                            <?php
                                echo h($restaurantName);
                            ?>
                        </h3>


                        <?php if (
                            !empty($deliveryTime)
                        ) { ?>

                            <p>

                                <i class="fas fa-clock"></i>

                                <?php
                                    echo h($deliveryTime);
                                ?>

                            </p>

                        <?php } ?>


                        <p>

                            <i class="fas fa-motorcycle"></i>

                            Delivery Fee:
                            Rs.
                            <?php
                                echo number_format(
                                    $deliveryFee,
                                    2
                                );
                            ?>

                        </p>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ORDER ITEMS
            ================================================= -->

            <div class="checkout-card">

                <h2>

                    <i class="fas fa-bag-shopping"></i>

                    Your Order

                </h2>


                <?php foreach (
                    $cartItems as $item
                ) { ?>

                    <div class="checkout-item">


                        <div class="checkout-item-image">

                            <?php if (
                                !empty($item['image'])
                            ) { ?>

                                <img
                                    src="assets/images/menu/<?php
                                        echo h($item['image']);
                                    ?>"
                                    alt="<?php
                                        echo h($item['name']);
                                    ?>"
                                >

                            <?php } else { ?>

                                <div style="
                                    width:100%;
                                    height:100%;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    color:#aaa;
                                ">

                                    <i class="fas fa-utensils"></i>

                                </div>

                            <?php } ?>

                        </div>


                        <div class="checkout-item-details">

                            <h4>
                                <?php
                                    echo h($item['name']);
                                ?>
                            </h4>

                            <p>

                                Qty:
                                <?php
                                    echo (int)$item['quantity'];
                                ?>

                                ×

                                Rs.
                                <?php
                                    echo number_format(
                                        $item['price'],
                                        2
                                    );
                                ?>

                            </p>

                        </div>


                        <div class="checkout-item-price">

                            Rs.
                            <?php
                                echo number_format(
                                    $item['subtotal'],
                                    2
                                );
                            ?>

                        </div>


                    </div>

                <?php } ?>

            </div>


            <!-- =================================================
                 DELIVERY ADDRESS
            ================================================= -->

            <div class="checkout-card">

                <h2>

                    <i class="fas fa-location-dot"></i>

                    Delivery Address

                </h2>


                <?php if (!empty($addresses)) { ?>

                    <div class="address-list">


                        <?php foreach (
                            $addresses as $addressRow
                        ) { ?>


                            <?php
                                $isSelected =
                                    (
                                        (int)$addressRow['id'] ===
                                        $selectedAddressId
                                    );
                            ?>


                            <label
                                class="address-option <?php
                                    echo $isSelected
                                        ? 'selected'
                                        : '';
                                ?>"
                            >


                                <input
                                    type="radio"
                                    name="display_address"
                                    value="<?php
                                        echo (int)$addressRow['id'];
                                    ?>"
                                    <?php
                                        echo $isSelected
                                            ? 'checked'
                                            : '';
                                    ?>
                                    onchange="selectAddress(
                                        <?php
                                            echo (int)$addressRow['id'];
                                        ?>
                                    )"
                                >


                                <div class="address-details">

                                    <h4>

                                        <?php
                                            if (
                                                $addressRow['address_type']
                                                === 'work'
                                            ) {

                                                echo '<i class="fas fa-briefcase"></i>';

                                            } elseif (
                                                $addressRow['address_type']
                                                === 'other'
                                            ) {

                                                echo '<i class="fas fa-location-dot"></i>';

                                            } else {

                                                echo '<i class="fas fa-house"></i>';

                                            }
                                        ?>


                                        <?php

                                        if (
                                            !empty(
                                                $addressRow['label']
                                            )
                                        ) {

                                            echo h(
                                                $addressRow['label']
                                            );

                                        } else {

                                            echo ucfirst(
                                                h(
                                                    $addressRow[
                                                        'address_type'
                                                    ]
                                                )
                                            );

                                        }

                                        ?>


                                        <?php if (
                                            (int)$addressRow[
                                                'is_default'
                                            ] === 1
                                        ) { ?>

                                            <span class="address-badge">
                                                Default
                                            </span>

                                        <?php } ?>

                                    </h4>


                                    <p>

                                        <strong>
                                            <?php
                                                echo h(
                                                    $addressRow[
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
                                                $addressRow['phone']
                                            );
                                        ?>

                                    </p>


                                    <p>

                                        <i class="fas fa-location-dot"></i>

                                        <?php
                                            echo h(
                                                $addressRow['address']
                                            );
                                        ?>


                                        <?php if (
                                            !empty(
                                                $addressRow['area']
                                            )
                                        ) { ?>

                                            ,
                                            <?php
                                                echo h(
                                                    $addressRow['area']
                                                );
                                            ?>

                                        <?php } ?>


                                        ,
                                        <?php
                                            echo h(
                                                $addressRow['city']
                                            );
                                        ?>


                                        <?php if (
                                            !empty(
                                                $addressRow['landmark']
                                            )
                                        ) { ?>

                                            <br>

                                            <i class="fas fa-map-pin"></i>

                                            Landmark:
                                            <?php
                                                echo h(
                                                    $addressRow[
                                                        'landmark'
                                                    ]
                                                );
                                            ?>

                                        <?php } ?>

                                    </p>

                                </div>

                            </label>


                        <?php } ?>

                    </div>

                <?php } else { ?>


                    <div class="message error-message">

                        <i class="fas fa-location-dot"></i>

                        You don't have any saved delivery address.

                    </div>


                <?php } ?>


                <!-- =================================================
                     ADD NEW ADDRESS BUTTON
                ================================================= -->

                <button
                    type="button"
                    class="btn btn-secondary"
                    style="margin-top:18px;width:100%;"
                    onclick="toggleNewAddressForm()"
                >

                    <i class="fas fa-plus"></i>

                    Add New Address

                </button>


                <!-- =================================================
                     NEW ADDRESS FORM
                ================================================= -->

                <div
                    id="newAddressForm"
                    class="new-address-form"
                >

                    <form
                        method="POST"
                        action="checkout.php"
                    >


                        <div class="form-grid">


                            <!-- ADDRESS TYPE -->

                            <div class="form-group full">

                                <label>
                                    Address Type
                                </label>


                                <div
                                    class="address-type-buttons"
                                >


                                    <div class="type-option">

                                        <input
                                            type="radio"
                                            id="typeHome"
                                            name="address_type"
                                            value="home"
                                            checked
                                        >

                                        <label
                                            for="typeHome"
                                        >

                                            <i class="fas fa-house"></i>

                                            Home

                                        </label>

                                    </div>


                                    <div class="type-option">

                                        <input
                                            type="radio"
                                            id="typeWork"
                                            name="address_type"
                                            value="work"
                                        >

                                        <label
                                            for="typeWork"
                                        >

                                            <i class="fas fa-briefcase"></i>

                                            Work

                                        </label>

                                    </div>


                                    <div class="type-option">

                                        <input
                                            type="radio"
                                            id="typeOther"
                                            name="address_type"
                                            value="other"
                                        >

                                        <label
                                            for="typeOther"
                                        >

                                            <i class="fas fa-location-dot"></i>

                                            Other

                                        </label>

                                    </div>


                                </div>

                            </div>


                            <!-- LABEL -->

                            <div class="form-group">

                                <label for="label">
                                    Address Name
                                </label>

                                <input
                                    type="text"
                                    id="label"
                                    name="label"
                                    placeholder="e.g. Home, Office"
                                >

                            </div>


                            <!-- FULL NAME -->

                            <div class="form-group">

                                <label for="full_name">
                                    Full Name *
                                </label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php
                                        echo h(
                                            $user['full_name']
                                        );
                                    ?>"
                                    required
                                >

                            </div>


                            <!-- PHONE -->

                            <div class="form-group">

                                <label for="phone">
                                    Phone Number *
                                </label>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?php
                                        echo h(
                                            $user['phone']
                                        );
                                    ?>"
                                    required
                                >

                            </div>


                            <!-- CITY -->

                            <div class="form-group">

                                <label for="city">
                                    City *
                                </label>

                                <input
                                    type="text"
                                    id="city"
                                    name="city"
                                    value="Hyderabad"
                                    required
                                >

                            </div>


                            <!-- AREA -->

                            <div class="form-group">

                                <label for="area">
                                    Area
                                </label>

                                <input
                                    type="text"
                                    id="area"
                                    name="area"
                                    placeholder="e.g. Latifabad"
                                >

                            </div>


                            <!-- LANDMARK -->

                            <div class="form-group">

                                <label for="landmark">
                                    Landmark
                                </label>

                                <input
                                    type="text"
                                    id="landmark"
                                    name="landmark"
                                    placeholder="Nearby landmark"
                                >

                            </div>


                            <!-- ADDRESS -->

                            <div class="form-group full">

                                <label for="address">
                                    Complete Address *
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    placeholder="House number, street, block, etc."
                                    required
                                ></textarea>

                            </div>


                        </div>


                        <div class="checkbox-row">

                            <input
                                type="checkbox"
                                id="is_default"
                                name="is_default"
                                value="1"
                            >

                            <label for="is_default">
                                Make this my default address
                            </label>

                        </div>


                        <button
                            type="submit"
                            name="save_address"
                            value="1"
                            class="btn btn-primary save-address-btn"
                        >

                            <i class="fas fa-location-dot"></i>

                            Save Address

                        </button>


                    </form>

                </div>

            </div>


            <!-- =================================================
                 PAYMENT METHOD
            ================================================= -->

            <div class="checkout-card">

                <h2>

                    <i class="fas fa-credit-card"></i>

                    Payment Method

                </h2>


                <div class="payment-options">


                    <label
                        class="payment-option selected"
                    >

                        <input
                            type="radio"
                            name="checkout_payment"
                            value="cash_on_delivery"
                            checked
                            onchange="selectPayment(this)"
                        >

                        <i class="fas fa-money-bill-wave"></i>


                        <div>

                            <strong>
                                Cash on Delivery
                            </strong>

                            <small>
                                Pay when your order arrives.
                            </small>

                        </div>

                    </label>


                    <label
                        class="payment-option"
                    >

                        <input
                            type="radio"
                            name="checkout_payment"
                            value="card"
                            onchange="selectPayment(this)"
                        >

                        <i class="fas fa-credit-card"></i>


                        <div>

                            <strong>
                                Card Payment
                            </strong>

                            <small>
                                Card payment integration can be connected later.
                            </small>

                        </div>

                    </label>


                    <label
                        class="payment-option"
                    >

                        <input
                            type="radio"
                            name="checkout_payment"
                            value="online"
                            onchange="selectPayment(this)"
                        >

                        <i class="fas fa-mobile-screen-button"></i>


                        <div>

                            <strong>
                                Online Payment
                            </strong>

                            <small>
                                Online payment gateway can be connected later.
                            </small>

                        </div>

                    </label>


                </div>

            </div>


        </div>


        <!-- =================================================
             RIGHT SIDE
        ================================================= -->

        <div>


            <div class="checkout-card summary-card">

                <h2>

                    <i class="fas fa-receipt"></i>

                    Order Summary

                </h2>


                <div class="summary-row">

                    <span>
                        Items
                    </span>

                    <span>
                        <?php
                            echo (int)$totalItems;
                        ?>
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <span>
                        Rs.
                        <?php
                            echo number_format(
                                $subtotal,
                                2
                            );
                        ?>
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Delivery Fee
                    </span>

                    <span>
                        Rs.
                        <?php
                            echo number_format(
                                $deliveryFee,
                                2
                            );
                        ?>
                    </span>

                </div>


                <?php if ($discount > 0) { ?>

                    <div class="summary-row">

                        <span>
                            Discount
                        </span>

                        <span style="color:#198754;">

                            − Rs.
                            <?php
                                echo number_format(
                                    $discount,
                                    2
                                );
                            ?>

                        </span>

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
                                $total,
                                2
                            );
                        ?>
                    </span>

                </div>


                <!-- =================================================
                     CUSTOMER NOTE
                ================================================= -->

                <div class="note-box">

                    <label for="customerNote">
                        Order Note
                    </label>

                    <textarea
                        id="customerNote"
                        placeholder="Any special instructions?"
                    ></textarea>

                </div>


                <!-- =================================================
                     PLACE ORDER FORM
                ================================================= -->

                <form
                    id="placeOrderForm"
                    method="POST"
                    action="checkout.php"
                    onsubmit="return prepareOrder();"
                >

                    <input
                        type="hidden"
                        name="place_order"
                        value="1"
                    >


                    <input
                        type="hidden"
                        name="address_id"
                        id="formAddressId"
                        value="<?php
                            echo (int)$selectedAddressId;
                        ?>"
                    >


                    <input
                        type="hidden"
                        name="payment_method"
                        id="formPaymentMethod"
                        value="<?php
                            echo h($paymentMethod);
                        ?>"
                    >


                    <input
                        type="hidden"
                        name="customer_note"
                        id="formCustomerNote"
                        value=""
                    >


                    <button
                        type="submit"
                        class="btn btn-primary place-order-btn"
                    >

                        <i class="fas fa-check"></i>

                        Place Order

                    </button>

                </form>


                <a
                    href="cart.php"
                    class="back-cart"
                >

                    <i class="fas fa-arrow-left"></i>

                    &nbsp; Back to Cart

                </a>


                <div class="security-note">

                    <i class="fas fa-shield-halved"></i>

                    Your order information is secure.

                </div>


            </div>

        </div>


    </div>

</div>


<script>

/* =====================================================
   SELECT ADDRESS
===================================================== */

function selectAddress(addressId) {

    document.getElementById(
        'formAddressId'
    ).value = addressId;


    var options =
        document.querySelectorAll(
            '.address-option'
        );


    options.forEach(function(option) {

        option.classList.remove(
            'selected'
        );

    });


    var selected =
        document.querySelector(
            'input[value="' +
            addressId +
            '"]'
        );


    if (
        selected &&
        selected.closest('.address-option')
    ) {

        selected.closest(
            '.address-option'
        ).classList.add(
            'selected'
        );

    }

}


/* =====================================================
   SELECT PAYMENT
===================================================== */

function selectPayment(radio) {

    var options =
        document.querySelectorAll(
            '.payment-option'
        );


    options.forEach(function(option) {

        option.classList.remove(
            'selected'
        );

    });


    var selectedOption =
        radio.closest(
            '.payment-option'
        );


    if (selectedOption) {

        selectedOption.classList.add(
            'selected'
        );

    }


    document.getElementById(
        'formPaymentMethod'
    ).value =
        radio.value;

}


/* =====================================================
   TOGGLE NEW ADDRESS FORM
===================================================== */

function toggleNewAddressForm() {

    var form =
        document.getElementById(
            'newAddressForm'
        );


    if (!form) {
        return;
    }


    if (
        form.classList.contains('show')
    ) {

        form.classList.remove('show');

    } else {

        form.classList.add('show');

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

    }

}


/* =====================================================
   PREPARE ORDER
===================================================== */

function prepareOrder() {

    var addressId =
        document.getElementById(
            'formAddressId'
        ).value;


    var paymentMethod =
        document.getElementById(
            'formPaymentMethod'
        ).value;


    var note =
        document.getElementById(
            'customerNote'
        ).value;


    if (
        !addressId ||
        parseInt(addressId) <= 0
    ) {

        alert(
            'Please select a delivery address.'
        );

        return false;

    }


    if (!paymentMethod) {

        alert(
            'Please select a payment method.'
        );

        return false;

    }


    document.getElementById(
        'formCustomerNote'
    ).value = note;


    return true;

}


/* =====================================================
   INITIAL ADDRESS
===================================================== */

document.addEventListener(
    'DOMContentLoaded',
    function() {

        var addressId =
            document.getElementById(
                'formAddressId'
            ).value;


        if (
            addressId &&
            parseInt(addressId) > 0
        ) {

            selectAddress(
                parseInt(addressId)
            );

        }


        var payment =
            document.querySelector(
                'input[name="checkout_payment"]:checked'
            );


        if (payment) {

            selectPayment(
                payment
            );

        }

    }
    if (!paymentMethod) {

        alert('Please select a payment method.');

        return false;

    }


    document.getElementById('formCustomerNote').value =
        note;


    return true;


);

</script>


</body>
</html>