<?php

require_once 'includes/session.php';
require_once 'includes/config.php';


/* =====================================================
   VARIABLES
===================================================== */

$error = "";
$success = "";


/* =====================================================
   HANDLE LOGIN
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email =
        trim(
            $_POST['email'] ?? ''
        );

    $password =
        $_POST['password'] ?? '';


    /* =================================================
       VALIDATION
    ================================================= */

    if ($email === '') {

        $error =
            "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            "Please enter a valid email address.";

    } elseif ($password === '') {

        $error =
            "Please enter your password.";

    } else {


        /* =================================================
           FIND RESTAURANT OWNER
           ONLY FROM restaurant_users
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                email,
                phone,
                password,
                status
            FROM restaurant_users
            WHERE email = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error =
                "Database error. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $owner =
                $result->fetch_assoc();

            $stmt->close();


            /* =================================================
               CHECK ACCOUNT
            ================================================= */

            if (!$owner) {

                $error =
                    "Account not found. Please check your email address.";

            } elseif ($owner['status'] !== 'active') {

                $error =
                    "Your restaurant owner account is currently inactive or blocked.";

            } elseif (
                !password_verify(
                    $password,
                    $owner['password']
                )
            ) {

                $error =
                    "Incorrect password. Please try again.";

            } else {


                /* =================================================
                   LOGIN SUCCESS
                ================================================= */

                $_SESSION['restaurant_owner_id'] =
                    $owner['id'];

                $_SESSION['restaurant_owner_name'] =
                    $owner['full_name'];

                $_SESSION['restaurant_owner_email'] =
                    $owner['email'];

                $_SESSION['restaurant_owner_phone'] =
                    $owner['phone'];

                $_SESSION['restaurant_owner_logged_in'] =
                    true;


                /*
                 * Redirect to Restaurant Owner Dashboard
                 */

                header(
                    "Location: restaurant-owner-dashboard.php"
                );

                exit;

            }

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
        Restaurant Owner Login - Humsafar
    </title>


    <!-- Font Awesome -->

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


        html,
        body {

            margin: 0;

            padding: 0;

            min-height: 100%;

        }


        body {

            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            background:
                #fff8fb;

            color:
                #292929;

        }


        a {
            text-decoration: none;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .owner-login-page {

            min-height: 100vh;

            display:
                flex;

            flex-direction:
                column;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .owner-header {

            height:
                70px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 6%;

            background:
                #ffffff;

            border-top:
                3px solid #ef0038;

            border-bottom:
                1px solid #eeeeee;

        }


        .owner-logo {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            color:
                #ed0038;

            font-size:
                23px;

            font-weight:
                800;

        }


        .owner-logo i {

            font-size:
                22px;

        }


        .back-link {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            color:
                #d90035;

            font-size:
                13px;

            font-weight:
                700;

            padding:
                9px 15px;

            border:
                1px solid #f1c8d7;

            border-radius:
                9px;

            transition:
                .2s ease;

        }


        .back-link:hover {

            background:
                #fff0f5;

            border-color:
                #ed0038;

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .owner-main {

            flex:
                1;

            width:
                100%;

            padding:
                45px 20px 60px;

        }


        .owner-wrapper {

            max-width:
                1100px;

            min-height:
                590px;

            margin:
                0 auto;

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            overflow:
                hidden;

            background:
                #ffffff;

            border-radius:
                24px;

            box-shadow:
                0 15px 45px
                rgba(80, 25, 50, .10);

        }


        /* =====================================================
           LEFT PROMOTION
        ===================================================== */

        .owner-promo {

            position:
                relative;

            overflow:
                hidden;

            display:
                flex;

            align-items:
                center;

            background:

                linear-gradient(
                    135deg,
                    rgba(239, 0, 56, .96),
                    rgba(255, 72, 132, .90)
                ),

                url(
                    "https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1000&q=85"
                );

            background-size:
                cover;

            background-position:
                center;

            color:
                #ffffff;

        }


        .owner-promo::after {

            content:
                "";

            position:
                absolute;

            inset:
                0;

            background:
                linear-gradient(
                    135deg,
                    rgba(239, 0, 56, .90),
                    rgba(255, 73, 133, .62)
                );

        }


        .promo-content {

            position:
                relative;

            z-index:
                2;

            padding:
                48px;

        }


        .promo-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                8px 14px;

            margin-bottom:
                18px;

            border:
                1px solid
                rgba(255,255,255,.28);

            border-radius:
                30px;

            background:
                rgba(255,255,255,.15);

            font-size:
                12px;

            font-weight:
                700;

        }


        .promo-content h1 {

            margin:
                0 0 15px;

            font-size:
                40px;

            line-height:
                1.15;

            font-weight:
                800;

        }


        .promo-content p {

            margin:
                0 0 25px;

            max-width:
                450px;

            color:
                rgba(255,255,255,.94);

            font-size:
                15px;

            line-height:
                1.7;

        }


        .promo-features {

            display:
                grid;

            gap:
                12px;

        }


        .promo-feature {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;

            font-size:
                13px;

            font-weight:
                600;

        }


        .promo-feature i {

            width:
                30px;

            height:
                30px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                9px;

            background:
                rgba(255,255,255,.16);

        }


        /* =====================================================
           LOGIN SIDE
        ===================================================== */

        .owner-form-side {

            padding:
                42px;

            display:
                flex;

            align-items:
                center;

            background:
                #ffffff;

        }


        .owner-form-container {

            width:
                100%;

            max-width:
                470px;

            margin:
                0 auto;

        }


        .form-heading {

            margin-bottom:
                25px;

        }


        .form-heading h2 {

            margin:
                0 0 7px;

            color:
                #292929;

            font-size:
                28px;

            font-weight:
                800;

        }


        .form-heading p {

            margin:
                0;

            color:
                #777777;

            font-size:
                13px;

            line-height:
                1.6;

        }


        /* =====================================================
           ERROR
        ===================================================== */

        .message {

            padding:
                12px 14px;

            margin-bottom:
                18px;

            border-radius:
                9px;

            font-size:
                13px;

            line-height:
                1.5;

        }


        .error-message {

            color:
                #a40027;

            background:
                #fff0f3;

            border:
                1px solid #ffc3d1;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .owner-form {

            width:
                100%;

        }


        .form-group {

            margin-bottom:
                18px;

        }


        .form-group label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                #333333;

            font-size:
                12.5px;

            font-weight:
                700;

        }


        .input-wrapper {

            position:
                relative;

        }


        .input-wrapper i {

            position:
                absolute;

            left:
                14px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #e4003b;

            font-size:
                14px;

            pointer-events:
                none;

        }


        .form-control {

            width:
                100%;

            height:
                47px;

            padding:
                0 14px 0 42px;

            border:
                1px solid #dddddd;

            border-radius:
                9px;

            outline:
                none;

            background:
                #ffffff;

            color:
                #333333;

            font-size:
                13px;

            transition:
                .2s ease;

        }


        .form-control:focus {

            border-color:
                #ef174b;

            box-shadow:
                0 0 0 3px
                rgba(239, 23, 75, .08);

        }


        /* =====================================================
           PASSWORD ROW
        ===================================================== */

        .password-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin-bottom:
                20px;

        }


        .remember-me {

            display:
                flex;

            align-items:
                center;

            gap:
                7px;

            color:
                #777777;

            font-size:
                11.5px;

        }


        .remember-me input {

            accent-color:
                #ed0038;

        }


        .forgot-password {

            color:
                #df0038;

            font-size:
                11.5px;

            font-weight:
                700;

        }


        .forgot-password:hover {

            text-decoration:
                underline;

        }


        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        .login-button {

            width:
                100%;

            height:
                47px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            border:
                none;

            border-radius:
                9px;

            color:
                #ffffff;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            font-size:
                13px;

            font-weight:
                800;

            cursor:
                pointer;

            box-shadow:
                0 7px 18px
                rgba(237,0,56,.18);

            transition:
                .2s ease;

        }


        .login-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 24px
                rgba(237,0,56,.25);

        }


        /* =====================================================
           REGISTER LINK
        ===================================================== */

        .register-text {

            margin:
                20px 0 0;

            text-align:
                center;

            color:
                #777777;

            font-size:
                12.5px;

        }


        .register-text a {

            color:
                #e00038;

            font-weight:
                800;

        }


        /* =====================================================
           CUSTOMER LOGIN
        ===================================================== */

        .customer-login {

            margin-top:
                25px;

            padding-top:
                20px;

            border-top:
                1px solid #eeeeee;

            text-align:
                center;

        }


        .customer-login p {

            margin:
                0 0 8px;

            color:
                #999999;

            font-size:
                11px;

        }


        .customer-login a {

            color:
                #555555;

            font-size:
                12px;

            font-weight:
                700;

        }


        .customer-login a:hover {

            color:
                #e00038;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .owner-footer {

            padding:
                18px 20px;

            text-align:
                center;

            background:
                #29232a;

            color:
                #aaa;

            font-size:
                12px;

        }


        .owner-footer strong {

            color:
                #ffffff;

        }


        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 900px) {

            .owner-wrapper {

                grid-template-columns:
                    1fr;

            }


            .owner-promo {

                min-height:
                    350px;

            }


            .promo-content {

                padding:
                    40px;

            }


            .owner-form-side {

                padding:
                    40px;

            }

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 600px) {

            .owner-header {

                height:
                    62px;

                padding:
                    0 18px;

            }


            .owner-logo {

                font-size:
                    20px;

            }


            .back-link {

                padding:
                    8px 10px;

                font-size:
                    11px;

            }


            .owner-main {

                padding:
                    20px 12px 35px;

            }


            .owner-wrapper {

                border-radius:
                    17px;

            }


            .owner-promo {

                min-height:
                    330px;

            }


            .promo-content {

                padding:
                    30px 24px;

            }


            .promo-content h1 {

                font-size:
                    30px;

            }


            .promo-content p {

                font-size:
                    13px;

            }


            .owner-form-side {

                padding:
                    30px 22px;

            }


            .form-heading h2 {

                font-size:
                    24px;

            }


            .password-row {

                align-items:
                    flex-start;

                gap:
                    10px;

            }

        }

    </style>

</head>


<body>


<div class="owner-login-page">


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="owner-header">


        <a
            href="join-humsafar.php"
            class="owner-logo"
        >

            <i class="fas fa-utensils"></i>

            <span>Humsafar</span>

        </a>


        <a
            href="join-humsafar.php"
            class="back-link"
        >

            <i class="fas fa-arrow-left"></i>

            Back to Humsafar

        </a>


    </header>



    <!-- =====================================================
         MAIN
    ===================================================== -->

    <main class="owner-main">


        <div class="owner-wrapper">


            <!-- =================================================
                 PROMOTION
            ================================================= -->

            <section class="owner-promo">


                <div class="promo-content">


                    <div class="promo-badge">

                        <i class="fas fa-store"></i>

                        Restaurant Partner

                    </div>


                    <h1>

                        Welcome Back, Restaurant Owner

                    </h1>


                    <p>

                        Login to your Humsafar restaurant
                        account and manage your restaurant,
                        menu, deals and orders from one place.

                    </p>


                    <div class="promo-features">


                        <div class="promo-feature">

                            <i class="fas fa-store"></i>

                            <span>
                                Manage your restaurant
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-utensils"></i>

                            <span>
                                Manage food items and menu
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-tags"></i>

                            <span>
                                Manage your deals and offers
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-receipt"></i>

                            <span>
                                Manage customer orders
                            </span>

                        </div>


                    </div>


                </div>


            </section>



            <!-- =================================================
                 LOGIN FORM
            ================================================= -->

            <section class="owner-form-side">


                <div class="owner-form-container">


                    <div class="form-heading">

                        <h2>

                            Restaurant Owner Login

                        </h2>


                        <p>

                            Login to continue to your restaurant
                            owner dashboard.

                        </p>

                    </div>



                    <!-- ERROR MESSAGE -->

                    <?php if ($error !== ''): ?>

                        <div class="message error-message">

                            <i class="fas fa-circle-exclamation"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>



                    <form
                        method="POST"
                        action=""
                        class="owner-form"
                    >


                        <!-- EMAIL -->

                        <div class="form-group">


                            <label for="email">

                                Email Address

                            </label>


                            <div class="input-wrapper">


                                <i class="fas fa-envelope"></i>


                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="Enter your email address"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    autocomplete="email"
                                    required
                                >


                            </div>


                        </div>



                        <!-- PASSWORD -->

                        <div class="form-group">


                            <label for="password">

                                Password

                            </label>


                            <div class="input-wrapper">


                                <i class="fas fa-lock"></i>


                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >


                            </div>


                        </div>



                        <!-- PASSWORD OPTIONS -->

                        <div class="password-row">


                            <label class="remember-me">

                                <input
                                    type="checkbox"
                                    name="remember"
                                >

                                Remember me

                            </label>


                            <a
                                href="#"
                                class="forgot-password"
                            >

                                Forgot Password?

                            </a>


                        </div>



                        <!-- LOGIN BUTTON -->

                        <button
                            type="submit"
                            class="login-button"
                        >

                            <i class="fas fa-right-to-bracket"></i>

                            Login to Dashboard

                        </button>


                    </form>



                    <!-- REGISTER -->

                    <div class="register-text">

                        Don't have a Restaurant Owner account?

                        <a
                            href="restaurant-owner-register.php"
                        >

                            Register Now

                        </a>

                    </div>



                    <!-- CUSTOMER LOGIN -->

                    <div class="customer-login">

                        <p>

                            Are you a customer?

                        </p>


                        <a href="login.php">

                            <i class="fas fa-user"></i>

                            Customer Login

                        </a>

                    </div>


                </div>


            </section>


        </div>


    </main>



    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="owner-footer">

        <strong>Humsafar</strong>
        Food Delivery

        &nbsp;•&nbsp;

        © <?= date('Y') ?>

    </footer>


</div>


</body>

</html>