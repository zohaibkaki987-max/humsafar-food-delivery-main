<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function restaurant_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$userId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;


/*
|--------------------------------------------------------------------------
| RESTAURANT ID
|--------------------------------------------------------------------------
*/

$restaurantId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


/*
|--------------------------------------------------------------------------
| INVALID RESTAURANT ID
|--------------------------------------------------------------------------
*/

if ($restaurantId <= 0) {

    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Restaurant Not Found - Humsafar</title>

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        >

        <style>

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f8f8f8;
                font-family: Arial, sans-serif;
            }

            .error-box {
                width: 90%;
                max-width: 480px;
                padding: 45px 30px;
                text-align: center;
                background: #ffffff;
                border-radius: 18px;
                box-shadow: 0 10px 35px rgba(0,0,0,.08);
            }

            .error-box i {
                color: #ed0038;
                font-size: 50px;
                margin-bottom: 15px;
            }

            .error-box h1 {
                margin: 0 0 10px;
                color: #222222;
            }

            .error-box p {
                color: #777777;
                margin-bottom: 25px;
            }

            .error-box a {
                display: inline-block;
                padding: 12px 22px;
                background: #ed0038;
                color: #ffffff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 700;
            }

        </style>

    </head>

    <body>

        <div class="error-box">

            <i class="fas fa-store-slash"></i>

            <h1>
                Restaurant Not Found
            </h1>

            <p>
                The restaurant you are looking for
                is not available.
            </p>

            <a href="restaurants.php">
                <i class="fas fa-arrow-left"></i>
                Back to Restaurants
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| GET RESTAURANT
|--------------------------------------------------------------------------
*/

$restaurant = null;

$sql = "
    SELECT
        id,
        name,
        description,
        image,
        address,
        latitude,
        longitude,
        phone,
        rating,
        delivery_time,
        delivery_fee,
        status
    FROM restaurants
    WHERE id = ?
      AND status = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $restaurantId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $restaurant = $result->fetch_assoc();

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| RESTAURANT NOT AVAILABLE
|--------------------------------------------------------------------------
*/

if (!$restaurant) {

    http_response_code(404);

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
            Restaurant Not Available - Humsafar
        </title>

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        >

        <style>

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f8f8f8;
                font-family: Arial, sans-serif;
            }

            .error-box {
                width: 90%;
                max-width: 480px;
                padding: 45px 30px;
                text-align: center;
                background: #ffffff;
                border-radius: 18px;
                box-shadow: 0 10px 35px rgba(0,0,0,.08);
            }

            .error-box i {
                color: #ed0038;
                font-size: 50px;
                margin-bottom: 15px;
            }

            .error-box h1 {
                margin: 0 0 10px;
                color: #222222;
            }

            .error-box p {
                color: #777777;
                margin-bottom: 25px;
            }

            .error-box a {
                display: inline-block;
                padding: 12px 22px;
                background: #ed0038;
                color: #ffffff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 700;
            }

        </style>

    </head>

    <body>

        <div class="error-box">

            <i class="fas fa-store-slash"></i>

            <h1>
                Restaurant Not Available
            </h1>

            <p>
                This restaurant is currently unavailable.
            </p>

            <a href="restaurants.php">
                <i class="fas fa-arrow-left"></i>
                Back to Restaurants
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['add_to_cart'])
) {

    /*
    |--------------------------------------------------------------------------
    | LOGIN CHECK
    |--------------------------------------------------------------------------
    */

    if ($userId <= 0) {

        header(
            'Location: login.php'
        );

        exit;
    }


    $menuItemId = isset($_POST['menu_item_id'])
        ? (int) $_POST['menu_item_id']
        : 0;


    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;


    if ($quantity < 1) {
        $quantity = 1;
    }

    if ($quantity > 99) {
        $quantity = 99;
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY ITEM BELONGS TO THIS RESTAURANT
    |--------------------------------------------------------------------------
    */

    $itemSql = "
        SELECT
            id,
            restaurant_id,
            name,
            price,
            status
        FROM menu_items
        WHERE id = ?
          AND restaurant_id = ?
          AND status = 1
        LIMIT 1
    ";

    $itemStmt = $conn->prepare($itemSql);

    if ($itemStmt) {

        $itemStmt->bind_param(
            "ii",
            $menuItemId,
            $restaurantId
        );

        $itemStmt->execute();

        $itemResult = $itemStmt->get_result();

        $item = $itemResult->fetch_assoc();

        $itemStmt->close();


        if ($item) {

            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING CART ITEM
            |--------------------------------------------------------------------------
            */

            $checkSql = "
                SELECT
                    id,
                    quantity
                FROM cart
                WHERE user_id = ?
                  AND menu_item_id = ?
                LIMIT 1
            ";

            $checkStmt = $conn->prepare($checkSql);

            if ($checkStmt) {

                $checkStmt->bind_param(
                    "ii",
                    $userId,
                    $menuItemId
                );

                $checkStmt->execute();

                $checkResult = $checkStmt->get_result();

                $existing = $checkResult->fetch_assoc();

                $checkStmt->close();


                if ($existing) {

                    $newQuantity =
                        (int) $existing['quantity']
                        + $quantity;


                    if ($newQuantity > 99) {
                        $newQuantity = 99;
                    }


                    $updateSql = "
                        UPDATE cart
                        SET quantity = ?
                        WHERE id = ?
                          AND user_id = ?
                    ";

                    $updateStmt =
                        $conn->prepare($updateSql);


                    if ($updateStmt) {

                        $updateStmt->bind_param(
                            "iii",
                            $newQuantity,
                            $existing['id'],
                            $userId
                        );

                        if ($updateStmt->execute()) {

                            $message =
                                $item['name']
                                . ' added to cart.';

                        } else {

                            $error =
                                'Unable to update cart.';
                        }

                        $updateStmt->close();
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT NEW CART ITEM
                    |--------------------------------------------------------------------------
                    */

                    $insertSql = "
                        INSERT INTO cart
                        (
                            user_id,
                            menu_item_id,
                            quantity
                        )
                        VALUES
                        (?, ?, ?)
                    ";

                    $insertStmt =
                        $conn->prepare($insertSql);


                    if ($insertStmt) {

                        $insertStmt->bind_param(
                            "iii",
                            $userId,
                            $menuItemId,
                            $quantity
                        );


                        if ($insertStmt->execute()) {

                            $message =
                                $item['name']
                                . ' added to cart.';

                        } else {

                            $error =
                                'Unable to add item to cart.';
                        }

                        $insertStmt->close();
                    }
                }
            }

        } else {

            $error =
                'This item is not available.';
        }

    } else {

        $error =
            'Unable to process your request.';
    }
}


/*
|--------------------------------------------------------------------------
| GET MENU ITEMS
|--------------------------------------------------------------------------
*/

$menuItems = [];

$menuSql = "
    SELECT
        id,
        restaurant_id,
        name,
        description,
        price,
        image,
        category,
        status
    FROM menu_items
    WHERE restaurant_id = ?
      AND status = 1
    ORDER BY
        category ASC,
        id ASC
";

$menuStmt = $conn->prepare($menuSql);

if ($menuStmt) {

    $menuStmt->bind_param(
        "i",
        $restaurantId
    );

    $menuStmt->execute();

    $menuResult =
        $menuStmt->get_result();


    while ($row = $menuResult->fetch_assoc()) {

        $menuItems[] = $row;
    }


    $menuStmt->close();
}


/*
|--------------------------------------------------------------------------
| GROUP ITEMS BY CATEGORY
|--------------------------------------------------------------------------
*/

$categories = [];

foreach ($menuItems as $item) {

    $category =
        trim(
            (string)
            ($item['category'] ?? '')
        );


    if ($category === '') {
        $category = 'Menu';
    }


    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }


    $categories[$category][] = $item;
}


/*
|--------------------------------------------------------------------------
| RESTAURANT INFORMATION
|--------------------------------------------------------------------------
*/

$restaurantName =
    $restaurant['name'] ?? 'Restaurant';


$restaurantDescription =
    trim(
        (string)
        ($restaurant['description'] ?? '')
    );


$restaurantAddress =
    trim(
        (string)
        ($restaurant['address'] ?? '')
    );


$restaurantPhone =
    trim(
        (string)
        ($restaurant['phone'] ?? '')
    );


$restaurantRating =
    (float)
    ($restaurant['rating'] ?? 0);


$deliveryTime =
    trim(
        (string)
        ($restaurant['delivery_time'] ?? '')
    );


$deliveryFee =
    (float)
    ($restaurant['delivery_fee'] ?? 0);


/*
|--------------------------------------------------------------------------
| RESTAURANT IMAGE
|--------------------------------------------------------------------------
*/

$restaurantImage =
    trim(
        (string)
        ($restaurant['image'] ?? '')
    );


$restaurantImageUrl = '';

if ($restaurantImage !== '') {

    if (
        preg_match(
            '/^(https?:\/\/|\/|data:)/i',
            $restaurantImage
        )
    ) {

        $restaurantImageUrl =
            $restaurantImage;

    } elseif (
        strpos(
            $restaurantImage,
            'assets/'
        ) === 0
    ) {

        $restaurantImageUrl =
            $restaurantImage;

    } else {

        $restaurantImageUrl =
            'assets/images/restaurants/'
            . basename(
                $restaurantImage
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

        <?php
        echo restaurant_h(
            $restaurantName
        );
        ?>

        - Humsafar

    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

/* ==========================================================
   PAGE
========================================================== */

.restaurant-detail-page {

    max-width: 1500px;

    margin: 0 auto;

    padding:
        25px 4%
        70px;
}


/* ==========================================================
   BACK BUTTON
========================================================== */

.restaurant-back {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #ed0038;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 18px;
}

.restaurant-back:hover {
    color: #c90031;
}


/* ==========================================================
   HERO
========================================================== */

.restaurant-detail-hero {

    position: relative;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 8px 28px
        rgba(40,10,20,.07);
}


/* ==========================================================
   TOP RESTAURANT COVER
========================================================== */

.restaurant-main-image {

    position: relative;

    width: 100%;

    height: 360px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fff0f5,
            #ffffff
        );
}


.restaurant-main-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}


/*
|--------------------------------------------------------------------------
| IMAGE OVERLAY
|--------------------------------------------------------------------------
*/

.restaurant-main-image::after {

    content: "";

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            to bottom,
            rgba(0,0,0,.03),
            rgba(0,0,0,.08) 35%,
            rgba(0,0,0,.58) 100%
        );

    pointer-events: none;
}


/* ==========================================================
   EMPTY IMAGE
========================================================== */

.restaurant-image-empty {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 75px;
}


/* ==========================================================
   RESTAURANT INFO
========================================================== */

.restaurant-main-info {

    position: relative;

    padding:
        28px 40px 35px;

    background: #ffffff;
}


/* ==========================================================
   LABEL
========================================================== */

.restaurant-label {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        7px 12px;

    border-radius: 20px;

    background: #fff0f5;

    color: #ed0038;

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 12px;
}


/* ==========================================================
   NAME
========================================================== */

.restaurant-main-info h1 {

    margin: 0;

    color: #222222;

    font-size: 34px;

    line-height: 1.15;

    font-weight: 800;
}


/* ==========================================================
   DESCRIPTION
========================================================== */

.restaurant-description {

    margin:
        12px 0 20px;

    color: #666666;

    font-size: 14px;

    line-height: 1.65;

    max-width: 900px;
}


/* ==========================================================
   STATS
========================================================== */

.restaurant-stats {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-bottom: 18px;
}


.restaurant-stat {

    display: flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 12px;

    background: #fafafa;

    border:
        1px solid #eeeeee;

    border-radius: 9px;

    color: #555555;

    font-size: 12px;

    font-weight: 600;
}


.restaurant-stat i {
    color: #ed0038;
}


.restaurant-stat.rating i {
    color: #f5a623;
}


/* ==========================================================
   ADDRESS
========================================================== */

.restaurant-address {

    display: flex;

    align-items: flex-start;

    gap: 8px;

    color: #666666;

    font-size: 13px;

    line-height: 1.5;
}


.restaurant-address i {

    color: #ed0038;

    margin-top: 3px;
}


/* ==========================================================
   DELIVERY FEE
========================================================== */

.delivery-fee-box {

    margin-top: 18px;

    padding:
        13px 15px;

    background: #fff7f9;

    border:
        1px solid #f5ccd8;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}


.delivery-fee-label {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #555555;

    font-size: 13px;

    font-weight: 600;
}


.delivery-fee-label i {
    color: #ed0038;
}


.delivery-fee-value {

    color: #ed0038;

    font-size: 14px;

    font-weight: 800;
}


/* ==========================================================
   ALERT
========================================================== */

.restaurant-alert {

    margin-bottom: 18px;

    padding:
        12px 15px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 600;
}


.restaurant-alert.success {

    color: #16743d;

    background: #eafaf0;

    border:
        1px solid #c9efd8;
}


.restaurant-alert.error {

    color: #b4233a;

    background: #fff0f2;

    border:
        1px solid #ffd2da;
}


/* ==========================================================
   MENU SECTION
========================================================== */

.restaurant-menu-section {

    margin-top: 38px;
}


.restaurant-menu-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    margin-bottom: 20px;
}


.restaurant-menu-header h2 {

    margin: 0;

    color: #222222;

    font-size: 28px;

    font-weight: 800;
}


.restaurant-menu-header p {

    margin:
        5px 0 0;

    color: #777777;

    font-size: 13px;
}


/* ==========================================================
   CATEGORY
========================================================== */

.menu-category-section {

    margin-bottom: 30px;
}


.menu-category-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 14px;
}


.menu-category-title h3 {

    margin: 0;

    color: #333333;

    font-size: 20px;

    font-weight: 800;
}


.menu-category-line {

    flex: 1;

    height: 1px;

    background: #eeeeee;
}


/* ==========================================================
   MENU GRID
========================================================== */

.menu-items-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 16px;
}


/* ==========================================================
   ITEM CARD
========================================================== */

.menu-item-card {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 15px;

    padding: 13px;

    display: grid;

    grid-template-columns:
        125px 1fr;

    gap: 15px;

    box-shadow:
        0 5px 20px
        rgba(40,10,20,.045);

    transition:
        .2s ease;
}


.menu-item-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 9px 25px
        rgba(40,10,20,.08);
}


/* ==========================================================
   ITEM IMAGE
========================================================== */

.menu-item-image {

    width: 125px;

    height: 125px;

    border-radius: 11px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fff0f5,
            #ffffff
        );

    display: flex;

    align-items: center;

    justify-content: center;
}


.menu-item-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}


.menu-item-placeholder {

    color: #ed0038;

    font-size: 38px;
}


/* ==========================================================
   ITEM CONTENT
========================================================== */

.menu-item-content {

    min-width: 0;

    display: flex;

    flex-direction: column;

    justify-content: center;
}


.menu-item-content h4 {

    margin: 0;

    color: #222222;

    font-size: 18px;

    font-weight: 700;
}


.menu-item-description {

    margin:
        6px 0 12px;

    color: #777777;

    font-size: 12px;

    line-height: 1.5;

    display:
        -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;
}


/* ==========================================================
   ITEM BOTTOM
========================================================== */

.menu-item-bottom {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}


.menu-item-price {

    color: #ed0038;

    font-size: 17px;

    font-weight: 800;

    white-space: nowrap;
}


/* ==========================================================
   QUANTITY
========================================================== */

.quantity-control {

    display: inline-flex;

    align-items: center;

    border:
        1px solid #eeeeee;

    border-radius: 8px;

    overflow: hidden;

    background: #ffffff;
}


.quantity-control button {

    width: 31px;

    height: 31px;

    border: 0;

    background: #fff5f7;

    color: #ed0038;

    cursor: pointer;

    font-size: 12px;
}


.quantity-control button:hover {

    background: #ed0038;

    color: #ffffff;
}


.quantity-control input {

    width: 35px;

    height: 31px;

    border: 0;

    border-left:
        1px solid #eeeeee;

    border-right:
        1px solid #eeeeee;

    outline: none;

    text-align: center;

    font-size: 12px;

    font-weight: 700;

    color: #333333;
}


/* ==========================================================
   ADD CART
========================================================== */

.add-cart-button {

    margin-top: 10px;

    width: 100%;

    min-height: 38px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #ffffff;

    cursor: pointer;

    font-size: 12px;

    font-weight: 800;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    transition: .2s ease;
}


.add-cart-button:hover {

    background: #d90035;

    transform:
        translateY(-1px);
}


/* ==========================================================
   EMPTY MENU
========================================================== */

.empty-menu {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 15px;

    padding:
        55px 20px;

    text-align: center;
}


.empty-menu i {

    color: #ed0038;

    font-size: 48px;

    margin-bottom: 15px;
}


.empty-menu h3 {

    margin:
        0 0 7px;

    color: #333333;

    font-size: 21px;
}


.empty-menu p {

    margin: 0;

    color: #777777;

    font-size: 13px;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 1000px) {

    .menu-items-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .restaurant-detail-page {

        padding:
            20px 15px
            45px;
    }


    .restaurant-main-image {

        height: 260px;
    }


    .restaurant-main-info {

        padding:
            25px 22px 28px;
    }


    .restaurant-main-info h1 {

        font-size: 29px;
    }


    .restaurant-menu-header {

        display: block;
    }


    .restaurant-menu-header h2 {

        font-size: 24px;
    }

}


@media (max-width: 500px) {

    .restaurant-main-image {

        height: 220px;
    }


    .restaurant-main-info h1 {

        font-size: 26px;
    }


    .restaurant-stats {

        display: grid;

        grid-template-columns:
            1fr 1fr;
    }


    .menu-item-card {

        grid-template-columns: 1fr;
    }


    .menu-item-image {

        width: 100%;

        height: 190px;
    }


    .menu-item-bottom {

        flex-wrap: wrap;
    }

}

    </style>

</head>

<body>


<?php

/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
*/

require_once
    __DIR__
    . '/includes/customer-header.php';

?>


<main class="restaurant-detail-page">


    <!-- ======================================================
         BACK
    ======================================================= -->

    <a
        href="restaurants.php"
        class="restaurant-back"
    >

        <i class="fas fa-arrow-left"></i>

        Back to Restaurants

    </a>


    <!-- ======================================================
         ALERT
    ======================================================= -->

    <?php if ($message !== '') { ?>

        <div class="restaurant-alert success">

            <i class="fas fa-circle-check"></i>

            <?php
            echo restaurant_h($message);
            ?>

        </div>

    <?php } ?>


    <?php if ($error !== '') { ?>

        <div class="restaurant-alert error">

            <i class="fas fa-circle-exclamation"></i>

            <?php
            echo restaurant_h($error);
            ?>

        </div>

    <?php } ?>


    <!-- ======================================================
         RESTAURANT HERO
    ======================================================= -->

    <section class="restaurant-detail-hero">


        <!-- ==================================================
             TOP BACKGROUND IMAGE
        =================================================== -->

        <div class="restaurant-main-image">

            <?php if ($restaurantImageUrl !== '') { ?>

                <img
                    src="<?php
                    echo restaurant_h(
                        $restaurantImageUrl
                    );
                    ?>"
                    alt="<?php
                    echo restaurant_h(
                        $restaurantName
                    );
                    ?>"
                >

            <?php } else { ?>

                <div class="restaurant-image-empty">

                    <i class="fas fa-utensils"></i>

                </div>

            <?php } ?>

        </div>


        <!-- ==================================================
             RESTAURANT INFORMATION
        =================================================== -->

        <div class="restaurant-main-info">


            <span class="restaurant-label">

                <i class="fas fa-store"></i>

                Restaurant

            </span>


            <h1>

                <?php
                echo restaurant_h(
                    $restaurantName
                );
                ?>

            </h1>


            <?php if (
                $restaurantDescription !== ''
            ) { ?>

                <p class="restaurant-description">

                    <?php
                    echo restaurant_h(
                        $restaurantDescription
                    );
                    ?>

                </p>

            <?php } ?>


            <!-- ==================================================
                 RESTAURANT STATS
            =================================================== -->

            <div class="restaurant-stats">


                <div class="restaurant-stat rating">

                    <i class="fas fa-star"></i>

                    <span>

                        <?php
                        echo number_format(
                            $restaurantRating,
                            1
                        );
                        ?>

                    </span>

                </div>


                <div class="restaurant-stat">

                    <i class="fas fa-clock"></i>

                    <span>

                        <?php
                        echo $deliveryTime !== ''
                            ? restaurant_h(
                                $deliveryTime
                            )
                            : 'Delivery time not set';
                        ?>

                    </span>

                </div>


                <div class="restaurant-stat">

                    <i class="fas fa-motorcycle"></i>

                    <span>
                        Delivery Available
                    </span>

                </div>


                <?php if (
                    $restaurantPhone !== ''
                ) { ?>

                    <div class="restaurant-stat">

                        <i class="fas fa-phone"></i>

                        <span>

                            <?php
                            echo restaurant_h(
                                $restaurantPhone
                            );
                            ?>

                        </span>

                    </div>

                <?php } ?>


            </div>


            <!-- ==================================================
                 ADDRESS
            =================================================== -->

            <?php if (
                $restaurantAddress !== ''
            ) { ?>

                <div class="restaurant-address">

                    <i class="fas fa-location-dot"></i>

                    <span>

                        <?php
                        echo restaurant_h(
                            $restaurantAddress
                        );
                        ?>

                    </span>

                </div>

            <?php } ?>


            <!-- ==================================================
                 DELIVERY FEE
            =================================================== -->

            <div class="delivery-fee-box">

                <div class="delivery-fee-label">

                    <i class="fas fa-truck"></i>

                    Delivery Fee

                </div>


                <div class="delivery-fee-value">

                    Rs.

                    <?php
                    echo number_format(
                        $deliveryFee,
                        2
                    );
                    ?>

                </div>

            </div>


        </div>

    </section>


    <!-- ======================================================
         MENU
    ======================================================= -->

    <section class="restaurant-menu-section">


        <div class="restaurant-menu-header">

            <div>

                <h2>
                    Menu
                </h2>

                <p>

                    Choose your favourite food from

                    <?php
                    echo restaurant_h(
                        $restaurantName
                    );
                    ?>

                </p>

            </div>

        </div>


        <?php if (
            !empty($categories)
        ) { ?>


            <?php foreach (
                $categories as $categoryName => $items
            ) { ?>


                <div class="menu-category-section">


                    <div class="menu-category-title">

                        <h3>

                            <?php
                            echo restaurant_h(
                                $categoryName
                            );
                            ?>

                        </h3>

                        <div class="menu-category-line"></div>

                    </div>


                    <div class="menu-items-grid">


                        <?php foreach (
                            $items as $item
                        ) { ?>


                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | ITEM IMAGE
                            |--------------------------------------------------------------------------
                            |
                            | Restaurant owner uploads item images here:
                            |
                            | assets/images/restaurants/
                            |
                            */

                            $itemImage =
                                trim(
                                    (string)
                                    (
                                        $item['image']
                                        ?? ''
                                    )
                                );


                            $itemImageUrl = '';


                            if (
                                $itemImage !== ''
                            ) {

                                if (
                                    preg_match(
                                        '/^(https?:\/\/|\/|data:)/i',
                                        $itemImage
                                    )
                                ) {

                                    $itemImageUrl =
                                        $itemImage;

                                } elseif (
                                    strpos(
                                        $itemImage,
                                        'assets/'
                                    ) === 0
                                ) {

                                    $itemImageUrl =
                                        $itemImage;

                                } else {

                                    $itemImageUrl =
                                        'assets/images/restaurants/'
                                        . basename(
                                            $itemImage
                                        );
                                }
                            }

                            ?>


                            <article class="menu-item-card">


                                <!-- ITEM IMAGE -->

                                <div class="menu-item-image">

                                    <?php if (
                                        $itemImageUrl !== ''
                                    ) { ?>

                                        <img
                                            src="<?php
                                            echo restaurant_h(
                                                $itemImageUrl
                                            );
                                            ?>"
                                            alt="<?php
                                            echo restaurant_h(
                                                $item['name']
                                            );
                                            ?>"
                                            loading="lazy"
                                            onerror="
                                                this.style.display='none';
                                                this.nextElementSibling.style.display='flex';
                                            "
                                        >

                                        <div
                                            class="menu-item-placeholder"
                                            style="
                                                display:none;
                                                width:100%;
                                                height:100%;
                                                align-items:center;
                                                justify-content:center;
                                            "
                                        >

                                            <i class="fas fa-utensils"></i>

                                        </div>

                                    <?php } else { ?>

                                        <div class="menu-item-placeholder">

                                            <i class="fas fa-utensils"></i>

                                        </div>

                                    <?php } ?>

                                </div>


                                <!-- ITEM CONTENT -->

                                <div class="menu-item-content">


                                    <h4>

                                        <?php
                                        echo restaurant_h(
                                            $item['name']
                                        );
                                        ?>

                                    </h4>


                                    <?php if (
                                        !empty(
                                            $item['description']
                                        )
                                    ) { ?>

                                        <p class="menu-item-description">

                                            <?php
                                            echo restaurant_h(
                                                $item['description']
                                            );
                                            ?>

                                        </p>

                                    <?php } else { ?>

                                        <p class="menu-item-description">

                                            Delicious food prepared
                                            fresh for you.

                                        </p>

                                    <?php } ?>


                                    <div class="menu-item-bottom">


                                        <span class="menu-item-price">

                                            Rs.

                                            <?php
                                            echo number_format(
                                                (float)
                                                $item['price'],
                                                2
                                            );
                                            ?>

                                        </span>


                                        <!-- QUANTITY -->

                                        <div class="quantity-control">


                                            <button
                                                type="button"
                                                onclick="
                                                    changeQuantity(
                                                        <?php
                                                        echo (int)
                                                        $item['id'];
                                                        ?>,
                                                        -1
                                                    );
                                                "
                                            >

                                                <i class="fas fa-minus"></i>

                                            </button>


                                            <input
                                                type="number"
                                                id="quantity-<?php
                                                echo (int)
                                                $item['id'];
                                                ?>"
                                                value="1"
                                                min="1"
                                                max="99"
                                                readonly
                                            >


                                            <button
                                                type="button"
                                                onclick="
                                                    changeQuantity(
                                                        <?php
                                                        echo (int)
                                                        $item['id'];
                                                        ?>,
                                                        1
                                                    );
                                                "
                                            >

                                                <i class="fas fa-plus"></i>

                                            </button>


                                        </div>

                                    </div>


                                    <!-- ADD TO CART -->

                                    <form
                                        method="POST"
                                        class="add-cart-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="menu_item_id"
                                            value="<?php
                                            echo (int)
                                            $item['id'];
                                            ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="quantity"
                                            id="hidden-quantity-<?php
                                            echo (int)
                                            $item['id'];
                                            ?>"
                                            value="1"
                                        >


                                        <button
                                            type="submit"
                                            name="add_to_cart"
                                            class="add-cart-button"
                                        >

                                            <i class="fas fa-cart-plus"></i>

                                            Add to Cart

                                        </button>

                                    </form>


                                </div>

                            </article>


                        <?php } ?>


                    </div>

                </div>


            <?php } ?>


        <?php } else { ?>


            <div class="empty-menu">

                <i class="fas fa-utensils"></i>

                <h3>
                    Menu Not Available
                </h3>

                <p>
                    This restaurant has not added
                    any menu items yet.
                </p>

            </div>


        <?php } ?>


    </section>


</main>


<script>

/*
|--------------------------------------------------------------------------
| QUANTITY CONTROL
|--------------------------------------------------------------------------
*/

function changeQuantity(
    itemId,
    change
) {

    const input =
        document.getElementById(
            'quantity-' + itemId
        );


    const hidden =
        document.getElementById(
            'hidden-quantity-' + itemId
        );


    if (!input || !hidden) {
        return;
    }


    let value =
        parseInt(
            input.value,
            10
        ) || 1;


    value += change;


    if (value < 1) {
        value = 1;
    }


    if (value > 99) {
        value = 99;
    }


    input.value = value;

    hidden.value = value;
}


/*
|--------------------------------------------------------------------------
| FORM QUANTITY SYNC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const forms =
            document.querySelectorAll(
                '.add-cart-form'
            );


        forms.forEach(
            function (form) {

                form.addEventListener(
                    'submit',
                    function () {

                        const itemInput =
                            form.querySelector(
                                '[name="menu_item_id"]'
                            );


                        const hidden =
                            form.querySelector(
                                '[name="quantity"]'
                            );


                        if (
                            !itemInput ||
                            !hidden
                        ) {
                            return;
                        }


                        const quantityInput =
                            document.getElementById(
                                'quantity-'
                                +
                                itemInput.value
                            );


                        if (quantityInput) {

                            hidden.value =
                                quantityInput.value;
                        }

                    }
                );

            }
        );

    }
);

</script>


</body>

</html>