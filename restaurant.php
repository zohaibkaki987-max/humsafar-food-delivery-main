<?php

/* =========================================================
   HUMSAFAR - RESTAURANT DETAIL PAGE
   Complete standalone restaurant.php
========================================================= */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   SESSION / USER
========================================================= */

$userId = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : 0;

$userName = $_SESSION['name'] ?? '';


/* =========================================================
   CART COUNT
========================================================= */

$cartCount = 0;

if ($userId > 0) {

    $cartCountSql = "
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM cart
        WHERE user_id = ?
    ";

    $cartStmt = $conn->prepare($cartCountSql);

    if ($cartStmt) {

        $cartStmt->bind_param(
            "i",
            $userId
        );

        $cartStmt->execute();

        $cartResult =
            $cartStmt->get_result();

        if ($cartRow =
            $cartResult->fetch_assoc()
        ) {

            $cartCount =
                (int)$cartRow['total'];

        }

        $cartStmt->close();
    }
}


/* =========================================================
   RESTAURANT ID
========================================================= */

$restaurantId =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;


/* =========================================================
   ADD TO CART
========================================================= */

$cartMessage = '';
$cartError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['add_to_cart'])
) {

    if ($userId <= 0) {

        header(
            "Location: login.php"
        );

        exit;
    }


    $menuItemId =
        isset($_POST['menu_item_id'])
            ? (int)$_POST['menu_item_id']
            : 0;


    $quantity =
        isset($_POST['quantity'])
            ? (int)$_POST['quantity']
            : 1;


    if ($quantity < 1) {

        $quantity = 1;

    }


    /* -----------------------------------------
       Verify menu item
    ----------------------------------------- */

    $itemSql = "
        SELECT
            id,
            restaurant_id,
            name,
            price
        FROM menu_items
        WHERE id = ?
          AND restaurant_id = ?
          AND status = 1
        LIMIT 1
    ";

    $itemStmt =
        $conn->prepare($itemSql);


    if ($itemStmt) {

        $itemStmt->bind_param(
            "ii",
            $menuItemId,
            $restaurantId
        );

        $itemStmt->execute();

        $itemResult =
            $itemStmt->get_result();

        $menuItem =
            $itemResult->fetch_assoc();

        $itemStmt->close();


        if ($menuItem) {

            /* -----------------------------------------
               Check existing cart item
            ----------------------------------------- */

            $existingSql = "
                SELECT id, quantity
                FROM cart
                WHERE user_id = ?
                  AND menu_item_id = ?
                LIMIT 1
            ";

            $existingStmt =
                $conn->prepare(
                    $existingSql
                );


            if ($existingStmt) {

                $existingStmt->bind_param(
                    "ii",
                    $userId,
                    $menuItemId
                );

                $existingStmt->execute();

                $existingResult =
                    $existingStmt->get_result();

                $existing =
                    $existingResult->fetch_assoc();

                $existingStmt->close();


                if ($existing) {

                    /* -------------------------------
                       UPDATE QUANTITY
                    ------------------------------- */

                    $newQuantity =
                        (int)$existing['quantity']
                        + $quantity;


                    $updateSql = "
                        UPDATE cart
                        SET quantity = ?
                        WHERE id = ?
                          AND user_id = ?
                    ";

                    $updateStmt =
                        $conn->prepare(
                            $updateSql
                        );


                    if ($updateStmt) {

                        $updateStmt->bind_param(
                            "iii",
                            $newQuantity,
                            $existing['id'],
                            $userId
                        );

                        $updateStmt->execute();

                        $updateStmt->close();

                        $cartMessage =
                            $menuItem['name']
                            . ' added to cart.';

                    }

                } else {

                    /* -------------------------------
                       INSERT NEW CART ITEM
                    ------------------------------- */

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
                        $conn->prepare(
                            $insertSql
                        );


                    if ($insertStmt) {

                        $insertStmt->bind_param(
                            "iii",
                            $userId,
                            $menuItemId,
                            $quantity
                        );

                        $insertStmt->execute();

                        $insertStmt->close();

                        $cartMessage =
                            $menuItem['name']
                            . ' added to cart.';

                    }

                }

            }

        } else {

            $cartError =
                'This menu item is not available.';

        }

    } else {

        $cartError =
            'Unable to add item to cart.';

    }


    /* -----------------------------------------
       Refresh cart count
    ----------------------------------------- */

    $cartCountSql = "
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM cart
        WHERE user_id = ?
    ";

    $cartStmt =
        $conn->prepare(
            $cartCountSql
        );

    if ($cartStmt) {

        $cartStmt->bind_param(
            "i",
            $userId
        );

        $cartStmt->execute();

        $cartResult =
            $cartStmt->get_result();

        if ($cartRow =
            $cartResult->fetch_assoc()
        ) {

            $cartCount =
                (int)$cartRow['total'];

        }

        $cartStmt->close();
    }
}


/* =========================================================
   GET RESTAURANT
========================================================= */

$restaurant = null;


if ($restaurantId > 0) {

    $restaurantSql = "
        SELECT
            id,
            name,
            description,
            image,
            address,
            phone,
            rating,
            delivery_time,
            delivery_fee
        FROM restaurants
        WHERE id = ?
          AND status = 1
        LIMIT 1
    ";


    $restaurantStmt =
        $conn->prepare(
            $restaurantSql
        );


    if ($restaurantStmt) {

        $restaurantStmt->bind_param(
            "i",
            $restaurantId
        );

        $restaurantStmt->execute();

        $restaurantResult =
            $restaurantStmt->get_result();

        $restaurant =
            $restaurantResult->fetch_assoc();

        $restaurantStmt->close();
    }
}


/* =========================================================
   RESTAURANT NOT FOUND
========================================================= */

if (!$restaurant) {

    http_response_code(404);

}


/* =========================================================
   MENU ITEMS
========================================================= */

$menuItems = [];


if ($restaurant) {

    $menuSql = "
        SELECT
            id,
            restaurant_id,
            name,
            description,
            price,
            image,
            category
        FROM menu_items
        WHERE restaurant_id = ?
          AND status = 1
        ORDER BY
            category ASC,
            id ASC
    ";


    $menuStmt =
        $conn->prepare(
            $menuSql
        );


    if ($menuStmt) {

        $menuStmt->bind_param(
            "i",
            $restaurantId
        );

        $menuStmt->execute();

        $menuResult =
            $menuStmt->get_result();


        while (
            $menuRow =
            $menuResult->fetch_assoc()
        ) {

            $menuItems[] =
                $menuRow;

        }


        $menuStmt->close();
    }
}


/* =========================================================
   MENU CATEGORIES
========================================================= */

$menuCategories = [];


foreach ($menuItems as $item) {

    $category =
        trim(
            $item['category'] ?? ''
        );


    if ($category === '') {

        $category = 'Menu';

    }


    if (
        !in_array(
            $category,
            $menuCategories,
            true
        )
    ) {

        $menuCategories[] =
            $category;

    }

}


/* =========================================================
   RESTAURANT VARIABLES
========================================================= */

$restaurantName =
    $restaurant['name']
    ?? 'Restaurant';


$restaurantDescription =
    $restaurant['description']
    ?? 'Delicious food and great service.';


$restaurantImage =
    $restaurant['image']
    ?? '';


$restaurantAddress =
    $restaurant['address']
    ?? '';


$restaurantPhone =
    $restaurant['phone']
    ?? '';


$restaurantRating =
    (float)(
        $restaurant['rating']
        ?? 0
    );


$deliveryTime =
    $restaurant['delivery_time']
    ?? '';


$deliveryFee =
    (float)(
        $restaurant['delivery_fee']
        ?? 0
    );


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
        <?php echo h($restaurantName); ?>
        - Humsafar
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

/* =========================================================
   HUMSAFAR RESTAURANT DETAIL
   Pink Gradient Theme
========================================================= */

*{
    box-sizing:border-box;
}


html{
    scroll-behavior:smooth;
}


body{

    margin:0;

    padding:0;

    background:#f5f6fa;

    color:#222;

    font-family:
        'Segoe UI',
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;

}


a{
    text-decoration:none;
}


/* =========================================================
   TOP HEADER
========================================================= */

.hf-header{

    background:#fff;

    box-shadow:
        0 2px 12px rgba(0,0,0,.06);

    position:relative;

    z-index:100;

}


.hf-top{

    max-width:1250px;

    min-height:64px;

    margin:auto;

    padding:0 20px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:25px;

}


.hf-logo{

    display:flex;

    align-items:center;

    gap:8px;

    color:#ed1747;

    font-size:22px;

    font-weight:800;

    white-space:nowrap;

}


.hf-logo i{

    font-size:20px;

}


.hf-search{

    width:360px;

    height:40px;

    position:relative;

}


.hf-search input{

    width:100%;

    height:100%;

    border:1px solid #ddd;

    border-radius:22px;

    padding:
        0 42px 0 18px;

    outline:none;

    color:#444;

    background:#fff;

    font-size:13px;

}


.hf-search input:focus{

    border-color:#f03b73;

    box-shadow:
        0 0 0 3px
        rgba(240,59,115,.10);

}


.hf-search i{

    position:absolute;

    right:15px;

    top:50%;

    transform:
        translateY(-50%);

    color:#888;

}


.hf-actions{

    display:flex;

    align-items:center;

    gap:18px;

}


.hf-actions a{

    color:#333;

    font-size:13px;

    font-weight:600;

}


.hf-actions .cart-link{

    display:flex;

    align-items:center;

    gap:5px;

}


.cart-count{

    min-width:20px;

    height:20px;

    padding:0 6px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    border-radius:20px;

    background:#ffca05;

    color:#222;

    font-size:11px;

    font-weight:800;

}


.hf-user{

    display:flex;

    align-items:center;

    gap:6px;

}


.hf-logout{

    background:#ffca05;

    color:#222 !important;

    padding:
        8px 16px;

    border-radius:20px;

    font-size:12px !important;

    font-weight:700 !important;

}


/* =========================================================
   NAVIGATION
========================================================= */

.hf-nav{

    background:
        linear-gradient(
            90deg,
            #ed0a3f 0%,
            #f62d67 50%,
            #ff6294 100%
        );

}


.hf-nav-inner{

    max-width:1250px;

    margin:auto;

    padding:0 20px;

    min-height:43px;

    display:flex;

    align-items:center;

    justify-content:center;

}


.hf-nav ul{

    list-style:none;

    padding:0;

    margin:0;

    display:flex;

    align-items:center;

    gap:32px;

}


.hf-nav a{

    color:#fff;

    font-size:12px;

    font-weight:700;

    padding:
        14px 0;

    display:block;

}


.hf-nav a:hover{

    opacity:.85;

}


/* =========================================================
   PAGE
========================================================= */

.restaurant-page{

    max-width:1250px;

    margin:35px auto;

    padding:
        0 20px 60px;

}


/* =========================================================
   BREADCRUMB
========================================================= */

.breadcrumb{

    margin-bottom:18px;

    font-size:13px;

    color:#888;

}


.breadcrumb a{

    color:#ed1747;

    font-weight:600;

}


.breadcrumb i{

    margin:
        0 7px;

    font-size:10px;

}


/* =========================================================
   RESTAURANT HERO
========================================================= */

.restaurant-hero{

    background:#fff;

    border-radius:20px;

    overflow:hidden;

    box-shadow:
        0 10px 30px rgba(0,0,0,.07);

    border:
        1px solid #eee;

    display:grid;

    grid-template-columns:
        43% 57%;

    min-height:330px;

}


.restaurant-hero-image{

    min-height:330px;

    background:
        linear-gradient(
            135deg,
            #ffe3ed,
            #fff
        );

    overflow:hidden;

}


.restaurant-hero-image img{

    width:100%;

    height:100%;

    min-height:330px;

    object-fit:cover;

    display:block;

}


.restaurant-image-placeholder{

    width:100%;

    height:100%;

    min-height:330px;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#ed1747;

    font-size:70px;

    background:
        linear-gradient(
            135deg,
            #ffe1eb,
            #fff
        );

}


.restaurant-hero-info{

    padding:
        38px 40px;

    display:flex;

    flex-direction:column;

    justify-content:center;

}


.restaurant-badge{

    display:inline-flex;

    align-items:center;

    gap:7px;

    width:max-content;

    padding:
        7px 12px;

    border-radius:20px;

    background:#fff0f5;

    color:#ed1747;

    font-size:12px;

    font-weight:700;

    margin-bottom:12px;

}


.restaurant-hero-info h1{

    margin:0;

    color:#222;

    font-size:38px;

    line-height:1.15;

    font-weight:800;

}


.restaurant-description{

    margin:
        12px 0 20px;

    color:#666;

    font-size:15px;

    line-height:1.6;

    max-width:620px;

}


.restaurant-stats{

    display:flex;

    flex-wrap:wrap;

    gap:10px;

    margin-bottom:22px;

}


.restaurant-stat{

    display:flex;

    align-items:center;

    gap:7px;

    padding:
        9px 12px;

    background:#f8f9fb;

    border:
        1px solid #eee;

    border-radius:10px;

    color:#555;

    font-size:12px;

    font-weight:600;

}


.restaurant-stat i{

    color:#ed1747;

}


.restaurant-stat.rating i{

    color:#f5a623;

}


.restaurant-details-row{

    display:flex;

    flex-wrap:wrap;

    gap:18px;

    color:#666;

    font-size:13px;

}


.restaurant-detail{

    display:flex;

    align-items:center;

    gap:7px;

}


.restaurant-detail i{

    color:#ed1747;

}


/* =========================================================
   MENU AREA
========================================================= */

.menu-section{

    margin-top:35px;

}


.menu-heading{

    display:flex;

    align-items:end;

    justify-content:space-between;

    gap:20px;

    margin-bottom:18px;

}


.menu-heading h2{

    margin:0;

    color:#222;

    font-size:28px;

    font-weight:800;

}


.menu-heading p{

    margin:5px 0 0;

    color:#777;

    font-size:13px;

}


.menu-categories{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

    margin-bottom:20px;

}


.category-tab{

    border:1px solid #eee;

    background:#fff;

    color:#555;

    padding:
        9px 16px;

    border-radius:20px;

    font-size:12px;

    font-weight:700;

    cursor:pointer;

    transition:.2s;

}


.category-tab:hover,
.category-tab.active{

    background:
        linear-gradient(
            90deg,
            #ed0a3f,
            #ff6294
        );

    color:#fff;

    border-color:transparent;

}


/* =========================================================
   MENU GRID
========================================================= */

.menu-grid{

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:18px;

}


.menu-card{

    background:#fff;

    border-radius:16px;

    border:
        1px solid #eee;

    box-shadow:
        0 7px 22px rgba(0,0,0,.05);

    padding:14px;

    display:grid;

    grid-template-columns:
        125px 1fr;

    gap:16px;

    transition:
        transform .2s,
        box-shadow .2s;

}


.menu-card:hover{

    transform:
        translateY(-3px);

    box-shadow:
        0 12px 28px rgba(0,0,0,.08);

}


.menu-image{

    width:125px;

    height:125px;

    border-radius:12px;

    overflow:hidden;

    background:
        linear-gradient(
            135deg,
            #ffe2ec,
            #fff
        );

    display:flex;

    align-items:center;

    justify-content:center;

}


.menu-image img{

    width:100%;

    height:100%;

    object-fit:cover;

}


.menu-placeholder{

    color:#ed1747;

    font-size:40px;

}


.menu-info{

    min-width:0;

    display:flex;

    flex-direction:column;

    justify-content:center;

}


.menu-category{

    color:#ed1747;

    font-size:10px;

    text-transform:uppercase;

    letter-spacing:.5px;

    font-weight:800;

    margin-bottom:5px;

}


.menu-info h3{

    margin:0;

    color:#222;

    font-size:18px;

    font-weight:800;

}


.menu-info p{

    margin:
        6px 0 12px;

    color:#777;

    font-size:12px;

    line-height:1.45;

}


.menu-bottom{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

}


.menu-price{

    color:#222;

    font-size:16px;

    font-weight:800;

    white-space:nowrap;

}


.add-cart-form{

    margin:0;

}


.add-cart-btn{

    border:none;

    background:
        linear-gradient(
            90deg,
            #ed0a3f,
            #f64c7d
        );

    color:#fff;

    border-radius:9px;

    padding:
        9px 13px;

    display:inline-flex;

    align-items:center;

    gap:6px;

    font-size:11px;

    font-weight:800;

    cursor:pointer;

    transition:.2s;

}


.add-cart-btn:hover{

    transform:
        translateY(-1px);

    box-shadow:
        0 5px 12px
        rgba(237,10,63,.22);

}


/* =========================================================
   ALERTS
========================================================= */

.page-message{

    padding:
        13px 16px;

    border-radius:10px;

    margin-bottom:18px;

    font-size:13px;

    font-weight:600;

}


.page-message.success{

    background:#eafaf0;

    color:#16743d;

    border:
        1px solid #c9efd8;

}


.page-message.error{

    background:#fff0f2;

    color:#b4233a;

    border:
        1px solid #ffd2da;

}


/* =========================================================
   EMPTY MENU
========================================================= */

.empty-menu{

    background:#fff;

    border-radius:18px;

    padding:60px 20px;

    text-align:center;

    border:
        1px solid #eee;

    box-shadow:
        0 8px 25px rgba(0,0,0,.05);

}


.empty-menu i{

    font-size:55px;

    color:#ed1747;

    margin-bottom:15px;

}


.empty-menu h3{

    margin:0;

    color:#222;

    font-size:22px;

}


.empty-menu p{

    color:#777;

    font-size:14px;

}


/* =========================================================
   RESTAURANT NOT FOUND
========================================================= */

.not-found{

    background:#fff;

    border-radius:20px;

    padding:80px 20px;

    text-align:center;

    box-shadow:
        0 10px 30px rgba(0,0,0,.06);

}


.not-found i{

    font-size:65px;

    color:#ed1747;

    margin-bottom:18px;

}


.not-found h1{

    margin:0;

    font-size:30px;

}


.not-found p{

    color:#777;

    margin:10px 0 25px;

}


.back-btn{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        11px 18px;

    border-radius:10px;

    background:
        linear-gradient(
            90deg,
            #ed0a3f,
            #ff6294
        );

    color:#fff;

    font-size:13px;

    font-weight:700;

}


/* =========================================================
   FOOTER
========================================================= */

.hf-footer{

    margin-top:20px;

    background:#222;

    color:#fff;

}


.hf-footer-inner{

    max-width:1250px;

    margin:auto;

    padding:
        45px 20px;

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:30px;

}


.hf-footer-column h3{

    margin:
        0 0 15px;

    font-size:17px;

}


.hf-footer-column ul{

    list-style:none;

    padding:0;

    margin:0;

}


.hf-footer-column li{

    margin-bottom:9px;

}


.hf-footer-column a{

    color:#bbb;

    font-size:13px;

}


.hf-footer-column a:hover{

    color:#ff6294;

}


.hf-social{

    display:flex;

    gap:9px;

    margin-top:15px;

}


.hf-social a{

    width:35px;

    height:35px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#333;

    color:#fff;

    transition:.2s;

}


.hf-social a:hover{

    background:#ed1747;

    color:#fff;

}


.hf-copyright{

    border-top:
        1px solid #444;

    text-align:center;

    padding:
        18px 15px;

    color:#aaa;

    font-size:12px;

}


.hf-copyright p{

    margin:0;

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:1000px){

    .hf-search{

        width:280px;

    }


    .restaurant-hero{

        grid-template-columns:1fr;

    }


    .restaurant-hero-image{

        min-height:280px;

    }


    .restaurant-hero-image img{

        min-height:280px;

    }


    .restaurant-image-placeholder{

        min-height:280px;

    }


    .menu-grid{

        grid-template-columns:1fr;

    }

}


@media(max-width:760px){

    .hf-top{

        flex-wrap:wrap;

        padding:
            12px 15px;

    }


    .hf-search{

        order:3;

        width:100%;

    }


    .hf-actions{

        gap:10px;

    }


    .hf-nav-inner{

        overflow-x:auto;

        justify-content:flex-start;

    }


    .hf-nav ul{

        gap:22px;

        white-space:nowrap;

    }


    .restaurant-page{

        margin-top:25px;

        padding:
            0 12px 45px;

    }


    .restaurant-hero-info{

        padding:
            28px 22px;

    }


    .restaurant-hero-info h1{

        font-size:30px;

    }


    .menu-heading{

        display:block;

    }


    .menu-heading h2{

        font-size:24px;

    }


    .hf-footer-inner{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:520px){

    .hf-logo{

        font-size:20px;

    }


    .hf-actions .user-name{

        display:none;

    }


    .restaurant-hero-image{

        min-height:220px;

    }


    .restaurant-hero-image img{

        min-height:220px;

    }


    .restaurant-image-placeholder{

        min-height:220px;

    }


    .restaurant-hero-info h1{

        font-size:27px;

    }


    .restaurant-description{

        font-size:13px;

    }


    .restaurant-stats{

        display:grid;

        grid-template-columns:
            1fr 1fr;

    }


    .menu-card{

        grid-template-columns:
            95px 1fr;

        gap:12px;

        padding:11px;

    }


    .menu-image{

        width:95px;

        height:105px;

    }


    .menu-info h3{

        font-size:15px;

    }


    .menu-info p{

        font-size:11px;

    }


    .menu-bottom{

        align-items:flex-end;

    }


    .menu-price{

        font-size:14px;

    }


    .add-cart-btn{

        padding:
            8px 9px;

        font-size:10px;

    }


    .hf-footer-inner{

        grid-template-columns:1fr;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="hf-header">


    <div class="hf-top">


        <a
            href="index.php"
            class="hf-logo"
        >

            <i class="fas fa-utensils"></i>

            Humsafar

        </a>


        <div class="hf-search">

            <input
                type="text"
                placeholder="Search for restaurants or food..."
                id="header-search"
            >

            <i class="fas fa-search"></i>

        </div>


        <div class="hf-actions">


            <a
                href="cart.php"
                class="cart-link"
            >

                <i
                    class="fas fa-shopping-cart"
                ></i>

                Cart

                <?php if ($cartCount > 0) { ?>

                    <span class="cart-count">

                        <?php
                            echo $cartCount;
                        ?>

                    </span>

                <?php } ?>

            </a>


            <?php if ($userId > 0) { ?>

                <div class="hf-user">

                    <i class="fas fa-user"></i>

                    <span class="user-name">

                        <?php
                            echo h(
                                $userName !== ''
                                    ? $userName
                                    : 'My Account'
                            );
                        ?>

                    </span>

                </div>


                <a
                    href="logout.php"
                    class="hf-logout"
                >

                    Logout

                </a>


            <?php } else { ?>

                <a
                    href="login.php"
                >

                    Sign In

                </a>


                <a
                    href="register.php"
                    class="hf-logout"
                >

                    Sign Up

                </a>

            <?php } ?>


        </div>


    </div>


    <nav class="hf-nav">

        <div class="hf-nav-inner">

            <ul>

                <li>
                    <a href="index.php">
                        Home
                    </a>
                </li>

                <li>
                    <a href="restaurants.php">
                        Restaurants
                    </a>
                </li>

                <li>
                    <a href="#">
                        Deals
                    </a>
                </li>

                <li>
                    <a href="customer/dashboard.php">
                        My Account
                    </a>
                </li>

                <li>
                    <a href="#">
                        Help
                    </a>
                </li>


            </ul>

        </div>

    </nav>


</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="restaurant-page">


<?php if (!$restaurant) { ?>


    <!-- NOT FOUND -->

    <div class="not-found">

        <i
            class="fas fa-store-slash"
        ></i>

        <h1>
            Restaurant Not Found
        </h1>

        <p>
            The restaurant you are looking for
            is not available right now.
        </p>

        <a
            href="restaurants.php"
            class="back-btn"
        >

            <i
                class="fas fa-arrow-left"
            ></i>

            Back to Restaurants

        </a>

    </div>


<?php } else { ?>


    <!-- =====================================================
         BREADCRUMB
    ====================================================== -->

    <div class="breadcrumb">

        <a href="index.php">
            Home
        </a>

        <i class="fas fa-chevron-right"></i>

        <a href="restaurants.php">
            Restaurants
        </a>

        <i class="fas fa-chevron-right"></i>

        <?php
            echo h($restaurantName);
        ?>

    </div>


    <!-- =====================================================
         SUCCESS / ERROR
    ====================================================== -->

    <?php if ($cartMessage !== '') { ?>

        <div class="page-message success">

            <i
                class="fas fa-check-circle"
            ></i>

            <?php
                echo h($cartMessage);
            ?>

            &nbsp;

            <a
                href="cart.php"
                style="
                    color:#16743d;
                    text-decoration:underline;
                "
            >
                View Cart
            </a>

        </div>

    <?php } ?>


    <?php if ($cartError !== '') { ?>

        <div class="page-message error">

            <i
                class="fas fa-exclamation-circle"
            ></i>

            <?php
                echo h($cartError);
            ?>

        </div>

    <?php } ?>


    <!-- =====================================================
         RESTAURANT HERO
    ====================================================== -->

    <section class="restaurant-hero">


        <!-- IMAGE -->

        <div class="restaurant-hero-image">


            <?php if ($restaurantImage !== '') { ?>

                <img
                    src="assets/images/restaurants/<?php
                        echo h($restaurantImage);
                    ?>"
                    alt="<?php
                        echo h($restaurantName);
                    ?>"
                >

            <?php } else { ?>

                <div
                    class="restaurant-image-placeholder"
                >

                    <i
                        class="fas fa-store"
                    ></i>

                </div>

            <?php } ?>


        </div>


        <!-- INFO -->

        <div class="restaurant-hero-info">


            <div class="restaurant-badge">

                <i
                    class="fas fa-utensils"
                ></i>

                Restaurant

            </div>


            <h1>

                <?php
                    echo h($restaurantName);
                ?>

            </h1>


            <p
                class="restaurant-description"
            >

                <?php
                    echo h(
                        $restaurantDescription
                    );
                ?>

            </p>


            <div class="restaurant-stats">


                <div
                    class="restaurant-stat rating"
                >

                    <i
                        class="fas fa-star"
                    ></i>

                    <strong>

                        <?php
                            echo number_format(
                                $restaurantRating,
                                1
                            );
                        ?>

                    </strong>

                    Rating

                </div>


                <div
                    class="restaurant-stat"
                >

                    <i
                        class="fas fa-clock"
                    ></i>

                    <?php
                        echo h(
                            $deliveryTime
                        );
                    ?>

                </div>


                <div
                    class="restaurant-stat"
                >

                    <i
                        class="fas fa-motorcycle"
                    ></i>

                    Rs.
                    <?php
                        echo number_format(
                            $deliveryFee,
                            0
                        );
                    ?>

                    Delivery

                </div>


            </div>


            <div
                class="restaurant-details-row"
            >


                <?php if ($restaurantAddress !== '') { ?>

                    <div
                        class="restaurant-detail"
                    >

                        <i
                            class="fas fa-location-dot"
                        ></i>

                        <?php
                            echo h(
                                $restaurantAddress
                            );
                        ?>

                    </div>

                <?php } ?>


                <?php if ($restaurantPhone !== '') { ?>

                    <div
                        class="restaurant-detail"
                    >

                        <i
                            class="fas fa-phone"
                        ></i>

                        <?php
                            echo h(
                                $restaurantPhone
                            );
                        ?>

                    </div>

                <?php } ?>


            </div>


        </div>


    </section>


    <!-- =====================================================
         MENU
    ====================================================== -->

    <section
        class="menu-section"
        id="menu"
    >


        <div class="menu-heading">


            <div>

                <h2>
                    Menu
                </h2>

                <p>
                    Choose your favorite food
                    and add it to your cart.
                </p>

            </div>


        </div>


        <?php if (!empty($menuCategories)) { ?>


            <div class="menu-categories">


                <button
                    type="button"
                    class="category-tab active"
                    data-category="all"
                >

                    All

                </button>


                <?php foreach (
                    $menuCategories
                    as $category
                ) { ?>

                    <button
                        type="button"
                        class="category-tab"
                        data-category="<?php
                            echo h(
                                strtolower(
                                    $category
                                )
                            );
                        ?>"
                    >

                        <?php
                            echo h(
                                $category
                            );
                        ?>

                    </button>

                <?php } ?>


            </div>


        <?php } ?>


        <?php if (!empty($menuItems)) { ?>


            <div class="menu-grid">


                <?php foreach (
                    $menuItems
                    as $item
                ) { ?>


                    <?php

                    $itemCategory =
                        trim(
                            $item['category']
                            ?? ''
                        );

                    if (
                        $itemCategory === ''
                    ) {

                        $itemCategory =
                            'Menu';

                    }


                    $itemImage =
                        $item['image']
                        ?? '';


                    $itemDescription =
                        $item['description']
                        ?? '';


                    $itemPrice =
                        (float)(
                            $item['price']
                            ?? 0
                        );

                    ?>


                    <article
                        class="menu-card"
                        data-category="<?php
                            echo h(
                                strtolower(
                                    $itemCategory
                                )
                            );
                        ?>"
                    >


                        <!-- ITEM IMAGE -->

                        <div
                            class="menu-image"
                        >


                            <?php if (
                                $itemImage !== ''
                            ) { ?>

                                <img
                                    src="assets/images/menu/<?php
                                        echo h(
                                            $itemImage
                                        );
                                    ?>"
                                    alt="<?php
                                        echo h(
                                            $item['name']
                                        );
                                    ?>"
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='flex';
                                    "
                                >


                                <div
                                    class="menu-placeholder"
                                    style="display:none;"
                                >

                                    <i
                                        class="fas fa-utensils"
                                    ></i>

                                </div>


                            <?php } else { ?>


                                <div
                                    class="menu-placeholder"
                                >

                                    <i
                                        class="fas fa-utensils"
                                    ></i>

                                </div>


                            <?php } ?>


                        </div>


                        <!-- ITEM INFO -->

                        <div
                            class="menu-info"
                        >


                            <div
                                class="menu-category"
                            >

                                <?php
                                    echo h(
                                        $itemCategory
                                    );
                                ?>

                            </div>


                            <h3>

                                <?php
                                    echo h(
                                        $item['name']
                                    );
                                ?>

                            </h3>


                            <p>

                                <?php

                                echo h(
                                    $itemDescription !== ''
                                        ? $itemDescription
                                        : 'Delicious food prepared fresh for you.'
                                );

                                ?>

                            </p>


                            <div
                                class="menu-bottom"
                            >


                                <span
                                    class="menu-price"
                                >

                                    Rs.
                                    <?php
                                        echo number_format(
                                            $itemPrice,
                                            0
                                        );
                                    ?>

                                </span>


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
                                        value="1"
                                    >


                                    <button
                                        type="submit"
                                        name="add_to_cart"
                                        class="add-cart-btn"
                                    >

                                        <i
                                            class="fas fa-plus"
                                        ></i>

                                        Add to Cart

                                    </button>

                                </form>


                            </div>


                        </div>


                    </article>


                <?php } ?>


            </div>


        <?php } else { ?>


            <div class="empty-menu">

                <i
                    class="fas fa-utensils"
                ></i>

                <h3>
                    Menu Not Available
                </h3>

                <p>
                    This restaurant currently
                    has no menu items available.
                </p>

            </div>


        <?php } ?>


    </section>


<?php } ?>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="hf-footer">


    <div class="hf-footer-inner">


        <div class="hf-footer-column">

            <h3>
                Humsafar
            </h3>

            <ul>

                <li>
                    <a href="#">
                        About Us
                    </a>
                </li>

                <li>
                    <a href="#">
                        Careers
                    </a>
                </li>

                <li>
                    <a href="#">
                        Press
                    </a>
                </li>

                <li>
                    <a href="#">
                        Blog
                    </a>
                </li>

            </ul>

        </div>


        <div class="hf-footer-column">

            <h3>
                For Foodies
            </h3>

            <ul>

                <li>
                    <a href="#">
                        Code of Conduct
                    </a>
                </li>

                <li>
                    <a href="#">
                        Community
                    </a>
                </li>

                <li>
                    <a href="#">
                        Blogger Help
                    </a>
                </li>

                <li>
                    <a href="#">
                        Mobile Apps
                    </a>
                </li>

            </ul>

        </div>




        <div class="hf-footer-column">

            <h3>
                Contact Us
            </h3>

            <ul>

                <li>
                    <a href="#">
                        Help & Support
                    </a>
                </li>

                

                

            </ul>


            <div class="hf-social">

                <a href="#">
                    <i
                        class="fab fa-facebook"
                    ></i>
                </a>

                <a href="#">
                    <i
                        class="fab fa-twitter"
                    ></i>
                </a>

                <a href="#">
                    <i
                        class="fab fa-instagram"
                    ></i>
                </a>

                <a href="#">
                    <i
                        class="fab fa-youtube"
                    ></i>
                </a>

            </div>


        </div>


    </div>


    <div class="hf-copyright">

        <p>

            &copy;
            <?php
                echo date('Y');
            ?>

            Humsafar Food Delivery.
            All rights reserved.

        </p>

    </div>


</footer>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        /* =========================================
           CATEGORY FILTER
        ========================================= */

        const tabs =
            document.querySelectorAll(
                '.category-tab'
            );


        const cards =
            document.querySelectorAll(
                '.menu-card'
            );


        tabs.forEach(function(tab){

            tab.addEventListener(
                'click',
                function(){

                    tabs.forEach(
                        function(item){
                            item.classList.remove(
                                'active'
                            );
                        }
                    );


                    this.classList.add(
                        'active'
                    );


                    const selected =
                        this.getAttribute(
                            'data-category'
                        );


                    cards.forEach(
                        function(card){

                            const cardCategory =
                                card.getAttribute(
                                    'data-category'
                                );


                            if (
                                selected === 'all'
                                ||
                                cardCategory === selected
                            ){

                                card.style.display =
                                    'grid';

                            }else{

                                card.style.display =
                                    'none';

                            }

                        }
                    );

                }
            );

        });


        /* =========================================
           HEADER SEARCH
        ========================================= */

        const searchInput =
            document.getElementById(
                'header-search'
            );


        if(searchInput){

            searchInput.addEventListener(
                'keydown',
                function(event){

                    if(
                        event.key === 'Enter'
                    ){

                        const value =
                            this.value.trim();


                        if(value !== ''){

                            window.location.href =
                                'restaurants.php?search='
                                +
                                encodeURIComponent(
                                    value
                                );

                        }

                    }

                }
            );

        }


    }
);

</script>


</body>

</html>