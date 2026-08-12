<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| CUSTOMER CART PAGE
|--------------------------------------------------------------------------
| File:
| /cart.php
|
| Uses:
| - includes/config.php
| - includes/session.php
| - includes/customer-header.php
|
| Cart table:
| - id
| - user_id
| - menu_item_id
| - quantity
|
| Customer address table:
| - customer_addresses
| - id
| - user_id
| - address_title
| - address_line
| - city
| - area
| - phone
| - is_default
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    (int)$_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function cart_h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| IMAGE PATH HELPERS
|--------------------------------------------------------------------------
*/

function cartRestaurantImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }

    return
        'assets/images/restaurants/' .
        basename($image);
}


function cartMenuImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }

    return
        'assets/images/menu-items/' .
        basename($image);
}


/*
|--------------------------------------------------------------------------
| GET CART ITEMS
|--------------------------------------------------------------------------
*/

$cartItems = [];

$cartSql = "
    SELECT
        c.id AS cart_id,
        c.menu_item_id,
        c.quantity,

        m.name AS item_name,
        m.description AS item_description,
        m.price AS item_price,
        m.image AS item_image,
        m.restaurant_id,

        r.name AS restaurant_name,
        r.image AS restaurant_image,
        r.address AS restaurant_address,
        r.delivery_time,
        r.delivery_fee,
        r.latitude AS restaurant_latitude,
        r.longitude AS restaurant_longitude

    FROM cart c

    INNER JOIN menu_items m
        ON c.menu_item_id = m.id

    INNER JOIN restaurants r
        ON m.restaurant_id = r.id

    WHERE c.user_id = ?

    ORDER BY c.id ASC
";


$cartStmt = $conn->prepare($cartSql);

if (!$cartStmt) {
    die(
        'Unable to load cart: ' .
        $conn->error
    );
}


$cartStmt->bind_param(
    "i",
    $userId
);

$cartStmt->execute();

$cartResult =
    $cartStmt->get_result();


while (
    $row =
    $cartResult->fetch_assoc()
) {

    $row['cart_id'] =
        (int)$row['cart_id'];

    $row['menu_item_id'] =
        (int)$row['menu_item_id'];

    $row['quantity'] =
        max(
            1,
            (int)$row['quantity']
        );

    $row['item_price'] =
        (float)$row['item_price'];

    $row['item_subtotal'] =
        $row['item_price'] *
        $row['quantity'];

    $cartItems[] =
        $row;
}


$cartStmt->close();


/*
|--------------------------------------------------------------------------
| BASIC CART VALUES
|--------------------------------------------------------------------------
*/

$isCartEmpty =
    empty($cartItems);

$totalItems = 0;

$subtotal = 0;


foreach (
    $cartItems
    as $item
) {

    $totalItems +=
        (int)$item['quantity'];

    $subtotal +=
        (float)$item['item_subtotal'];
}


/*
|--------------------------------------------------------------------------
| RESTAURANTS IN CART
|--------------------------------------------------------------------------
*/

$restaurantIds = [];

foreach (
    $cartItems
    as $item
) {

    $restaurantIds[] =
        (int)$item['restaurant_id'];
}


$restaurantIds =
    array_values(
        array_unique(
            $restaurantIds
        )
    );


$multipleRestaurants =
    count($restaurantIds) > 1;


/*
|--------------------------------------------------------------------------
| FIRST RESTAURANT
|--------------------------------------------------------------------------
*/

$restaurantId = 0;

$restaurantName = '';

$restaurantImage = '';

$restaurantAddress = '';

$deliveryTime = '';

$deliveryFee = 0;


if (!$isCartEmpty) {

    $restaurantId =
        (int)
        $cartItems[0]['restaurant_id'];

    $restaurantName =
        $cartItems[0]['restaurant_name']
        ?? '';

    $restaurantImage =
        $cartItems[0]['restaurant_image']
        ?? '';

    $restaurantAddress =
        $cartItems[0]['restaurant_address']
        ?? '';

    $deliveryTime =
        trim(
            (string)
            (
                $cartItems[0]['delivery_time']
                ?? ''
            )
        );

    $deliveryFee =
        (float)
        (
            $cartItems[0]['delivery_fee']
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| DELIVERY FEE
|--------------------------------------------------------------------------
|
| Current database has restaurant.delivery_fee.
|
| Later, when customer_addresses and restaurants
| both have valid latitude/longitude, this can be
| replaced with the kilometer-based calculation.
|
*/

if (
    $multipleRestaurants
) {

    $deliveryFee = 0;
}


/*
|--------------------------------------------------------------------------
| DISCOUNT
|--------------------------------------------------------------------------
*/

$discount = 0;


/*
|--------------------------------------------------------------------------
| GRAND TOTAL
|--------------------------------------------------------------------------
*/

$grandTotal =
    $subtotal +
    $deliveryFee -
    $discount;


/*
|--------------------------------------------------------------------------
| CUSTOMER ADDRESSES
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We use customer_addresses.
|
| NOT:
| addresses
|
| Actual columns:
| address_title
| address_line
| city
| area
| phone
| is_default
|--------------------------------------------------------------------------
*/

$addresses = [];

$addressSql = "
    SELECT
        id,
        user_id,
        address_title,
        address_line,
        city,
        area,
        phone,
        is_default,
        created_at,
        updated_at

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
        $userId
    );

    $addressStmt->execute();

    $addressResult =
        $addressStmt->get_result();


    while (
        $addressRow =
        $addressResult->fetch_assoc()
    ) {

        $addresses[] =
            $addressRow;
    }


    $addressStmt->close();
}


/*
|--------------------------------------------------------------------------
| SELECT DEFAULT ADDRESS
|--------------------------------------------------------------------------
*/

$selectedAddressId = 0;

$selectedAddress = null;


foreach (
    $addresses
    as $address
) {

    if (
        (int)
        $address['is_default'] === 1
    ) {

        $selectedAddressId =
            (int)$address['id'];

        $selectedAddress =
            $address;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| IF NO DEFAULT
|--------------------------------------------------------------------------
|
| Use latest saved address.
|--------------------------------------------------------------------------
*/

if (
    $selectedAddressId <= 0 &&
    !empty($addresses)
) {

    $selectedAddressId =
        (int)
        $addresses[0]['id'];

    $selectedAddress =
        $addresses[0];
}


/*
|--------------------------------------------------------------------------
| SELECTED ADDRESS TEXT
|--------------------------------------------------------------------------
*/

$selectedAddressText = '';

$selectedAddressLabel = '';


if ($selectedAddress) {

    $selectedAddressLabel =
        $selectedAddress['address_title']
        ?? 'Address';


    $selectedParts = [];


    if (
        !empty(
            $selectedAddress['address_line']
        )
    ) {

        $selectedParts[] =
            $selectedAddress[
                'address_line'
            ];
    }


    if (
        !empty(
            $selectedAddress['area']
        )
    ) {

        $selectedParts[] =
            $selectedAddress[
                'area'
            ];
    }


    if (
        !empty(
            $selectedAddress['city']
        )
    ) {

        $selectedParts[] =
            $selectedAddress[
                'city'
            ];
    }


    $selectedAddressText =
        implode(
            ', ',
            $selectedParts
        );
}


/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/includes/customer-header.php';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Humsafar Food Delivery Cart"
    >

    <title>
        Your Cart - Humsafar
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {
            margin: 0;
            background: #f8f8f9;
            color: #222;
            font-family:
                "Segoe UI",
                Arial,
                Helvetica,
                sans-serif;
        }


        button,
        input {
            font-family: inherit;
        }


        a {
            text-decoration: none;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .humsafar-cart-page {
            min-height:
                calc(100vh - 100px);

            padding:
                35px 4%
                70px;

            background:
                linear-gradient(
                    180deg,
                    #fff7fa 0,
                    #ffffff 330px
                );
        }


        .cart-wrapper {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
        }


        /* =====================================================
           HEADING
        ===================================================== */

        .cart-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }


        .cart-heading h1 {
            margin: 0;
            color: #ed0038;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -.6px;
        }


        .cart-heading p {
            margin:
                7px 0 0;

            color: #777;
            font-size: 13px;
        }


        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: #ed0038;

            font-size: 13px;
            font-weight: 800;

            transition: .2s ease;
        }


        .continue-shopping:hover {
            color: #c90030;
            transform:
                translateX(-2px);
        }


        /* =====================================================
           MAIN GRID
        ===================================================== */

        .cart-layout {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                390px;

            gap: 25px;

            align-items: start;
        }


        /* =====================================================
           CARDS
        ===================================================== */

        .cart-card {
            background: #fff;

            border:
                1px solid #eeeeee;

            border-radius: 18px;

            box-shadow:
                0 8px 30px
                rgba(40, 15, 25, .06);

            overflow: hidden;
        }


        /* =====================================================
           RESTAURANT
        ===================================================== */

        .restaurant-header {
            padding: 20px 22px;

            display: flex;
            align-items: center;

            gap: 14px;

            border-bottom:
                1px solid #eeeeee;

            background:
                linear-gradient(
                    90deg,
                    #fff5f8,
                    #ffffff
                );
        }


        .restaurant-image {
            width: 60px;
            height: 60px;

            flex-shrink: 0;

            border-radius: 13px;

            overflow: hidden;

            background: #fff0f4;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #ed0038;

            font-size: 22px;
        }


        .restaurant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .restaurant-info {
            min-width: 0;
        }


        .restaurant-info h2 {
            margin: 0;

            color: #222;

            font-size: 18px;
            font-weight: 900;
        }


        .restaurant-address {
            margin-top: 5px;

            color: #888;

            font-size: 10px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }


        .restaurant-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;

            margin-top: 7px;

            color: #777;

            font-size: 10px;
            font-weight: 700;
        }


        .restaurant-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }


        .restaurant-meta i {
            color: #ed0038;
        }


        /* =====================================================
           MULTIPLE RESTAURANT WARNING
        ===================================================== */

        .restaurant-warning {
            margin: 18px 20px;

            padding: 14px;

            display: flex;
            gap: 10px;

            background: #fff7e8;

            border:
                1px solid #f0d29d;

            border-radius: 11px;

            color: #765000;

            font-size: 11px;
            line-height: 1.55;
        }


        .restaurant-warning i {
            color: #df9700;
            margin-top: 2px;
        }


        .restaurant-warning strong {
            display: block;
            margin-bottom: 3px;
            font-size: 12px;
        }


        /* =====================================================
           ITEMS HEADING
        ===================================================== */

        .items-heading {
            padding:
                18px 22px
                12px;

            color: #333;

            font-size: 14px;
            font-weight: 900;
        }


        /* =====================================================
           ITEM
        ===================================================== */

        .cart-item {
            margin:
                0 18px
                12px;

            padding: 15px;

            display: grid;

            grid-template-columns:
                88px
                minmax(0, 1fr)
                115px
                95px
                35px;

            align-items: center;

            gap: 14px;

            border:
                1px solid #eeeeee;

            border-radius: 14px;

            background: #fff;

            transition: .2s ease;
        }


        .cart-item:hover {
            border-color: #f0b6c6;

            box-shadow:
                0 5px 18px
                rgba(237,0,56,.06);
        }


        .item-image {
            width: 88px;
            height: 78px;

            border-radius: 11px;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff0f4,
                    #ffffff
                );

            display: flex;
            align-items: center;
            justify-content: center;

            color: #ed0038;

            font-size: 22px;
        }


        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        .item-details {
            min-width: 0;
        }


        .item-details h3 {
            margin:
                0 0 5px;

            color: #222;

            font-size: 15px;
            font-weight: 900;
        }


        .item-description {
            margin: 0;

            color: #888;

            font-size: 10px;

            line-height: 1.45;

            display: -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        .item-price {
            margin-top: 7px;

            color: #ed0038;

            font-size: 12px;
            font-weight: 800;
        }


        /* =====================================================
           QUANTITY
        ===================================================== */

        .quantity-area {
            text-align: center;
        }


        .quantity-label {
            margin-bottom: 6px;

            color: #999;

            font-size: 9px;
            font-weight: 700;
        }


        .quantity-box {
            display: inline-flex;

            align-items: center;

            border:
                1px solid #e3e3e3;

            border-radius: 8px;

            overflow: hidden;

            background: #fff;
        }


        .quantity-btn {
            width: 30px;
            height: 31px;

            border: 0;

            background: #fff;

            color: #ed0038;

            cursor: pointer;

            font-size: 13px;
            font-weight: 900;
        }


        .quantity-btn:hover {
            background: #fff1f5;
        }


        .quantity-number {
            min-width: 30px;

            text-align: center;

            color: #333;

            font-size: 11px;
            font-weight: 800;
        }


        .quantity-form {
            margin: 0;
        }


        .item-total {
            text-align: right;
        }


        .item-total-label {
            margin-bottom: 4px;

            color: #999;

            font-size: 9px;
        }


        .item-total-value {
            color: #222;

            font-size: 14px;
            font-weight: 900;
        }


        /* =====================================================
           REMOVE
        ===================================================== */

        .remove-item {
            width: 34px;
            height: 34px;

            border: 0;

            border-radius: 8px;

            background: #fff1f4;

            color: #d52949;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;

            transition: .2s ease;
        }


        .remove-item:hover {
            background: #ed0038;
            color: #fff;
        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-card {
            position: sticky;
            top: 95px;

            padding: 22px;
        }


        .summary-title {
            margin:
                0 0 20px;

            color: #222;

            font-size: 19px;
            font-weight: 900;
        }


        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            margin-bottom: 13px;

            color: #666;

            font-size: 12px;
        }


        .summary-row strong {
            color: #222;
            font-weight: 900;
        }


        .summary-divider {
            height: 1px;

            margin:
                17px 0;

            background: #eeeeee;
        }


        .summary-total {
            display: flex;
            align-items: center;
            justify-content: space-between;

            color: #222;

            font-size: 17px;
            font-weight: 900;
        }


        .summary-total strong {
            color: #ed0038;
            font-size: 20px;
        }


        /* =====================================================
           DELIVERY TIME
        ===================================================== */

        .delivery-time-box {
            margin-top: 18px;

            padding: 13px;

            display: flex;
            align-items: center;

            gap: 10px;

            background: #fff7fa;

            border:
                1px solid #f2c4d0;

            border-radius: 11px;
        }


        .delivery-time-icon {
            width: 35px;
            height: 35px;

            flex-shrink: 0;

            border-radius: 9px;

            background: #ed0038;

            color: #fff;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        .delivery-time-text {
            min-width: 0;
        }


        .delivery-time-text span {
            display: block;

            color: #888;

            font-size: 9px;
            font-weight: 700;
        }


        .delivery-time-text strong {
            display: block;

            margin-top: 3px;

            color: #222;

            font-size: 12px;
            font-weight: 900;
        }


        /* =====================================================
           ADDRESS
        ===================================================== */

        .summary-section {
            margin-top: 22px;
        }


        .section-title {
            display: flex;
            align-items: center;

            gap: 8px;

            margin-bottom: 10px;

            color: #333;

            font-size: 13px;
            font-weight: 900;
        }


        .section-title i {
            color: #ed0038;
        }


        .address-box {
            padding: 13px;

            background: #fff8fa;

            border:
                1px solid #f1c5d1;

            border-radius: 11px;
        }


        .address-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 10px;

            margin-bottom: 7px;
        }


        .address-label {
            color: #ed0038;

            font-size: 11px;
            font-weight: 900;

            text-transform: capitalize;
        }


        .address-default {
            padding:
                3px 7px;

            border-radius: 20px;

            background: #ed0038;

            color: #fff;

            font-size: 8px;
            font-weight: 900;
        }


        .address-text {
            margin: 0;

            color: #666;

            font-size: 10px;

            line-height: 1.55;
        }


        .address-phone {
            margin-top: 6px;

            color: #777;

            font-size: 10px;
        }


        .change-address {
            margin-top: 10px;

            padding: 0;

            border: 0;

            background: transparent;

            color: #ed0038;

            cursor: pointer;

            font-size: 10px;
            font-weight: 900;
        }


        .no-address {
            padding: 13px;

            border:
                1px solid #f0d4dc;

            border-radius: 10px;

            background: #fff8fa;

            color: #777;

            font-size: 10px;

            line-height: 1.5;
        }


        .add-address-link {
            display: inline-flex;

            margin-top: 8px;

            color: #ed0038;

            font-weight: 900;
        }


        /* =====================================================
           PROMO
        ===================================================== */

        .promo-box {
            margin-top: 20px;
        }


        .promo-form {
            display: flex;
            gap: 7px;
        }


        .promo-input {
            width: 100%;

            min-height: 40px;

            padding:
                0 11px;

            border:
                1px solid #ddd;

            border-radius: 8px;

            outline: none;

            color: #333;

            font-size: 11px;
        }


        .promo-input:focus {
            border-color: #ed0038;
        }


        .promo-button {
            flex-shrink: 0;

            min-width: 65px;

            border: 0;

            border-radius: 8px;

            background: #222;

            color: #fff;

            cursor: pointer;

            font-size: 10px;
            font-weight: 800;
        }


        /* =====================================================
           CHECKOUT
        ===================================================== */

        .checkout-btn {
            width: 100%;

            min-height: 48px;

            margin-top: 20px;

            border: 0;

            border-radius: 10px;

            background:
                linear-gradient(
                    90deg,
                    #ed0038,
                    #f64f7d
                );

            color: #fff;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            font-size: 13px;
            font-weight: 900;

            box-shadow:
                0 8px 20px
                rgba(237,0,56,.18);

            transition: .2s ease;
        }


        .checkout-btn:hover {
            transform:
                translateY(-1px);

            box-shadow:
                0 10px 25px
                rgba(237,0,56,.25);
        }


        .checkout-btn.disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }


        .security-note {
            margin-top: 11px;

            text-align: center;

            color: #999;

            font-size: 9px;
        }


        .security-note i {
            color: #40a563;
            margin-right: 4px;
        }


        /* =====================================================
           EMPTY CART
        ===================================================== */

        .empty-cart {
            padding:
                75px 25px;

            text-align: center;
        }


        .empty-cart-icon {
            width: 88px;
            height: 88px;

            margin:
                0 auto 18px;

            border-radius: 50%;

            background: #fff1f5;

            color: #ed0038;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 34px;
        }


        .empty-cart h2 {
            margin:
                0 0 8px;

            color: #222;

            font-size: 24px;
            font-weight: 900;
        }


        .empty-cart p {
            max-width: 420px;

            margin:
                0 auto 22px;

            color: #888;

            font-size: 13px;

            line-height: 1.6;
        }


        .browse-btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            padding:
                12px 20px;

            border-radius: 10px;

            background:
                linear-gradient(
                    90deg,
                    #ed0038,
                    #f64f7d
                );

            color: #fff;

            font-size: 12px;
            font-weight: 900;
        }


        /* =====================================================
           ADDRESS MODAL
        ===================================================== */

        .address-modal {
            position: fixed;

            inset: 0;

            z-index: 5000;

            display: none;

            align-items: center;
            justify-content: center;

            padding: 20px;

            background:
                rgba(20,10,15,.55);
        }


        .address-modal.open {
            display: flex;
        }


        .address-modal-box {
            width: 100%;
            max-width: 520px;

            max-height:
                calc(100vh - 40px);

            overflow-y: auto;

            border-radius: 18px;

            background: #fff;

            box-shadow:
                0 20px 60px
                rgba(0,0,0,.20);
        }


        .modal-header {
            padding:
                18px 20px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-bottom:
                1px solid #eee;
        }


        .modal-header h2 {
            margin: 0;

            color: #222;

            font-size: 18px;
            font-weight: 900;
        }


        .modal-close {
            width: 34px;
            height: 34px;

            border: 0;

            border-radius: 50%;

            background: #f5f5f5;

            color: #555;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        .modal-body {
            padding: 15px;
        }


        .address-option {
            position: relative;

            margin-bottom: 10px;

            padding: 14px;

            border:
                1px solid #e5e5e5;

            border-radius: 12px;

            cursor: pointer;

            transition: .2s ease;
        }


        .address-option:last-child {
            margin-bottom: 0;
        }


        .address-option:hover {
            border-color: #f0a8ba;
        }


        .address-option.selected {
            border-color: #ed0038;

            background: #fff7fa;

            box-shadow:
                0 4px 15px
                rgba(237,0,56,.06);
        }


        .address-option-radio {
            position: absolute;

            top: 15px;
            right: 15px;
        }


        .address-option-radio input {
            accent-color: #ed0038;
        }


        .address-option-title {
            padding-right: 30px;

            color: #222;

            font-size: 13px;
            font-weight: 900;
        }


        .address-option-default {
            display: inline-block;

            margin-left: 5px;

            padding:
                3px 6px;

            border-radius: 20px;

            background: #ed0038;

            color: #fff;

            font-size: 7px;
            font-weight: 900;
        }


        .address-option-text {
            margin-top: 7px;

            padding-right: 30px;

            color: #666;

            font-size: 10px;

            line-height: 1.5;
        }


        .address-option-phone {
            margin-top: 5px;

            color: #888;

            font-size: 9px;
        }


        .modal-footer {
            padding:
                15px;

            display: flex;
            gap: 8px;

            border-top:
                1px solid #eee;
        }


        .modal-footer a,
        .modal-footer button {
            min-height: 42px;

            border-radius: 9px;

            cursor: pointer;

            font-size: 11px;
            font-weight: 800;
        }


        .manage-address-btn {
            flex: 1;

            display: flex;
            align-items: center;
            justify-content: center;

            border:
                1px solid #ed0038;

            background: #fff;

            color: #ed0038;
        }


        .use-address-btn {
            flex: 1;

            border: 0;

            background: #ed0038;

            color: #fff;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 1100px) {

            .cart-item {
                grid-template-columns:
                    80px
                    minmax(0, 1fr)
                    105px
                    85px
                    34px;
            }

            .item-image {
                width: 80px;
                height: 72px;
            }

        }


        @media (max-width: 900px) {

            .cart-layout {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

        }


        @media (max-width: 650px) {

            .humsafar-cart-page {
                padding:
                    25px 12px
                    50px;
            }


            .cart-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }


            .cart-heading h1 {
                font-size: 27px;
            }


            .cart-item {
                grid-template-columns:
                    68px
                    minmax(0,1fr)
                    32px;

                padding: 12px;
                gap: 10px;
            }


            .item-image {
                width: 68px;
                height: 64px;
            }


            .quantity-area {
                grid-column: 2;

                justify-self: start;

                margin-top: 8px;
            }


            .item-total {
                grid-column: 2;

                text-align: left;

                margin-top: 3px;
            }


            .remove-item {
                grid-column: 3;
                grid-row: 1;
            }


            .restaurant-header {
                padding: 16px;
            }


            .summary-card {
                padding: 18px;
            }

        }

    </style>

</head>


<body>


<main class="humsafar-cart-page">


    <div class="cart-wrapper">


        <!-- =================================================
             PAGE HEADING
        ================================================== -->

        <div class="cart-heading">

            <div>

                <h1>
                    Your Cart
                </h1>


                <p>

                    <?php if (!$isCartEmpty): ?>

                        <?php
                        echo $totalItems;
                        ?>

                        <?php
                        echo
                            $totalItems === 1
                            ? ' item'
                            : ' items';
                        ?>

                        ready for checkout

                    <?php else: ?>

                        Your cart is currently empty.

                    <?php endif; ?>

                </p>

            </div>


            <a
                href="restaurants.php"
                class="continue-shopping"
            >

                <i
                    class="fas fa-arrow-left"
                ></i>

                Continue Shopping

            </a>

        </div>


        <?php if ($isCartEmpty): ?>


            <!-- =================================================
                 EMPTY CART
            ================================================== -->

            <section class="cart-card">


                <div class="empty-cart">


                    <div class="empty-cart-icon">

                        <i
                            class="
                                fas
                                fa-basket-shopping
                            "
                        ></i>

                    </div>


                    <h2>
                        Your Cart is Empty
                    </h2>


                    <p>

                        Looks like you haven't
                        added anything to your cart
                        yet. Explore restaurants and
                        order your favourite food.

                    </p>


                    <a
                        href="restaurants.php"
                        class="browse-btn"
                    >

                        <i
                            class="fas fa-store"
                        ></i>

                        Browse Restaurants

                    </a>


                </div>


            </section>


        <?php else: ?>


            <!-- =================================================
                 CART LAYOUT
            ================================================== -->

            <div class="cart-layout">


                <!-- =============================================
                     LEFT CART
                ============================================== -->

                <section class="cart-card">


                    <!-- RESTAURANT -->

                    <div class="restaurant-header">


                        <div class="restaurant-image">


                            <?php
                            $restaurantImg =
                                cartRestaurantImage(
                                    $restaurantImage
                                );
                            ?>


                            <?php if (
                                $restaurantImg !== ''
                            ): ?>

                                <img
                                    src="<?php
                                    echo cart_h(
                                        $restaurantImg
                                    );
                                    ?>"
                                    alt="<?php
                                    echo cart_h(
                                        $restaurantName
                                    );
                                    ?>"
                                >

                            <?php else: ?>

                                <i
                                    class="fas fa-store"
                                ></i>

                            <?php endif; ?>


                        </div>


                        <div class="restaurant-info">


                            <h2>

                                <?php
                                echo cart_h(
                                    $restaurantName
                                );
                                ?>

                            </h2>


                            <?php if (
                                $restaurantAddress !== ''
                            ): ?>

                                <div
                                    class="
                                        restaurant-address
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-location-dot
                                        "
                                    ></i>

                                    <?php
                                    echo cart_h(
                                        $restaurantAddress
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                            <div
                                class="
                                    restaurant-meta
                                "
                            >


                                <?php if (
                                    $deliveryTime !== ''
                                ): ?>

                                    <span>

                                        <i
                                            class="
                                                fas
                                                fa-clock
                                            "
                                        ></i>

                                        <?php
                                        echo cart_h(
                                            $deliveryTime
                                        );
                                        ?>

                                    </span>

                                <?php endif; ?>


                                <span>

                                    <i
                                        class="
                                            fas
                                            fa-truck
                                        "
                                    ></i>

                                    Delivery

                                </span>


                            </div>


                        </div>


                    </div>


                    <?php if (
                        $multipleRestaurants
                    ): ?>


                        <div
                            class="
                                restaurant-warning
                            "
                        >

                            <i
                                class="
                                    fas
                                    fa-triangle-exclamation
                                "
                            ></i>


                            <div>

                                <strong>
                                    Multiple restaurants
                                </strong>

                                Your cart contains items
                                from more than one
                                restaurant. Please keep
                                one restaurant's items in
                                the cart before checkout.

                            </div>

                        </div>


                    <?php endif; ?>


                    <!-- ITEMS HEADING -->

                    <div class="items-heading">

                        Cart Items

                    </div>


                    <!-- ITEMS -->

                    <?php foreach (
                        $cartItems
                        as $item
                    ): ?>


                        <article
                            class="cart-item"
                        >


                            <!-- IMAGE -->

                            <div class="item-image">


                                <?php
                                $itemImg =
                                    cartMenuImage(
                                        $item[
                                            'item_image'
                                        ]
                                    );
                                ?>


                                <?php if (
                                    $itemImg !== ''
                                ): ?>

                                    <img
                                        src="<?php
                                        echo cart_h(
                                            $itemImg
                                        );
                                        ?>"
                                        alt="<?php
                                        echo cart_h(
                                            $item[
                                                'item_name'
                                            ]
                                        );
                                        ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <i
                                        class="
                                            fas
                                            fa-utensils
                                        "
                                    ></i>

                                <?php endif; ?>


                            </div>


                            <!-- DETAILS -->

                            <div
                                class="item-details"
                            >


                                <h3>

                                    <?php
                                    echo cart_h(
                                        $item[
                                            'item_name'
                                        ]
                                    );
                                    ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $item[
                                            'item_description'
                                        ]
                                    )
                                ): ?>

                                    <p
                                        class="
                                            item-description
                                        "
                                    >

                                        <?php
                                        echo cart_h(
                                            $item[
                                                'item_description'
                                            ]
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>


                                <div
                                    class="item-price"
                                >

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


                            <!-- QUANTITY -->

                            <div
                                class="quantity-area"
                            >


                                <div
                                    class="
                                        quantity-label
                                    "
                                >
                                    Quantity
                                </div>


                                <form
                                    method="POST"
                                    action="update_cart.php"
                                    class="quantity-form"
                                >


                                    <input
                                        type="hidden"
                                        name="cart_id"
                                        value="<?php
                                        echo (int)
                                            $item[
                                                'cart_id'
                                            ];
                                        ?>"
                                    >


                                    <div
                                        class="
                                            quantity-box
                                        "
                                    >


                                        <button
                                            type="submit"
                                            name="quantity"
                                            value="<?php
                                            echo max(
                                                1,
                                                $item[
                                                    'quantity'
                                                ] - 1
                                            );
                                            ?>"
                                            class="
                                                quantity-btn
                                            "
                                            <?php
                                            echo
                                                $item[
                                                    'quantity'
                                                ] <= 1
                                                ? 'disabled'
                                                : '';
                                            ?>
                                        >

                                            −

                                        </button>


                                        <span
                                            class="
                                                quantity-number
                                            "
                                        >

                                            <?php
                                            echo (int)
                                                $item[
                                                    'quantity'
                                                ];
                                            ?>

                                        </span>


                                        <button
                                            type="submit"
                                            name="quantity"
                                            value="<?php
                                            echo
                                                (int)
                                                $item[
                                                    'quantity'
                                                ] + 1;
                                            ?>"
                                            class="
                                                quantity-btn
                                            "
                                        >

                                            +

                                        </button>


                                    </div>


                                </form>


                            </div>


                            <!-- TOTAL -->

                            <div
                                class="item-total"
                            >


                                <div
                                    class="
                                        item-total-label
                                    "
                                >
                                    Total
                                </div>


                                <div
                                    class="
                                        item-total-value
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


                            <!-- REMOVE -->

                            <a
                                href="
                                    remove_from_cart.php?id=<?php
                                    echo (int)
                                        $item[
                                            'cart_id'
                                        ];
                                    ?>"
                                class="remove-item"
                                title="Remove item"
                                onclick="
                                    return confirm(
                                        'Remove this item from cart?'
                                    );
                                "
                            >

                                <i
                                    class="fas fa-trash"
                                ></i>

                            </a>


                        </article>


                    <?php endforeach; ?>


                </section>


                <!-- =============================================
                     RIGHT SUMMARY
                ============================================== -->

                <aside
                    class="
                        cart-card
                        summary-card
                    "
                >


                    <h2
                        class="summary-title"
                    >

                        Order Summary

                    </h2>


                    <!-- SUBTOTAL -->

                    <div
                        class="summary-row"
                    >

                        <span>
                            Items
                        </span>

                        <strong>
                            <?php
                            echo $totalItems;
                            ?>
                        </strong>

                    </div>


                    <div
                        class="summary-row"
                    >

                        <span>
                            Subtotal
                        </span>

                        <strong>

                            Rs.
                            <?php
                            echo number_format(
                                $subtotal,
                                2
                            );
                            ?>

                        </strong>

                    </div>


                    <!-- DELIVERY -->

                    <div
                        class="summary-row"
                    >

                        <span>
                            Delivery Fee
                        </span>


                        <strong>

                            <?php if (
                                $multipleRestaurants
                            ): ?>

                                —

                            <?php else: ?>

                                Rs.
                                <?php
                                echo number_format(
                                    $deliveryFee,
                                    2
                                );
                                ?>

                            <?php endif; ?>

                        </strong>

                    </div>


                    <?php if (
                        $discount > 0
                    ): ?>


                        <div
                            class="summary-row"
                        >

                            <span>
                                Discount
                            </span>

                            <strong
                                style="
                                    color:#218c4b;
                                "
                            >

                                − Rs.
                                <?php
                                echo number_format(
                                    $discount,
                                    2
                                );
                                ?>

                            </strong>

                        </div>


                    <?php endif; ?>


                    <div
                        class="summary-divider"
                    ></div>


                    <!-- TOTAL -->

                    <div
                        class="summary-total"
                    >

                        <span>
                            Total
                        </span>

                        <strong
                            id="grandTotal"
                        >

                            Rs.
                            <?php
                            echo number_format(
                                $grandTotal,
                                2
                            );
                            ?>

                        </strong>

                    </div>


                    <!-- DELIVERY TIME -->

                    <?php if (
                        $deliveryTime !== ''
                    ): ?>


                        <div
                            class="
                                delivery-time-box
                            "
                        >


                            <div
                                class="
                                    delivery-time-icon
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-clock
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    delivery-time-text
                                "
                            >

                                <span>
                                    Estimated Delivery Time
                                </span>

                                <strong>

                                    <?php
                                    echo cart_h(
                                        $deliveryTime
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>


                    <?php endif; ?>


                    <!-- ADDRESS -->

                    <div
                        class="
                            summary-section
                        "
                    >


                        <div
                            class="
                                section-title
                            "
                        >

                            <i
                                class="
                                    fas
                                    fa-location-dot
                                "
                            ></i>

                            Delivery Address

                        </div>


                        <?php if (
                            $selectedAddress
                        ): ?>


                            <div
                                class="
                                    address-box
                                "
                                id="
                                    selectedAddressBox
                                "
                            >


                                <div
                                    class="
                                        address-top
                                    "
                                >


                                    <span
                                        class="
                                            address-label
                                        "
                                        id="
                                            selectedAddressLabel
                                        "
                                    >

                                        <?php
                                        echo cart_h(
                                            $selectedAddressLabel
                                        );
                                        ?>

                                    </span>


                                    <?php if (
                                        (int)
                                        $selectedAddress[
                                            'is_default'
                                        ] === 1
                                    ): ?>

                                        <span
                                            class="
                                                address-default
                                            "
                                        >
                                            Default
                                        </span>

                                    <?php endif; ?>


                                </div>


                                <p
                                    class="
                                        address-text
                                    "
                                    id="
                                        selectedAddressText
                                    "
                                >

                                    <?php
                                    echo cart_h(
                                        $selectedAddressText
                                    );
                                    ?>

                                </p>


                                <?php if (
                                    !empty(
                                        $selectedAddress[
                                            'phone'
                                        ]
                                    )
                                ): ?>


                                    <div
                                        class="
                                            address-phone
                                        "
                                        id="
                                            selectedAddressPhone
                                        "
                                    >

                                        <i
                                            class="
                                                fas
                                                fa-phone
                                            "
                                        ></i>

                                        <?php
                                        echo cart_h(
                                            $selectedAddress[
                                                'phone'
                                            ]
                                        );
                                        ?>

                                    </div>


                                <?php endif; ?>


                                <button
                                    type="button"
                                    class="
                                        change-address
                                    "
                                    onclick="
                                        openAddressModal();
                                    "
                                >

                                    <i
                                        class="fas fa-pen"
                                    ></i>

                                    Change Address

                                </button>


                            </div>


                        <?php else: ?>


                            <div
                                class="no-address"
                            >

                                <i
                                    class="
                                        fas
                                        fa-location-dot
                                    "
                                ></i>

                                No delivery address
                                has been saved yet.

                                <br>


                                <a
                                    href="
                                        customer/manage-addresses.php
                                    "
                                    class="
                                        add-address-link
                                    "
                                >

                                    Add Delivery Address

                                </a>

                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- PROMO -->

                    <div
                        class="promo-box"
                    >


                        <div
                            class="section-title"
                        >

                            <i
                                class="
                                    fas
                                    fa-ticket
                                "
                            ></i>

                            Promo Code

                        </div>


                        <div
                            class="promo-form"
                        >

                            <input
                                type="text"
                                id="promoCode"
                                class="promo-input"
                                placeholder="
                                    Enter promo code
                                "
                                autocomplete="off"
                            >


                            <button
                                type="button"
                                class="promo-button"
                                onclick="
                                    applyPromo();
                                "
                            >

                                Apply

                            </button>

                        </div>


                    </div>


                    <!-- CHECKOUT -->

                    <?php
                    $checkoutDisabled =
                        $multipleRestaurants ||
                        empty($addresses);
                    ?>


                    <button
                        type="button"
                        id="checkoutButton"
                        class="
                            checkout-btn
                            <?php
                            echo
                                $checkoutDisabled
                                ? 'disabled'
                                : '';
                            ?>
                        "
                        <?php
                        echo
                            $checkoutDisabled
                            ? 'disabled'
                            : '';
                        ?>
                        onclick="
                            proceedToCheckout();
                        "
                    >

                        <i
                            class="
                                fas
                                fa-lock
                            "
                        ></i>

                        Proceed to Checkout

                    </button>


                    <div
                        class="
                            security-note
                        "
                    >

                        <i
                            class="
                                fas
                                fa-shield-halved
                            "
                        ></i>

                        Your order information is
                        securely handled.

                    </div>


                </aside>


            </div>


        <?php endif; ?>


    </div>


</main>


<!-- =========================================================
     ADDRESS MODAL
========================================================== -->

<div
    class="address-modal"
    id="addressModal"
    aria-hidden="true"
>


    <div
        class="
            address-modal-box
        "
    >


        <div
            class="modal-header"
        >

            <h2>
                Select Delivery Address
            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="
                    closeAddressModal();
                "
            >

                <i
                    class="fas fa-xmark"
                ></i>

            </button>

        </div>


        <div
            class="modal-body"
        >


            <?php if (
                empty($addresses)
            ): ?>


                <div
                    class="no-address"
                >

                    You don't have any saved
                    delivery addresses.

                    <br>


                    <a
                        href="
                            customer/manage-addresses.php
                        "
                        class="
                            add-address-link
                        "
                    >

                        Add New Address

                    </a>

                </div>


            <?php else: ?>


                <?php foreach (
                    $addresses
                    as $address
                ): ?>


                    <?php

                    $optionParts = [];


                    if (
                        !empty(
                            $address[
                                'address_line'
                            ]
                        )
                    ) {

                        $optionParts[] =
                            $address[
                                'address_line'
                            ];
                    }


                    if (
                        !empty(
                            $address['area']
                        )
                    ) {

                        $optionParts[] =
                            $address['area'];
                    }


                    if (
                        !empty(
                            $address['city']
                        )
                    ) {

                        $optionParts[] =
                            $address['city'];
                    }


                    $optionText =
                        implode(
                            ', ',
                            $optionParts
                        );


                    $isSelected =
                        (
                            (int)
                            $address['id']
                            ===
                            $selectedAddressId
                        );

                    ?>


                    <div
                        class="
                            address-option
                            <?php
                            echo
                                $isSelected
                                ? 'selected'
                                : '';
                            ?>
                        "
                        data-address-id="<?php
                            echo (int)
                                $address['id'];
                        ?>"
                        data-address-label="<?php
                            echo cart_h(
                                $address[
                                    'address_title'
                                ]
                            );
                        ?>"
                        data-address-text="<?php
                            echo cart_h(
                                $optionText
                            );
                        ?>"
                        data-address-phone="<?php
                            echo cart_h(
                                $address['phone']
                                ?? ''
                            );
                        ?>"
                        onclick="
                            selectAddress(this);
                        "
                    >


                        <div
                            class="
                                address-option-radio
                            "
                        >

                            <input
                                type="radio"
                                name="selected_address"
                                value="<?php
                                echo (int)
                                    $address['id'];
                                ?>"
                                <?php
                                echo
                                    $isSelected
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                        </div>


                        <div
                            class="
                                address-option-title
                            "
                        >

                            <?php
                            echo cart_h(
                                $address[
                                    'address_title'
                                ]
                            );
                            ?>


                            <?php if (
                                (int)
                                $address[
                                    'is_default'
                                ] === 1
                            ): ?>


                                <span
                                    class="
                                        address-option-default
                                    "
                                >
                                    Default
                                </span>


                            <?php endif; ?>


                        </div>


                        <div
                            class="
                                address-option-text
                            "
                        >

                            <?php
                            echo cart_h(
                                $optionText
                            );
                            ?>

                        </div>


                        <?php if (
                            !empty(
                                $address['phone']
                            )
                        ): ?>

                            <div
                                class="
                                    address-option-phone
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-phone
                                    "
                                ></i>

                                <?php
                                echo cart_h(
                                    $address[
                                        'phone'
                                    ]
                                );
                                ?>

                            </div>

                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


        <div
            class="modal-footer"
        >


            <a
                href="
                    customer/manage-addresses.php
                "
                class="
                    manage-address-btn
                "
            >

                <i
                    class="
                        fas
                        fa-location-dot
                    "
                ></i>

                &nbsp;

                Manage Addresses

            </a>


            <button
                type="button"
                class="use-address-btn"
                onclick="
                    useSelectedAddress();
                "
            >

                Use This Address

            </button>


        </div>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SELECTED ADDRESS
|--------------------------------------------------------------------------
*/

var selectedAddressId =
    <?php
    echo (int)$selectedAddressId;
    ?>;


/*
|--------------------------------------------------------------------------
| OPEN ADDRESS MODAL
|--------------------------------------------------------------------------
*/

function openAddressModal()
{
    var modal =
        document.getElementById(
            'addressModal'
        );

    if (!modal) {
        return;
    }

    modal.classList.add(
        'open'
    );

    modal.setAttribute(
        'aria-hidden',
        'false'
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE ADDRESS MODAL
|--------------------------------------------------------------------------
*/

function closeAddressModal()
{
    var modal =
        document.getElementById(
            'addressModal'
        );

    if (!modal) {
        return;
    }

    modal.classList.remove(
        'open'
    );

    modal.setAttribute(
        'aria-hidden',
        'true'
    );
}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE MODAL
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'addressModal'
    )
    ?.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                this
            ) {

                closeAddressModal();

            }

        }
    );


/*
|--------------------------------------------------------------------------
| SELECT ADDRESS
|--------------------------------------------------------------------------
*/

function selectAddress(element)
{

    document
        .querySelectorAll(
            '.address-option'
        )
        .forEach(
            function(option) {

                option.classList.remove(
                    'selected'
                );


                var radio =
                    option.querySelector(
                        'input[type="radio"]'
                    );


                if (radio) {

                    radio.checked =
                        false;
                }

            }
        );


    element.classList.add(
        'selected'
    );


    var radio =
        element.querySelector(
            'input[type="radio"]'
        );


    if (radio) {

        radio.checked =
            true;
    }


    selectedAddressId =
        parseInt(
            element.getAttribute(
                'data-address-id'
            )
        );

}


/*
|--------------------------------------------------------------------------
| USE SELECTED ADDRESS
|--------------------------------------------------------------------------
*/

function useSelectedAddress()
{

    var selected =
        document.querySelector(
            '.address-option.selected'
        );


    if (!selected) {

        alert(
            'Please select a delivery address.'
        );

        return;
    }


    selectedAddressId =
        parseInt(
            selected.getAttribute(
                'data-address-id'
            )
        );


    var label =
        selected.getAttribute(
            'data-address-label'
        );


    var text =
        selected.getAttribute(
            'data-address-text'
        );


    var phone =
        selected.getAttribute(
            'data-address-phone'
        );


    var labelElement =
        document.getElementById(
            'selectedAddressLabel'
        );


    var textElement =
        document.getElementById(
            'selectedAddressText'
        );


    var phoneElement =
        document.getElementById(
            'selectedAddressPhone'
        );


    if (labelElement) {

        labelElement.textContent =
            label ||
            'Address';
    }


    if (textElement) {

        textElement.textContent =
            text || '';
    }


    if (phoneElement) {

        phoneElement.innerHTML =
            '<i class="fas fa-phone"></i> ' +
            escapeHtml(
                phone || ''
            );
    }


    closeAddressModal();

}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML FOR JS
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{

    var div =
        document.createElement(
            'div'
        );

    div.textContent =
        value;

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| PROMO
|--------------------------------------------------------------------------
*/

function applyPromo()
{

    var input =
        document.getElementById(
            'promoCode'
        );


    if (!input) {
        return;
    }


    var code =
        input.value
            .trim()
            .toUpperCase();


    if (code === '') {

        alert(
            'Please enter a promo code.'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Demo promo currently
    |--------------------------------------------------------------------------
    */

    if (
        code === 'WELCOME10'
    ) {

        alert(
            'Promo code accepted.\
            Real discount calculation will be connected to the coupons system.'
        );

    } else {

        alert(
            'Invalid promo code.'
        );

    }

}


/*
|--------------------------------------------------------------------------
| PROCEED TO CHECKOUT
|--------------------------------------------------------------------------
*/

function proceedToCheckout()
{

    <?php if (
        $multipleRestaurants
    ): ?>

        alert(
            'Please keep items from one restaurant in the cart before checkout.'
        );

        return;

    <?php endif; ?>


    <?php if (
        empty($addresses)
    ): ?>

        alert(
            'Please add a delivery address first.'
        );

        window.location.href =
            'customer/manage-addresses.php';

        return;

    <?php endif; ?>


    if (
        !selectedAddressId ||
        selectedAddressId <= 0
    ) {

        alert(
            'Please select a valid delivery address.'
        );

        openAddressModal();

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Send selected address ID to checkout
    |--------------------------------------------------------------------------
    */

    window.location.href =
        'checkout.php?address_id=' +
        encodeURIComponent(
            selectedAddressId
        );

}


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeAddressModal();

        }

    }
);

</script>


</body>

</html>