<?php

require_once '../includes/session.php';
require_once '../includes/config.php';

$error = "";

$full_name = "";
$email = "";
$phone = "";
$restaurant_name = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $restaurant_name = trim($_POST["restaurant_name"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $terms = isset($_POST["terms"]);

    if ($full_name === "") {
        $error = "Please enter your full name.";

    } elseif ($restaurant_name === "") {
        $error = "Please enter your restaurant name.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif ($phone === "") {
        $error = "Please enter your phone number.";

    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";

    } elseif (!$terms) {
        $error = "Please agree to the Humsafar Restaurant Partner Terms & Conditions.";

    } else {

        /* Check email */
        $stmt = $conn->prepare("
            SELECT id
            FROM restaurant_users
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Database error. Please try again.";
        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_email = $result->fetch_assoc();
            $stmt->close();

            if ($existing_email) {
                $error = "This email address is already registered.";
            } else {

                /* Check phone */
                $stmt = $conn->prepare("
                    SELECT id
                    FROM restaurant_users
                    WHERE phone = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    $error = "Database error. Please try again.";
                } else {

                    $stmt->bind_param("s", $phone);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing_phone = $result->fetch_assoc();
                    $stmt->close();

                    if ($existing_phone) {
                        $error = "This phone number is already registered.";
                    } else {

                        /* Check restaurant name */
                        $stmt = $conn->prepare("
                            SELECT id
                            FROM restaurant_users
                            WHERE restaurant_name = ?
                            LIMIT 1
                        ");

                        if (!$stmt) {
                            $error = "Database error. Please try again.";
                        } else {

                            $stmt->bind_param("s", $restaurant_name);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $existing_restaurant = $result->fetch_assoc();
                            $stmt->close();

                            if ($existing_restaurant) {
                                $error = "This restaurant name is already registered.";
                            } else {

                                $hashed_password = password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );

                                if ($hashed_password === false) {
                                    $error = "Unable to secure your password. Please try again.";
                                } else {

                                    /*
                                     * New restaurant owners are created as PENDING.
                                     * They can log in after registration, but the
                                     * dashboard/features will remain locked until
                                     * admin approval.
                                     */
                                    $stmt = $conn->prepare("
                                        INSERT INTO restaurant_users
                                        (
                                            restaurant_name,
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
                                            ?,
                                            'pending'
                                        )
                                    ");

                                    if (!$stmt) {
                                        $error = "Unable to create your account. Please try again.";
                                    } else {

                                        $stmt->bind_param(
                                            "sssss",
                                            $restaurant_name,
                                            $full_name,
                                            $email,
                                            $phone,
                                            $hashed_password
                                        );

                                        if ($stmt->execute()) {

                                            $stmt->close();

                                            /*
                                             * Registration successful:
                                             * do not auto-login. Send the owner
                                             * to the restaurant login page.
                                             */
                                            header(
                                                "Location: restaurant-owner-login.php?registered=1"
                                            );
                                            exit;

                                        } else {
                                            $error = "Unable to create your account. Please try again.";
                                            $stmt->close();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
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

    <title>Become a Restaurant Partner - Humsafar</title>

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
            background: #fff8fb;
            color: #292929;
        }

        a {
            text-decoration: none;
        }

        .restaurant-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* =========================
           HEADER
        ========================= */

        .top-header {
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.7%;
            background: #ffffff;
            border-bottom: 1px solid #eeeeee;
            border-top: 3px solid #ef0038;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ed0038;
            font-size: 23px;
            font-weight: 800;
        }

        .brand i {
            font-size: 20px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid #f3bfd0;
            border-radius: 9px;
            color: #d90035;
            background: #ffffff;
            font-size: 12px;
            font-weight: 700;
            transition: .2s ease;
        }

        .back-button:hover {
            background: #fff1f5;
            border-color: #ed0038;
        }

        /* =========================
           MAIN
        ========================= */

        .main-area {
            flex: 1;
            padding: 45px 20px 55px;
        }

        .registration-card {
            width: min(1085px, 100%);
            min-height: 750px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            background: #ffffff;
            border-radius: 24px;
            box-shadow:
                0 15px 45px rgba(80, 25, 50, .10);
        }

        /* =========================
           LEFT SIDE
        ========================= */

        .hero-side {
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
            color: #ffffff;

            background:
                linear-gradient(
                    135deg,
                    rgba(239, 0, 56, .97),
                    rgba(255, 65, 126, .92)
                ),
                url(
                    "https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1000&q=85"
                );

            background-size: cover;
            background-position: center;
        }

        .hero-side::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(
                    135deg,
                    rgba(239, 0, 56, .92),
                    rgba(255, 72, 132, .62)
                );
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 48px;
            max-width: 520px;
        }

        .partner-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            margin-bottom: 18px;
            border: 1px solid rgba(255,255,255,.30);
            border-radius: 30px;
            background: rgba(255,255,255,.15);
            font-size: 12px;
            font-weight: 700;
        }

        .hero-content h1 {
            margin: 0 0 15px;
            font-size: 39px;
            line-height: 1.12;
            font-weight: 800;
        }

        .hero-content p {
            margin: 0 0 26px;
            max-width: 455px;
            color: rgba(255,255,255,.94);
            font-size: 14px;
            line-height: 1.7;
        }

        .benefits {
            display: grid;
            gap: 12px;
        }

        .benefit {
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 13px;
            font-weight: 600;
        }

        .benefit-icon {
            width: 31px;
            height: 31px;
            flex: 0 0 31px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: rgba(255,255,255,.16);
        }

        /* =========================
           FORM SIDE
        ========================= */

        .form-side {
            display: flex;
            align-items: center;
            padding: 42px;
            background: #ffffff;
        }

        .form-container {
            width: 100%;
            max-width: 470px;
            margin: 0 auto;
        }

        .form-heading {
            margin-bottom: 23px;
        }

        .form-heading h2 {
            margin: 0 0 6px;
            color: #292929;
            font-size: 27px;
            line-height: 1.2;
            font-weight: 800;
        }

        .form-heading p {
            margin: 0;
            color: #777777;
            font-size: 12.5px;
            line-height: 1.6;
        }

        /* =========================
           ERROR
        ========================= */

        .error-box {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 11px 13px;
            margin-bottom: 17px;
            border: 1px solid #ffc4d2;
            border-radius: 9px;
            background: #fff0f3;
            color: #a40027;
            font-size: 12px;
            line-height: 1.5;
        }

        /* =========================
           FORM
        ========================= */

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #333333;
            font-size: 12px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #e4003b;
            font-size: 13px;
            pointer-events: none;
            z-index: 1;
        }

        .input-wrap .toggle-password {
            left: auto;
            right: 13px;
            cursor: pointer;
            pointer-events: auto;
            color: #ed0038;
        }

        .form-control {
            width: 100%;
            height: 46px;
            padding: 0 38px 0 41px;
            border: 1px solid #dddddd;
            border-radius: 9px;
            outline: none;
            background: #ffffff;
            color: #333333;
            font-size: 12.5px;
            transition: .2s ease;
        }

        .form-control:focus {
            border-color: #ef174b;
            box-shadow: 0 0 0 3px rgba(239, 23, 75, .08);
        }

        textarea.form-control {
            height: 78px;
            padding-top: 13px;
            padding-bottom: 10px;
            resize: vertical;
        }

        .textarea-icon {
            top: 17px !important;
            transform: none !important;
        }

        .field-note {
            margin: 5px 0 0;
            color: #8a8a8a;
            font-size: 10.5px;
        }

        /* =========================
           PASSWORD
        ========================= */

        .password-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px;
        }

        /* =========================
           TERMS
        ========================= */

        .terms {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 3px 0 19px;
            color: #777777;
            font-size: 10.8px;
            line-height: 1.5;
        }

        .terms input {
            margin-top: 2px;
            accent-color: #ed0038;
        }

        .terms a {
            color: #df0038;
            font-weight: 700;
        }

        /* =========================
           BUTTON
        ========================= */

        .submit-button {
            width: 100%;
            height: 47px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 9px;
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow:
                0 7px 18px rgba(237,0,56,.18);
            transition: .2s ease;
        }

        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow:
                0 10px 24px rgba(237,0,56,.25);
        }

        /* =========================
           LOGIN
        ========================= */

        .login-line {
            margin: 18px 0 0;
            text-align: center;
            color: #777777;
            font-size: 11.5px;
        }

        .login-line a {
            color: #e00038;
            font-weight: 800;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            padding: 17px 20px;
            text-align: center;
            background: #29232a;
            color: #aaa;
            font-size: 11px;
        }

        .footer strong {
            color: #ffffff;
        }

        /* =========================
           TABLET
        ========================= */

        @media (max-width: 900px) {

            .registration-card {
                grid-template-columns: 1fr;
            }

            .hero-side {
                min-height: 390px;
            }

            .form-side {
                padding: 38px;
            }
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 600px) {

            .top-header {
                height: 60px;
                padding: 0 15px;
            }

            .brand {
                font-size: 20px;
            }

            .back-button {
                padding: 7px 9px;
                font-size: 10.5px;
            }

            .main-area {
                padding: 18px 10px 30px;
            }

            .registration-card {
                border-radius: 17px;
            }

            .hero-side {
                min-height: 340px;
            }

            .hero-content {
                padding: 30px 24px;
            }

            .hero-content h1 {
                font-size: 30px;
            }

            .hero-content p {
                font-size: 12.5px;
            }

            .form-side {
                padding: 30px 21px;
            }

            .form-heading h2 {
                font-size: 23px;
            }

            .form-row,
            .password-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

    </style>

</head>

<body>

<div class="restaurant-page">

    <!-- HEADER -->

    <header class="top-header">

        <a href="../index.php" class="brand">

            <i class="fas fa-utensils"></i>

            <span>Humsafar</span>

        </a>

        <a href="../join-humsafar.php" class="back-button">

            <i class="fas fa-arrow-left"></i>

            Back to Humsafar

        </a>

    </header>


    <!-- MAIN -->

    <main class="main-area">

        <div class="registration-card">

            <!-- LEFT PROMOTION -->

            <section class="hero-side">

                <div class="hero-content">

                    <div class="partner-badge">

                        <i class="fas fa-store"></i>

                        Humsafar Restaurant Partner

                    </div>

                    <h1>
                        Grow Your Restaurant With Humsafar
                    </h1>

                    <p>
                        Join Humsafar as a restaurant partner,
                        reach more customers and manage your
                        restaurant business from one place.
                    </p>

                    <div class="benefits">

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-store"></i>
                            </span>
                            <span>
                                Bring your restaurant online
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-users"></i>
                            </span>
                            <span>
                                Reach more customers in your area
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-utensils"></i>
                            </span>
                            <span>
                                Manage your menu and food items
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-receipt"></i>
                            </span>
                            <span>
                                Receive and manage customer orders
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-shield-halved"></i>
                            </span>
                            <span>
                                Secure restaurant partner account
                            </span>
                        </div>

                    </div>

                </div>

            </section>


            <!-- FORM -->

            <section class="form-side">

                <div class="form-container">

                    <div class="form-heading">

                        <h2>
                            Register Your Restaurant
                        </h2>

                        <p>
                            Create your Humsafar restaurant partner account.
                        </p>

                    </div>


                    <?php if ($error !== ""): ?>

                        <div class="error-box">

                            <i class="fas fa-circle-exclamation"></i>

                            <span><?= e($error) ?></span>

                        </div>

                    <?php endif; ?>


                    <form
                        method="POST"
                        action=""
                        autocomplete="off"
                    >

                        <!-- FULL NAME + PHONE -->

                        <div class="form-row">

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
                                        value="<?= e($full_name) ?>"
                                        maxlength="100"
                                        autocomplete="name"
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
                                        value="<?= e($phone) ?>"
                                        maxlength="30"
                                        autocomplete="tel"
                                        required
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- EMAIL + RESTAURANT NAME -->

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
                                        placeholder="owner@example.com"
                                        value="<?= e($email) ?>"
                                        maxlength="150"
                                        autocomplete="email"
                                        required
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="restaurant_name">
                                    Restaurant Name
                                </label>

                                <div class="input-wrap">

                                    <i class="fas fa-store"></i>

                                    <input
                                        type="text"
                                        id="restaurant_name"
                                        name="restaurant_name"
                                        class="form-control"
                                        placeholder="Enter restaurant name"
                                        value="<?= e($restaurant_name) ?>"
                                        maxlength="150"
                                        required
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- RESTAURANT ADDRESS / APPROVAL NOTE -->

                        <div class="form-group">

                            <label for="restaurant_info">
                                Restaurant Information
                            </label>

                            <div class="input-wrap">

                                <i class="fas fa-circle-info textarea-icon"></i>

                                <textarea
                                    id="restaurant_info"
                                    class="form-control"
                                    placeholder="Restaurant address, area or any basic information"
                                    maxlength="500"
                                ></textarea>

                            </div>

                            <p class="field-note">
                                Restaurant address and detailed information can be completed from your restaurant dashboard after approval.
                            </p>

                        </div>


                        <!-- PASSWORDS -->

                        <div class="password-row">

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
                                        placeholder="Create password"
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


                            <div class="form-group">

                                <label for="confirm_password">
                                    Confirm Password
                                </label>

                                <div class="input-wrap">

                                    <i class="fas fa-shield-halved"></i>

                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="form-control"
                                        placeholder="Confirm password"
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

                        </div>


                        <p class="field-note" style="margin-top:-8px; margin-bottom:15px;">
                            Password must be at least 6 characters.
                        </p>


                        <!-- TERMS -->

                        <label class="terms">

                            <input
                                type="checkbox"
                                name="terms"
                                required
                            >

                            <span>
                                I agree to the Humsafar
                                <a href="#">Restaurant Partner Terms &amp; Conditions</a>
                                and
                                <a href="#">Partner Policy</a>.
                            </span>

                        </label>


                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            class="submit-button"
                        >

                            <i class="fas fa-store"></i>

                            Create Restaurant Account

                        </button>


                        <!-- LOGIN -->

                        <div class="login-line">

                            Already have a Restaurant Partner account?

                            <a href="restaurant-owner-login.php">
                                Login here
                            </a>

                        </div>

                    </form>

                </div>

            </section>

        </div>

    </main>


    <!-- FOOTER -->

    <footer class="footer">

        <strong>Humsafar</strong>
        Food Delivery
        &nbsp;•&nbsp;
        © <?= date("Y") ?>

    </footer>

</div>


<script>

    document.querySelectorAll(".toggle-password").forEach(function (icon) {

        icon.addEventListener("click", function () {

            const targetId = this.getAttribute("data-target");
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            if (input.type === "password") {

                input.type = "text";

                this.classList.remove("fa-eye");
                this.classList.add("fa-eye-slash");

            } else {

                input.type = "password";

                this.classList.remove("fa-eye-slash");
                this.classList.add("fa-eye");
            }

        });

    });

</script>

</body>
</html>
