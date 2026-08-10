<?php
/*
=================================================
    HUMSAFAR - JOIN HUMSAFAR PAGE
=================================================
*/

$page_title = "Join Humsafar";
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
        Join Humsafar
    </title>


    <!-- EXISTING HUMSAFAR CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >

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

/* =================================================
   GLOBAL
================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f7f7fb;

    color: #333;

    font-family:
        'Segoe UI',
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;
}


/* =================================================
   MAIN
================================================= */

.join-page {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        35px 20px 65px;
}


/* =================================================
   HERO
================================================= */

.join-hero {

    position: relative;

    min-height: 330px;

    overflow: hidden;

    border-radius: 24px;

    display: flex;

    align-items: center;

    margin-bottom: 42px;

    background:

        linear-gradient(
            90deg,
            rgba(237, 0, 56, .96),
            rgba(244, 63, 120, .88),
            rgba(255, 91, 145, .30)
        ),

        url("https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1500&q=85");

    background-size: cover;

    background-position: center;

    box-shadow:
        0 12px 35px
        rgba(237, 0, 56, .18);
}


.join-hero-content {

    position: relative;

    z-index: 2;

    max-width: 650px;

    padding:
        45px;

    color: #fff;
}


.join-hero-content h1 {

    margin: 0 0 12px;

    font-size: 42px;

    line-height: 1.15;

    font-weight: 800;
}


.join-hero-content p {

    margin: 0;

    max-width: 600px;

    color:
        rgba(255,255,255,.94);

    font-size: 16px;

    line-height: 1.7;
}


.hero-small-text {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 16px;

    padding:
        8px 14px;

    border-radius: 30px;

    background:
        rgba(255,255,255,.16);

    border:
        1px solid
        rgba(255,255,255,.25);

    font-size: 13px;

    font-weight: 700;
}


.hero-small-text i {

    color: #fff;
}


/* =================================================
   SECTION TITLE
================================================= */

.join-section-title {

    text-align: center;

    margin-bottom: 28px;
}


.join-section-title h2 {

    margin: 0;

    color: #292929;

    font-size: 28px;

    font-weight: 800;
}


.join-section-title p {

    margin:
        7px 0 0;

    color: #777;

    font-size: 14px;
}


/* =================================================
   ACCOUNT GRID
================================================= */

.account-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 24px;
}


/* =================================================
   ACCOUNT CARD
================================================= */

.account-card {

    overflow: hidden;

    background: #fff;

    border:
        1px solid #f3dce5;

    border-radius: 20px;

    box-shadow:
        0 8px 28px
        rgba(60, 20, 35, .07);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.account-card:hover {

    transform:
        translateY(-6px);

    box-shadow:
        0 16px 38px
        rgba(60, 20, 35, .12);
}


/* =================================================
   CARD IMAGE
================================================= */

.account-image {

    position: relative;

    height: 165px;

    overflow: hidden;
}


.account-image img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: cover;

    transition:
        transform .4s ease;
}


.account-card:hover
.account-image img {

    transform:
        scale(1.06);
}


.account-image-overlay {

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            to top,
            rgba(0,0,0,.42),
            transparent 70%
        );
}


.account-image-icon {

    position: absolute;

    left: 18px;

    bottom: 15px;

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #ff4f8b,
            #ed0038
        );

    color: #fff;

    font-size: 21px;

    box-shadow:
        0 6px 16px
        rgba(237, 0, 56, .22);
}


/* =================================================
   CARD BODY
================================================= */

.account-card-body {

    padding:
        22px;
}


.account-card h3 {

    margin:
        0 0 8px;

    color: #292929;

    font-size: 21px;

    font-weight: 800;
}


.account-card-description {

    min-height: 63px;

    margin:
        0 0 19px;

    color: #777;

    font-size: 13px;

    line-height: 1.6;
}


/* =================================================
   FEATURES
================================================= */

.account-features {

    margin-bottom: 20px;
}


.account-feature {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 8px;

    color: #555;

    font-size: 12.5px;
}


.account-feature i {

    width: 16px;

    color: #ed0038;
}


/* =================================================
   BUTTONS
================================================= */

.account-buttons {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 9px;
}


.account-btn {

    min-height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding:
        9px 10px;

    border-radius: 9px;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    transition:
        all .2s ease;
}


.login-btn {

    color: #ed0038;

    background: #fff;

    border:
        1px solid #f1b8c9;
}


.login-btn:hover {

    background: #fff0f4;

    border-color: #ed0038;
}


.register-btn {

    color: #fff;

    border: none;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f43f78
        );
}


.register-btn:hover {

    box-shadow:
        0 6px 16px
        rgba(237, 0, 56, .24);

    transform:
        translateY(-1px);
}


/* =================================================
   PROMOTION SECTION
================================================= */

.promotion-section {

    position: relative;

    overflow: hidden;

    margin-top: 55px;

    padding:
        45px 40px;

    border-radius: 22px;

    background:

        linear-gradient(
            100deg,
            rgba(237, 0, 56, .97),
            rgba(244, 63, 120, .92)
        ),

        url("https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=1400&q=80");

    background-size: cover;

    background-position: center;

    color: #fff;
}


.promotion-content {

    position: relative;

    z-index: 2;

    max-width: 650px;
}


.promotion-content h2 {

    margin:
        0 0 10px;

    font-size: 28px;

    font-weight: 800;
}


.promotion-content p {

    margin: 0;

    color:
        rgba(255,255,255,.92);

    font-size: 14px;

    line-height: 1.7;
}


/* =================================================
   BENEFITS
================================================= */

.why-humsafar {

    margin-top: 58px;
}


.benefits-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 17px;
}


.benefit-card {

    padding:
        24px 17px;

    text-align: center;

    background: #fff;

    border:
        1px solid #f3dce5;

    border-radius: 15px;
}


.benefit-card i {

    display: flex;

    align-items: center;

    justify-content: center;

    width: 48px;

    height: 48px;

    margin:
        0 auto 13px;

    border-radius: 14px;

    color: #ed0038;

    background: #ffe8ef;

    font-size: 21px;
}


.benefit-card h4 {

    margin:
        0 0 7px;

    color: #333;

    font-size: 15px;
}


.benefit-card p {

    margin: 0;

    color: #777;

    font-size: 12px;

    line-height: 1.5;
}


/* =================================================
   FOOTER
================================================= */

footer {

    margin-top: 0;

    background: #222;

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

    color: #ff4f8b;
}


.social-icons {

    display: flex;

    gap: 9px;

    margin-top: 15px;
}


.social-icons a {

    width: 35px;

    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #333;

    color: #fff;
}


.social-icons a:hover {

    background: #ed0038;
}


.copyright {

    border-top:
        1px solid #444;

    text-align: center;

    padding:
        18px 15px;

    color: #aaa;

    font-size: 13px;
}


.copyright p {

    margin: 0;
}


/* =================================================
   TABLET
================================================= */

@media (max-width: 1000px) {

    .account-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .benefits-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


/* =================================================
   MOBILE
================================================= */

@media (max-width: 700px) {

    .join-page {

        padding:
            25px 13px 50px;

    }


    .join-hero {

        min-height: 360px;

        align-items: flex-end;

        border-radius: 18px;

        background-position:
            center;

    }


    .join-hero-content {

        padding:
            30px 22px;

    }


    .join-hero-content h1 {

        font-size: 31px;

    }


    .join-hero-content p {

        font-size: 14px;

    }


    .account-grid {

        grid-template-columns: 1fr;

        gap: 18px;

    }


    .promotion-section {

        padding:
            35px 24px;

        border-radius: 18px;

    }


    .promotion-content h2 {

        font-size: 24px;

    }


    .benefits-grid {

        grid-template-columns:
            1fr 1fr;

    }

}


@media (max-width: 430px) {

    .benefits-grid {

        grid-template-columns: 1fr;

    }


    .account-buttons {

        grid-template-columns: 1fr;

    }

}
</style>

</head>


<body>


<!-- =================================================
     HEADER
================================================= -->

<!--
    Aapki existing website ka header yahan use hoga.
    Agar header already common file se aa raha hai
    to usko remove/change nahi karna.
-->


<!-- =================================================
     MAIN
================================================= -->

<main class="join-page">


    <!-- =================================================
         HERO
    ================================================= -->

    <section class="join-hero">

        <div class="join-hero-content">


            <div class="hero-small-text">

                <i class="fas fa-heart"></i>

                Everything starts with Humsafar

            </div>


            <h1>

                Welcome to Humsafar

            </h1>


            <p>

                Order your favourite food, grow your
                restaurant or join our delivery team.
                Humsafar brings customers, restaurants
                and riders together on one platform.

            </p>


        </div>

    </section>



    <!-- =================================================
         ACCOUNT OPTIONS
    ================================================= -->

    <section>


        <div class="join-section-title">

            <h2>
                Get Started with Humsafar
            </h2>

            <p>
                Choose the account that matches you
            </p>

        </div>



        <div class="account-grid">


            <!-- =================================================
                 CUSTOMER
            ================================================= -->

            <div class="account-card">


                <div class="account-image">

                    <img
                        src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85"
                        alt="Delicious food"
                    >

                    <div class="account-image-overlay"></div>


                    <div class="account-image-icon">

                        <i class="fas fa-user"></i>

                    </div>

                </div>


                <div class="account-card-body">


                    <h3>
                        Customer
                    </h3>


                    <p class="account-card-description">

                        Discover restaurants, explore
                        delicious meals and get your
                        favourite food delivered to your door.

                    </p>


                    <div class="account-features">

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Browse Restaurants
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Explore Deals & Offers
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Place & Track Orders
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Manage Your Account
                        </div>

                    </div>


                    <div class="account-buttons">


                        <a
                            href="login.php"
                            class="account-btn login-btn"
                        >

                            <i class="fas fa-right-to-bracket"></i>

                            Login

                        </a>


                        <a
                            href="signup.php"
                            class="account-btn register-btn"
                        >

                            <i class="fas fa-user-plus"></i>

                            Register

                        </a>


                    </div>


                </div>

            </div>



            <!-- =================================================
                 RESTAURANT OWNER
            ================================================= -->

            <div class="account-card">


                <div class="account-image">

                    <img
                        src="https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=85"
                        alt="Restaurant"
                    >

                    <div class="account-image-overlay"></div>


                    <div class="account-image-icon">

                        <i class="fas fa-store"></i>

                    </div>

                </div>


                <div class="account-card-body">


                    <h3>
                        Restaurant Owner
                    </h3>


                    <p class="account-card-description">

                        Bring your restaurant online,
                        reach more customers and manage
                        your food business with Humsafar.

                    </p>


                    <div class="account-features">

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Register Your Restaurant
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Add Food Items
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Create Deals & Offers
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Manage Restaurant Orders
                        </div>

                    </div>


                    <div class="account-buttons">


                        <a
                            href="restaurant-owner-login.php"
                            class="account-btn login-btn"
                        >

                            <i class="fas fa-right-to-bracket"></i>

                            Login

                        </a>


                        <a
                            href="restaurant-owner-register.php"
                            class="account-btn register-btn"
                        >

                            <i class="fas fa-store"></i>

                            Register

                        </a>


                    </div>


                </div>

            </div>



            <!-- =================================================
                 RIDER
            ================================================= -->

            <div class="account-card">


                <div class="account-image">

                    <img
                        src="https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=85"
                        alt="Delivery rider"
                    >

                    <div class="account-image-overlay"></div>


                    <div class="account-image-icon">

                        <i class="fas fa-motorcycle"></i>

                    </div>

                </div>


                <div class="account-card-body">


                    <h3>
                        Delivery Rider
                    </h3>


                    <p class="account-card-description">

                        Join the Humsafar delivery team,
                        deliver orders and earn while
                        working with flexibility.

                    </p>


                    <div class="account-features">

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Become a Humsafar Rider
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Receive Delivery Requests
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Manage Your Deliveries
                        </div>

                        <div class="account-feature">
                            <i class="fas fa-check-circle"></i>
                            Track Your Earnings
                        </div>

                    </div>


                    <div class="account-buttons">


                        <a
                            href="rider/rider-login.php"
                            class="account-btn login-btn"
                        >

                            <i class="fas fa-right-to-bracket"></i>

                            Login

                        </a>


                        <a
                            href="rider/rider-register.php"
                            class="account-btn register-btn"
                        >

                            <i class="fas fa-motorcycle"></i>

                            Register

                        </a>


                    </div>


                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         PROMOTIONAL FOOD SECTION
    ================================================= -->

    <section class="promotion-section">


        <div class="promotion-content">


            <h2>

                Great Food.
                Great Restaurants.
                One Humsafar.

            </h2>


            <p>

                Whether you are looking for your next
                favourite meal, want to grow your
                restaurant business, or are ready to
                become a delivery rider, Humsafar is
                built to connect you with the right
                opportunities.

            </p>


        </div>


    </section>



    <!-- =================================================
         WHY HUMSAFAR
    ================================================= -->

    <section class="why-humsafar">


        <div class="join-section-title">

            <h2>
                Why Choose Humsafar?
            </h2>

            <p>
                One platform connecting everyone
            </p>

        </div>


        <div class="benefits-grid">


            <div class="benefit-card">

                <i class="fas fa-utensils"></i>

                <h4>
                    Delicious Food
                </h4>

                <p>
                    Discover restaurants and
                    delicious meals near you.
                </p>

            </div>


            <div class="benefit-card">

                <i class="fas fa-bolt"></i>

                <h4>
                    Easy Ordering
                </h4>

                <p>
                    Order your favourite food
                    quickly and easily.
                </p>

            </div>


            <div class="benefit-card">

                <i class="fas fa-store"></i>

                <h4>
                    Grow Your Restaurant
                </h4>

                <p>
                    Reach more customers and
                    manage your restaurant online.
                </p>

            </div>


            <div class="benefit-card">

                <i class="fas fa-motorcycle"></i>

                <h4>
                    Earn with Humsafar
                </h4>

                <p>
                    Deliver orders and build your
                    earning opportunity with Humsafar.
                </p>

            </div>


        </div>


    </section>


</main>



<!-- =================================================
     FOOTER
================================================= -->

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
                For Restaurants
            </h3>

            <ul>

                <li>
                    <a href="restaurant-owner-register.php">
                        Add Restaurant
                    </a>
                </li>

                <li>
                    <a href="restaurant-owner-login.php">
                        Business Login
                    </a>
                </li>

                <li>
                    <a href="#">
                        Restaurant Widgets
                    </a>
                </li>

                <li>
                    <a href="#">
                        Products for Businesses
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

                <li>
                    <a href="join-humsafar.php">
                        Partner with us
                    </a>
                </li>

                <li>
                    <a href="join-humsafar.php">
                        Ride with us
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