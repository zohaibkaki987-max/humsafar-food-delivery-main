<?php

require_once '../includes/session.php';
require_once '../includes/config.php';

/*
|--------------------------------------------------------------------------
| Humsafar Restaurant Owner Login
|--------------------------------------------------------------------------
| Registration page creates accounts with:
|     status = pending
|
| Pending owners ARE allowed to log in so they can see their dashboard
| and approval status. The dashboard and management pages will enforce
| the actual permission/approval checks.
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}

$conn->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| If already logged in, send owner to dashboard.
|--------------------------------------------------------------------------
|
| We intentionally use a dedicated restaurant-owner session namespace.
| This keeps the restaurant module separate from customer/rider sessions.
|
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['restaurant_owner_id'])) {
    header("Location: restaurant-owner-dashboard.php");
    exit;
}

$error = "";
$success = "";

$email = "";

/*
|--------------------------------------------------------------------------
| Registration success message
|--------------------------------------------------------------------------
*/

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = "Registration successful. Your account is pending admin approval. Please log in to continue.";
}

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "") {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password === "") {

        $error = "Please enter your password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                restaurant_name,
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

            $error = "Unable to process your login right now. Please try again.";

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $owner = $result->fetch_assoc();

            $stmt->close();

            if (!$owner) {

                $error = "Invalid email or password.";

            } else {

                $stored_password = (string)($owner["password"] ?? "");

                /*
                 * New registrations use password_hash().
                 *
                 * The fallback below is deliberately NOT a plain-text
                 * password comparison. It only supports old PHP password
                 * hashes that may exist in the existing project.
                 */
                $password_valid = false;

                if ($stored_password !== "") {
                    $password_valid = password_verify(
                        $password,
                        $stored_password
                    );
                }

                if (!$password_valid) {

                    $error = "Invalid email or password.";

                } else {

                    /*
                     * Regenerate the session ID after successful login.
                     */
                    session_regenerate_id(true);

                    /*
                     * Store only the information needed by the restaurant
                     * owner module. The password is NEVER stored in session.
                     */
                    $_SESSION["restaurant_owner_id"] = (int)$owner["id"];
                    $_SESSION["restaurant_owner_name"] = (string)$owner["full_name"];
                    $_SESSION["restaurant_owner_email"] = (string)$owner["email"];
                    $_SESSION["restaurant_owner_phone"] = (string)$owner["phone"];
                    $_SESSION["restaurant_owner_restaurant_name"] = (string)$owner["restaurant_name"];
                    $_SESSION["restaurant_owner_status"] = (string)$owner["status"];

                    /*
                     * Dashboard is intentionally the destination for both
                     * pending and approved owners.
                     *
                     * Pending:
                     *   Dashboard visible
                     *   Features locked
                     *
                     * Approved:
                     *   Dashboard + management features available
                     */
                    header("Location: restaurant-owner-dashboard.php");
                    exit;
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

    <title>Restaurant Owner Login - Humsafar</title>

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
            display: flex;
            align-items: center;
        }

        .login-card {
            width: min(1085px, 100%);
            min-height: 660px;
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
            max-width: 440px;
            margin: 0 auto;
        }

        .form-heading {
            margin-bottom: 23px;
        }

        .form-heading h2 {
            margin: 0 0 6px;
            color: #292929;
            font-size: 28px;
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
           MESSAGES
        ========================= */

        .message {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 12px 13px;
            margin-bottom: 17px;
            border-radius: 9px;
            font-size: 11.5px;
            line-height: 1.5;
        }

        .success-message {
            color: #17643a;
            background: #effbf4;
            border: 1px solid #bce8ce;
        }

        .error-message {
            color: #a40027;
            background: #fff0f3;
            border: 1px solid #ffc4d2;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 17px;
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
            height: 48px;
            padding: 0 42px 0 42px;
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

        .form-control::placeholder {
            color: #999999;
        }

        /* =========================
           REMEMBER / FORGOT
        ========================= */

        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin: -2px 0 21px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #777777;
            font-size: 11px;
        }

        .remember input {
            margin: 0;
            accent-color: #ed0038;
        }

        .forgot-link {
            color: #df0038;
            font-size: 11px;
            font-weight: 700;
        }

        /* =========================
           BUTTON
        ========================= */

        .submit-button {
            width: 100%;
            height: 48px;
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
           PENDING INFO
        ========================= */

        .approval-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-top: 18px;
            padding: 12px 13px;
            border: 1px solid #f4d9a8;
            border-radius: 9px;
            background: #fffaf0;
            color: #75541a;
            font-size: 10.8px;
            line-height: 1.55;
        }

        .approval-note i {
            margin-top: 2px;
            color: #d58a00;
        }

        /* =========================
           REGISTER
        ========================= */

        .register-line {
            margin: 20px 0 0;
            text-align: center;
            color: #777777;
            font-size: 11.5px;
        }

        .register-line a {
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

            .login-card {
                grid-template-columns: 1fr;
            }

            .hero-side {
                min-height: 370px;
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

            .login-card {
                border-radius: 17px;
            }

            .hero-side {
                min-height: 330px;
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

            .options-row {
                align-items: flex-start;
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

        <a href="../index.php" class="back-button">

            <i class="fas fa-arrow-left"></i>

            Back to Humsafar

        </a>

    </header>


    <!-- MAIN -->

    <main class="main-area">

        <div class="login-card">

            <!-- LEFT PROMOTION -->

            <section class="hero-side">

                <div class="hero-content">

                    <div class="partner-badge">

                        <i class="fas fa-store"></i>

                        Humsafar Restaurant Partner

                    </div>

                    <h1>
                        Welcome Back, Restaurant Partner
                    </h1>

                    <p>
                        Login to your Humsafar restaurant dashboard
                        to check your account status and manage your
                        restaurant once it has been approved.
                    </p>

                    <div class="benefits">

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-chart-line"></i>
                            </span>
                            <span>
                                Track your restaurant activity
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-utensils"></i>
                            </span>
                            <span>
                                Manage your menu after approval
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-receipt"></i>
                            </span>
                            <span>
                                Manage customer orders
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-circle-check"></i>
                            </span>
                            <span>
                                Your restaurant goes live after approval
                            </span>
                        </div>

                        <div class="benefit">
                            <span class="benefit-icon">
                                <i class="fas fa-shield-halved"></i>
                            </span>
                            <span>
                                Secure restaurant partner access
                            </span>
                        </div>

                    </div>

                </div>

            </section>


            <!-- LOGIN FORM -->

            <section class="form-side">

                <div class="form-container">

                    <div class="form-heading">

                        <h2>
                            Restaurant Owner Login
                        </h2>

                        <p>
                            Login to access your Humsafar restaurant dashboard.
                        </p>

                    </div>


                    <!-- SUCCESS -->

                    <?php if ($success !== ""): ?>

                        <div class="message success-message">

                            <i class="fas fa-circle-check"></i>

                            <span><?= e($success) ?></span>

                        </div>

                    <?php endif; ?>


                    <!-- ERROR -->

                    <?php if ($error !== ""): ?>

                        <div class="message error-message">

                            <i class="fas fa-circle-exclamation"></i>

                            <span><?= e($error) ?></span>

                        </div>

                    <?php endif; ?>


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
                                    value="<?= e($email) ?>"
                                    maxlength="150"
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
                                    data-target="password"
                                    title="Show password"
                                ></i>

                            </div>

                        </div>


                        <!-- OPTIONS -->

                        <div class="options-row">

                            <label class="remember">

                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                >

                                <span>Remember me</span>

                            </label>

                            <a
                                href="#"
                                class="forgot-link"
                                onclick="alert('Password recovery will be added with the restaurant owner account recovery system.'); return false;"
                            >
                                Forgot Password?
                            </a>

                        </div>


                        <!-- LOGIN -->

                        <button
                            type="submit"
                            class="submit-button"
                        >

                            <i class="fas fa-right-to-bracket"></i>

                            Login to Restaurant Dashboard

                        </button>


                        <!-- APPROVAL INFO -->

                        <div class="approval-note">

                            <i class="fas fa-hourglass-half"></i>

                            <span>
                                <strong>New restaurant partners:</strong>
                                You can log in after registration, but your
                                restaurant management features will remain
                                locked until the Humsafar admin team approves
                                your account.
                            </span>

                        </div>


                        <!-- REGISTER -->

                        <div class="register-line">

                            Don't have a Restaurant Partner account?

                            <a href="restaurant-owner-register.php">
                                Register your restaurant
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
