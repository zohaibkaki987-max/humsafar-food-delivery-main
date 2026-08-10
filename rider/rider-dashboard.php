<?php

session_start();

require_once '../includes/config.php';

/*
|--------------------------------------------------------------------------
| Rider Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['rider_logged_in']) ||
    $_SESSION['rider_logged_in'] !== true
) {
    header('Location: rider-login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Rider ID
|--------------------------------------------------------------------------
*/

$riderId = isset($_SESSION['rider_id'])
    ? (int) $_SESSION['rider_id']
    : 0;

if ($riderId <= 0) {
    session_unset();
    session_destroy();

    header('Location: rider-login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Default Rider Data
|--------------------------------------------------------------------------
*/

$rider = array(
    'id' => $riderId,
    'full_name' => $_SESSION['rider_name'] ?? 'Rider',
    'email' => $_SESSION['rider_email'] ?? '',
    'phone' => $_SESSION['rider_phone'] ?? '',
    'vehicle_type' => $_SESSION['rider_vehicle'] ?? 'bike',
    'address' => '',
    'status' => 'active'
);


/*
|--------------------------------------------------------------------------
| Get Latest Rider Data
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        vehicle_type,
        address,
        status
    FROM riders
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $riderId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $databaseRider = $result->fetch_assoc();

    $stmt->close();

    if ($databaseRider) {

        $rider = $databaseRider;

        /*
        |--------------------------------------------------------------------------
        | Keep Session Updated
        |--------------------------------------------------------------------------
        */

        $_SESSION['rider_name'] =
            $rider['full_name'];

        $_SESSION['rider_email'] =
            $rider['email'];

        $_SESSION['rider_phone'] =
            $rider['phone'];

        $_SESSION['rider_vehicle'] =
            $rider['vehicle_type'];
    }
}


/*
|--------------------------------------------------------------------------
| Status Protection
|--------------------------------------------------------------------------
*/

if ($rider['status'] !== 'active') {

    if ($rider['status'] === 'pending') {

        $statusTitle =
            'Waiting for Approval';

        $statusMessage =
            'Your rider account is still waiting for admin approval.';

    } elseif ($rider['status'] === 'blocked') {

        $statusTitle =
            'Account Blocked';

        $statusMessage =
            'Your rider account has been blocked. Please contact Humsafar administration.';

    } else {

        $statusTitle =
            'Account Inactive';

        $statusMessage =
            'Your rider account is currently inactive.';
    }

} else {

    $statusTitle =
        'Account Active';

    $statusMessage =
        'Your rider account is active and ready for delivery orders.';
}


/*
|--------------------------------------------------------------------------
| Vehicle Name
|--------------------------------------------------------------------------
*/

$vehicleName = 'Bike';


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['logout']) &&
    $_GET['logout'] === '1'
) {

    unset(
        $_SESSION['rider_logged_in'],
        $_SESSION['rider_id'],
        $_SESSION['rider_name'],
        $_SESSION['rider_email'],
        $_SESSION['rider_phone'],
        $_SESSION['rider_vehicle']
    );

    header('Location: rider-login.php');
    exit;
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
        Rider Dashboard - Humsafar
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f7f7f9;

            color: #252525;
        }


        .topbar {
            height: 70px;

            background: #ffffff;

            border-bottom:
                1px solid #eeeeee;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .logo {
            color: #e00038;

            font-size: 25px;

            font-weight: 800;

            text-decoration: none;
        }


        .top-right {
            display: flex;

            align-items: center;

            gap: 15px;
        }


        .rider-name {
            font-size: 13px;

            font-weight: 700;

            color: #444444;
        }


        .logout-btn {
            text-decoration: none;

            background: #fff0f2;

            color: #d00035;

            border: 1px solid #ffd6df;

            padding: 9px 14px;

            border-radius: 8px;

            font-size: 12px;

            font-weight: 700;
        }


        .logout-btn:hover {
            background: #ffe2e8;
        }


        .layout {
            display: flex;

            min-height:
                calc(100vh - 70px);
        }


        .sidebar {
            width: 235px;

            background: #ffffff;

            border-right:
                1px solid #eeeeee;

            padding: 25px 15px;
        }


        .sidebar-title {
            color: #999999;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin:
                5px 12px 12px;
        }


        .menu-link {
            display: block;

            text-decoration: none;

            color: #555555;

            padding: 12px 13px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 5px;
        }


        .menu-link:hover,
        .menu-link.active {
            background: #fff0f3;

            color: #e00038;
        }


        .content {
            flex: 1;

            padding: 30px;

            max-width: 1400px;

            margin: 0 auto;

            width: 100%;
        }


        .welcome {
            margin-bottom: 25px;
        }


        .welcome h1 {
            margin:
                0 0 7px;

            font-size: 27px;
        }


        .welcome p {
            margin: 0;

            color: #777777;

            font-size: 13px;
        }


        .status-card {
            background: #ffffff;

            border-radius: 14px;

            border: 1px solid #eeeeee;

            padding: 20px;

            margin-bottom: 22px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;
        }


        .status-left {
            display: flex;

            align-items: center;

            gap: 15px;
        }


        .status-icon {
            width: 46px;

            height: 46px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eaf8ef;

            color: #17833f;

            font-size: 20px;

            font-weight: 800;
        }


        .status-card h3 {
            margin:
                0 0 5px;

            font-size: 15px;
        }


        .status-card p {
            margin: 0;

            color: #777777;

            font-size: 12px;

            line-height: 1.5;
        }


        .status-badge {
            padding: 8px 13px;

            border-radius: 20px;

            background: #eaf8ef;

            color: #17833f;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;
        }


        .cards {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 22px;
        }


        .card {
            background: #ffffff;

            border:
                1px solid #eeeeee;

            border-radius: 14px;

            padding: 20px;
        }


        .card-label {
            color: #888888;

            font-size: 11px;

            margin-bottom: 9px;
        }


        .card-value {
            font-size: 19px;

            font-weight: 700;

            color: #252525;
        }


        .main-grid {
            display: grid;

            grid-template-columns:
                1.5fr 1fr;

            gap: 20px;
        }


        .panel {
            background: #ffffff;

            border:
                1px solid #eeeeee;

            border-radius: 14px;

            padding: 22px;
        }


        .panel-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 18px;
        }


        .panel-header h2 {
            margin: 0;

            font-size: 17px;
        }


        .panel-header span {
            font-size: 11px;

            color: #999999;
        }


        .empty-orders {
            text-align: center;

            padding: 35px 15px;

            color: #999999;
        }


        .empty-icon {
            font-size: 34px;

            margin-bottom: 10px;
        }


        .empty-orders h3 {
            margin:
                0 0 6px;

            color: #555555;

            font-size: 14px;
        }


        .empty-orders p {
            margin: 0;

            font-size: 12px;

            line-height: 1.5;
        }


        .profile-row {
            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding:
                12px 0;

            border-bottom:
                1px solid #f1f1f1;
        }


        .profile-row:last-child {
            border-bottom: 0;
        }


        .profile-label {
            color: #888888;

            font-size: 12px;
        }


        .profile-value {
            color: #333333;

            font-size: 12px;

            font-weight: 600;

            text-align: right;

            word-break: break-word;
        }


        .quick-actions {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

            margin-top: 18px;
        }


        .action-btn {
            text-decoration: none;

            text-align: center;

            padding: 12px 8px;

            border-radius: 8px;

            background: #e00038;

            color: #ffffff;

            font-size: 12px;

            font-weight: 700;
        }


        .action-btn.secondary {
            background: #fff0f3;

            color: #e00038;

            border:
                1px solid #ffd6df;
        }


        @media (max-width: 900px) {

            .cards {
                grid-template-columns:
                    1fr 1fr;
            }


            .main-grid {
                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 700px) {

            .sidebar {
                display: none;
            }


            .content {
                padding: 20px 15px;
            }


            .topbar {
                padding: 0 15px;
            }


            .rider-name {
                display: none;
            }


            .cards {
                grid-template-columns:
                    1fr;
            }


            .status-card {
                align-items: flex-start;

                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| Top Navigation
|--------------------------------------------------------------------------
-->

<header class="topbar">


    <a
        href="rider-dashboard.php"
        class="logo"
    >
        Humsafar
    </a>


    <div class="top-right">

        <span class="rider-name">

            Welcome,
            <?= htmlspecialchars(
                $rider['full_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>


        <a
            href="rider-dashboard.php?logout=1"
            class="logout-btn"
        >
            Logout
        </a>

    </div>


</header>


<div class="layout">


<!--
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
-->

<aside class="sidebar">


    <div class="sidebar-title">
        Rider Menu
    </div>


    <a
        href="rider-dashboard.php"
        class="menu-link active"
    >
        Dashboard
    </a>


    <a
        href="#orders"
        class="menu-link"
    >
        My Orders
    </a>


    <a
        href="#profile"
        class="menu-link"
    >
        My Profile
    </a>


    <a
        href="#earnings"
        class="menu-link"
    >
        Earnings
    </a>


    <a
        href="#support"
        class="menu-link"
    >
        Support
    </a>


    <div
        class="sidebar-title"
        style="margin-top: 25px;"
    >
        Account
    </div>


    <a
        href="rider-dashboard.php?logout=1"
        class="menu-link"
    >
        Logout
    </a>


</aside>


<!--
|--------------------------------------------------------------------------
| Main Content
|--------------------------------------------------------------------------
-->

<main class="content">


    <div class="welcome">

        <h1>

            Welcome,
            <?= htmlspecialchars(
                $rider['full_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </h1>

        <p>
            Manage your rider account and delivery activities from here.
        </p>

    </div>


    <!-- Status -->

    <div class="status-card">


        <div class="status-left">


            <div class="status-icon">
                ✓
            </div>


            <div>

                <h3>
                    <?= htmlspecialchars(
                        $statusTitle,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>


                <p>
                    <?= htmlspecialchars(
                        $statusMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

            </div>


        </div>


        <span class="status-badge">

            <?= htmlspecialchars(
                strtoupper($rider['status']),
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>


    </div>


    <!-- Summary Cards -->

    <div class="cards">


        <div class="card">

            <div class="card-label">
                Rider ID
            </div>

            <div class="card-value">
                #<?= (int)$rider['id'] ?>
            </div>

        </div>


        <div class="card">

            <div class="card-label">
                Vehicle
            </div>

            <div class="card-value">
                <?= htmlspecialchars(
                    $vehicleName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </div>


        <div class="card">

            <div class="card-label">
                Account Status
            </div>

            <div class="card-value">
                <?= htmlspecialchars(
                    ucfirst($rider['status']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </div>


    </div>


    <div class="main-grid">


        <!-- Orders -->

        <section
            class="panel"
            id="orders"
        >


            <div class="panel-header">

                <h2>
                    Delivery Orders
                </h2>

                <span>
                    Latest orders
                </span>

            </div>


            <div class="empty-orders">


                <div class="empty-icon">
                    🛵
                </div>


                <h3>
                    No delivery orders yet
                </h3>


                <p>
                    New delivery orders assigned to you
                    will appear here.
                </p>


            </div>


        </section>


        <!-- Profile -->

        <section
            class="panel"
            id="profile"
        >


            <div class="panel-header">

                <h2>
                    Rider Profile
                </h2>

                <span>
                    Account information
                </span>

            </div>


            <div class="profile-row">

                <span class="profile-label">
                    Name
                </span>

                <span class="profile-value">

                    <?= htmlspecialchars(
                        $rider['full_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


            <div class="profile-row">

                <span class="profile-label">
                    Email
                </span>

                <span class="profile-value">

                    <?= htmlspecialchars(
                        $rider['email'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


            <div class="profile-row">

                <span class="profile-label">
                    Phone
                </span>

                <span class="profile-value">

                    <?= htmlspecialchars(
                        $rider['phone'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


            <div class="profile-row">

                <span class="profile-label">
                    Vehicle
                </span>

                <span class="profile-value">
                    Bike
                </span>

            </div>


            <div class="profile-row">

                <span class="profile-label">
                    Address
                </span>

                <span class="profile-value">

                    <?= htmlspecialchars(
                        $rider['address'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


        </section>


    </div>


    <!-- Quick Actions -->

    <section
        class="panel"
        id="earnings"
        style="margin-top: 20px;"
    >


        <div class="panel-header">

            <h2>
                Quick Actions
            </h2>

            <span>
                Rider tools
            </span>

        </div>


        <div class="quick-actions">


            <a
                href="#orders"
                class="action-btn"
            >
                View Orders
            </a>


            <a
                href="#profile"
                class="action-btn secondary"
            >
                View Profile
            </a>


        </div>


    </section>


    <!-- Support -->

    <section
        class="panel"
        id="support"
        style="margin-top: 20px;"
    >


        <div class="panel-header">

            <h2>
                Humsafar Support
            </h2>

        </div>


        <p
            style="
                margin:0;
                color:#777;
                font-size:13px;
                line-height:1.7;
            "
        >

            If you have any problem with your rider account
            or delivery orders, please contact Humsafar administration.

        </p>


    </section>


</main>


</div>


</body>

</html>