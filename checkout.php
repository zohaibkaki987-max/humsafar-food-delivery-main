<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| CUSTOMER CHECKOUT
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| VERIFIED PROJECT INCLUDES
|--------------------------------------------------------------------------
| Same pattern used by my-account.php
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/customer-header.php';


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    (int)$_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}


$userId =
    (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function checkout_h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| IMAGE HELPERS
|--------------------------------------------------------------------------
| Same paths used by cart.php
|--------------------------------------------------------------------------
*/

function checkoutRestaurantImage($image)
{
    $image =
        trim(
            (string)$image
        );

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
        strpos(
            $image,
            'assets/'
        ) === 0
    ) {
        return $image;
    }

    return
        'assets/images/restaurants/' .
        basename($image);
}


function checkoutMenuImage($image)
{
    $image =
        trim(
            (string)$image
        );

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
        strpos(
            $image,
            'assets/'
        ) === 0
    ) {
        return $image;
    }

    return
        'assets/images/menu/' .
        basename($image);
}


/*
|--------------------------------------------------------------------------
| GET CART
|--------------------------------------------------------------------------
| Same query structure as cart.php
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
        r.delivery_fee

    FROM cart c

    INNER JOIN menu_items m
        ON c.menu_item_id = m.id

    INNER JOIN restaurants r
        ON m.restaurant_id = r.id

    WHERE c.user_id = ?

    ORDER BY c.id ASC
";


$cartStmt =
    $conn->prepare(
        $cartSql
    );


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
| EMPTY CART
|--------------------------------------------------------------------------
*/

if (
    empty($cartItems)
) {

    header(
        'Location: cart.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| RESTAURANT CHECK
|--------------------------------------------------------------------------
*/

$restaurantIds = [];


foreach (
    $cartItems as $item
) {

    $restaurantIds[] =
        (int)$item[
            'restaurant_id'
        ];
}


$restaurantIds =
    array_values(
        array_unique(
            $restaurantIds
        )
    );


/*
|--------------------------------------------------------------------------
| SAME RESTAURANT ONLY
|--------------------------------------------------------------------------
| Existing checkout follows this rule.
|--------------------------------------------------------------------------
*/

if (
    count($restaurantIds) !== 1
) {

    die(
        'Your cart contains items from multiple restaurants. ' .
        'Please keep items from one restaurant in the cart.'
    );
}


$restaurantId =
    (int)$restaurantIds[0];


/*
|--------------------------------------------------------------------------
| RESTAURANT INFORMATION
|--------------------------------------------------------------------------
*/

$firstItem =
    $cartItems[0];


$restaurantName =
    $firstItem[
        'restaurant_name'
    ] ?? '';


$restaurantImage =
    $firstItem[
        'restaurant_image'
    ] ?? '';


$restaurantAddress =
    $firstItem[
        'restaurant_address'
    ] ?? '';


$deliveryTime =
    $firstItem[
        'delivery_time'
    ] ?? '';


$deliveryFee =
    (float)(
        $firstItem[
            'delivery_fee'
        ] ?? 0
    );


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$subtotal = 0;

$totalItems = 0;


foreach (
    $cartItems as $item
) {

    $subtotal +=
        (float)$item[
            'item_subtotal'
        ];

    $totalItems +=
        (int)$item[
            'quantity'
        ];
}


$discount = 0;


$total =
    $subtotal +
    $deliveryFee -
    $discount;


/*
|--------------------------------------------------------------------------
| CUSTOMER ADDRESSES
|--------------------------------------------------------------------------
| EXACTLY the working structure used by cart.php
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


if (!$addressStmt) {

    die(
        'Unable to load addresses: ' .
        $conn->error
    );

}


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


/*
|--------------------------------------------------------------------------
| SELECTED ADDRESS
|--------------------------------------------------------------------------
*/

$selectedAddressId = 0;


/*
|--------------------------------------------------------------------------
| ADDRESS FROM CART / URL
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_GET['address_id']
    )
    &&
    (int)$_GET['address_id'] > 0
) {

    $selectedAddressId =
        (int)$_GET['address_id'];
}


if (
    isset(
        $_POST['address_id']
    )
    &&
    (int)$_POST['address_id'] > 0
) {

    $selectedAddressId =
        (int)$_POST['address_id'];
}


/*
|--------------------------------------------------------------------------
| VERIFY SELECTED ADDRESS
|--------------------------------------------------------------------------
*/

if (
    $selectedAddressId > 0
) {

    $addressExists = false;


    foreach (
        $addresses as $address
    ) {

        if (
            (int)$address['id']
            ===
            $selectedAddressId
        ) {

            $addressExists = true;

            break;
        }
    }


    if (
        !$addressExists
    ) {

        $selectedAddressId = 0;
    }
}


/*
|--------------------------------------------------------------------------
| DEFAULT ADDRESS
|--------------------------------------------------------------------------
*/

if (
    $selectedAddressId <= 0
) {

    foreach (
        $addresses as $address
    ) {

        if (
            (int)$address[
                'is_default'
            ] === 1
        ) {

            $selectedAddressId =
                (int)$address['id'];

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| FIRST ADDRESS FALLBACK
|--------------------------------------------------------------------------
*/

if (
    $selectedAddressId <= 0
    &&
    !empty($addresses)
) {

    $selectedAddressId =
        (int)$addresses[0]['id'];
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD
|--------------------------------------------------------------------------
| Same values used by existing checkout flow.
|--------------------------------------------------------------------------
*/

$paymentMethod =
    'cash_on_delivery';


if (
    isset(
        $_POST['payment_method']
    )
) {

    $paymentMethod =
        trim(
            $_POST[
                'payment_method'
            ]
        );
}


$allowedPayments = [

    'cash_on_delivery',
    'card',
    'online'

];


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


/*
|--------------------------------------------------------------------------
| ORDER ERROR
|--------------------------------------------------------------------------
*/

$orderError = '';


/*
|--------------------------------------------------------------------------
| PLACE ORDER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'
    &&
    isset(
        $_POST['place_order']
    )
) {

    $selectedAddressId =
        isset(
            $_POST['address_id']
        )
        ?
        (int)$_POST['address_id']
        :
        0;


    $paymentMethod =
        isset(
            $_POST['payment_method']
        )
        ?
        trim(
            $_POST[
                'payment_method'
            ]
        )
        :
        'cash_on_delivery';


    $customerNote =
        isset(
            $_POST['customer_note']
        )
        ?
        trim(
            $_POST[
                'customer_note'
            ]
        )
        :
        '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PAYMENT
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $paymentMethod,
            $allowedPayments,
            true
        )
    ) {

        $orderError =
            'Please select a valid payment method.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ADDRESS
    |--------------------------------------------------------------------------
    */

    if (
        $orderError === ''
    ) {

        if (
            $selectedAddressId <= 0
        ) {

            $orderError =
                'Please select a delivery address.';

        } else {

            $verifyAddress =
                $conn->prepare("

                    SELECT id

                    FROM customer_addresses

                    WHERE id = ?

                    AND user_id = ?

                    LIMIT 1

                ");


            if (!$verifyAddress) {

                $orderError =
                    'Unable to verify address.';

            } else {

                $verifyAddress->bind_param(
                    "ii",
                    $selectedAddressId,
                    $userId
                );


                $verifyAddress->execute();


                $verifyResult =
                    $verifyAddress->get_result();


                if (
                    $verifyResult->num_rows
                    ===
                    0
                ) {

                    $orderError =
                        'Please select a valid delivery address.';
                }


                $verifyAddress->close();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK CART
    |--------------------------------------------------------------------------
    | Prices and delivery fee are read again from DB.
    |--------------------------------------------------------------------------
    */

    if (
        $orderError === ''
    ) {

        $freshCartStmt =
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


        if (!$freshCartStmt) {

            $orderError =
                'Unable to verify cart.';

        } else {

            $freshCartStmt->bind_param(
                "i",
                $userId
            );


            $freshCartStmt->execute();


            $freshResult =
                $freshCartStmt->get_result();


            $freshCart = [];

            $freshSubtotal = 0;

            $freshRestaurantId = 0;


            while (
                $freshItem =
                $freshResult->fetch_assoc()
            ) {

                $freshItem[
                    'quantity'
                ] =
                    max(
                        1,
                        (int)$freshItem[
                            'quantity'
                        ]
                    );


                $freshItem[
                    'price'
                ] =
                    (float)$freshItem[
                        'price'
                    ];


                $freshItem[
                    'subtotal'
                ] =
                    $freshItem[
                        'price'
                    ]
                    *
                    $freshItem[
                        'quantity'
                    ];


                $freshCart[] =
                    $freshItem;


                $freshSubtotal +=
                    $freshItem[
                        'subtotal'
                    ];


                if (
                    $freshRestaurantId
                    ===
                    0
                ) {

                    $freshRestaurantId =
                        (int)$freshItem[
                            'restaurant_id'
                        ];
                }
            }


            $freshCartStmt->close();


            if (
                empty($freshCart)
            ) {

                $orderError =
                    'Your cart is empty.';

            } elseif (
                $freshRestaurantId
                !==
                $restaurantId
            ) {

                $orderError =
                    'Your cart has changed. Please refresh the page.';

            } else {

                $subtotal =
                    $freshSubtotal;


                $deliveryFee =
                    isset(
                        $freshCart[0][
                            'delivery_fee'
                        ]
                    )
                    ?
                    (float)$freshCart[0][
                        'delivery_fee'
                    ]
                    :
                    $deliveryFee;


                $total =
                    $subtotal +
                    $deliveryFee -
                    $discount;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ORDER
    |--------------------------------------------------------------------------
    */

    if (
        $orderError === ''
    ) {

        $conn->begin_transaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | ORDER NUMBER
            |--------------------------------------------------------------------------
            */

            $orderNumber =
                'HUM-' .
                date('YmdHis') .
                '-' .
                random_int(
                    100,
                    999
                );


            /*
            |--------------------------------------------------------------------------
            | CHECK ORDER NUMBER
            |--------------------------------------------------------------------------
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


            if (
                $orderCheckResult->num_rows
                > 0
            ) {

                $orderNumber =
                    'HUM-' .
                    date('YmdHis') .
                    '-' .
                    random_int(
                        1000,
                        9999
                    );
            }


            $orderCheck->close();


            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER
            |--------------------------------------------------------------------------
            | Same order columns used by
            | the project's existing checkout.
            |--------------------------------------------------------------------------
            */

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
                $userId,
                $restaurantId,
                $selectedAddressId,
                $paymentMethod,
                $subtotal,
                $deliveryFee,
                $discount,
                $total,
                $customerNote

            );


            if (
                !$orderStmt->execute()
            ) {

                throw new Exception(
                    $orderStmt->error
                );
            }


            $orderId =
                (int)$conn->insert_id;


            $orderStmt->close();


            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER ITEMS
            |--------------------------------------------------------------------------
            */

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


            foreach (
                $cartItems as $item
            ) {

                $menuItemId =
                    (int)$item[
                        'menu_item_id'
                    ];


                $itemName =
                    $item[
                        'item_name'
                    ];


                $itemPrice =
                    (float)$item[
                        'item_price'
                    ];


                $quantity =
                    (int)$item[
                        'quantity'
                    ];


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


                if (
                    !$itemStmt->execute()
                ) {

                    throw new Exception(
                        $itemStmt->error
                    );
                }
            }


            $itemStmt->close();


            /*
            |--------------------------------------------------------------------------
            | CLEAR CART
            |--------------------------------------------------------------------------
            */

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
                $userId
            );


            if (
                !$deleteCart->execute()
            ) {

                throw new Exception(
                    $deleteCart->error
                );
            }


            $deleteCart->close();


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | EXISTING PROJECT SUCCESS PAGE
            |--------------------------------------------------------------------------
            */

            header(
                'Location: order_success.php?order_id=' .
                $orderId
            );

            exit;


        } catch (
            Throwable $e
        ) {

            $conn->rollback();


            $orderError =
                'Unable to place your order. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| SELECTED ADDRESS OBJECT
|--------------------------------------------------------------------------
*/

$selectedAddress = null;


foreach (
    $addresses as $address
) {

    if (
        (int)$address['id']
        ===
        $selectedAddressId
    ) {

        $selectedAddress =
            $address;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

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

    background: #f8f8f9;

    color: #222;

    font-family:
        "Segoe UI",
        Arial,
        Helvetica,
        sans-serif;
}


a {
    text-decoration: none;
}


.checkout-page {

    width: 100%;

    min-height:
        calc(
            100vh - 100px
        );

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


.checkout-wrapper {

    width: 100%;

    max-width: 1250px;

    margin: 0 auto;
}


/*
|--------------------------------------------------------------------------
| HEADING
|--------------------------------------------------------------------------
*/

.checkout-heading {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.checkout-heading h1 {

    margin: 0;

    color: #ed0038;

    font-size: 32px;

    font-weight: 800;
}


.checkout-heading p {

    margin:
        7px 0 0;

    color: #777;

    font-size: 13px;
}


.back-cart {

    color: #ed0038;

    font-size: 13px;

    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    padding: 13px 16px;

    margin-bottom: 20px;

    border-radius: 10px;

    font-size: 13px;
}


.error-message {

    background: #fff0f3;

    color: #c51e43;

    border:
        1px solid #ffd2dc;
}


/*
|--------------------------------------------------------------------------
| GRID
|--------------------------------------------------------------------------
*/

.checkout-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        380px;

    gap: 24px;

    align-items: start;
}


.checkout-card {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 16px;

    padding: 22px;

    margin-bottom: 18px;

    box-shadow:
        0 8px 30px
        rgba(
            40,
            15,
            25,
            .06
        );
}


.card-title {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 18px;
}


.card-title i {

    color: #ed0038;

    font-size: 17px;
}


.card-title h2 {

    margin: 0;

    color: #222;

    font-size: 19px;

    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| RESTAURANT
|--------------------------------------------------------------------------
*/

.restaurant-box {

    display: flex;

    align-items: center;

    gap: 14px;

    padding-bottom: 16px;

    border-bottom:
        1px solid #eeeeee;
}


.restaurant-image {

    width: 68px;

    height: 68px;

    flex-shrink: 0;

    overflow: hidden;

    border-radius: 13px;

    background: #fff0f4;

    border:
        2px solid #ed0038;
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

    color: #ed0038;

    font-size: 22px;
}


.restaurant-info h3 {

    margin: 0 0 5px;

    color: #222;

    font-size: 18px;
}


.restaurant-info p {

    margin: 4px 0;

    color: #777;

    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

.checkout-item {

    display: grid;

    grid-template-columns:
        70px
        minmax(0, 1fr)
        auto;

    gap: 13px;

    align-items: center;

    padding:
        14px 0;

    border-bottom:
        1px solid #eeeeee;
}


.checkout-item:last-child {

    border-bottom: 0;
}


.checkout-item-image {

    width: 70px;

    height: 65px;

    overflow: hidden;

    border-radius: 10px;

    background: #fff0f4;
}


.checkout-item-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}


.item-placeholder {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 20px;
}


.checkout-item-details h3 {

    margin: 0 0 5px;

    color: #222;

    font-size: 14px;

    font-weight: 700;
}


.checkout-item-details p {

    margin: 3px 0;

    color: #888;

    font-size: 12px;
}


.checkout-item-price {

    color: #222;

    font-size: 13px;

    font-weight: 700;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| ADDRESS
|--------------------------------------------------------------------------
*/

.address-list {

    display: flex;

    flex-direction: column;

    gap: 10px;
}


.address-option {

    display: flex;

    align-items: flex-start;

    gap: 11px;

    padding: 14px;

    border:
        2px solid #eeeeee;

    border-radius: 11px;

    cursor: pointer;

    transition: .2s;
}


.address-option:hover {

    border-color: #ed0038;

    background: #fff8fa;
}


.address-option.selected {

    border-color: #ed0038;

    background: #fff5f8;
}


.address-option input {

    margin-top: 4px;

    accent-color: #ed0038;
}


.address-details {

    flex: 1;
}


.address-details h4 {

    margin:
        0 0 5px;

    color: #222;

    font-size: 14px;
}


.address-details p {

    margin: 4px 0;

    color: #777;

    font-size: 12px;

    line-height: 1.5;
}


.default-badge {

    display: inline-block;

    margin-left: 6px;

    padding:
        3px 7px;

    border-radius: 12px;

    background: #ed0038;

    color: #fff;

    font-size: 9px;
}


.manage-address {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 12px;

    color: #ed0038;

    font-size: 12px;

    font-weight: 600;
}


.no-address {

    padding: 15px;

    border-radius: 10px;

    background: #fff5f7;

    color: #777;

    font-size: 13px;

    line-height: 1.6;
}


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

.payment-list {

    display: flex;

    flex-direction: column;

    gap: 10px;
}


.payment-option {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 14px;

    border:
        2px solid #eeeeee;

    border-radius: 11px;

    cursor: pointer;

    transition: .2s;
}


.payment-option:hover {

    border-color: #ed0038;

    background: #fff8fa;
}


.payment-option.selected {

    border-color: #ed0038;

    background: #fff5f8;
}


.payment-option input {

    accent-color: #ed0038;
}


.payment-icon {

    width: 38px;

    height: 38px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background: #fff0f4;

    color: #ed0038;
}


.payment-text strong {

    display: block;

    color: #222;

    font-size: 13px;
}


.payment-text small {

    display: block;

    margin-top: 3px;

    color: #888;

    font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.summary-card {

    position: sticky;

    top: 25px;
}


.summary-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 13px;

    color: #666;

    font-size: 13px;
}


.summary-row strong {

    color: #222;

    font-weight: 600;
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

    font-size: 18px;

    font-weight: 700;
}


.summary-total strong {

    color: #ed0038;

    font-size: 21px;
}


/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
*/

.note-box {

    margin-top: 18px;
}


.note-box label {

    display: block;

    margin-bottom: 7px;

    color: #333;

    font-size: 13px;

    font-weight: 600;
}


.note-box textarea {

    width: 100%;

    min-height: 75px;

    resize: vertical;

    padding: 10px;

    border:
        1px solid #ddd;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 12px;
}


.note-box textarea:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(
            237,
            0,
            56,
            .08
        );
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.place-order-btn {

    width: 100%;

    margin-top: 18px;

    padding: 14px;

    border: 0;

    border-radius: 9px;

    background: #ed0038;

    color: #fff;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;
}


.place-order-btn:hover {

    background: #d90034;

    transform:
        translateY(-1px);
}


.place-order-btn:disabled {

    opacity: .5;

    cursor: not-allowed;

    transform: none;
}


.security-note {

    margin-top: 12px;

    text-align: center;

    color: #888;

    font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 900px
) {

    .checkout-grid {

        grid-template-columns: 1fr;
    }


    .summary-card {

        position: relative;

        top: 0;
    }

}


@media (
    max-width: 600px
) {

    .checkout-page {

        padding:
            25px 12px
            50px;
    }


    .checkout-heading {

        align-items: flex-start;

        flex-direction: column;
    }


    .checkout-heading h1 {

        font-size: 27px;
    }


    .checkout-card {

        padding: 17px;

        border-radius: 13px;
    }


    .checkout-item {

        grid-template-columns:
            60px
            minmax(0, 1fr);

    }


    .checkout-item-image {

        width: 60px;

        height: 58px;
    }


    .checkout-item-price {

        grid-column: 2;

        text-align: left;
    }

}

</style>

</head>


<body>


<main
    class="checkout-page"
>

    <div
        class="checkout-wrapper"
    >


        <!-- =====================================================
             HEADING
        ====================================================== -->

        <div
            class="checkout-heading"
        >

            <div>

                <h1>

                    <i
                        class="fas fa-bag-shopping"
                    ></i>

                    Checkout

                </h1>


                <p>
                    Review your order before placing it.
                </p>

            </div>


            <a
                href="cart.php"
                class="back-cart"
            >

                <i
                    class="fas fa-arrow-left"
                ></i>

                Back to Cart

            </a>

        </div>


        <?php if (
            $orderError !== ''
        ): ?>

            <div
                class="
                    message
                    error-message
                "
            >

                <i
                    class="
                        fas
                        fa-circle-exclamation
                    "
                ></i>

                &nbsp;

                <?php
                    echo checkout_h(
                        $orderError
                    );
                ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="checkout.php"
            id="checkoutForm"
        >

            <div
                class="checkout-grid"
            >


                <!-- =================================================
                     LEFT SIDE
                ================================================== -->

                <div>


                    <!-- RESTAURANT -->

                    <section
                        class="checkout-card"
                    >

                        <div
                            class="card-title"
                        >

                            <i
                                class="
                                    fas
                                    fa-store
                                "
                            ></i>


                            <h2>
                                Restaurant
                            </h2>

                        </div>


                        <div
                            class="restaurant-box"
                        >

                            <div
                                class="restaurant-image"
                            >

                                <?php

                                $restaurantImagePath =
                                    checkoutRestaurantImage(
                                        $restaurantImage
                                    );

                                ?>


                                <?php if (
                                    $restaurantImagePath
                                    !==
                                    ''
                                ): ?>

                                    <img
                                        src="<?php
                                            echo checkout_h(
                                                $restaurantImagePath
                                            );
                                        ?>"
                                        alt="<?php
                                            echo checkout_h(
                                                $restaurantName
                                            );
                                        ?>"
                                    >

                                <?php else: ?>

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

                                <?php endif; ?>

                            </div>


                            <div
                                class="restaurant-info"
                            >

                                <h3>

                                    <?php
                                        echo checkout_h(
                                            $restaurantName
                                        );
                                    ?>

                                </h3>


                                <?php if (
                                    trim(
                                        (string)
                                        $restaurantAddress
                                    )
                                    !==
                                    ''
                                ): ?>

                                    <p>

                                        <i
                                            class="
                                                fas
                                                fa-location-dot
                                            "
                                        ></i>

                                        &nbsp;

                                        <?php
                                            echo checkout_h(
                                                $restaurantAddress
                                            );
                                        ?>

                                    </p>

                                <?php endif; ?>


                                <?php if (
                                    trim(
                                        (string)
                                        $deliveryTime
                                    )
                                    !==
                                    ''
                                ): ?>

                                    <p>

                                        <i
                                            class="
                                                fas
                                                fa-clock
                                            "
                                        ></i>

                                        &nbsp;

                                        Delivery:

                                        <?php
                                            echo checkout_h(
                                                $deliveryTime
                                            );
                                        ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>

                    </section>


                    <!-- ITEMS -->

                    <section
                        class="checkout-card"
                    >

                        <div
                            class="card-title"
                        >

                            <i
                                class="
                                    fas
                                    fa-utensils
                                "
                            ></i>


                            <h2>

                                Your Items

                                <span
                                    style="
                                        color:#888;
                                        font-size:12px;
                                        font-weight:400;
                                    "
                                >

                                    (
                                    <?php
                                        echo
                                        $totalItems;
                                    ?>
                                    )

                                </span>

                            </h2>

                        </div>


                        <?php foreach (
                            $cartItems
                            as $item
                        ): ?>


                            <?php

                            $itemImagePath =
                                checkoutMenuImage(
                                    $item[
                                        'item_image'
                                    ]
                                );

                            ?>


                            <div
                                class="
                                    checkout-item
                                "
                            >


                                <div
                                    class="
                                        checkout-item-image
                                    "
                                >

                                    <?php if (
                                        $itemImagePath
                                        !==
                                        ''
                                    ): ?>

                                        <img
                                            src="<?php
                                                echo checkout_h(
                                                    $itemImagePath
                                                );
                                            ?>"
                                            alt="<?php
                                                echo checkout_h(
                                                    $item[
                                                        'item_name'
                                                    ]
                                                );
                                            ?>"
                                        >

                                    <?php else: ?>

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

                                    <?php endif; ?>

                                </div>


                                <div
                                    class="
                                        checkout-item-details
                                    "
                                >

                                    <h3>

                                        <?php
                                            echo checkout_h(
                                                $item[
                                                    'item_name'
                                                ]
                                            );
                                        ?>

                                    </h3>


                                    <p>

                                        Qty:

                                        <?php
                                            echo
                                            (int)
                                            $item[
                                                'quantity'
                                            ];
                                        ?>

                                        × Rs.

                                        <?php
                                            echo
                                            number_format(
                                                (float)
                                                $item[
                                                    'item_price'
                                                ],
                                                0
                                            );
                                        ?>

                                    </p>


                                    <?php if (
                                        !empty(
                                            $item[
                                                'item_description'
                                            ]
                                        )
                                    ): ?>

                                        <p>

                                            <?php
                                                echo checkout_h(
                                                    $item[
                                                        'item_description'
                                                    ]
                                                );
                                            ?>

                                        </p>

                                    <?php endif; ?>

                                </div>


                                <div
                                    class="
                                        checkout-item-price
                                    "
                                >

                                    Rs.

                                    <?php
                                        echo
                                        number_format(
                                            (float)
                                            $item[
                                                'item_subtotal'
                                            ],
                                            0
                                        );
                                    ?>

                                </div>


                            </div>


                        <?php endforeach; ?>

                    </section>


                    <!-- ADDRESS -->

                    <section
                        class="checkout-card"
                    >

                        <div
                            class="card-title"
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
                        ): ?>


                            <div
                                class="no-address"
                            >

                                <i
                                    class="
                                        fas
                                        fa-circle-info
                                    "
                                ></i>

                                &nbsp;

                                You don't have a saved delivery
                                address yet.


                                <br><br>


                                <a
                                    href="customer/manage-addresses.php"
                                    class="
                                        manage-address
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-location-dot
                                        "
                                    ></i>

                                    Manage Addresses

                                </a>

                            </div>


                        <?php else: ?>


                            <div
                                class="address-list"
                            >

                                <?php foreach (
                                    $addresses
                                    as $address
                                ): ?>


                                    <?php

                                    $addressId =
                                        (int)
                                        $address[
                                            'id'
                                        ];


                                    $isSelected =
                                        (
                                            $addressId
                                            ===
                                            $selectedAddressId
                                        );

                                    ?>


                                    <label
                                        class="
                                            address-option
                                            <?php
                                            echo
                                            $isSelected
                                                ? 'selected'
                                                : '';
                                            ?>
                                        "
                                    >

                                        <input
                                            type="radio"
                                            name="address_id"
                                            value="<?php
                                                echo
                                                $addressId;
                                            ?>"
                                            <?php
                                            echo
                                            $isSelected
                                                ? 'checked'
                                                : '';
                                            ?>
                                            required
                                        >


                                        <div
                                            class="
                                                address-details
                                            "
                                        >

                                            <h4>

                                                <i
                                                    class="
                                                        fas
                                                        fa-location-dot
                                                    "
                                                ></i>

                                                <?php
                                                    echo checkout_h(
                                                        $address[
                                                            'address_title'
                                                        ]
                                                        ??
                                                        'Address'
                                                    );
                                                ?>


                                                <?php if (
                                                    (int)
                                                    $address[
                                                        'is_default'
                                                    ]
                                                    ===
                                                    1
                                                ): ?>


                                                    <span
                                                        class="
                                                            default-badge
                                                        "
                                                    >
                                                        Default
                                                    </span>


                                                <?php endif; ?>

                                            </h4>


                                            <p>

                                                <?php
                                                    echo checkout_h(
                                                        $address[
                                                            'address_line'
                                                        ]
                                                        ??
                                                        ''
                                                    );
                                                ?>

                                            </p>


                                            <p>

                                                <?php

                                                $locationParts = [];


                                                if (
                                                    !empty(
                                                        $address[
                                                            'area'
                                                        ]
                                                    )
                                                ) {

                                                    $locationParts[] =
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

                                                    $locationParts[] =
                                                        $address[
                                                            'city'
                                                        ];
                                                }


                                                echo checkout_h(
                                                    implode(
                                                        ', ',
                                                        $locationParts
                                                    )
                                                );

                                                ?>

                                            </p>


                                            <?php if (
                                                !empty(
                                                    $address[
                                                        'phone'
                                                    ]
                                                )
                                            ): ?>

                                                <p>

                                                    <i
                                                        class="
                                                            fas
                                                            fa-phone
                                                        "
                                                    ></i>

                                                    &nbsp;

                                                    <?php
                                                        echo checkout_h(
                                                            $address[
                                                                'phone'
                                                            ]
                                                        );
                                                    ?>

                                                </p>

                                            <?php endif; ?>

                                        </div>

                                    </label>


                                <?php endforeach; ?>

                            </div>


                            <a
                                href="customer/manage-addresses.php"
                                class="
                                    manage-address
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-location-dot
                                    "
                                ></i>

                                Manage Addresses

                            </a>


                        <?php endif; ?>

                    </section>


                    <!-- PAYMENT -->

                    <section
                        class="checkout-card"
                    >

                        <div
                            class="card-title"
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


                        <div
                            class="payment-list"
                        >


                            <label
                                class="
                                    payment-option
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'cash_on_delivery'
                                        ? 'selected'
                                        : '';
                                    ?>
                                "
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="cash_on_delivery"
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'cash_on_delivery'
                                        ? 'checked'
                                        : '';
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
                                        payment-text
                                    "
                                >

                                    <strong>
                                        Cash on Delivery
                                    </strong>

                                    <small>
                                        Pay when your order arrives.
                                    </small>

                                </span>

                            </label>


                            <label
                                class="
                                    payment-option
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'card'
                                        ? 'selected'
                                        : '';
                                    ?>
                                "
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="card"
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'card'
                                        ? 'checked'
                                        : '';
                                    ?>
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
                                        payment-text
                                    "
                                >

                                    <strong>
                                        Debit / Credit Card
                                    </strong>

                                    <small>
                                        Card payment option.
                                    </small>

                                </span>

                            </label>


                            <label
                                class="
                                    payment-option
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'online'
                                        ? 'selected'
                                        : '';
                                    ?>
                                "
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="online"
                                    <?php
                                    echo
                                    $paymentMethod
                                    ===
                                    'online'
                                        ? 'checked'
                                        : '';
                                    ?>
                                >


                                <span
                                    class="
                                        payment-icon
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-mobile-screen
                                        "
                                    ></i>

                                </span>


                                <span
                                    class="
                                        payment-text
                                    "
                                >

                                    <strong>
                                        Online Payment
                                    </strong>

                                    <small>
                                        Online payment option.
                                    </small>

                                </span>

                            </label>


                        </div>

                    </section>

                </div>


                <!-- =================================================
                     RIGHT SIDE
                ================================================== -->

                <aside>


                    <section
                        class="
                            checkout-card
                            summary-card
                        "
                    >

                        <div
                            class="card-title"
                        >

                            <i
                                class="
                                    fas
                                    fa-receipt
                                "
                            ></i>


                            <h2>
                                Order Summary
                            </h2>

                        </div>


                        <div
                            class="summary-row"
                        >

                            <span>
                                Items
                            </span>


                            <strong>

                                <?php
                                    echo
                                    $totalItems;
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
                                    echo
                                    number_format(
                                        $subtotal,
                                        0
                                    );
                                ?>

                            </strong>

                        </div>


                        <div
                            class="summary-row"
                        >

                            <span>
                                Delivery Fee
                            </span>


                            <strong>

                                <?php if (
                                    $deliveryFee
                                    >
                                    0
                                ): ?>

                                    Rs.

                                    <?php
                                        echo
                                        number_format(
                                            $deliveryFee,
                                            0
                                        );
                                    ?>

                                <?php else: ?>

                                    FREE

                                <?php endif; ?>

                            </strong>

                        </div>


                        <div
                            class="
                                summary-divider
                            "
                        ></div>


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
                                    echo
                                    number_format(
                                        $total,
                                        0
                                    );
                                ?>

                            </strong>

                        </div>


                        <div
                            class="note-box"
                        >

                            <label
                                for="customer_note"
                            >

                                Order Note
                                <span
                                    style="
                                        color:#999;
                                        font-weight:400;
                                    "
                                >
                                    (Optional)
                                </span>

                            </label>


                            <textarea
                                id="customer_note"
                                name="customer_note"
                                placeholder="Any special instructions..."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            name="place_order"
                            value="1"
                            class="
                                place-order-btn
                            "
                            <?php
                            echo
                            empty($addresses)
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

                            Complete Order

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

                            Your order information is secure.

                        </div>


                    </section>


                </aside>


            </div>

        </form>

    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| ADDRESS SELECTION
|--------------------------------------------------------------------------
*/

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

                                item.classList.remove(
                                    'selected'
                                );

                            }
                        );


                    option.classList.add(
                        'selected'
                    );

                }
            );

        }
    );


/*
|--------------------------------------------------------------------------
| PAYMENT SELECTION
|--------------------------------------------------------------------------
*/

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

                                item.classList.remove(
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


/*
|--------------------------------------------------------------------------
| FORM VALIDATION
|--------------------------------------------------------------------------
*/

var checkoutForm =
    document.getElementById(
        'checkoutForm'
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


</body>

</html>