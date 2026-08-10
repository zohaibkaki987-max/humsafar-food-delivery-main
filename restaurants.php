<?php

/* =========================================================
   HUMSAFAR - RESTAURANTS PAGE
   Complete standalone page
========================================================= */

require_once 'includes/config.php';
require_once 'includes/session.php';


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('h')) {

    function h($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/* =========================================================
   USER INFORMATION
========================================================= */

$userName = 'Guest';

if (isset($_SESSION['user_id'])) {

    if (!empty($_SESSION['full_name'])) {

        $userName =
            $_SESSION['full_name'];

    } elseif (!empty($_SESSION['user_name'])) {

        $userName =
            $_SESSION['user_name'];

    } elseif (!empty($_SESSION['name'])) {

        $userName =
            $_SESSION['name'];

    }

}


/* =========================================================
   RESTAURANTS
========================================================= */

$restaurants = [];

$sql = "
    SELECT *
    FROM restaurants
    WHERE status = 1
    ORDER BY rating DESC
";

$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $restaurants[] = $row;

    }

}


/* =========================================================
   CART COUNT
========================================================= */

$cartCount = 0;

if (isset($_SESSION['user_id'])) {

    $currentUserId =
        (int)$_SESSION['user_id'];

    $cartSql = "
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM cart
        WHERE user_id = $currentUserId
    ";

    $cartResult =
        $conn->query($cartSql);

    if ($cartResult) {

        $cartRow =
            $cartResult->fetch_assoc();

        $cartCount =
            (int)($cartRow['total'] ?? 0);

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
        Restaurants - Humsafar
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
           GLOBAL
        ===================================================== */

        * {

            box-sizing: border-box;

        }


        html {

            scroll-behavior: smooth;

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


        a {

            text-decoration: none;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .hs-header {

            width: 100%;

            background:
                #ffffff;

            box-shadow:
                0 2px 12px
                rgba(0,0,0,.06);

            position: relative;

            z-index: 1000;

        }


        .hs-top-bar {

            width: 100%;

            max-width: 1250px;

            min-height: 54px;

            margin: 0 auto;

            padding:
                0 20px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 22px;

        }


        /* LOGO */

        .hs-logo {

            display: flex;

            align-items: center;

            gap: 7px;

            color:
                #ed1748;

            flex-shrink: 0;

        }


        .hs-logo i {

            font-size: 17px;

        }


        .hs-logo h1 {

            margin: 0;

            font-size: 21px;

            font-weight: 800;

            color:
                #ed1748;

        }


        /* SEARCH */

        .hs-header-search {

            position: relative;

            width: 350px;

            max-width: 100%;

        }


        .hs-header-search input {

            width: 100%;

            height: 36px;

            padding:
                0 40px 0 15px;

            border:
                1px solid #ddd;

            border-radius: 20px;

            outline: none;

            background:
                #fafafa;

            color:
                #333;

            font-size: 12px;

        }


        .hs-header-search input:focus {

            border-color:
                #f42a65;

            background:
                #fff;

            box-shadow:
                0 0 0 3px
                rgba(244,42,101,.08);

        }


        .hs-header-search i {

            position: absolute;

            right: 14px;

            top: 50%;

            transform:
                translateY(-50%);

            color:
                #888;

            font-size: 13px;

        }


        /* USER ACTIONS */

        .hs-user-actions {

            display: flex;

            align-items: center;

            gap: 18px;

            flex-shrink: 0;

        }


        .hs-user-actions a {

            color:
                #333;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

        }


        .hs-user-actions a:hover {

            color:
                #ed1748;

        }


        .hs-cart {

            display: flex;

            align-items: center;

            gap: 5px;

        }


        .hs-cart-count {

            min-width: 17px;

            height: 17px;

            padding: 0 4px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                #ed1748;

            color:
                #fff;

            font-size: 9px;

            font-weight: 700;

        }


        .hs-user-name {

            display: flex;

            align-items: center;

            gap: 5px;

        }


        .hs-user-name i {

            color:
                #f3a900;

        }


        .hs-logout {

            padding:
                7px 16px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #ffd400,
                    #ffbd00
                );

            color:
                #222 !important;

            font-size: 11px !important;

            font-weight: 700 !important;

        }


        .hs-logout:hover {

            color:
                #222 !important;

            filter:
                brightness(.96);

        }


        /* NAVIGATION */

        .hs-nav {

            width: 100%;

            background:
                linear-gradient(
                    100deg,
                    #ef003b 0%,
                    #f32b68 52%,
                    #ff6190 100%
                );

        }


        .hs-nav ul {

            max-width: 1250px;

            min-height: 43px;

            margin: 0 auto;

            padding:
                0 20px;

            list-style: none;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 30px;

        }


        .hs-nav li {

            margin: 0;

            padding: 0;

        }


        .hs-nav a {

            display: flex;

            align-items: center;

            min-height: 43px;

            color:
                #fff;

            font-size: 12px;

            font-weight: 700;

            position: relative;

        }


        .hs-nav a::after {

            content: "";

            position: absolute;

            left: 0;

            right: 0;

            bottom: 7px;

            height: 2px;

            border-radius: 5px;

            background:
                #fff;

            transform:
                scaleX(0);

            transition:
                transform .2s;

        }


        .hs-nav a:hover::after,
        .hs-nav a.active::after {

            transform:
                scaleX(1);

        }


        /* =====================================================
           PAGE
        ===================================================== */

        .restaurants-page {

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

        .restaurants-title {

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
                rgba(239,30,84,.16);

        }


        .restaurants-title::before {

            content: "";

            position: absolute;

            width: 170px;

            height: 170px;

            right: -55px;

            top: -85px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.09);

        }


        .restaurants-title h1 {

            position: relative;

            z-index: 2;

            margin: 0;

            color:
                #fff;

            font-size: 31px;

            font-weight: 800;

        }


        .restaurants-title h1 i {

            margin-right: 9px;

        }


        .restaurants-title p {

            position: relative;

            z-index: 2;

            margin:
                7px 0 0;

            color:
                rgba(255,255,255,.93);

            font-size: 14px;

        }


        /* =====================================================
           FILTERS
        ===================================================== */

        .filters-container {

            margin-bottom: 27px;

            padding:
                17px;

            background:
                #fff;

            border:
                1px solid #ededed;

            border-radius: 15px;

            box-shadow:
                0 7px 23px
                rgba(0,0,0,.055);

        }


        .filters {

            display: grid;

            grid-template-columns:
                1fr
                1fr
                1fr
                1.35fr;

            gap: 13px;

            align-items: end;

        }


        .filter-group {

            min-width: 0;

        }


        .filter-group label {

            display: block;

            margin-bottom: 6px;

            color:
                #444;

            font-size: 12px;

            font-weight: 700;

        }


        .filter-group select,
        .restaurant-search {

            width: 100%;

            height: 43px;

            padding:
                0 12px;

            border:
                1px solid #ddd;

            border-radius: 9px;

            background:
                #fff;

            color:
                #333;

            font-family: inherit;

            font-size: 13px;

            outline: none;

        }


        .filter-group select:focus,
        .restaurant-search:focus {

            border-color:
                #f32b68;

            box-shadow:
                0 0 0 3px
                rgba(243,43,104,.09);

        }


        /* SEARCH BOX */

        .restaurant-search-box {

            position: relative;

        }


        .restaurant-search-box i {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color:
                #999;

            pointer-events: none;

        }


        .restaurant-search {

            padding-left:
                38px;

        }


        /* =====================================================
           RESTAURANT GRID
        ===================================================== */

        .restaurant-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0,1fr)
                );

            gap: 22px;

            align-items: stretch;

        }


        /* =====================================================
           CARD
        ===================================================== */

        .restaurant-card {

            min-width: 0;

            display: flex;

            flex-direction: column;

            overflow: hidden;

            background:
                #fff;

            border:
                1px solid #ededed;

            border-radius: 17px;

            box-shadow:
                0 7px 23px
                rgba(0,0,0,.055);

            transition:
                transform .25s ease,
                box-shadow .25s ease;

        }


        .restaurant-card:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 15px 32px
                rgba(0,0,0,.10);

        }


        /* =====================================================
           IMAGE
        ===================================================== */

        .restaurant-image {

            position: relative;

            width: 100%;

            height: 205px;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff1f5,
                    #ffe3eb
                );

        }


        .restaurant-image img {

            width: 100%;

            height: 100%;

            display: block;

            object-fit: cover;

            transition:
                transform .35s ease;

        }


        .restaurant-card:hover
        .restaurant-image img {

            transform:
                scale(1.05);

        }


        .restaurant-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color:
                #f22b65;

            font-size: 55px;

            background:
                linear-gradient(
                    135deg,
                    #fff0f4,
                    #ffe3ec
                );

        }


        /* =====================================================
           RATING BADGE
        ===================================================== */

        .rating-badge {

            position: absolute;

            right: 11px;

            bottom: 11px;

            min-width: 49px;

            height: 30px;

            padding:
                0 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 4px;

            background:
                #fff;

            border-radius: 17px;

            color:
                #333;

            font-size: 12px;

            font-weight: 800;

            box-shadow:
                0 4px 13px
                rgba(0,0,0,.16);

        }


        .rating-badge i {

            color:
                #f6a623;

        }


        /* =====================================================
           CARD CONTENT
        ===================================================== */

        .restaurant-info {

            flex: 1;

            min-width: 0;

            display: flex;

            flex-direction: column;

            padding:
                17px;

        }


        .restaurant-name {

            margin: 0;

            color:
                #222;

            font-size: 19px;

            line-height: 1.25;

            font-weight: 800;

            overflow-wrap: anywhere;

        }


        .restaurant-description {

            margin:
                7px 0 14px;

            min-height: 38px;

            color:
                #777;

            font-size: 13px;

            line-height: 1.45;

            display:
                -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;

        }


        /* =====================================================
           META BOXES
        ===================================================== */

        .restaurant-meta {

            width: 100%;

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0,1fr)
                );

            gap: 7px;

            margin-bottom: 15px;

        }


        .meta-box {

            min-width: 0;

            min-height: 39px;

            padding:
                5px 4px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 4px;

            border:
                1px solid #ededed;

            border-radius: 8px;

            background:
                #fafafa;

            color:
                #555;

            font-size: 10.5px;

            font-weight: 600;

            text-align: center;

            line-height: 1.25;

            overflow-wrap:
                anywhere;

        }


        .meta-box i {

            flex-shrink: 0;

            color:
                #f32b68;

            font-size: 10px;

        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .view-btn {

            width: 100%;

            min-height: 43px;

            margin-top: auto;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding:
                10px 13px;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #ed1748,
                    #f53b74
                );

            color:
                #fff;

            font-size: 13px;

            font-weight: 700;

            box-shadow:
                0 5px 14px
                rgba(237,23,72,.16);

            transition:
                transform .2s,
                box-shadow .2s,
                filter .2s;

        }


        .view-btn:hover {

            color:
                #fff;

            transform:
                translateY(-1px);

            filter:
                brightness(.97);

            box-shadow:
                0 7px 18px
                rgba(237,23,72,.24);

        }


        /* =====================================================
           NO RESULTS
        ===================================================== */

        .no-restaurants {

            padding:
                65px 20px;

            background:
                #fff;

            border:
                1px solid #eee;

            border-radius: 17px;

            text-align: center;

            box-shadow:
                0 7px 23px
                rgba(0,0,0,.05);

        }


        .no-restaurants i {

            display: block;

            margin-bottom: 14px;

            color:
                #f32b68;

            font-size: 50px;

        }


        .no-restaurants h2 {

            margin: 0;

            color:
                #222;

            font-size: 22px;

        }


        .no-restaurants p {

            margin:
                7px 0 0;

            color:
                #777;

            font-size: 13px;

        }


        .restaurant-card.hidden {

            display: none;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .hs-footer {

            margin-top: 15px;

            background:
                #252525;

            color:
                #fff;

        }


        .hs-footer-content {

            width: 100%;

            max-width: 1250px;

            margin: 0 auto;

            padding:
                43px 20px;

            display: grid;

            grid-template-columns:
                repeat(4,1fr);

            gap: 30px;

        }


        .hs-footer-column h3 {

            margin:
                0 0 15px;

            color:
                #fff;

            font-size: 17px;

        }


        .hs-footer-column ul {

            margin: 0;

            padding: 0;

            list-style: none;

        }


        .hs-footer-column li {

            margin-bottom: 9px;

        }


        .hs-footer-column a {

            color:
                #bbb;

            font-size: 13px;

            transition:
                color .2s;

        }


        .hs-footer-column a:hover {

            color:
                #ff4f83;

        }


        .hs-social {

            display: flex;

            gap: 9px;

            margin-top: 16px;

        }


        .hs-social a {

            width: 35px;

            height: 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                rgba(255,255,255,.08);

            color:
                #fff;

        }


        .hs-social a:hover {

            background:
                linear-gradient(
                    135deg,
                    #ed1748,
                    #f65385
                );

            color:
                #fff;

        }


        .hs-copyright {

            padding:
                17px 15px;

            border-top:
                1px solid
                rgba(255,255,255,.10);

            text-align: center;

            color:
                #aaa;

            font-size: 12px;

        }


        .hs-copyright p {

            margin: 0;

        }


        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 1000px) {


            .hs-top-bar {

                gap: 14px;

            }


            .hs-header-search {

                width: 280px;

            }


            .hs-nav ul {

                gap: 20px;

            }


            .restaurant-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );

            }


            .filters {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );

            }

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 700px) {


            .hs-top-bar {

                min-height: auto;

                padding:
                    12px 15px;

                flex-wrap: wrap;

                justify-content: center;

            }


            .hs-logo {

                order: 1;

            }


            .hs-header-search {

                order: 3;

                width: 100%;

            }


            .hs-user-actions {

                order: 2;

                margin-left: auto;

                gap: 10px;

            }


            .hs-user-name {

                display: none;

            }


            .hs-nav ul {

                padding:
                    5px 10px;

                gap:
                    0 16px;

                flex-wrap: wrap;

                justify-content: center;

            }


            .hs-nav a {

                min-height: 35px;

                font-size: 11px;

            }


            .restaurants-page {

                margin-top:
                    22px;

                padding:
                    0 12px 45px;

            }


            .restaurants-title {

                padding:
                    25px 21px;

            }


            .restaurants-title h1 {

                font-size:
                    25px;

            }


            .restaurants-title p {

                font-size:
                    12px;

            }


            .filters {

                grid-template-columns:
                    1fr;

            }


            .restaurant-grid {

                grid-template-columns:
                    1fr;

                gap:
                    18px;

            }


            .restaurant-image {

                height:
                    220px;

            }


            .hs-footer-content {

                grid-template-columns:
                    repeat(2,1fr);

                gap:
                    25px;

            }

        }


        /* =====================================================
           SMALL MOBILE
        ===================================================== */

        @media (max-width: 420px) {


            .hs-user-actions {

                gap:
                    7px;

            }


            .hs-user-actions a {

                font-size:
                    11px;

            }


            .hs-logout {

                padding:
                    6px 11px;

            }


            .restaurant-meta {

                gap:
                    5px;

            }


            .meta-box {

                font-size:
                    9.5px;

            }


            .hs-footer-content {

                grid-template-columns:
                    1fr;

            }

        }


    </style>


</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="hs-header">


    <!-- TOP BAR -->

    <div class="hs-top-bar">


        <!-- LOGO -->

        <a
            href="index.php"
            class="hs-logo"
        >

            <i
                class="fas fa-utensils"
            ></i>

            <h1>
                Humsafar
            </h1>

        </a>


        <!-- SEARCH -->

        <div class="hs-header-search">

            <input
                type="text"
                id="header-search"
                placeholder="Search for restaurants or food..."
                autocomplete="off"
            >

            <i
                class="fas fa-search"
            ></i>

        </div>


        <!-- USER -->

        <div class="hs-user-actions">


            <a
                href="cart.php"
                class="hs-cart"
            >

                <i
                    class="fas fa-shopping-cart"
                ></i>

                Cart

                <?php if ($cartCount > 0) { ?>

                    <span
                        class="hs-cart-count"
                    >
                        <?php
                            echo $cartCount;
                        ?>
                    </span>

                <?php } ?>


            </a>


            <?php if (isset($_SESSION['user_id'])) { ?>


                <a
                    href="my-account.php"
                    class="hs-user-name"
                >

                    <i
                        class="fas fa-user"
                    ></i>

                    <?php
                        echo h(
                            $userName
                        );
                    ?>

                </a>


                <a
                    href="logout.php"
                    class="hs-logout"
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
                    href="signup.php"
                    class="hs-logout"
                >
                    Sign Up
                </a>


            <?php } ?>


        </div>


    </div>


    <!-- NAVIGATION -->

    <nav class="hs-nav">


        <ul>


            <li>

                <a
                    href="index.php"
                >
                    Home
                </a>

            </li>


            <li>

                <a
                    href="restaurants.php"
                    class="active"
                >
                    Restaurants
                </a>

            </li>


            <li>

                <a
                    href="deals.php"
                >
                    Deals
                </a>

            </li>


            <li>

                <a
                    href="my-account.php"
                >
                    My Account
                </a>

            </li>


            <li>

                <a
                    href="#"
                >
                    Help
                </a>

            </li>

        </ul>


    </nav>


</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="restaurants-page">


    <!-- =====================================================
         PAGE TITLE
    ====================================================== -->

    <section class="restaurants-title">


        <h1>

            <i
                class="fas fa-store"
            ></i>

            All Restaurants

        </h1>


        <p>
            Discover the best restaurants in your area
        </p>


    </section>


    <!-- =====================================================
         FILTERS
    ====================================================== -->

    <section class="filters-container">


        <div class="filters">


            <!-- SORT -->

            <div class="filter-group">

                <label
                    for="sort-by"
                >
                    Sort by:
                </label>


                <select
                    id="sort-by"
                >

                    <option value="rating">
                        Rating
                    </option>

                    <option value="delivery-time">
                        Delivery Time
                    </option>

                    <option value="name">
                        Name
                    </option>

                </select>

            </div>


            <!-- PRICE -->

            <div class="filter-group">

                <label
                    for="price-range"
                >
                    Price Range:
                </label>


                <select
                    id="price-range"
                >

                    <option value="all">
                        All
                    </option>

                    <option value="$">
                        $
                    </option>

                    <option value="$$">
                        $$
                    </option>

                    <option value="$$$">
                        $$$
                    </option>

                </select>

            </div>


            <!-- CUISINE -->

            <div class="filter-group">

                <label
                    for="cuisine"
                >
                    Cuisine:
                </label>


                <select
                    id="cuisine"
                >

                    <option value="all">
                        All Cuisines
                    </option>

                    <option value="italian">
                        Italian
                    </option>

                    <option value="asian">
                        Asian
                    </option>

                    <option value="mexican">
                        Mexican
                    </option>

                    <option value="indian">
                        Indian
                    </option>

                </select>

            </div>


            <!-- SEARCH -->

            <div class="filter-group">

                <label
                    for="restaurant-search"
                >
                    Search Restaurant:
                </label>


                <div
                    class="restaurant-search-box"
                >

                    <i
                        class="fas fa-search"
                    ></i>


                    <input
                        type="text"
                        id="restaurant-search"
                        class="restaurant-search"
                        placeholder="Search restaurants..."
                        autocomplete="off"
                    >

                </div>


            </div>


        </div>


    </section>


    <!-- =====================================================
         RESTAURANTS
    ====================================================== -->


    <?php if (!empty($restaurants)) { ?>


        <section
            class="restaurant-grid"
            id="restaurant-grid"
        >


            <?php foreach ($restaurants as $restaurant) { ?>


                <?php

                $id =
                    (int)(
                        $restaurant['id']
                        ?? 0
                    );


                $name =
                    trim(
                        (string)(
                            $restaurant['name']
                            ?? 'Restaurant'
                        )
                    );


                $description =
                    trim(
                        (string)(
                            $restaurant['description']
                            ?? ''
                        )
                    );


                $rating =
                    (float)(
                        $restaurant['rating']
                        ?? 0
                    );


                $deliveryTime =
                    trim(
                        (string)(
                            $restaurant['delivery_time']
                            ?? ''
                        )
                    );


                $deliveryFee =
                    (float)(
                        $restaurant['delivery_fee']
                        ?? 0
                    );


                $image =
                    trim(
                        (string)(
                            $restaurant['image']
                            ?? ''
                        )
                    );


                /* PRICE CATEGORY */

                if ($deliveryFee >= 300) {

                    $priceRange =
                        '$$$';

                } elseif ($deliveryFee >= 150) {

                    $priceRange =
                        '$$';

                } else {

                    $priceRange =
                        '$';

                }


                ?>


                <article
                    class="restaurant-card"

                    data-name="<?php
                        echo h(
                            strtolower(
                                $name
                            )
                        );
                    ?>"

                    data-rating="<?php
                        echo $rating;
                    ?>"

                    data-delivery-time="<?php
                        echo h(
                            $deliveryTime
                        );
                    ?>"

                    data-price="<?php
                        echo h(
                            $priceRange
                        );
                    ?>"
                >


                    <!-- IMAGE -->

                    <div
                        class="restaurant-image"
                    >


                        <?php if (!empty($image)) { ?>


                            <img
                                src="assets/images/restaurants/<?php
                                    echo h(
                                        $image
                                    );
                                ?>"
                                alt="<?php
                                    echo h(
                                        $name
                                    );
                                ?>"
                                loading="lazy"
                            >


                        <?php } else { ?>


                            <div
                                class="restaurant-placeholder"
                            >

                                <i
                                    class="fas fa-store"
                                ></i>

                            </div>


                        <?php } ?>


                        <!-- RATING -->

                        <div
                            class="rating-badge"
                        >

                            <i
                                class="fas fa-star"
                            ></i>

                            <?php

                            echo number_format(
                                $rating,
                                1
                            );

                            ?>

                        </div>


                    </div>


                    <!-- INFO -->

                    <div
                        class="restaurant-info"
                    >


                        <!-- NAME -->

                        <h2
                            class="restaurant-name"
                        >

                            <?php
                                echo h(
                                    $name
                                );
                            ?>

                        </h2>


                        <!-- DESCRIPTION -->

                        <p
                            class="restaurant-description"
                        >

                            <?php

                            if (
                                $description !== ''
                            ) {

                                echo h(
                                    $description
                                );

                            } else {

                                echo
                                    'Delicious food and great service.';

                            }

                            ?>

                        </p>


                        <!-- META -->

                        <div
                            class="restaurant-meta"
                        >


                            <!-- DELIVERY TIME -->

                            <div
                                class="meta-box"
                            >

                                <i
                                    class="fas fa-clock"
                                ></i>

                                <span>

                                    <?php

                                    if (
                                        $deliveryTime !== ''
                                    ) {

                                        echo h(
                                            $deliveryTime
                                        );

                                    } else {

                                        echo
                                            'N/A';

                                    }

                                    ?>

                                </span>

                            </div>


                            <!-- DELIVERY FEE -->

                            <div
                                class="meta-box"
                            >

                                <i
                                    class="fas fa-motorcycle"
                                ></i>

                                <span>

                                    Rs.

                                    <?php

                                    echo number_format(
                                        $deliveryFee,
                                        0
                                    );

                                    ?>

                                </span>

                            </div>


                            <!-- RATING -->

                            <div
                                class="meta-box"
                            >

                                <i
                                    class="fas fa-star"
                                ></i>

                                <span>

                                    <?php

                                    echo number_format(
                                        $rating,
                                        1
                                    );

                                    ?>

                                </span>

                            </div>


                        </div>


                        <!-- VIEW BUTTON -->

                        <a
                            href="restaurant.php?id=<?php
                                echo $id;
                            ?>"
                            class="view-btn"
                        >

                            <i
                                class="fas fa-utensils"
                            ></i>

                            View Restaurant

                        </a>


                    </div>


                </article>


            <?php } ?>


        </section>


        <!-- =================================================
             FILTER EMPTY
        ================================================== -->

        <div
            class="no-restaurants"
            id="no-filter-results"
            style="display:none;"
        >

            <i
                class="fas fa-store-slash"
            ></i>


            <h2>
                No Restaurants Found
            </h2>


            <p>
                Try changing your search or filters.
            </p>


        </div>


    <?php } else { ?>


        <!-- =================================================
             DATABASE EMPTY
        ================================================== -->

        <div
            class="no-restaurants"
        >

            <i
                class="fas fa-store-slash"
            ></i>


            <h2>
                No Restaurants Available
            </h2>


            <p>
                There are currently no restaurants to display.
            </p>


        </div>


    <?php } ?>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer
    class="hs-footer"
>


    <div
        class="hs-footer-content"
    >


        <!-- COLUMN 1 -->

        <div
            class="hs-footer-column"
        >

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


        <!-- COLUMN 2 -->

        <div
            class="hs-footer-column"
        >

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


        


        <!-- COLUMN 4 -->

        <div
            class="hs-footer-column"
        >

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


            <div
                class="hs-social"
            >

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


    <div
        class="hs-copyright"
    >

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
    function () {


        const grid =
            document.getElementById(
                'restaurant-grid'
            );


        if (!grid) {

            return;

        }


        const cards =
            Array.from(
                grid.querySelectorAll(
                    '.restaurant-card'
                )
            );


        const sortBy =
            document.getElementById(
                'sort-by'
            );


        const priceRange =
            document.getElementById(
                'price-range'
            );


        const search =
            document.getElementById(
                'restaurant-search'
            );


        const headerSearch =
            document.getElementById(
                'header-search'
            );


        const noResults =
            document.getElementById(
                'no-filter-results'
            );


        /* =====================================================
           FILTER
        ===================================================== */

        function filterRestaurants() {


            const searchValue =
                search
                    ? search.value
                        .trim()
                        .toLowerCase()
                    : '';


            const priceValue =
                priceRange
                    ? priceRange.value
                    : 'all';


            let visibleCount =
                0;


            cards.forEach(
                function (card) {


                    const name =
                        (
                            card.dataset.name
                            || ''
                        ).toLowerCase();


                    const price =
                        card.dataset.price
                        || '$';


                    const matchesSearch =
                        name.includes(
                            searchValue
                        );


                    const matchesPrice =
                        priceValue === 'all'
                        ||
                        price === priceValue;


                    if (
                        matchesSearch
                        &&
                        matchesPrice
                    ) {

                        card.classList.remove(
                            'hidden'
                        );

                        visibleCount++;

                    } else {

                        card.classList.add(
                            'hidden'
                        );

                    }

                }
            );


            sortRestaurants();


            if (noResults) {

                noResults.style.display =
                    visibleCount === 0
                        ? 'block'
                        : 'none';

            }

        }


        /* =====================================================
           SORT
        ===================================================== */

        function sortRestaurants() {


            if (!sortBy) {

                return;

            }


            const sortValue =
                sortBy.value;


            cards.sort(
                function (a, b) {


                    if (
                        sortValue ===
                        'rating'
                    ) {

                        return (
                            parseFloat(
                                b.dataset.rating
                                || 0
                            )
                            -
                            parseFloat(
                                a.dataset.rating
                                || 0
                            )
                        );

                    }


                    if (
                        sortValue ===
                        'delivery-time'
                    ) {


                        const aTime =
                            parseInt(
                                a.dataset.deliveryTime
                                || '0'
                            );


                        const bTime =
                            parseInt(
                                b.dataset.deliveryTime
                                || '0'
                            );


                        return (
                            aTime -
                            bTime
                        );

                    }


                    if (
                        sortValue ===
                        'name'
                    ) {

                        return (
                            (
                                a.dataset.name
                                || ''
                            ).localeCompare(
                                b.dataset.name
                                || ''
                            )
                        );

                    }


                    return 0;

                }
            );


            cards.forEach(
                function (card) {

                    grid.appendChild(
                        card
                    );

                }
            );

        }


        /* =====================================================
           EVENTS
        ===================================================== */

        if (sortBy) {

            sortBy.addEventListener(
                'change',
                filterRestaurants
            );

        }


        if (priceRange) {

            priceRange.addEventListener(
                'change',
                filterRestaurants
            );

        }


        if (search) {

            search.addEventListener(
                'input',
                filterRestaurants
            );

        }


        /* =====================================================
           HEADER SEARCH
        ===================================================== */

        if (headerSearch) {

            headerSearch.addEventListener(
                'input',
                function () {


                    if (search) {

                        search.value =
                            headerSearch.value;

                        filterRestaurants();

                    }

                }
            );

        }


        /* INITIAL */

        filterRestaurants();


    }
);


</script>


</body>

</html>