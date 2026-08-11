<?php

require_once 'includes/config.php';

$error = "";
$success = "";

$full_name = "";
$email = "";
$phone = "";


/* =====================================================
   HANDLE CUSTOMER REGISTRATION
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name =
        trim($_POST['full_name'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $confirm_password =
        $_POST['confirm_password'] ?? '';

    $terms =
        isset($_POST['terms']);


    /* =================================================
       VALIDATION
    ================================================= */

    if ($full_name === '') {

        $error =
            "Please enter your full name.";

    } elseif (strlen($full_name) < 2) {

        $error =
            "Please enter a valid name.";

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

    } elseif (!$terms) {

        $error =
            "Please accept the Terms & Conditions.";

    } else {


        /* =================================================
           CHECK EMAIL
        ================================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM users
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
                   CHECK PHONE
                ================================================= */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM users
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
                           CUSTOMER ROLE
                        ================================================= */

                        $role =
                            'customer';


                        /* =================================================
                           CUSTOMER STATUS
                        ================================================= */

                        $status =
                            'active';


                        /* =================================================
                           CREATE CUSTOMER ACCOUNT
                        ================================================= */

                        $stmt = $conn->prepare("
                            INSERT INTO users
                            (
                                full_name,
                                email,
                                phone,
                                password,
                                role,
                                status
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


                        if (!$stmt) {

                            $error =
                                "Unable to create account. Please try again.";

                        } else {

                            $stmt->bind_param(
                                "ssssss",
                                $full_name,
                                $email,
                                $phone,
                                $hashed_password,
                                $role,
                                $status
                            );


                            if ($stmt->execute()) {

                                /*
                                 * Registration successful.
                                 *
                                 * Redirect customer to login page.
                                 */

                                header(
                                    "Location: login.php?registered=1"
                                );

                                exit;

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
        Sign Up - Humsafar
    </title>


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

        .signup-page {

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

            font-size: 24px;

            font-weight: 800;
        }


        .brand i {

            font-size: 21px;
        }


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

            font-size: 11px;

            font-weight: 700;

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
           SIGNUP CARD
        ===================================================== */

        .signup-card {

            width: 100%;

            max-width: 980px;

            display: grid;

            grid-template-columns:
                42% 58%;

            background:
                #ffffff;

            border-radius:
                20px;

            overflow:
                hidden;

            box-shadow:
                0 18px 50px
                rgba(0, 0, 0, .08);

            border:
                1px solid #f0e4e8;
        }


        /* =====================================================
           LEFT SIDE
        ===================================================== */

        .welcome-side {

            position: relative;

            min-height: 610px;

            display: flex;

            align-items: center;

            overflow: hidden;

            background:
                #e00038;

            color:
                #ffffff;
        }


        .welcome-side::before {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.08);

            top: -100px;

            right: -80px;
        }


        .welcome-side::after {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.06);

            bottom: -90px;

            left: -80px;
        }


        .welcome-content {

            position: relative;

            z-index: 2;

            padding:
                45px;
        }


        .welcome-icon {

            width: 66px;

            height: 66px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius:
                17px;

            background:
                rgba(255,255,255,.16);

            margin-bottom:
                25px;

            font-size: 27px;
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


        .benefit {

            display: flex;

            align-items: center;

            gap: 11px;

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

            width: 25px;

            height: 25px;

            display: flex;

            align-items: center;

            justify-content: center;

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
                42px 48px;
        }


        .form-heading {

            margin-bottom:
                25px;
        }


        .form-heading h2 {

            margin:
                0 0 7px;

            font-size:
                25px;

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

            display: flex;

            align-items: flex-start;

            gap: 9px;

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
           FORM GROUP
        ===================================================== */

        .form-row {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                13px;
        }


        .form-group {

            margin-bottom:
                16px;
        }


        .form-group label {

            display: block;

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

            position: relative;
        }


        .input-wrap > i:first-child {

            position: absolute;

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

            width: 100%;

            height:
                45px;

            padding:
                0 13px 0 38px;

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
           PASSWORD TOGGLE
        ===================================================== */

        .toggle-password {

            position: absolute;

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


        .password-input {

            padding-right:
                38px;
        }


        /* =====================================================
           TERMS
        ===================================================== */

        .terms {

            display: flex;

            align-items:
                flex-start;

            gap:
                8px;

            margin:
                4px 0 20px;

            color:
                #777;

            font-size:
                10.5px;

            line-height:
                1.5;
        }


        .terms input {

            width:
                14px;

            height:
                14px;

            margin:
                1px 0 0;

            accent-color:
                #e00038;

            cursor:
                pointer;
        }


        .terms label {

            cursor:
                pointer;
        }


        .terms a {

            color:
                #e00038;

            font-weight:
                700;
        }


        /* =====================================================
           REGISTER BUTTON
        ===================================================== */

        .register-button {

            width:
                100%;

            height:
                46px;

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


        .register-button:hover {

            background:
                #c90032;

            transform:
                translateY(-1px);

            box-shadow:
                0 11px 24px
                rgba(224,0,56,.23);
        }


        .register-button i {

            margin-right:
                7px;
        }


        /* =====================================================
           LOGIN
        ===================================================== */

        .login-text {

            margin-top:
                20px;

            text-align:
                center;

            color:
                #777;

            font-size:
                11px;
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

            .signup-card {

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
                    38px;
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


            .signup-card {

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


            .welcome-content p {

                font-size:
                    12px;
            }


            .form-side {

                padding:
                    30px 21px;
            }


            .form-heading h2 {

                font-size:
                    23px;
            }


            .form-row {

                grid-template-columns:
                    1fr;

                gap:
                    0;
            }

        }

    </style>

</head>


<body>


<div class="signup-page">


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="top-header">


        <a
            href="index.php"
            class="brand"
        >

            <i class="fas fa-utensils"></i>

            <span>
                Humsafar
            </span>

        </a>


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


        <div class="signup-card">


            <!-- =================================================
                 LEFT
            ================================================= -->

            <section class="welcome-side">


                <div class="welcome-content">


                    <div class="welcome-icon">

                        <i class="fas fa-user-plus"></i>

                    </div>


                    <h1>
                        Join Humsafar
                    </h1>


                    <p>
                        Create your Humsafar account and discover your favorite food from local restaurants.
                    </p>


                    <div class="benefit">

                        <i class="fas fa-utensils"></i>

                        <span>
                            Order from your favorite restaurants
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-bolt"></i>

                        <span>
                            Fast and easy food ordering
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-location-dot"></i>

                        <span>
                            Get food delivered to your doorstep
                        </span>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-tags"></i>

                        <span>
                            Discover exclusive deals
                        </span>

                    </div>


                </div>


            </section>


            <!-- =================================================
                 FORM
            ================================================= -->

            <section class="form-side">


                <div class="form-heading">

                    <h2>
                        Create Your Account
                    </h2>

                    <p>
                        Enter your details below to get started with Humsafar.
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


                <!-- FORM -->

                <form
                    method="POST"
                    action=""
                    autocomplete="on"
                >


                    <!-- NAME -->

                    <div class="form-group">

                        <label for="full_name">
                            Full Name
                        </label>


                        <div class="input-wrap">

                            <i class="fas fa-user"></i>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                class="form-control"
                                placeholder="Enter your full name"
                                value="<?=
                                    htmlspecialchars(
                                        $full_name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ?>"
                                maxlength="100"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>


                    <!-- EMAIL + PHONE -->

                    <div class="form-row">


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
                                    placeholder="Enter email"
                                    value="<?=
                                        htmlspecialchars(
                                            $email,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ?>"
                                    maxlength="150"
                                    autocomplete="email"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>


                            <div class="input-wrap">

                                <i class="fas fa-phone"></i>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    placeholder="03XXXXXXXXX"
                                    value="<?=
                                        htmlspecialchars(
                                            $phone,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ?>"
                                    maxlength="20"
                                    autocomplete="tel"
                                    required
                                >

                            </div>

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
                                class="form-control password-input"
                                placeholder="Create a password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >


                            <i
                                class="fas fa-eye toggle-password"
                                data-target="password"
                                title="Show password"
                            ></i>

                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>


                        <div class="input-wrap">

                            <i class="fas fa-lock"></i>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control password-input"
                                placeholder="Confirm your password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >


                            <i
                                class="fas fa-eye toggle-password"
                                data-target="confirm_password"
                                title="Show password"
                            ></i>

                        </div>

                    </div>


                    <!-- TERMS -->

                    <div class="terms">

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
                                Privacy Policy
                            </a>.

                        </label>

                    </div>


                    <!-- REGISTER -->

                    <button
                        type="submit"
                        class="register-button"
                    >

                        <i class="fas fa-user-plus"></i>

                        Create My Account

                    </button>


                </form>


                <!-- LOGIN -->

                <div class="login-text">

                    Already have a Humsafar account?

                    <a href="login.php">
                        Login here
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

    document
        .querySelectorAll('.toggle-password')
        .forEach(function(toggle) {

            toggle.addEventListener(
                'click',
                function() {

                    const targetId =
                        this.getAttribute(
                            'data-target'
                        );

                    const input =
                        document.getElementById(
                            targetId
                        );


                    if (!input) {
                        return;
                    }


                    if (
                        input.type ===
                        'password'
                    ) {

                        input.type =
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

                        input.type =
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

        });


    /* =====================================================
       CONFIRM PASSWORD CHECK
    ===================================================== */

    const signupForm =
        document.querySelector(
            'form'
        );


    if (signupForm) {

        signupForm.addEventListener(
            'submit',
            function(event) {

                const password =
                    document.getElementById(
                        'password'
                    );

                const confirmPassword =
                    document.getElementById(
                        'confirm_password'
                    );


                if (
                    password &&
                    confirmPassword &&
                    password.value !==
                    confirmPassword.value
                ) {

                    event.preventDefault();

                    alert(
                        'Passwords do not match.'
                    );

                    confirmPassword.focus();
                }

            }
        );

    }

</script>


</body>

</html>