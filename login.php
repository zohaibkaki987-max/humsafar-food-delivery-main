<?php

require_once 'includes/config.php';

$error = "";
$success = "";

$email = "";


/* =====================================================
   REGISTRATION SUCCESS MESSAGE
===================================================== */

if (isset($_GET['registered']) && $_GET['registered'] == '1') {

    $success =
        "Account created successfully. Please login to continue.";
}


/* =====================================================
   LOGIN
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email =
        trim($_POST['email'] ?? '');

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
           FIND CUSTOMER
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                email,
                phone,
                password,
                role,
                status
            FROM users
            WHERE email = ?
            AND role = 'customer'
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

            $user =
                $result->fetch_assoc();

            $stmt->close();


            /* =============================================
               CHECK USER
            ============================================= */

            if (!$user) {

                $error =
                    "Invalid email or password.";

            } elseif (
                !password_verify(
                    $password,
                    $user['password']
                )
            ) {

                $error =
                    "Invalid email or password.";

            } elseif (
                isset($user['status']) &&
                strtolower($user['status']) !== 'active'
            ) {

                $error =
                    "Your account is currently inactive.";

            } else {


                /* =============================================
                   START SESSION
                ============================================= */

                if (session_status() === PHP_SESSION_NONE) {

                    session_start();
                }


                /* =============================================
                   CUSTOMER SESSION
                ============================================= */

                $_SESSION['user_id'] =
                    (int)$user['id'];

                $_SESSION['customer_id'] =
                    (int)$user['id'];

                $_SESSION['full_name'] =
                    $user['full_name'];

                $_SESSION['email'] =
                    $user['email'];

                $_SESSION['phone'] =
                    $user['phone'];

                $_SESSION['role'] =
                    'customer';

                $_SESSION['status'] =
                    $user['status'];


                /* =============================================
                   LOGIN SUCCESS
                ============================================= */

                header(
                    "Location: index.php"
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
        Login - Humsafar
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

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

        .login-page {

            min-height: 100vh;

            display: flex;

            flex-direction: column;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 6%;

            background:
                #ffffff;

            border-bottom:
                1px solid #f0e5e9;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 10px;

            color:
                #e00038;

            font-size:
                24px;

            font-weight:
                800;
        }


        .brand i {

            font-size:
                21px;
        }


        /* BACK TO HUMSAFAR */

        .back-button {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                9px 13px;

            border:
                1px solid #eadfe3;

            border-radius:
                9px;

            background:
                #ffffff;

            color:
                #555;

            font-size:
                11px;

            font-weight:
                700;

            transition:
                .2s;
        }


        .back-button:hover {

            color:
                #e00038;

            border-color:
                #e00038;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main-area {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                45px 20px 55px;
        }


        /* =====================================================
           LOGIN CARD
        ===================================================== */

        .login-card {

            width: 100%;

            max-width:
                980px;

            display: grid;

            grid-template-columns:
                42% 58%;

            background:
                #ffffff;

            border-radius:
                20px;

            overflow:
                hidden;

            border:
                1px solid #f0e4e8;

            box-shadow:
                0 18px 50px
                rgba(0, 0, 0, .08);
        }


        /* =====================================================
           LEFT SIDE
        ===================================================== */

        .welcome-side {

            position: relative;

            min-height:
                550px;

            display: flex;

            align-items: center;

            overflow:
                hidden;

            background:
                #e00038;

            color:
                #ffffff;
        }


        .welcome-side::before {

            content: "";

            position: absolute;

            width:
                280px;

            height:
                280px;

            border-radius:
                50%;

            background:
                rgba(255,255,255,.08);

            top:
                -100px;

            right:
                -80px;
        }


        .welcome-side::after {

            content: "";

            position: absolute;

            width:
                220px;

            height:
                220px;

            border-radius:
                50%;

            background:
                rgba(255,255,255,.06);

            bottom:
                -90px;

            left:
                -80px;
        }


        .welcome-content {

            position:
                relative;

            z-index:
                2;

            padding:
                45px;
        }


        .welcome-icon {

            width:
                66px;

            height:
                66px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                17px;

            background:
                rgba(255,255,255,.16);

            margin-bottom:
                25px;

            font-size:
                27px;
        }


        .welcome-content h1 {

            margin:
                0 0 13px;

            font-size:
                36px;

            line-height:
                1.15;

            font-weight:
                800;
        }


        .welcome-content p {

            margin:
                0 0 30px;

            max-width:
                330px;

            color:
                rgba(255,255,255,.88);

            font-size:
                13px;

            line-height:
                1.7;
        }


        /* BENEFITS */

        .benefit {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;

            margin-bottom:
                15px;

            color:
                rgba(255,255,255,.94);

            font-size:
                12px;

            font-weight:
                600;
        }


        .benefit i {

            width:
                25px;

            height:
                25px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                rgba(255,255,255,.15);

            font-size:
                10px;
        }


        /* =====================================================
           FORM SIDE
        ===================================================== */

        .form-side {

            padding:
                55px 55px;
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
                #777;

            font-size:
                12px;

            line-height:
                1.6;
        }


        /* =====================================================
           MESSAGES
        ===================================================== */

        .message {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            margin-bottom:
                18px;

            padding:
                12px 13px;

            border-radius:
                9px;

            font-size:
                11px;

            line-height:
                1.5;
        }


        .error-message {

            background:
                #fff0f3;

            border:
                1px solid #ffd2dc;

            color:
                #c90034;
        }


        .success-message {

            background:
                #eefaf2;

            border:
                1px solid #cdebd8;

            color:
                #248447;
        }


        /* =====================================================
           FORM
        ===================================================== */

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
                #444;

            font-size:
                11px;

            font-weight:
                800;
        }


        .input-wrap {

            position:
                relative;
        }


        .input-wrap > i:first-child {

            position:
                absolute;

            left:
                13px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #aaa;

            font-size:
                12px;

            pointer-events:
                none;
        }


        .form-control {

            width:
                100%;

            height:
                47px;

            padding:
                0 40px 0 38px;

            border:
                1px solid #e4dfe1;

            border-radius:
                10px;

            outline:
                none;

            background:
                #ffffff;

            color:
                #292929;

            font-family:
                inherit;

            font-size:
                12px;

            transition:
                .2s;
        }


        .form-control::placeholder {

            color:
                #aaa;
        }


        .form-control:focus {

            border-color:
                #e00038;

            box-shadow:
                0 0 0 3px
                rgba(224,0,56,.08);
        }


        /* =====================================================
           PASSWORD
        ===================================================== */

        .toggle-password {

            position:
                absolute;

            right:
                13px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #aaa;

            font-size:
                12px;

            cursor:
                pointer;
        }


        /* =====================================================
           OPTIONS
        ===================================================== */

        .login-options {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin:
                2px 0 22px;
        }


        .remember {

            display:
                flex;

            align-items:
                center;

            gap:
                7px;

            color:
                #777;

            font-size:
                10.5px;
        }


        .remember input {

            width:
                14px;

            height:
                14px;

            accent-color:
                #e00038;

            cursor:
                pointer;
        }


        .forgot-password {

            color:
                #e00038;

            font-size:
                10.5px;

            font-weight:
                700;
        }


        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        .login-button {

            width:
                100%;

            height:
                47px;

            border:
                none;

            border-radius:
                10px;

            background:
                #e00038;

            color:
                #ffffff;

            font-family:
                inherit;

            font-size:
                12px;

            font-weight:
                800;

            cursor:
                pointer;

            box-shadow:
                0 8px 20px
                rgba(224,0,56,.18);

            transition:
                .2s;
        }


        .login-button:hover {

            background:
                #c90032;

            transform:
                translateY(-1px);

            box-shadow:
                0 11px 24px
                rgba(224,0,56,.23);
        }


        .login-button i {

            margin-right:
                7px;
        }


        /* =====================================================
           SIGNUP
        ===================================================== */

        .signup-text {

            margin-top:
                23px;

            text-align:
                center;

            color:
                #777;

            font-size:
                11px;
        }


        .signup-text a {

            color:
                #e00038;

            font-weight:
                800;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            padding:
                17px 20px;

            text-align:
                center;

            background:
                #29232a;

            color:
                #aaa;

            font-size:
                11px;
        }


        .footer strong {

            color:
                #ffffff;
        }


        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 900px) {

            .login-card {

                grid-template-columns:
                    1fr;
            }


            .welcome-side {

                min-height:
                    auto;
            }


            .welcome-content {

                padding:
                    35px;
            }


            .welcome-content h1 {

                font-size:
                    30px;
            }


            .form-side {

                padding:
                    40px;
            }

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 600px) {

            .top-header {

                height:
                    60px;

                padding:
                    0 15px;
            }


            .brand {

                font-size:
                    20px;
            }


            .back-button {

                padding:
                    7px 9px;

                font-size:
                    10px;
            }


            .main-area {

                padding:
                    20px 10px 30px;
            }


            .login-card {

                border-radius:
                    16px;
            }


            .welcome-content {

                padding:
                    30px 24px;
            }


            .welcome-content h1 {

                font-size:
                    28px;
            }


            .form-side {

                padding:
                    30px 21px;
            }


            .form-heading h2 {

                font-size:
                    23px;
            }


            .login-options {

                align-items:
                    flex-start;

                gap:
                    10px;
            }

        }

    </style>

</head>


<body>


<div class="login-page">


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="top-header">


        <a
            href="join-humsafar.php"
            class="brand"
        >

            <i class="fas fa-utensils"></i>

            <span>
                Humsafar
            </span>

        </a>


        <!-- IMPORTANT:
             BACK TO HUMSAFAR GOES TO join-humsafar.php
        -->

        <a
            href="join-humsafar.php"
            class="back-button"
        >

            <i class="fas fa-arrow-left"></i>

            Back to Humsafar

        </a>


    </header>


    <!-- =====================================================
         MAIN
    ===================================================== -->

    <main class="main-area">


        <div class="login-card">


            <!-- =================================================
                 LEFT SIDE
            ================================================= -->

            <section class="welcome-side">


                <div class="welcome-content">


                    <div class="welcome-icon">

                        <i class="fas fa-right-to-bracket"></i>

                    </div>


                    <h1>
                        Welcome Back
                    </h1>


                    <p>
                        Login to your Humsafar account and continue ordering your favorite food from nearby restaurants.
                    </p>


                    <div class="benefit">

                        <i class="fas fa-utensils"></i>

                        <span>
                            Order your favorite food
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-bolt"></i>

                        <span>
                            Fast and easy ordering
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-location-dot"></i>

                        <span>
                            Convenient doorstep delivery
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-heart"></i>

                        <span>
                            Your favorite restaurants in one place
                        </span>

                    </div>


                </div>


            </section>


            <!-- =================================================
                 LOGIN FORM
            ================================================= -->

            <section class="form-side">


                <div class="form-heading">

                    <h2>
                        Login to Your Account
                    </h2>

                    <p>
                        Enter your email and password to continue.
                    </p>

                </div>


                <!-- ERROR -->

                <?php if ($error !== ""): ?>

                    <div class="message error-message">

                        <i class="fas fa-circle-exclamation"></i>

                        <span>

                            <?php
                            echo htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- SUCCESS -->

                <?php if ($success !== ""): ?>

                    <div class="message success-message">

                        <i class="fas fa-circle-check"></i>

                        <span>

                            <?php
                            echo htmlspecialchars(
                                $success,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- LOGIN FORM -->

                <form
                    method="POST"
                    action=""
                    autocomplete="on"
                >


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>


                        <div class="input-wrap">

                            <i class="fas fa-envelope"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                placeholder="Enter your email address"
                                value="<?=
                                    htmlspecialchars(
                                        $email,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ?>"
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


                        <div class="input-wrap">

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


                            <i
                                class="fas fa-eye toggle-password"
                                id="togglePassword"
                                title="Show password"
                            ></i>

                        </div>

                    </div>


                    <!-- OPTIONS -->

                    <div class="login-options">


                        <label class="remember">

                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                            >

                            <span>
                                Remember me
                            </span>

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

                        Login to Humsafar

                    </button>


                </form>


                <!-- SIGN UP -->

                <div class="signup-text">

                    Don't have an account?

                    <a href="register.php">
                        Create Account
                    </a>

                </div>


            </section>


        </div>


    </main>


    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="footer">

        <strong>
            Humsafar
        </strong>

        Food Delivery

        &nbsp;•&nbsp;

        © <?= date('Y') ?>

    </footer>


</div>


<script>

    /* =====================================================
       PASSWORD SHOW / HIDE
    ===================================================== */

    const togglePassword =
        document.getElementById(
            'togglePassword'
        );


    const password =
        document.getElementById(
            'password'
        );


    if (
        togglePassword &&
        password
    ) {

        togglePassword.addEventListener(
            'click',
            function() {

                if (
                    password.type ===
                    'password'
                ) {

                    password.type =
                        'text';

                    this.classList.remove(
                        'fa-eye'
                    );

                    this.classList.add(
                        'fa-eye-slash'
                    );

                    this.setAttribute(
                        'title',
                        'Hide password'
                    );

                } else {

                    password.type =
                        'password';

                    this.classList.remove(
                        'fa-eye-slash'
                    );

                    this.classList.add(
                        'fa-eye'
                    );

                    this.setAttribute(
                        'title',
                        'Show password'
                    );

                }

            }
        );

    }

</script>


</body>

</html>