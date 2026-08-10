<?php

session_start();

/*
|--------------------------------------------------------------------------
| ADMIN SETTINGS
|--------------------------------------------------------------------------
| Humsafar Food Delivery
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: admin-login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$admin_name = isset($_SESSION['admin_name'])
    ? $_SESSION['admin_name']
    : "Humsafar Administrator";

$admin_email = isset($_SESSION['admin_email'])
    ? $_SESSION['admin_email']
    : "admin@humsafar.com";


/*
|--------------------------------------------------------------------------
| SETTINGS VALUES
|--------------------------------------------------------------------------
| These settings are currently session/browser based.
| No database table is assumed.
|--------------------------------------------------------------------------
*/

$maintenance_mode = isset($_SESSION['maintenance_mode'])
    ? $_SESSION['maintenance_mode']
    : false;

$email_notifications = isset($_SESSION['email_notifications'])
    ? $_SESSION['email_notifications']
    : true;

$payment_notifications = isset($_SESSION['payment_notifications'])
    ? $_SESSION['payment_notifications']
    : true;

$order_notifications = isset($_SESSION['order_notifications'])
    ? $_SESSION['order_notifications']
    : true;

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_settings"])
) {

    $maintenance_mode =
        isset($_POST["maintenance_mode"]);

    $email_notifications =
        isset($_POST["email_notifications"]);

    $payment_notifications =
        isset($_POST["payment_notifications"]);

    $order_notifications =
        isset($_POST["order_notifications"]);


    $_SESSION['maintenance_mode'] =
        $maintenance_mode;

    $_SESSION['email_notifications'] =
        $email_notifications;

    $_SESSION['payment_notifications'] =
        $payment_notifications;

    $_SESSION['order_notifications'] =
        $order_notifications;


    $success =
        "Settings saved successfully.";
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
        Settings | Humsafar Admin
    </title>


    <!-- Font Awesome -->

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
                linear-gradient(
                    135deg,
                    #fff7fb 0%,
                    #ffeaf4 50%,
                    #fff8fb 100%
                );

            color: #29232a;

            min-height: 100vh;
        }


        a {
            text-decoration: none;
        }


        button,
        input {
            font-family: inherit;
        }


        /*
        |--------------------------------------------------------------------------
        | TOP BAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            width: 100%;

            min-height: 74px;

            padding:
                14px 28px;

            background:
               linear-gradient(
                    135deg,
                    #8b0038,
                    #b0004b,
                    #d0005b
                );

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            box-shadow:
                0 8px 28px
                rgba(190, 48, 119, 0.16);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .brand-icon {

            width: 44px;

            height: 44px;

            border-radius: 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(255, 255, 255, 0.17);

            font-size: 20px;
        }


        .brand-text h2 {

            margin: 0;

            font-size: 20px;

            font-weight: 800;
        }


        .brand-text p {

            margin: 3px 0 0;

            font-size: 11px;

            opacity: .85;
        }


        .admin-area {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .admin-name {

            font-size: 13px;

            font-weight: 700;
        }


        .logout-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                9px 14px;

            border-radius: 10px;

            background: #ffffff;

            color:  #e00038;

            font-size: 12px;

            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN PAGE
        |--------------------------------------------------------------------------
        */

        .page {

            width: min(1100px, 94%);

            margin:
                32px auto 60px;
        }


        .page-header {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;
        }


        .page-title h1 {

            margin: 0;

            color: #29232a;

            font-size: 30px;

            font-weight: 800;
        }


        .page-title p {

            margin:
                7px 0 0;

            color: #777;

            font-size: 14px;
        }


        .back-btn {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding:
                11px 16px;

            border-radius: 10px;

            background: #ffffff;

            color:  #e00038;

            border:
                1px solid #f1dce7;

            font-size: 13px;

            font-weight: 800;

            box-shadow:
                0 5px 15px
                rgba(80, 0, 30, 0.05);

            transition: .2s ease;
        }


        .back-btn:hover {

            background: #fff0f7;

            transform:
                translateY(-1px);
        }


        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .alert {

            padding:
                14px 16px;

            margin-bottom: 20px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            gap: 9px;

            font-size: 13px;

            font-weight: 600;
        }


        .alert-success {

            color: #176c3a;

            background: #eaf9f0;

            border:
                1px solid #bee6cc;
        }


        .alert-error {

            color: #b42323;

            background: #fff0f0;

            border:
                1px solid #f1caca;
        }


        /*
        |--------------------------------------------------------------------------
        | SETTINGS GRID
        |--------------------------------------------------------------------------
        */

        .settings-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 22px;
        }


        /*
        |--------------------------------------------------------------------------
        | SETTINGS CARD
        |--------------------------------------------------------------------------
        */

        .settings-card {

            background: #ffffff;

            border:
                1px solid #f1dce7;

            border-radius: 20px;

            padding: 26px;

            box-shadow:
                0 12px 32px
                rgba(100, 0, 45, 0.06);
        }


        .card-header {

            display: flex;

            align-items: center;

            gap: 13px;

            padding-bottom: 18px;

            margin-bottom: 20px;

            border-bottom:
                1px solid #f0e2e8;
        }


        .card-icon {

            width: 44px;

            height: 44px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff0f7;

            color:  #e00038;

            font-size: 17px;
        }


        .card-header h2 {

            margin: 0;

            color: #29232a;

            font-size: 18px;

            font-weight: 800;
        }


        .card-header p {

            margin:
                4px 0 0;

            color: #888;

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | SETTING ROW
        |--------------------------------------------------------------------------
        */

        .setting-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            padding:
                16px 0;

            border-bottom:
                1px solid #f3e7ed;
        }


        .setting-row:last-child {

            border-bottom: none;
        }


        .setting-info {

            flex: 1;
        }


        .setting-title {

            display: block;

            color: #3a2933;

            font-size: 13px;

            font-weight: 800;

            margin-bottom: 5px;
        }


        .setting-description {

            display: block;

            color: #888;

            font-size: 11px;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE
        |--------------------------------------------------------------------------
        */

        .switch {

            position: relative;

            width: 48px;

            height: 26px;

            flex-shrink: 0;
        }


        .switch input {

            opacity: 0;

            width: 0;

            height: 0;
        }


        .slider {

            position: absolute;

            inset: 0;

            cursor: pointer;

            background: #ddd0d7;

            border-radius: 30px;

            transition: .25s ease;
        }


        .slider:before {

            content: "";

            position: absolute;

            width: 20px;

            height: 20px;

            left: 3px;

            top: 3px;

            background: #ffffff;

            border-radius: 50%;

            box-shadow:
                0 2px 5px
                rgba(0,0,0,.12);

            transition: .25s ease;
        }


        .switch input:checked + .slider {

            background:
                linear-gradient(
                    135deg,
                    #e00038,
                    #e00036;
                );
        }


        .switch input:checked + .slider:before {

            transform:
                translateX(22px);
        }


        /*
        |--------------------------------------------------------------------------
        | ADMIN ACCOUNT
        |--------------------------------------------------------------------------
        */

        .account-box {

            padding:
                16px;

            background: #fff8fb;

            border:
                1px solid #f1dce7;

            border-radius: 12px;
        }


        .account-row {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                11px 0;

            border-bottom:
                1px solid #f1e4e9;
        }


        .account-row:last-child {

            border-bottom: none;

            padding-bottom: 0;
        }


        .account-row:first-child {

            padding-top: 0;
        }


        .account-icon {

            width: 35px;

            height: 35px;

            border-radius: 9px;

            background: #fff0f7;

            color: #e00038;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;

            flex-shrink: 0;
        }


        .account-label {

            color: #999;

            font-size: 10px;

            text-transform: uppercase;

            font-weight: 700;

            letter-spacing: .3px;
        }


        .account-value {

            color: #452536;

            font-size: 13px;

            font-weight: 700;

            margin-top: 2px;

            word-break: break-word;
        }


        /*
        |--------------------------------------------------------------------------
        | WARNING BOX
        |--------------------------------------------------------------------------
        */

        .warning-box {

            margin-top: 18px;

            padding: 14px;

            border-radius: 12px;

            background: #fffaf0;

            border:
                1px solid #f1dfb8;

            color: #765c28;

            font-size: 11px;

            line-height: 1.6;
        }


        .warning-box strong {

            color: #654b17;
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE BUTTON
        |--------------------------------------------------------------------------
        */

        .save-area {

            grid-column: 1 / -1;

            display: flex;

            justify-content: flex-end;

            margin-top: 2px;
        }


        .save-btn {

            min-width: 190px;

            height: 48px;

            border: none;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #8b0038,
                    #b0004b,
                    #d0005b
                );

            color: #ffffff;

            font-size: 13px;

            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 9px 22px
                rgba(207, 50, 125, 0.18);

            transition: .2s ease;
        }


        .save-btn:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 12px 27px
                rgba(207, 50, 125, 0.24);
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .settings-grid {

                grid-template-columns: 1fr;
            }


            .save-area {

                grid-column: auto;

                justify-content: stretch;
            }


            .save-btn {

                width: 100%;
            }

        }


        @media (max-width: 600px) {

            .topbar {

                padding:
                    13px 16px;
            }


            .admin-name {

                display: none;
            }


            .page {

                width: 94%;

                margin-top: 22px;
            }


            .page-header {

                flex-direction: column;

                align-items: flex-start;
            }


            .page-title h1 {

                font-size: 25px;
            }


            .settings-card {

                padding: 21px;
            }


            .setting-row {

                align-items: flex-start;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     TOP BAR
====================================================== -->

<header class="topbar">


    <div class="brand">

        <div class="brand-icon">

            <i class="fa-solid fa-utensils"></i>

        </div>


        <div class="brand-text">

            <h2>
                Humsafar
            </h2>

            <p>
                Food Delivery Administration
            </p>

        </div>

    </div>


    <div class="admin-area">

        <span class="admin-name">

            <i class="fa-solid fa-user-shield"></i>

            <?php

            echo htmlspecialchars(
                $admin_name,
                ENT_QUOTES,
                "UTF-8"
            );

            ?>

        </span>


        <a
            href="admin-logout.php"
            class="logout-btn"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>

    </div>


</header>


<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<main class="page">


    <!-- PAGE HEADER -->

    <div class="page-header">


        <div class="page-title">

            <h1>
                Settings
            </h1>

            <p>
                Manage Humsafar administrator preferences
                and system notifications.
            </p>

        </div>


        <a
            href="admin-panel.php"
            class="back-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Back to Dashboard

        </a>


    </div>


    <!-- =================================================
         SUCCESS MESSAGE
    ================================================== -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            <?php

            echo htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ERROR MESSAGE
    ================================================== -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php

            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         SETTINGS FORM
    ================================================== -->

    <form
        method="POST"
        action=""
    >


        <div class="settings-grid">


            <!-- =================================================
                 SYSTEM SETTINGS
            ================================================== -->

            <section class="settings-card">


                <div class="card-header">

                    <div class="card-icon">

                        <i class="fa-solid fa-sliders"></i>

                    </div>


                    <div>

                        <h2>
                            System Settings
                        </h2>

                        <p>
                            Control basic Humsafar system behaviour.
                        </p>

                    </div>

                </div>


                <!-- Maintenance -->

                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Maintenance Mode

                        </span>


                        <span class="setting-description">

                            Temporarily place the system in
                            maintenance mode.

                        </span>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            name="maintenance_mode"
                            <?php
                            echo $maintenance_mode
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span class="slider"></span>

                    </label>


                </div>


                <!-- Order notifications -->

                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Order Notifications

                        </span>


                        <span class="setting-description">

                            Enable administrator notifications
                            related to new orders.

                        </span>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            name="order_notifications"
                            <?php
                            echo $order_notifications
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span class="slider"></span>

                    </label>


                </div>


            </section>


            <!-- =================================================
                 NOTIFICATION SETTINGS
            ================================================== -->

            <section class="settings-card">


                <div class="card-header">

                    <div class="card-icon">

                        <i class="fa-solid fa-bell"></i>

                    </div>


                    <div>

                        <h2>
                            Notifications
                        </h2>

                        <p>
                            Manage important administrator alerts.
                        </p>

                    </div>

                </div>


                <!-- Email -->

                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Email Notifications

                        </span>


                        <span class="setting-description">

                            Receive general system notifications
                            through the configured email.

                        </span>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            name="email_notifications"
                            <?php
                            echo $email_notifications
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span class="slider"></span>

                    </label>


                </div>


                <!-- Payments -->

                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Payment Notifications

                        </span>


                        <span class="setting-description">

                            Receive alerts when restaurant owners
                            submit payments.

                        </span>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            name="payment_notifications"
                            <?php
                            echo $payment_notifications
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span class="slider"></span>

                    </label>


                </div>


            </section>


            <!-- =================================================
                 ADMIN ACCOUNT
            ================================================== -->

            <section class="settings-card">


                <div class="card-header">

                    <div class="card-icon">

                        <i class="fa-solid fa-user-shield"></i>

                    </div>


                    <div>

                        <h2>
                            Administrator Account
                        </h2>

                        <p>
                            Current administrator account details.
                        </p>

                    </div>

                </div>


                <div class="account-box">


                    <div class="account-row">


                        <div class="account-icon">

                            <i class="fa-solid fa-user"></i>

                        </div>


                        <div>

                            <div class="account-label">
                                Administrator
                            </div>

                            <div class="account-value">

                                <?php

                                echo htmlspecialchars(
                                    $admin_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </div>

                        </div>


                    </div>


                    <div class="account-row">


                        <div class="account-icon">

                            <i class="fa-solid fa-envelope"></i>

                        </div>


                        <div>

                            <div class="account-label">
                                Email
                            </div>

                            <div class="account-value">

                                <?php

                                echo htmlspecialchars(
                                    $admin_email,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </div>

                        </div>


                    </div>


                    <div class="account-row">


                        <div class="account-icon">

                            <i class="fa-solid fa-shield-halved"></i>

                        </div>


                        <div>

                            <div class="account-label">
                                Access Level
                            </div>

                            <div class="account-value">
                                Full Administrator
                            </div>

                        </div>


                    </div>


                </div>


                <div class="warning-box">

                    <strong>
                        <i class="fa-solid fa-circle-info"></i>
                        Current Login System
                    </strong>

                    <br>

                    The Humsafar administrator credentials are
                    currently defined in
                    <strong>admin-login.php</strong>.

                    Database-based administrator account settings
                    have not been assumed here.

                </div>


            </section>


            <!-- =================================================
                 ADMIN TOOLS
            ================================================== -->

            <section class="settings-card">


                <div class="card-header">

                    <div class="card-icon">

                        <i class="fa-solid fa-toolbox"></i>

                    </div>


                    <div>

                        <h2>
                            Admin Tools
                        </h2>

                        <p>
                            Quickly access important admin sections.
                        </p>

                    </div>

                </div>


                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Restaurant Management

                        </span>


                        <span class="setting-description">

                            Review and manage registered restaurants.

                        </span>

                    </div>


                    <a
                        href="manage-restaurants.php"
                        class="back-btn"
                    >

                        <i class="fa-solid fa-arrow-right"></i>

                        Open

                    </a>


                </div>


                <div class="setting-row">


                    <div class="setting-info">

                        <span class="setting-title">

                            Admin Profile

                        </span>


                        <span class="setting-description">

                            View your administrator profile.

                        </span>

                    </div>


                    <a
                        href="profile.php"
                        class="back-btn"
                    >

                        <i class="fa-solid fa-user"></i>

                        Profile

                    </a>


                </div>


            </section>


            <!-- =================================================
                 SAVE
            ================================================== -->

            <div class="save-area">

                <button
                    type="submit"
                    name="save_settings"
                    class="save-btn"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    &nbsp;

                    Save Settings

                </button>

            </div>


        </div>


    </form>


</main>


</body>

</html>