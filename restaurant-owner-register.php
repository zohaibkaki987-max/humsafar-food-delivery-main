<?php

require_once 'includes/session.php';
require_once 'includes/config.php';


/* =====================================================
   VARIABLES
===================================================== */

$error = "";
$success = "";


/* =====================================================
   HANDLE REGISTRATION
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name =
        trim(
            $_POST['full_name'] ?? ''
        );

    $email =
        trim(
            $_POST['email'] ?? ''
        );

    $phone =
        trim(
            $_POST['phone'] ?? ''
        );

    $password =
        $_POST['password'] ?? '';

    $confirm_password =
        $_POST['confirm_password'] ?? '';


    /* =================================================
       VALIDATION
    ================================================= */

    if ($full_name === '') {

        $error =
            "Please enter your full name.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            "Please enter a valid email address.";

    } elseif ($phone === '') {

        $error =
            "Please enter your phone number.";

    } elseif (strlen($password) < 6) {

        $error =
            "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error =
            "Passwords do not match.";

    } else {


        /* =================================================
           CHECK EMAIL IN RESTAURANT USERS
        ================================================= */

        $stmt = $conn->prepare("
            SELECT id
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

            $existing_email =
                $result->fetch_assoc();

            $stmt->close();


            if ($existing_email) {

                $error =
                    "This email address is already registered.";

            } else {


                /* =================================================
                   CHECK PHONE IN RESTAURANT USERS
                ================================================= */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM restaurant_users
                    WHERE phone = ?
                    LIMIT 1
                ");


                if (!$stmt) {

                    $error =
                        "Database error. Please try again.";

                } else {

                    $stmt->bind_param(
                        "s",
                        $phone
                    );

                    $stmt->execute();

                    $result =
                        $stmt->get_result();

                    $existing_phone =
                        $result->fetch_assoc();

                    $stmt->close();


                    if ($existing_phone) {

                        $error =
                            "This phone number is already registered.";

                    } else {


                        /* =================================================
                           HASH PASSWORD
                        ================================================= */

                        $hashed_password =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );


                        /* =================================================
                           CREATE RESTAURANT OWNER ACCOUNT
                        ================================================= */

                        $stmt = $conn->prepare("
                            INSERT INTO restaurant_users
                            (
                                full_name,
                                email,
                                phone,
                                password,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'active'
                            )
                        ");


                        if (!$stmt) {

                            $error =
                                "Unable to create account. Please try again.";

                        } else {

                            $stmt->bind_param(
                                "ssss",
                                $full_name,
                                $email,
                                $phone,
                                $hashed_password
                            );


                            if ($stmt->execute()) {

                                $success =
                                    "Restaurant Owner account created successfully.";


                                /*
                                 * Redirect to owner login
                                 * after 2 seconds.
                                 */

                                header(
                                    "Refresh: 2; URL=restaurant-owner-login.php"
                                );

                            } else {

                                $error =
                                    "Unable to create your account. Please try again.";

                            }


                            $stmt->close();

                        }

                    }

                }

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
        Restaurant Owner Registration - Humsafar
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

        .owner-register-page {

            min-height: 100vh;

            display: flex;

            flex-direction: column;

        }


        /* =====================================================
           TOP HEADER
        ===================================================== */

        .owner-header {

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: space-between;

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

            display: flex;

            align-items: center;

            gap: 9px;

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

            display: inline-flex;

            align-items: center;

            gap: 8px;

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

            flex: 1;

            width: 100%;

            padding:
                45px 20px 60px;

        }


        .owner-wrapper {

            max-width:
                1100px;

            margin:
                0 auto;

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            min-height:
                610px;

            background:
                #ffffff;

            border-radius:
                24px;

            overflow:
                hidden;

            box-shadow:
                0 15px 45px
                rgba(80, 25, 50, .10);

        }


        /* =====================================================
           LEFT PROMOTION PANEL
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
           FORM SIDE
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

            font-size:
                27px;

            font-weight:
                800;

            color:
                #292929;

        }


        .form-heading p {

            margin:
                0;

            color:
                #777777;

            font-size:
                13px;

        }


        /* =====================================================
           MESSAGES
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


        .success-message {

            color:
                #08743a;

            background:
                #effff6;

            border:
                1px solid #bcebd0;

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
                17px;

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
                46px;

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


        .password-note {

            margin:
                6px 0 0;

            color:
                #888888;

            font-size:
                11px;

        }


        /* =====================================================
           TERMS
        ===================================================== */

        .terms-row {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            margin:
                5px 0 20px;

            color:
                #777777;

            font-size:
                11.5px;

            line-height:
                1.5;

        }


        .terms-row input {

            margin-top:
                3px;

            accent-color:
                #ed0038;

        }


        .terms-row a {

            color:
                #df0038;

            font-weight:
                700;

        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .register-button {

            width:
                100%;

            height:
                47px;

            border:
                none;

            border-radius:
                9px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

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


        .register-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 24px
                rgba(237,0,56,.25);

        }


        /* =====================================================
           LOGIN LINK
        ===================================================== */

        .login-text {

            text-align:
                center;

            margin:
                20px 0 0;

            color:
                #777777;

            font-size:
                12.5px;

        }


        .login-text a {

            color:
                #e00038;

            font-weight:
                800;

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

        }

    </style>

</head>


<body>


<div class="owner-register-page">


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

                        Grow Your Restaurant With Humsafar

                    </h1>


                    <p>

                        Join Humsafar and bring your restaurant
                        to more customers. Manage your restaurant,
                        menu items, deals and orders from one place.

                    </p>


                    <div class="promo-features">


                        <div class="promo-feature">

                            <i class="fas fa-store"></i>

                            <span>
                                Add and manage your restaurant
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-utensils"></i>

                            <span>
                                Add your food items and prices
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-tags"></i>

                            <span>
                                Create special deals and offers
                            </span>

                        </div>


                        <div class="promo-feature">

                            <i class="fas fa-chart-line"></i>

                            <span>
                                Manage orders and grow your business
                            </span>

                        </div>


                    </div>


                </div>


            </section>



            <!-- =================================================
                 REGISTRATION FORM
            ================================================= -->

            <section class="owner-form-side">


                <div class="owner-form-container">


                    <div class="form-heading">

                        <h2>

                            Create Restaurant Owner Account

                        </h2>


                        <p>

                            Register your account to start managing
                            your restaurant on Humsafar.

                        </p>

                    </div>



                    <!-- ERROR -->

                    <?php if ($error !== ''): ?>

                        <div class="message error-message">

                            <i class="fas fa-circle-exclamation"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>



                    <!-- SUCCESS -->

                    <?php if ($success !== ''): ?>

                        <div class="message success-message">

                            <i class="fas fa-circle-check"></i>

                            <?= htmlspecialchars($success) ?>

                        </div>

                    <?php endif; ?>



                    <form
                        method="POST"
                        action=""
                        class="owner-form"
                    >


                        <!-- FULL NAME -->

                        <div class="form-group">


                            <label for="full_name">

                                Full Name

                            </label>


                            <div class="input-wrapper">


                                <i class="fas fa-user"></i>


                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="form-control"
                                    placeholder="Enter your full name"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                    required
                                >


                            </div>


                        </div>
                        <div class="form-group">    
                        <label for="restaurant_name">
                            
                            Restaurant Name
                        </label>

                            <div class="input-wrapper">

                         <i class="fas fa-store"></i>

                        <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="form-control"
                                    placeholder="Restaurant Name"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                    required
                                >
    </div>
</div>



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
                                    required
                                >


                            </div>


                        </div>



                        <!-- PHONE -->

                        <div class="form-group">


                            <label for="phone">

                                Phone Number

                            </label>


                            <div class="input-wrapper">


                                <i class="fas fa-phone"></i>


                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    placeholder="Enter your phone number"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
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
                                    placeholder="Create a password"
                                    required
                                >


                            </div>


                            <p class="password-note">

                                Password must be at least 6 characters.

                            </p>


                        </div>



                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">


                            <label for="confirm_password">

                                Confirm Password

                            </label>


                            <div class="input-wrapper">


                                <i class="fas fa-shield-halved"></i>


                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="form-control"
                                    placeholder="Confirm your password"
                                    required
                                >


                            </div>


                        </div>



                        <!-- TERMS -->

                        <div class="terms-row">


                            <input
                                type="checkbox"
                                id="terms"
                                name="terms"
                                required
                            >


                            <label for="terms">

                                I agree to the Humsafar

                                <a href="#">
                                    Terms & Conditions
                                </a>

                                and

                                <a href="#">
                                    Partner Policy
                                </a>.

                            </label>


                        </div>



                        <!-- REGISTER -->

                        <button
                            type="submit"
                            class="register-button"
                        >

                            <i class="fas fa-user-plus"></i>

                            Create Restaurant Owner Account

                        </button>


                    </form>



                    <!-- LOGIN -->

                    <div class="login-text">

                        Already have a Restaurant Owner account?

                        <a href="restaurant-owner-login.php">

                            Login here

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