<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| CUSTOMER CHECKOUT
|--------------------------------------------------------------------------
| Root file:
|   checkout.php
|
| IMPORTANT:
| - Cart item image path is copied from cart.php:
|   assets/images/restaurants/
|
| - Only ONE payment method is shown.
|
| - payment.php should save the selected method in:
|   $_SESSION['selected_payment_method']
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/customer-pricing.php';

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
| MENU ITEM IMAGE
|--------------------------------------------------------------------------
| EXACT SAME LOGIC AS cart.php
|--------------------------------------------------------------------------
*/

function checkoutMenuImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    /*
    | Full URL / absolute path / data URI
    */
    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }

    /*
    | Database already contains complete assets path
    */
    if (
        strpos(
            $image,
            'assets/'
        ) === 0
    ) {
        return $image;
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | This is the SAME path used by cart.php.
    |
    | Menu item images are also saved by the project inside:
    |
    | assets/images/restaurants/
    |--------------------------------------------------------------------------
    */

    return
        'assets/images/restaurants/' .
        basename($image);
}


/*
|--------------------------------------------------------------------------
| RESTAURANT IMAGE
|--------------------------------------------------------------------------
*/

function checkoutRestaurantImage($image)
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


/*
|--------------------------------------------------------------------------
| LOAD CART
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
    'i',
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
        humsafar_customer_price_from_db(
            $conn,
            (float)$row['item_price']
        );

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
| Same restaurant only.
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
    trim(
        (string)(
            $firstItem[
                'delivery_time'
            ] ?? ''
        )
    );


$deliveryFee =
    (float)(
        $firstItem[
            'delivery_fee'
        ] ?? 0
    );


/*
|--------------------------------------------------------------------------
| CART TOTALS
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
    'i',
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
| URL address selection
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


/*
| POST address selection
*/

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
| VERIFY ADDRESS
|--------------------------------------------------------------------------
*/

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
| PAYMENT
|--------------------------------------------------------------------------
|
| Checkout par koi payment selector nahi hoga.
|
| Sirf payment.php se selected/default payment show hogi.
|
| payment.php ko selected method is session key mein save karna hoga:
|
| $_SESSION['selected_payment_method']
|
|--------------------------------------------------------------------------
*/

$allowedPayments = [

    'cash_on_delivery',
    'card',
    'online'

];


$paymentMethod =
    'cash_on_delivery';


if (
    isset(
        $_SESSION[
            'selected_payment_method'
        ]
    )
    &&
    trim(
        (string)
        $_SESSION[
            'selected_payment_method'
        ]
    ) !== ''
) {

    $paymentMethod =
        trim(
            (string)
            $_SESSION[
                'selected_payment_method'
            ]
        );

}


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
| PAYMENT DISPLAY
|--------------------------------------------------------------------------
*/

$paymentTitle =
    'Cash on Delivery';


$paymentDescription =
    'Pay when your order arrives.';


$paymentIcon =
    'fa-money-bill-wave';


if (
    $paymentMethod === 'card'
) {

    $paymentTitle =
        'Debit / Credit Card';

    $paymentDescription =
        'Pay securely by card.';

    $paymentIcon =
        'fa-credit-card';

}


elseif (
    $paymentMethod === 'online'
) {

    $paymentTitle =
        'Online Payment';

    $paymentDescription =
        'Your selected online payment method.';

    $paymentIcon =
        'fa-mobile-screen';

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

    /*
    |--------------------------------------------------------------------------
    | PAYMENT
    |--------------------------------------------------------------------------
    | Do not trust payment value coming from browser.
    | Read it from session again.
    |--------------------------------------------------------------------------
    */

    $paymentMethod =
        'cash_on_delivery';


    if (
        isset(
            $_SESSION[
                'selected_payment_method'
            ]
        )
        &&
        trim(
            (string)
            $_SESSION[
                'selected_payment_method'
            ]
        ) !== ''
    ) {

        $paymentMethod =
            trim(
                (string)
                $_SESSION[
                    'selected_payment_method'
                ]
            );

    }


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
    | ADDRESS
    |--------------------------------------------------------------------------
    */

    $selectedAddressId =
        isset(
            $_POST['address_id']
        )
        ?
        (int)$_POST['address_id']
        :
        0;


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER NOTE
    |--------------------------------------------------------------------------
    */

    $customerNote =
        isset(
            $_POST['customer_note']
        )
        ?
        trim(
            $_POST['customer_note']
        )
        :
        '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ADDRESS
    |--------------------------------------------------------------------------
    */

    if (
        $selectedAddressId <= 0
    ) {

        $orderError =
            'Please select a delivery address.';

    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY ADDRESS BELONGS TO USER
    |--------------------------------------------------------------------------
    */

    if (
        $orderError === ''
    ) {

        $verifyAddress =
            $conn->prepare("
                SELECT id

                FROM customer_addresses

                WHERE id = ?
                AND user_id = ?

                LIMIT 1
            ");


        if (
            !$verifyAddress
        ) {

            $orderError =
                'Unable to verify address.';

        }

        else {

            $verifyAddress->bind_param(
                'ii',
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


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK CART
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

                ORDER BY c.id ASC
            ");


        if (
            !$freshCartStmt
        ) {

            $orderError =
                'Unable to verify cart.';

        }

        else {

            $freshCartStmt->bind_param(
                'i',
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

                $freshItem['quantity'] =
                    max(
                        1,
                        (int)$freshItem[
                            'quantity'
                        ]
                    );


                $freshItem['price'] =
                    (float)$freshItem[
                        'price'
                    ];


                $freshItem['subtotal'] =
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
                empty(
                    $freshCart
                )
            ) {

                $orderError =
                    'Your cart is empty.';

            }


            elseif (
                $freshRestaurantId
                !==
                $restaurantId
            ) {

                $orderError =
                    'Your cart has changed. Please refresh the page.';

            }


            else {

                $subtotal =
                    $freshSubtotal;


                $deliveryFee =
                    (float)(
                        $freshCart[0][
                            'delivery_fee'
                        ]
                        ??
                        0
                    );


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
            | FINAL CART ITEMS
            |--------------------------------------------------------------------------
            */

            $finalCartStmt =
                $conn->prepare("
                    SELECT

                        c.menu_item_id,
                        c.quantity,

                        m.name AS item_name,
                        m.price AS item_price,
                        m.restaurant_id

                    FROM cart c

                    INNER JOIN menu_items m
                        ON c.menu_item_id = m.id

                    WHERE c.user_id = ?

                    ORDER BY c.id ASC
                ");


            if (
                !$finalCartStmt
            ) {

                throw new Exception(
                    $conn->error
                );

            }


            $finalCartStmt->bind_param(
                'i',
                $userId
            );


            $finalCartStmt->execute();


            $finalResult =
                $finalCartStmt->get_result();


            $finalCartItems = [];


            while (
                $item =
                $finalResult->fetch_assoc()
            ) {

                $item['menu_item_id'] =
                    (int)$item[
                        'menu_item_id'
                    ];


                $item['quantity'] =
                    max(
                        1,
                        (int)$item[
                            'quantity'
                        ]
                    );


                $item['item_price'] =
                    (float)$item[
                        'item_price'
                    ];


                $item['item_subtotal'] =
                    $item[
                        'item_price'
                    ]
                    *
                    $item[
                        'quantity'
                    ];


                $finalCartItems[] =
                    $item;

            }


            $finalCartStmt->close();


            if (
                empty(
                    $finalCartItems
                )
            ) {

                throw new Exception(
                    'Cart is empty.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | FINAL SUBTOTAL
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;


            foreach (
                $finalCartItems as $item
            ) {

                $subtotal +=
                    (float)$item[
                        'item_subtotal'
                    ];

            }


            $total =
                $subtotal +
                $deliveryFee -
                $discount;


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


            if (
                !$orderCheck
            ) {

                throw new Exception(
                    $conn->error
                );

            }


            $orderCheck->bind_param(
                's',
                $orderNumber
            );


            $orderCheck->execute();


            $orderCheckResult =
                $orderCheck->get_result();


            if (
                $orderCheckResult->num_rows
                >
                0
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


            if (
                !$orderStmt
            ) {

                throw new Exception(
                    $conn->error
                );

            }


            $orderStmt->bind_param(
                'siiisdddds',

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


            if (
                !$itemStmt
            ) {

                throw new Exception(
                    $conn->error
                );

            }


            foreach (
                $finalCartItems as $item
            ) {

                $menuItemId =
                    (int)$item[
                        'menu_item_id'
                    ];


                $itemName =
                    (string)$item[
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
                    'iisdid',

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


            if (
                !$deleteCart
            ) {

                throw new Exception(
                    $conn->error
                );

            }


            $deleteCart->bind_param(
                'i',
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
            | SUCCESS PAGE
            |--------------------------------------------------------------------------
            */

            header(
                'Location: order_success.php?order_id=' .
                $orderId
            );

            exit;


        }

        catch (
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
| IMAGE
|--------------------------------------------------------------------------
*/

$restaurantImagePath =
    checkoutRestaurantImage(
        $restaurantImage
    );


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
        content="Humsafar Food Delivery Checkout"
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


        a {
            text-decoration: none;
        }


        button,
        input,
        textarea {
            font-family: inherit;
        }


        /* PAGE */

        .checkout-page {

            width: 100%;

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


        .checkout-wrapper {

            width: 100%;

            max-width: 1250px;

            margin: 0 auto;

        }


        /* HEADING */

        .checkout-heading {

            display: flex;

            align-items: flex-end;

            justify-content:
                space-between;

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

            display: inline-flex;

            align-items: center;

            gap: 7px;

            color: #ed0038;

            font-size: 13px;

            font-weight: 700;

        }


        /* ERROR */

        .message {

            padding:
                13px 16px;

            margin-bottom: 20px;

            border-radius: 10px;

            background: #fff0f3;

            color: #c51e43;

            border:
                1px solid #ffd2dc;

            font-size: 13px;

        }


        /* GRID */

        .checkout-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                380px;

            gap: 24px;

            align-items: start;

        }


        /* CARD */

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


        /* RESTAURANT */

        .restaurant-box {

            display: flex;

            align-items: center;

            gap: 14px;

        }


        .restaurant-image {

            width: 62px;

            height: 62px;

            flex: 0 0 62px;

            overflow: hidden;

            border-radius: 13px;

            background: #fff0f4;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ed0038;

            font-size: 21px;

        }


        .restaurant-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .restaurant-info {

            min-width: 0;

        }


        .restaurant-info h3 {

            margin:
                0 0 6px;

            color: #222;

            font-size: 17px;

            font-weight: 700;

        }


        .restaurant-info p {

            margin:
                4px 0;

            color: #777;

            font-size: 11px;

        }


        .restaurant-info p i {

            color: #ed0038;

            width: 15px;

        }


        /* ITEMS */

        .checkout-item {

            display: grid;

            grid-template-columns:
                78px
                minmax(0, 1fr)
                auto;

            align-items: center;

            gap: 14px;

            padding:
                13px 0;

            border-bottom:
                1px solid #eeeeee;

        }


        .checkout-item:last-child {

            border-bottom: 0;

        }


        .item-image {

            width: 78px;

            height: 70px;

            overflow: hidden;

            border-radius: 11px;

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

            font-size: 14px;

            font-weight: 700;

        }


        .item-description {

            margin: 0;

            color: #888;

            font-size: 11px;

            line-height: 1.45;

            display:
                -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient:
                vertical;

            overflow: hidden;

        }


        .item-price {

            margin-top: 7px;

            color: #ed0038;

            font-size: 12px;

        }


        .item-total {

            text-align: right;

            white-space: nowrap;

        }


        .item-total-label {

            margin-bottom: 4px;

            color: #999;

            font-size: 10px;

        }


        .item-total-value {

            color: #222;

            font-size: 14px;

            font-weight: 700;

        }


        /* ADDRESS */

        .address-list {

            display: flex;

            flex-direction: column;

            gap: 10px;

        }


        .address-option {

            position: relative;

            display: flex;

            align-items: flex-start;

            gap: 10px;

            padding: 14px;

            border:
                2px solid #eeeeee;

            border-radius: 11px;

            background: #fff;

            cursor: pointer;

            transition: .2s ease;

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

            min-width: 0;

        }


        .address-title-row {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 5px;

        }


        .address-title-row h4 {

            margin: 0;

            color: #222;

            font-size: 14px;

            font-weight: 750;

        }


        .address-title-row h4 i {

            margin-right: 4px;

            color: #222;

        }


        .default-badge {

            display: inline-flex;

            align-items: center;

            min-height: 20px;

            padding:
                0 8px;

            border-radius: 12px;

            background: #ed0038;

            color: #fff;

            font-size: 9px;

            font-weight: 800;

        }


        .address-details p {

            margin:
                4px 0;

            color: #777;

            font-size: 12px;

            line-height: 1.45;

        }


        .address-details .phone i {

            width: 15px;

        }


        .manage-address {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 13px;

            color: #ed0038;

            font-size: 12px;

            font-weight: 700;

        }


        .manage-address:hover {

            color: #c90032;

        }


        .no-address {

            padding: 14px;

            border:
                1px dashed #efb9ca;

            border-radius: 10px;

            background: #fff7f9;

            color: #777;

            font-size: 12px;

            line-height: 1.5;

        }


        /* PAYMENT */

        .payment-display {

            display: flex;

            align-items: center;

            gap: 11px;

            padding: 14px;

            border:
                2px solid #ed0038;

            border-radius: 11px;

            background: #fff5f8;

        }


        .payment-icon {

            width: 40px;

            height: 40px;

            flex:
                0 0 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background: #fff0f4;

            color: #ed0038;

            font-size: 16px;

        }


        .payment-text {

            min-width: 0;

        }


        .payment-text strong {

            display: block;

            color: #222;

            font-size: 13px;

            font-weight: 750;

        }


        .payment-text small {

            display: block;

            margin-top: 3px;

            color: #888;

            font-size: 11px;

        }


        .manage-payment {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 13px;

            color: #ed0038;

            font-size: 12px;

            font-weight: 700;

        }


        .manage-payment:hover {

            color: #c90032;

        }


        /* SUMMARY */

        .summary-card {

            position: sticky;

            top: 20px;

        }


        .summary-row {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 15px;

            margin-bottom: 12px;

            color: #666;

            font-size: 13px;

        }


        .summary-row strong {

            color: #222;

            font-weight: 650;

        }


        .summary-divider {

            height: 1px;

            margin:
                16px 0;

            background: #eeeeee;

        }


        .summary-total {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            color: #222;

            font-size: 18px;

            font-weight: 800;

        }


        .summary-total strong {

            color: #ed0038;

            font-size: 21px;

        }


        .note-box {

            margin-top: 17px;

        }


        .note-box label {

            display: block;

            margin-bottom: 7px;

            color: #333;

            font-size: 12px;

            font-weight: 700;

        }


        .note-box textarea {

            width: 100%;

            min-height: 78px;

            padding: 10px;

            resize: vertical;

            border:
                1px solid #ddd;

            border-radius: 9px;

            outline: none;

            color: #333;

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


        .place-order-btn {

            width: 100%;

            margin-top: 17px;

            padding: 14px;

            border: 0;

            border-radius: 9px;

            background: #ed0038;

            color: #fff;

            font-size: 14px;

            font-weight: 750;

            cursor: pointer;

            transition: .2s ease;

        }


        .place-order-btn:hover {

            background: #d90035;

            transform:
                translateY(-1px);

        }


        .place-order-btn:disabled {

            background: #cfcfcf;

            cursor: not-allowed;

            transform: none;

        }


        .security-note {

            margin-top: 10px;

            text-align: center;

            color: #999;

            font-size: 10px;

        }


        /* RESPONSIVE */

        @media (
            max-width: 900px
        ) {

            .checkout-grid {

                grid-template-columns:
                    1fr;

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

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .checkout-heading h1 {

                font-size: 27px;

            }


            .checkout-card {

                padding: 16px;

            }


            .checkout-item {

                grid-template-columns:
                    64px
                    minmax(0, 1fr);

            }


            .item-image {

                width: 64px;

                height: 60px;

            }


            .item-total {

                grid-column: 2;

                text-align: left;

            }

        }

    </style>

</head>


<body>


<main class="checkout-page">


    <div class="checkout-wrapper">


        <!-- HEADING -->

        <div class="checkout-heading">

            <div>

                <h1>

                    <i
                        class="
                            fas
                            fa-bag-shopping
                        "
                    ></i>

                    Checkout

                </h1>


                <p>
                    Review your order before
                    placing it.
                </p>

            </div>


            <a
                href="cart.php"
                class="back-cart"
            >

                <i
                    class="
                        fas
                        fa-arrow-left
                    "
                ></i>

                Back to Cart

            </a>

        </div>


        <!-- ERROR -->

        <?php if (
            $orderError !== ''
        ): ?>

            <div class="message">

                <i
                    class="
                        fas
                        fa-circle-exclamation
                    "
                ></i>

                <?= checkout_h(
                    $orderError
                ) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="checkout.php"
            id="checkoutForm"
        >


            <div class="checkout-grid">


                <!-- LEFT -->

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
                                class="
                                    restaurant-image
                                "
                            >

                                <?php if (
                                    $restaurantImagePath !== ''
                                ): ?>

                                    <img
                                        src="<?=
                                            checkout_h(
                                                $restaurantImagePath
                                            )
                                        ?>"
                                        alt="<?=
                                            checkout_h(
                                                $restaurantName
                                            )
                                        ?>"
                                        loading="lazy"
                                        onerror="
                                            this.style.display='none';
                                            this.nextElementSibling.style.display='flex';
                                        "
                                    >

                                    <div
                                        style="
                                            display:none;
                                            width:100%;
                                            height:100%;
                                            align-items:center;
                                            justify-content:center;
                                        "
                                    >

                                        <i
                                            class="
                                                fas
                                                fa-store
                                            "
                                        ></i>

                                    </div>

                                <?php else: ?>

                                    <i
                                        class="
                                            fas
                                            fa-store
                                        "
                                    ></i>

                                <?php endif; ?>

                            </div>


                            <div
                                class="
                                    restaurant-info
                                "
                            >

                                <h3>

                                    <?= checkout_h(
                                        $restaurantName
                                    ) ?>

                                </h3>


                                <?php if (
                                    $restaurantAddress !== ''
                                ): ?>

                                    <p>

                                        <i
                                            class="
                                                fas
                                                fa-location-dot
                                            "
                                        ></i>

                                        <?= checkout_h(
                                            $restaurantAddress
                                        ) ?>

                                    </p>

                                <?php endif; ?>


                                <?php if (
                                    $deliveryTime !== ''
                                ): ?>

                                    <p>

                                        <i
                                            class="
                                                fas
                                                fa-clock
                                            "
                                        ></i>

                                        Delivery:

                                        <?= checkout_h(
                                            $deliveryTime
                                        ) ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>

                    </section>


                    <!-- CART ITEMS -->

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
                                    <?= (int)$totalItems ?>
                                    )
                                </span>

                            </h2>

                        </div>


                        <?php foreach (
                            $cartItems
                            as $item
                        ): ?>


                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | EXACT CART.PHP IMAGE PATH
                            |--------------------------------------------------------------------------
                            */

                            $itemImage =
                                checkoutMenuImage(
                                    $item[
                                        'item_image'
                                    ] ?? ''
                                );

                            ?>


                            <div
                                class="
                                    checkout-item
                                "
                            >


                                <div
                                    class="
                                        item-image
                                    "
                                >


                                    <?php if (
                                        $itemImage !== ''
                                    ): ?>

                                        <img
                                            src="<?=
                                                checkout_h(
                                                    $itemImage
                                                )
                                            ?>"
                                            alt="<?=
                                                checkout_h(
                                                    $item[
                                                        'item_name'
                                                    ]
                                                )
                                            ?>"
                                            loading="lazy"
                                            onerror="
                                                this.style.display='none';
                                                this.nextElementSibling.style.display='flex';
                                            "
                                        >


                                        <div
                                            style="
                                                display:none;
                                                width:100%;
                                                height:100%;
                                                align-items:center;
                                                justify-content:center;
                                            "
                                        >

                                            <i
                                                class="
                                                    fas
                                                    fa-utensils
                                                "
                                            ></i>

                                        </div>


                                    <?php else: ?>

                                        <i
                                            class="
                                                fas
                                                fa-utensils
                                            "
                                        ></i>

                                    <?php endif; ?>


                                </div>


                                <div
                                    class="
                                        item-details
                                    "
                                >

                                    <h3>

                                        <?= checkout_h(
                                            $item[
                                                'item_name'
                                            ]
                                        ) ?>

                                    </h3>


                                    <?php if (
                                        trim(
                                            (string)(
                                                $item[
                                                    'item_description'
                                                ]
                                                ??
                                                ''
                                            )
                                        ) !== ''
                                    ): ?>

                                        <p
                                            class="
                                                item-description
                                            "
                                        >

                                            <?= checkout_h(
                                                $item[
                                                    'item_description'
                                                ]
                                            ) ?>

                                        </p>

                                    <?php endif; ?>


                                    <div
                                        class="
                                            item-price
                                        "
                                    >

                                        Rs.

                                        <?= number_format(
                                            (float)$item[
                                                'item_price'
                                            ],
                                            0
                                        ) ?>

                                        ×

                                        <?= (int)$item[
                                            'quantity'
                                        ] ?>

                                    </div>

                                </div>


                                <div
                                    class="
                                        item-total
                                    "
                                >

                                    <div
                                        class="
                                            item-total-label
                                        "
                                    >
                                        Item Total
                                    </div>


                                    <div
                                        class="
                                            item-total-value
                                        "
                                    >

                                        Rs.

                                        <?= number_format(
                                            (float)$item[
                                                'item_subtotal'
                                            ],
                                            0
                                        ) ?>

                                    </div>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </section>


                    <!-- DELIVERY ADDRESS -->

                    <section
                        class="
                            checkout-card
                        "
                    >

                        <div
                            class="
                                card-title
                            "
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
                            empty(
                                $addresses
                            )
                        ): ?>


                            <div
                                class="
                                    no-address
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-circle-info
                                    "
                                ></i>

                                Please add a delivery
                                address before
                                placing your order.

                            </div>


                        <?php else: ?>


                            <div
                                class="
                                    address-list
                                "
                            >


                                <?php foreach (
                                    $addresses
                                    as $address
                                ): ?>


                                    <?php

                                    $addressId =
                                        (int)$address[
                                            'id'
                                        ];


                                    $isSelected =
                                        $addressId
                                        ===
                                        $selectedAddressId;


                                    $addressTitle =
                                        trim(
                                            (string)(
                                                $address[
                                                    'address_title'
                                                ]
                                                ??
                                                'Home'
                                            )
                                        );


                                    if (
                                        $addressTitle
                                        ===
                                        ''
                                    ) {

                                        $addressTitle =
                                            'Home';

                                    }


                                    $locationParts =
                                        [];


                                    if (
                                        !empty(
                                            $address[
                                                'address_line'
                                            ]
                                        )
                                    ) {

                                        $locationParts[] =
                                            $address[
                                                'address_line'
                                            ];

                                    }


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

                                    ?>


                                    <label
                                        class="
                                            address-option
                                            <?= $isSelected
                                                ? 'selected'
                                                : ''
                                            ?>
                                        "
                                    >


                                        <input
                                            type="radio"
                                            name="address_id"
                                            value="<?=
                                                $addressId
                                            ?>"
                                            <?=
                                                $isSelected
                                                ? 'checked'
                                                : ''
                                            ?>
                                            required
                                        >


                                        <div
                                            class="
                                                address-details
                                            "
                                        >


                                            <div
                                                class="
                                                    address-title-row
                                                "
                                            >


                                                <h4>

                                                    <i
                                                        class="
                                                            fas
                                                            fa-location-dot
                                                        "
                                                    ></i>

                                                    <?= checkout_h(
                                                        $addressTitle
                                                    ) ?>

                                                </h4>


                                                <?php if (
                                                    (int)$address[
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


                                            </div>


                                            <?php if (
                                                !empty(
                                                    $locationParts
                                                )
                                            ): ?>

                                                <p>

                                                    <?= checkout_h(
                                                        implode(
                                                            ', ',
                                                            $locationParts
                                                        )
                                                    ) ?>

                                                </p>

                                            <?php endif; ?>


                                            <?php if (
                                                !empty(
                                                    $address[
                                                        'phone'
                                                    ]
                                                )
                                            ): ?>

                                                <p
                                                    class="phone"
                                                >

                                                    <i
                                                        class="
                                                            fas
                                                            fa-phone
                                                        "
                                                    ></i>

                                                    <?= checkout_h(
                                                        $address[
                                                            'phone'
                                                        ]
                                                    ) ?>

                                                </p>

                                            <?php endif; ?>


                                        </div>


                                    </label>


                                <?php endforeach; ?>


                            </div>


                        <?php endif; ?>


                        <a
                            href="
                                customer/manage-addresses.php
                            "
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


                    </section>


                    <!-- PAYMENT -->

                    <section
                        class="
                            checkout-card
                        "
                    >

                        <div
                            class="
                                card-title
                            "
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


                        <!--
                        ----------------------------------------------------------
                        ONLY ONE PAYMENT METHOD IS DISPLAYED
                        ----------------------------------------------------------
                        -->

                        <div
                            class="
                                payment-display
                            "
                        >


                            <div
                                class="
                                    payment-icon
                                "
                            >

                                <i
                                    class="
                                        fas
                                        <?= checkout_h(
                                            $paymentIcon
                                        )
                                        ?>
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    payment-text
                                "
                            >

                                <strong>

                                    <?= checkout_h(
                                        $paymentTitle
                                    ) ?>

                                </strong>


                                <small>

                                    <?= checkout_h(
                                        $paymentDescription
                                    ) ?>

                                </small>

                            </div>


                        </div>


                        <a
                            href="payments.php"
                            class="
                                manage-payment
                            "
                        >

                            <i
                                class="
                                    fas
                                    fa-credit-card
                                "
                            ></i>

                            Manage Payment

                        </a>


                        <!--
                        Payment is controlled by session.
                        No payment radio buttons are shown.
                        -->

                        <input
                            type="hidden"
                            name="payment_method"
                            value="<?=
                                checkout_h(
                                    $paymentMethod
                                )
                            ?>"
                        >


                    </section>


                </div>


                <!-- RIGHT / ORDER SUMMARY -->

                <aside>


                    <section
                        class="
                            checkout-card
                            summary-card
                        "
                    >


                        <div
                            class="
                                card-title
                            "
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
                            class="
                                summary-row
                            "
                        >

                            <span>
                                Items
                            </span>

                            <strong>
                                <?= (int)$totalItems ?>
                            </strong>

                        </div>


                        <div
                            class="
                                summary-row
                            "
                        >

                            <span>
                                Subtotal
                            </span>

                            <strong>

                                Rs.

                                <?= number_format(
                                    $subtotal,
                                    0
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="
                                summary-row
                            "
                        >

                            <span>
                                Delivery Fee
                            </span>


                            <strong>

                                <?php if (
                                    $deliveryFee > 0
                                ): ?>

                                    Rs.

                                    <?= number_format(
                                        $deliveryFee,
                                        0
                                    ) ?>

                                <?php else: ?>

                                    FREE

                                <?php endif; ?>

                            </strong>

                        </div>


                        <?php if (
                            $discount > 0
                        ): ?>


                            <div
                                class="
                                    summary-row
                                "
                            >

                                <span>
                                    Discount
                                </span>


                                <strong>

                                    - Rs.

                                    <?= number_format(
                                        $discount,
                                        0
                                    ) ?>

                                </strong>

                            </div>


                        <?php endif; ?>


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

                                <?= number_format(
                                    $total,
                                    0
                                ) ?>

                            </strong>

                        </div>


                        <!-- ORDER NOTE -->

                        <div
                            class="
                                note-box
                            "
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
                                placeholder="
                                    Add delivery instructions...
                                "
                            ><?= checkout_h(
                                $_POST[
                                    'customer_note'
                                ] ?? ''
                            ) ?></textarea>


                        </div>


                        <!-- PLACE ORDER -->

                        <button
                            type="submit"
                            name="place_order"
                            value="1"
                            class="
                                place-order-btn
                            "
                            <?=
                                empty($addresses)
                                ? 'disabled'
                                : ''
                            ?>
                        >

                            <i
                                class="
                                    fas
                                    fa-check
                                "
                            ></i>

                            Place Order

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

                            Your checkout information
                            is handled securely.

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
| ADDRESS SELECTOR UI
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        '.address-option input'
    )
    .forEach(
        function (radio) {

            radio.addEventListener(
                'change',
                function () {

                    document
                        .querySelectorAll(
                            '.address-option'
                        )
                        .forEach(
                            function (option) {

                                option.classList.remove(
                                    'selected'
                                );

                            }
                        );


                    var parent =
                        this.closest(
                            '.address-option'
                        );


                    if (parent) {

                        parent.classList.add(
                            'selected'
                        );

                    }

                }
            );

        }
    );

</script>


</body>

</html>