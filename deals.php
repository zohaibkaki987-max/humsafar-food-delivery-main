<?php

/* =========================================================
   HUMSAFAR - DEALS PAGE
   Uses existing project database connection.
   IMPORTANT:
   Existing project uses includes/config.php
   ========================================================= */

require_once __DIR__ . '/includes/config.php';


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
   LOAD DEALS
   No separate deals table is required.
   Deals are created from existing menu_items.
========================================================= */

$deals = [];

$sql = "
    SELECT
        menu_items.id AS menu_id,
        menu_items.restaurant_id,
        menu_items.name AS item_name,
        menu_items.description AS item_description,
        menu_items.price,
        menu_items.image AS item_image,
        restaurants.name AS restaurant_name,
        restaurants.image AS restaurant_image,
        restaurants.rating,
        restaurants.delivery_time,
        restaurants.delivery_fee
    FROM menu_items
    INNER JOIN restaurants
        ON restaurants.id = menu_items.restaurant_id
    WHERE
        menu_items.status = 1
        AND restaurants.status = 1
    ORDER BY
        restaurants.rating DESC,
        menu_items.price ASC
";


$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $deals[] = $row;

    }

}


/* =========================================================
   CREATE DEAL INFORMATION
========================================================= */

foreach ($deals as &$deal) {

    /*
     * Existing database does not have discount columns.
     * We display attractive promotional deals without
     * changing the database structure.
     */

    $price = (float)$deal['price'];

    if ($price >= 1000) {

        $discountPercent = 20;

    } elseif ($price >= 600) {

        $discountPercent = 15;

    } elseif ($price >= 300) {

        $discountPercent = 10;

    } else {

        $discountPercent = 5;

    }


    $deal['discount_percent'] = $discountPercent;

    $deal['old_price'] = $price;

    $deal['deal_price'] =
        $price - (
            $price * $discountPercent / 100
        );

}

unset($deal);

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
        Deals - Humsafar
    </title>


    <!-- Existing Website CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #faf7fb;

    color: #333;

    font-family:
        'Segoe UI',
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;

}


/* =========================================================
   PAGE WRAPPER
========================================================= */

.deals-page {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        45px 20px 70px;

}


/* =========================================================
   PAGE HERO
========================================================= */

.deals-hero {

    position: relative;

    overflow: hidden;

    border-radius: 24px;

    padding:
        45px 45px;

    margin-bottom: 30px;

    background:
        linear-gradient(
            135deg,
            #ff416c 0%,
            #ff4b8b 45%,
            #c850c0 100%
        );

    color: #fff;

    box-shadow:
        0 15px 40px
        rgba(210, 70, 150, .18);

}


.deals-hero::before {

    content: "";

    position: absolute;

    width: 230px;

    height: 230px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.10);

    right: -60px;

    top: -70px;

}


.deals-hero::after {

    content: "";

    position: absolute;

    width: 160px;

    height: 160px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    right: 170px;

    bottom: -100px;

}


.deals-hero-content {

    position: relative;

    z-index: 2;

}


.deals-hero h1 {

    margin: 0;

    font-size: 38px;

    font-weight: 800;

}


.deals-hero h1 i {

    margin-right: 10px;

}


.deals-hero p {

    margin:
        10px 0 0;

    max-width: 650px;

    font-size: 16px;

    line-height: 1.6;

    color:
        rgba(255,255,255,.92);

}


/* =========================================================
   SECTION TITLE
========================================================= */

.deals-section-title {

    margin-bottom: 20px;

}


.deals-section-title h2 {

    margin: 0;

    color: #262626;

    font-size: 27px;

    font-weight: 800;

}


.deals-section-title p {

    margin:
        6px 0 0;

    color: #777;

    font-size: 14px;

}


/* =========================================================
   DEAL GRID
========================================================= */

.deals-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 24px;

}


/* =========================================================
   DEAL CARD
========================================================= */

.deal-card {

    position: relative;

    overflow: hidden;

    background: #fff;

    border-radius: 20px;

    border:
        1px solid #f0e5ed;

    box-shadow:
        0 8px 28px
        rgba(60, 30, 60, .07);

    transition:
        transform .25s ease,
        box-shadow .25s ease;

}


.deal-card:hover {

    transform:
        translateY(-6px);

    box-shadow:
        0 18px 40px
        rgba(100, 40, 100, .13);

}


/* =========================================================
   DEAL IMAGE
========================================================= */

.deal-image {

    position: relative;

    height: 205px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fff0f6,
            #f9e9f8
        );

}


.deal-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

    transition:
        transform .35s ease;

}


.deal-card:hover
.deal-image img {

    transform:
        scale(1.06);

}


.deal-placeholder {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #d44c9b;

    font-size: 55px;

}


/* =========================================================
   DISCOUNT BADGE
========================================================= */

.discount-badge {

    position: absolute;

    top: 14px;

    left: 14px;

    padding:
        8px 12px;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #ff416c,
            #c850c0
        );

    color: #fff;

    font-size: 13px;

    font-weight: 800;

    box-shadow:
        0 5px 15px
        rgba(190, 50, 130, .25);

}


/* =========================================================
   RATING
========================================================= */

.deal-rating {

    position: absolute;

    right: 12px;

    bottom: 12px;

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        7px 10px;

    border-radius: 20px;

    background: rgba(255,255,255,.96);

    color: #333;

    font-size: 12px;

    font-weight: 800;

    box-shadow:
        0 4px 12px
        rgba(0,0,0,.12);

}


.deal-rating i {

    color: #f5a623;

}


/* =========================================================
   DEAL CONTENT
========================================================= */

.deal-content {

    padding: 20px;

}


.deal-restaurant {

    margin-bottom: 7px;

    color: #b33c8b;

    font-size: 12px;

    font-weight: 700;

}


.deal-title {

    margin: 0;

    color: #222;

    font-size: 20px;

    font-weight: 800;

    line-height: 1.3;

}


.deal-description {

    margin:
        8px 0 15px;

    min-height: 42px;

    color: #777;

    font-size: 13px;

    line-height: 1.5;

}


/* =========================================================
   PRICE AREA
========================================================= */

.deal-price-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 17px;

    padding:
        12px 13px;

    border-radius: 12px;

    background:
        #fff7fb;

    border:
        1px solid #f7e4ef;

}


.deal-prices {

    display: flex;

    align-items: baseline;

    flex-wrap: wrap;

    gap: 7px;

}


.old-price {

    color: #999;

    font-size: 13px;

    text-decoration: line-through;

}


.new-price {

    color: #c43d91;

    font-size: 21px;

    font-weight: 800;

}


.save-price {

    flex-shrink: 0;

    padding:
        5px 8px;

    border-radius: 7px;

    background:
        #f2e4f0;

    color: #9e3c82;

    font-size: 11px;

    font-weight: 800;

}


/* =========================================================
   META
========================================================= */

.deal-meta {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 8px;

    margin-bottom: 17px;

}


.deal-meta-item {

    min-width: 0;

    display: flex;

    align-items: center;

    gap: 6px;

    padding:
        8px 9px;

    border-radius: 9px;

    background: #f8f8fa;

    color: #666;

    font-size: 11px;

    font-weight: 600;

    white-space: nowrap;

}


.deal-meta-item i {

    flex-shrink: 0;

    color: #c43d91;

}


.deal-meta-item span {

    overflow: hidden;

    text-overflow: ellipsis;

}


/* =========================================================
   BUTTON
========================================================= */

.deal-button {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding:
        12px 15px;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #ff416c,
            #c850c0
        );

    color: #fff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 700;

    transition:
        transform .2s ease,
        box-shadow .2s ease;

}


.deal-button:hover {

    color: #fff;

    transform:
        translateY(-1px);

    box-shadow:
        0 8px 18px
        rgba(196,61,145,.25);

}


/* =========================================================
   EMPTY
========================================================= */

.no-deals {

    padding:
        70px 20px;

    text-align: center;

    background: #fff;

    border-radius: 20px;

    border:
        1px solid #f0e5ed;

    box-shadow:
        0 8px 28px
        rgba(60,30,60,.06);

}


.no-deals i {

    margin-bottom: 15px;

    color: #c850a0;

    font-size: 55px;

}


.no-deals h2 {

    margin: 0;

    color: #222;

}


.no-deals p {

    margin-top: 8px;

    color: #777;

}


/* =========================================================
   FOOTER
========================================================= */

footer {

    margin-top: 0;

    background: #242024;

    color: #fff;

}


.footer-content {

    max-width: 1250px;

    margin: auto;

    padding:
        45px 20px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 30px;

}


.footer-column h3 {

    margin:
        0 0 15px;

    font-size: 17px;

}


.footer-column ul {

    list-style: none;

    padding: 0;

    margin: 0;

}


.footer-column li {

    margin-bottom: 9px;

}


.footer-column a {

    color: #bbb;

    text-decoration: none;

    font-size: 14px;

}


.footer-column a:hover {

    color: #ff5f91;

}


.social-icons {

    display: flex;

    gap: 10px;

    margin-top: 15px;

}


.social-icons a {

    width: 35px;

    height: 35px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #353035;

    color: #fff;

}


.social-icons a:hover {

    background:
        linear-gradient(
            135deg,
            #ff416c,
            #c850c0
        );

}


.copyright {

    border-top:
        1px solid #3d383d;

    text-align: center;

    padding:
        18px 15px;

    color: #aaa;

    font-size: 13px;

}


.copyright p {

    margin: 0;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .deals-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 650px) {

    .deals-page {

        padding:
            25px 12px 50px;

    }


    .deals-hero {

        padding:
            30px 25px;

        border-radius: 18px;

    }


    .deals-hero h1 {

        font-size: 29px;

    }


    .deals-hero p {

        font-size: 14px;

    }


    .deals-grid {

        grid-template-columns: 1fr;

        gap: 18px;

    }


    .deal-image {

        height: 220px;

    }


    .footer-content {

        grid-template-columns:
            1fr 1fr;

    }

}


@media (max-width: 420px) {

    .footer-content {

        grid-template-columns:
            1fr;

    }


    .deal-price-row {

        align-items: flex-start;

        flex-direction: column;

    }


    .deal-meta {

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

<header>

    <div class="top-bar">


        <a
            href="index.php"
            class="logo"
        >

            <i class="fas fa-utensils"></i>

            <h1>
                Humsafar
            </h1>

        </a>


        <div class="search-bar">

            <input
                type="text"
                placeholder="Search for restaurants or food..."
            >

            <i class="fas fa-search"></i>

        </div>


        <div class="user-actions">

            <a
                href="cart.php"
                id="cart-btn"
            >

                <i class="fas fa-shopping-cart"></i>

                Cart

            </a>


            <a
                href="login.php"
                class="sign-in"
            >

                Sign In

            </a>


            <a
                href="signup.php"
                class="sign-up"
            >

                Sign Up

            </a>

        </div>

    </div>


    <nav>

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
                <a
                    href="deals.php"
                    class="active"
                >
                    Deals
                </a>
            </li>

            <li>
                <a href="my-account.php">
                    My Account
                </a>
            </li>

            <li>
                <a href="#">
                    Help
                </a>
            </li>

            

        </ul>

    </nav>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="deals-page">


    <!-- HERO -->

    <section class="deals-hero">

        <div class="deals-hero-content">

            <h1>

                <i class="fas fa-tags"></i>

                Today's Best Deals

            </h1>

            <p>

                Save more on your favorite food.
                Explore special offers from restaurants
                available on Humsafar.

            </p>

        </div>

    </section>


    <!-- TITLE -->

    <div class="deals-section-title">

        <h2>
            Exclusive Food Deals
        </h2>

        <p>
            Grab your favorite meals at special prices.
        </p>

    </div>


    <?php if (!empty($deals)) { ?>


        <!-- DEALS -->

        <div class="deals-grid">


            <?php foreach ($deals as $deal) { ?>


                <?php

                $itemName =
                    $deal['item_name']
                    ?? 'Special Deal';

                $restaurantName =
                    $deal['restaurant_name']
                    ?? 'Restaurant';

                $description =
                    $deal['item_description']
                    ?? 'Delicious food at a special price.';

                $itemImage =
                    $deal['item_image']
                    ?? '';

                $rating =
                    (float)(
                        $deal['rating']
                        ?? 0
                    );

                $deliveryTime =
                    $deal['delivery_time']
                    ?? '';

                $deliveryFee =
                    (float)(
                        $deal['delivery_fee']
                        ?? 0
                    );

                $oldPrice =
                    (float)(
                        $deal['old_price']
                        ?? 0
                    );

                $dealPrice =
                    (float)(
                        $deal['deal_price']
                        ?? 0
                    );

                $discount =
                    (int)(
                        $deal['discount_percent']
                        ?? 0
                    );

                $saving =
                    $oldPrice - $dealPrice;

                ?>


                <article class="deal-card">


                    <!-- IMAGE -->

                    <div class="deal-image">


                        <?php if (!empty($itemImage)) { ?>

                            <img
                                src="assets/images/menu/<?php
                                    echo h($itemImage);
                                ?>"
                                alt="<?php
                                    echo h($itemName);
                                ?>"
                                loading="lazy"
                                onerror="
                                    this.onerror=null;
                                    this.src='assets/images/restaurants/<?php
                                        echo h(
                                            $deal['restaurant_image']
                                            ?? ''
                                        );
                                    ?>';
                                "
                            >

                        <?php } else { ?>

                            <div class="deal-placeholder">

                                <i class="fas fa-utensils"></i>

                            </div>

                        <?php } ?>


                        <div class="discount-badge">

                            <i class="fas fa-percent"></i>

                            <?php echo $discount; ?>% OFF

                        </div>


                        <div class="deal-rating">

                            <i class="fas fa-star"></i>

                            <?php
                                echo number_format(
                                    $rating,
                                    1
                                );
                            ?>

                        </div>


                    </div>


                    <!-- CONTENT -->

                    <div class="deal-content">


                        <div class="deal-restaurant">

                            <i class="fas fa-store"></i>

                            <?php
                                echo h(
                                    $restaurantName
                                );
                            ?>

                        </div>


                        <h3 class="deal-title">

                            <?php
                                echo h(
                                    $itemName
                                );
                            ?>

                        </h3>


                        <p class="deal-description">

                            <?php

                            if (
                                trim(
                                    $description
                                ) !== ''
                            ) {

                                echo h(
                                    $description
                                );

                            } else {

                                echo
                                    'Delicious food at a special price.';

                            }

                            ?>

                        </p>


                        <!-- PRICE -->

                        <div class="deal-price-row">


                            <div class="deal-prices">

                                <span class="old-price">

                                    Rs.
                                    <?php
                                        echo number_format(
                                            $oldPrice,
                                            0
                                        );
                                    ?>

                                </span>


                                <span class="new-price">

                                    Rs.
                                    <?php
                                        echo number_format(
                                            $dealPrice,
                                            0
                                        );
                                    ?>

                                </span>

                            </div>


                            <span class="save-price">

                                Save Rs.
                                <?php
                                    echo number_format(
                                        $saving,
                                        0
                                    );
                                ?>

                            </span>


                        </div>


                        <!-- META -->

                        <div class="deal-meta">


                            <div class="deal-meta-item">

                                <i
                                    class="fas fa-clock"
                                ></i>

                                <span>

                                    <?php
                                        echo h(
                                            $deliveryTime
                                        );
                                    ?>

                                </span>

                            </div>


                            <div class="deal-meta-item">

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


                        </div>


                        <!-- BUTTON -->

                        <a
                            href="restaurant.php?id=<?php
                                echo (int)
                                    $deal['restaurant_id'];
                            ?>"
                            class="deal-button"
                        >

                            <i
                                class="fas fa-utensils"
                            ></i>

                            View Restaurant

                        </a>


                    </div>


                </article>


            <?php } ?>


        </div>


    <?php } else { ?>


        <!-- EMPTY -->

        <div class="no-deals">

            <i
                class="fas fa-tags"
            ></i>

            <h2>
                No Deals Available
            </h2>

            <p>
                There are currently no food deals available.
            </p>

        </div>


    <?php } ?>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>


    <div class="footer-content">


        <div class="footer-column">

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


        <div class="footer-column">

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


        

        <div class="footer-column">

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


            <div class="social-icons">

                <a href="#">
                    <i class="fab fa-facebook"></i>
                </a>

                <a href="#">
                    <i class="fab fa-twitter"></i>
                </a>

                <a href="#">
                    <i class="fab fa-instagram"></i>
                </a>

                <a href="#">
                    <i class="fab fa-youtube"></i>
                </a>

            </div>

        </div>


    </div>


    <div class="copyright">

        <p>

            &copy;
            <?php echo date('Y'); ?>

            Humsafar Food Delivery.
            All rights reserved.

        </p>

    </div>


</footer>


</body>

</html>