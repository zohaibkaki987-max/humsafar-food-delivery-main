<?php
session_start();

/*
|--------------------------------------------------------------------------
| HUMSAFAR ADMIN LOGIN
|--------------------------------------------------------------------------
| Existing admin authentication preserved.
| Credentials:
| admin@humsafar.com
| admin123
|--------------------------------------------------------------------------
*/

/* Already logged in */
if (
    isset($_SESSION['admin_logged_in']) &&
    $_SESSION['admin_logged_in'] === true
) {
    header("Location: admin-panel.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN CREDENTIALS
|--------------------------------------------------------------------------
*/
$admin_email = "admin@humsafar.com";
$admin_password = "admin123";

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error =
            "Please enter your email and password.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } elseif (
        $email === $admin_email &&
        $password === $admin_password
    ) {

        /*
        |--------------------------------------------------------------------------
        | Successful Login
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;

        $_SESSION['admin_email'] =
            $admin_email;

        $_SESSION['admin_name'] =
            "Humsafar Administrator";

        header(
            "Location: admin-panel.php"
        );

        exit;

    } else {

        $error =
            "Invalid admin email or password.";
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
    Admin Login | Humsafar
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   RESET
========================================================= */

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

    background: #f7f7f9;

    color: #292929;

    min-height: 100vh;
}


/* =========================================================
   MAIN WRAPPER
========================================================= */

.page {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 25px;
}


.login-wrapper {

    width: 100%;

    max-width: 1030px;

    min-height: 620px;

    background: #fff;

    border-radius: 24px;

    overflow: hidden;

    display: grid;

    grid-template-columns:
        0.95fr 1.05fr;

    box-shadow:
        0 18px 55px
        rgba(237, 0, 56, .10);

    border:
        1px solid #f0dfe4;
}


/* =========================================================
   LEFT PROMO
========================================================= */

.promo {

    position: relative;

    overflow: hidden;

    padding: 48px 42px;

    background:
        linear-gradient(
            145deg,
            #ed0038 0%,
            #d80035 55%,
            #bf002e 100%
        );

    color: #fff;

    display: flex;

    flex-direction: column;

    justify-content:
        space-between;
}


.promo::before {

    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    top: -80px;
    right: -90px;
}


.promo::after {

    content: "";

    position: absolute;

    width: 190px;
    height: 190px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.05);

    bottom: -70px;
    left: -60px;
}


.brand {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 12px;
}


.brand-icon {

    width: 47px;
    height: 47px;

    border-radius: 13px;

    background: #fff;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;
}


.brand-name {

    font-size: 25px;

    line-height: 1;

    font-weight: 900;
}


.brand-subtitle {

    margin-top: 5px;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1.1px;

    opacity: .8;
}


.promo-content {

    position: relative;

    z-index: 2;
}


.promo-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid
        rgba(255,255,255,.18);

    padding:
        8px 12px;

    border-radius: 22px;

    font-size: 10px;

    font-weight: 800;

    margin-bottom: 17px;
}


.promo h1 {

    margin:
        0 0 13px;

    font-size: 37px;

    line-height: 1.08;

    font-weight: 900;
}


.promo h1 span {

    color: #ffd900;
}


.promo-text {

    margin: 0;

    max-width: 390px;

    color:
        rgba(255,255,255,.85);

    font-size: 13px;

    line-height: 1.7;
}


.features {

    margin-top: 27px;

    display: grid;

    gap: 12px;
}


.feature {

    display: flex;

    align-items: center;

    gap: 10px;
}


.feature-icon {

    width: 31px;
    height: 31px;

    flex-shrink: 0;

    border-radius: 8px;

    background:
        rgba(255,255,255,.14);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;
}


.feature-text {

    font-size: 10px;

    font-weight: 700;

    color:
        rgba(255,255,255,.9);
}


.promo-footer {

    position: relative;

    z-index: 2;

    color:
        rgba(255,255,255,.65);

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   RIGHT LOGIN
========================================================= */

.form-side {

    padding: 48px 52px;

    display: flex;

    flex-direction: column;

    justify-content: center;
}


.form-top {

    margin-bottom: 28px;
}


.form-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    background: #fff0f4;

    color: #ed0038;

    padding:
        7px 11px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 800;

    margin-bottom: 13px;
}


.form-top h2 {

    margin:
        0 0 7px;

    font-size: 28px;

    font-weight: 900;

    color: #292929;
}


.form-top p {

    margin: 0;

    color: #8a8a8a;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    padding:
        11px 13px;

    margin-bottom: 18px;

    border-radius: 9px;

    font-size: 11px;

    line-height: 1.5;
}


.alert-error {

    background: #fff0f1;

    border:
        1px solid #ffd0d6;

    color: #c82333;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #333;

    font-size: 11px;

    font-weight: 800;
}


.input-wrap {

    position: relative;
}


.input-wrap > i {

    position: absolute;

    left: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #ed0038;

    font-size: 13px;

    pointer-events: none;
}


.form-control {

    width: 100%;

    height: 46px;

    padding:
        0 42px 0 39px;

    border:
        1px solid #e2d7dc;

    border-radius: 9px;

    background: #fff;

    outline: none;

    color: #333;

    font-size: 12px;

    transition:
        border .2s ease,
        box-shadow .2s ease;
}


.form-control:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.08);
}


.password-toggle {

    position: absolute;

    right: 11px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    color: #999;

    cursor: pointer;

    font-size: 12px;

    padding: 5px;
}


.password-toggle:hover {

    color: #ed0038;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-btn {

    width: 100%;

    height: 47px;

    border: 0;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #c90031
        );

    color: #fff;

    font-size: 12px;

    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 8px 18px
        rgba(237,0,56,.18);

    transition:
        transform .18s ease,
        box-shadow .18s ease;
}


.login-btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 10px 23px
        rgba(237,0,56,.23);
}


.login-btn:active {

    transform:
        translateY(0);
}


/* =========================================================
   SECURITY
========================================================= */

.security {

    margin-top: 17px;

    padding:
        11px 12px;

    border-radius: 9px;

    background: #fafafa;

    border:
        1px solid #eeeeee;

    color: #888;

    text-align: center;

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   BACK
========================================================= */

.back-link {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    width: 100%;

    margin-top: 17px;

    color: #ed0038;

    text-decoration: none;

    font-size: 10px;

    font-weight: 800;
}


.back-link:hover {

    text-decoration: underline;
}


/* =========================================================
   FOOTER
========================================================= */

.copy {

    margin-top: 19px;

    text-align: center;

    color: #aaa;

    font-size: 8px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 820px) {

    .page {

        padding: 15px;
    }


    .login-wrapper {

        min-height: auto;

        grid-template-columns: 1fr;

        max-width: 500px;
    }


    .promo {

        padding: 30px 26px;

        min-height: 280px;
    }


    .promo h1 {

        font-size: 28px;
    }


    .promo-text {

        font-size: 11px;
    }


    .features {

        display: none;
    }


    .form-side {

        padding:
            32px 26px;
    }

}


@media (max-width: 430px) {

    .promo {

        min-height: 240px;
    }


    .brand-name {

        font-size: 22px;
    }


    .promo h1 {

        font-size: 24px;
    }


    .form-top h2 {

        font-size: 24px;
    }

}

</style>

</head>


<body>


<div class="page">


    <div class="login-wrapper">


        <!-- =================================================
             LEFT PROMOTIONAL AREA
        ================================================== -->

        <section class="promo">


            <div class="brand">

                <div class="brand-icon">

                    <i class="fas fa-shield-halved"></i>

                </div>


                <div>

                    <div class="brand-name">
                        Humsafar
                    </div>

                    <div class="brand-subtitle">
                        FOOD DELIVERY
                    </div>

                </div>

            </div>


            <div class="promo-content">


                <div class="promo-badge">

                    <i class="fas fa-lock"></i>

                    ADMINISTRATOR ACCESS

                </div>


                <h1>

                    Control
                    <span>Everything.</span>

                </h1>


                <p class="promo-text">

                    Manage Humsafar from one secure admin panel.
                    Control restaurants, riders, users, orders
                    and the complete food delivery operation.

                </p>


                <div class="features">


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fas fa-store"></i>

                        </div>

                        <div class="feature-text">
                            Manage Restaurants & Owners
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fas fa-motorcycle"></i>

                        </div>

                        <div class="feature-text">
                            Approve & Manage Riders
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">

                            <i class="fas fa-chart-line"></i>

                        </div>

                        <div class="feature-text">
                            Monitor Platform Activity
                        </div>

                    </div>


                </div>


            </div>


            <div class="promo-footer">

                <i class="fas fa-shield-halved"></i>

                Authorized Humsafar administrators only.

            </div>


        </section>


        <!-- =================================================
             LOGIN FORM
        ================================================== -->

        <section class="form-side">


            <div class="form-top">


                <div class="form-badge">

                    <i class="fas fa-user-shield"></i>

                    ADMIN PORTAL

                </div>


                <h2>
                    Admin Login
                </h2>


                <p>
                    Sign in to access the Humsafar administration panel.
                </p>

            </div>


            <?php if ($error !== ""): ?>

                <div class="alert alert-error">

                    <i class="fas fa-circle-exclamation"></i>

                    &nbsp;

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                autocomplete="off"
            >


                <!-- EMAIL -->

                <div class="form-group">


                    <label for="email">
                        Admin Email
                    </label>


                    <div class="input-wrap">


                        <i class="fas fa-envelope"></i>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter admin email"
                            value="<?= isset($_POST['email'])
                                ? htmlspecialchars(
                                    $_POST['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '' ?>"
                            autocomplete="username"
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
                            placeholder="Enter admin password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >

                            <i
                                class="fas fa-eye"
                                id="passwordIcon"
                            ></i>

                        </button>


                    </div>

                </div>


                <!-- LOGIN -->

                <button
                    type="submit"
                    class="login-btn"
                >

                    <i class="fas fa-right-to-bracket"></i>

                    &nbsp;

                    Login to Admin Panel

                </button>


            </form>


            <!-- SECURITY -->

            <div class="security">

                <i class="fas fa-shield-halved"></i>

                This area is restricted to authorized
                Humsafar administrators only.

            </div>


            <!-- BACK -->

            <a
                href="../index.php"
                class="back-link"
            >

                <i class="fas fa-arrow-left"></i>

                Back to Humsafar

            </a>


            <div class="copy">

                © <?= date("Y") ?>

                Humsafar Food Delivery.

                All rights reserved.

            </div>


        </section>


    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Password Toggle
|--------------------------------------------------------------------------
*/

const passwordInput =
    document.getElementById(
        'password'
    );


const passwordToggle =
    document.getElementById(
        'passwordToggle'
    );


const passwordIcon =
    document.getElementById(
        'passwordIcon'
    );


if (
    passwordInput &&
    passwordToggle &&
    passwordIcon
) {

    passwordToggle.addEventListener(
        'click',
        function () {

            if (
                passwordInput.type ===
                'password'
            ) {

                passwordInput.type =
                    'text';

                passwordIcon.classList.remove(
                    'fa-eye'
                );

                passwordIcon.classList.add(
                    'fa-eye-slash'
                );

                passwordToggle.setAttribute(
                    'aria-label',
                    'Hide password'
                );

            } else {

                passwordInput.type =
                    'password';

                passwordIcon.classList.remove(
                    'fa-eye-slash'
                );

                passwordIcon.classList.add(
                    'fa-eye'
                );

                passwordToggle.setAttribute(
                    'aria-label',
                    'Show password'
                );
            }

        }
    );

}

</script>


</body>

</html>
