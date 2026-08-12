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

    'full_name' =>
        $_SESSION['rider_name'] ?? 'Rider',

    'email' =>
        $_SESSION['rider_email'] ?? '',

    'phone' =>
        $_SESSION['rider_phone'] ?? '',

    'vehicle_type' =>
        $_SESSION['rider_vehicle'] ?? 'bike',

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

    $result =
        $stmt->get_result();

    $databaseRider =
        $result->fetch_assoc();

    $stmt->close();


    if ($databaseRider) {

        $rider =
            $databaseRider;


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
| Rider Status
|--------------------------------------------------------------------------
*/

$riderStatus =
    strtolower(
        (string)$rider['status']
    );


$isActive =
    $riderStatus === 'active';


if ($riderStatus === 'pending') {

    $statusTitle =
        'Waiting for Approval';

    $statusMessage =
        'Your rider account is waiting for admin approval.';

} elseif ($riderStatus === 'blocked') {

    $statusTitle =
        'Account Blocked';

    $statusMessage =
        'Your rider account has been blocked. Please contact Humsafar administration.';

} elseif (!$isActive) {

    $statusTitle =
        'Account Inactive';

    $statusMessage =
        'Your rider account is currently inactive.';

} else {

    $statusTitle =
        'Account Active';

    $statusMessage =
        'Your rider account is active and ready for delivery orders.';
}


/*
|--------------------------------------------------------------------------
| Vehicle
|--------------------------------------------------------------------------
*/

$vehicleType =
    strtolower(
        trim(
            (string)$rider['vehicle_type']
        )
    );


$vehicleNames = array(

    'bike' =>
        'Bike',

    'motorbike' =>
        'Motorbike',

    'motorcycle' =>
        'Motorcycle',

    'scooter' =>
        'Scooter',

    'car' =>
        'Car',

    'van' =>
        'Van'

);


$vehicleName =
    $vehicleNames[$vehicleType]
    ?? ucfirst($vehicleType ?: 'Bike');


/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            (string)$rider['full_name']
        )
    );


$initials = '';


if (!empty($nameParts[0])) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );
}


if (
    !empty($nameParts[1])
) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[1],
                0,
                1
            )
        );
}


if ($initials === '') {

    $initials = 'R';
}


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

    header(
        'Location: rider-login.php'
    );

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


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f6f7f9;

    color: #252525;
}


/* =========================================================
   MAIN LAYOUT
========================================================= */

.rider-page {

    min-height: 100vh;

    padding-left: 223px;
}


/* =========================================================
   TOPBAR
========================================================= */

.rider-topbar {

    height: 72px;

    background: #fff;

    border-bottom:
        1px solid #eeeeee;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    padding:
        0 30px;

    position: sticky;

    top: 0;

    z-index: 100;
}


.rider-top-title {

    display: flex;

    align-items: center;

    gap: 12px;
}


.rider-top-title-icon {

    width: 40px;

    height: 40px;

    border-radius: 10px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}


.rider-top-title h2 {

    margin: 0;

    font-size: 18px;

    font-weight: 800;
}


.rider-top-title span {

    display: block;

    margin-top: 3px;

    color: #999;

    font-size: 10px;
}


.rider-top-right {

    display: flex;

    align-items: center;

    gap: 14px;
}


.rider-top-user {

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 13px;

    font-weight: 700;
}


.rider-top-avatar {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    background: #ffd900;

    color: #111;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 900;
}


.rider-logout {

    height: 36px;

    padding:
        0 13px;

    border-radius: 8px;

    background: #fff0f3;

    border:
        1px solid #ffd4df;

    color: #ed0038;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 7px;

    font-size: 12px;

    font-weight: 700;
}


.rider-logout:hover {

    background: #ffe1e8;
}


/* =========================================================
   CONTENT
========================================================= */

.rider-content {

    padding: 28px;

    max-width: 1500px;

    margin: 0 auto;
}


/* =========================================================
   WELCOME
========================================================= */

.rider-welcome {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 20px;

    margin-bottom: 22px;
}


.rider-welcome h1 {

    margin: 0;

    font-size: 27px;

    font-weight: 800;
}


.rider-welcome p {

    margin:
        6px 0 0;

    color: #777;

    font-size: 13px;
}


.rider-date {

    padding:
        10px 14px;

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 9px;

    color: #777;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   ACCOUNT STATUS
========================================================= */

.rider-status {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 18px 20px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 20px;
}


.rider-status-left {

    display: flex;

    align-items: center;

    gap: 13px;
}


.rider-status-icon {

    width: 44px;

    height: 44px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eaf8ef;

    color: #198842;

    font-size: 17px;
}


.rider-status-icon.pending {

    background: #fff7df;

    color: #d79a00;
}


.rider-status-icon.blocked {

    background: #fff0f2;

    color: #e00038;
}


.rider-status-text h3 {

    margin:
        0 0 4px;

    font-size: 15px;
}


.rider-status-text p {

    margin: 0;

    color: #777;

    font-size: 11px;
}


.rider-status-badge {

    padding:
        8px 13px;

    border-radius: 20px;

    background: #eaf8ef;

    color: #198842;

    font-size: 10px;

    font-weight: 800;

    text-transform: uppercase;
}


.rider-status-badge.pending {

    background: #fff7df;

    color: #bd8600;
}


.rider-status-badge.blocked {

    background: #fff0f2;

    color: #e00038;
}


/* =========================================================
   STAT CARDS
========================================================= */

.rider-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}


.rider-stat-card {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 19px;

    position: relative;

    overflow: hidden;
}


.rider-stat-icon {

    width: 42px;

    height: 42px;

    border-radius: 10px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 16px;

    margin-bottom: 13px;
}


.rider-stat-label {

    color: #888;

    font-size: 11px;

    font-weight: 600;

    margin-bottom: 6px;
}


.rider-stat-value {

    color: #252525;

    font-size: 24px;

    font-weight: 800;
}


.rider-stat-note {

    margin-top: 5px;

    color: #aaa;

    font-size: 10px;
}


/* =========================================================
   MAIN GRID
========================================================= */

.rider-main-grid {

    display: grid;

    grid-template-columns:
        1.55fr 1fr;

    gap: 20px;

    margin-bottom: 20px;
}


/* =========================================================
   PANEL
========================================================= */

.rider-panel {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 21px;
}


.rider-panel-header {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 10px;

    margin-bottom: 17px;
}


.rider-panel-header h2 {

    margin: 0;

    font-size: 17px;

    font-weight: 800;
}


.rider-panel-header span {

    color: #999;

    font-size: 10px;
}


.rider-view-btn {

    text-decoration: none;

    color: #ed0038;

    font-size: 11px;

    font-weight: 800;
}


/* =========================================================
   ACTIVE DELIVERY
========================================================= */

.active-delivery {

    border:
        1px solid #f0f0f0;

    border-radius: 11px;

    padding: 16px;

    background: #fffafa;
}


.delivery-top {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    margin-bottom: 14px;
}


.delivery-order {

    color: #252525;

    font-size: 14px;

    font-weight: 800;
}


.delivery-status {

    padding:
        6px 9px;

    border-radius: 20px;

    background: #fff0f3;

    color: #ed0038;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}


.delivery-route {

    display: flex;

    flex-direction: column;

    gap: 13px;

    margin-bottom: 15px;
}


.route-row {

    display: flex;

    align-items: flex-start;

    gap: 10px;
}


.route-dot {

    width: 10px;

    height: 10px;

    margin-top: 3px;

    border-radius: 50%;

    background: #ed0038;

    flex-shrink: 0;
}


.route-dot.green {

    background: #24a15b;
}


.route-info {

    flex: 1;
}


.route-label {

    color: #999;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

    margin-bottom: 3px;
}


.route-address {

    color: #333;

    font-size: 12px;

    font-weight: 700;

    line-height: 1.4;
}


.delivery-actions {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 9px;
}


.delivery-btn {

    min-height: 38px;

    border-radius: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    text-decoration: none;

    background: #ed0038;

    color: #fff;

    font-size: 11px;

    font-weight: 800;
}


.delivery-btn.secondary {

    background: #fff0f3;

    color: #ed0038;

    border:
        1px solid #ffd4df;
}


/* =========================================================
   NO ACTIVE DELIVERY
========================================================= */

.no-delivery {

    padding:
        35px 15px;

    text-align: center;

    border:
        1px dashed #dddddd;

    border-radius: 11px;
}


.no-delivery-icon {

    width: 52px;

    height: 52px;

    margin:
        0 auto 12px;

    border-radius: 50%;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;
}


.no-delivery h3 {

    margin:
        0 0 5px;

    font-size: 14px;
}


.no-delivery p {

    margin: 0;

    color: #999;

    font-size: 11px;

    line-height: 1.5;
}


/* =========================================================
   PROFILE
========================================================= */

.profile-box {

    display: flex;

    align-items: center;

    gap: 12px;

    padding-bottom: 16px;

    border-bottom:
        1px solid #f0f0f0;

    margin-bottom: 6px;
}


.profile-avatar {

    width: 50px;

    height: 50px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #ffd900;

    color: #111;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 15px;

    font-weight: 900;
}


.profile-name {

    font-size: 14px;

    font-weight: 800;

    margin-bottom: 4px;
}


.profile-role {

    color: #999;

    font-size: 10px;
}


.profile-row {

    min-height: 42px;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 12px;

    border-bottom:
        1px solid #f2f2f2;
}


.profile-row:last-child {

    border-bottom: 0;
}


.profile-label {

    color: #999;

    font-size: 11px;
}


.profile-value {

    max-width: 60%;

    text-align: right;

    color: #333;

    font-size: 11px;

    font-weight: 700;

    word-break: break-word;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}


.quick-action {

    min-height: 82px;

    padding: 13px;

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 12px;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 11px;

    transition:
        .18s ease;
}


.quick-action:hover {

    border-color: #ffc5d2;

    transform:
        translateY(-1px);
}


.quick-action-icon {

    width: 39px;

    height: 39px;

    border-radius: 9px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    font-size: 14px;
}


.quick-action-text strong {

    display: block;

    color: #333;

    font-size: 11px;

    margin-bottom: 4px;
}


.quick-action-text span {

    color: #999;

    font-size: 9px;
}


/* =========================================================
   RIDER TIPS
========================================================= */

.rider-tips {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 14px;
}


.tip {

    padding: 16px;

    border-radius: 11px;

    background: #fff;

    border:
        1px solid #eeeeee;
}


.tip-icon {

    color: #ed0038;

    font-size: 16px;

    margin-bottom: 9px;
}


.tip h3 {

    margin:
        0 0 5px;

    font-size: 12px;
}


.tip p {

    margin: 0;

    color: #888;

    font-size: 10px;

    line-height: 1.5;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .rider-stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .quick-actions {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 900px) {

    .rider-page {

        padding-left: 0;
    }


    .rider-main-grid {

        grid-template-columns: 1fr;
    }


    .rider-content {

        padding: 22px 18px;
    }

}


@media (max-width: 650px) {

    .rider-topbar {

        padding:
            0 15px;
    }


    .rider-top-title h2 {

        font-size: 15px;
    }


    .rider-top-title span {

        display: none;
    }


    .rider-top-user {

        display: none;
    }


    .rider-content {

        padding:
            18px 13px;
    }


    .rider-welcome {

        align-items: flex-start;

        flex-direction: column;
    }


    .rider-welcome h1 {

        font-size: 23px;
    }


    .rider-date {

        width: 100%;

        text-align: center;
    }


    .rider-status {

        align-items: flex-start;

        flex-direction: column;
    }


    .rider-stats {

        grid-template-columns: 1fr 1fr;

        gap: 10px;
    }


    .rider-stat-card {

        padding: 14px;
    }


    .rider-stat-value {

        font-size: 20px;
    }


    .quick-actions {

        grid-template-columns:
            1fr 1fr;

        gap: 9px;
    }


    .rider-tips {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 430px) {

    .rider-stats {

        grid-template-columns: 1fr;
    }


    .quick-actions {

        grid-template-columns: 1fr;
    }


    .delivery-actions {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<?php
/*
|--------------------------------------------------------------------------
| Existing Rider Sidebar
|--------------------------------------------------------------------------
*/

include_once 'rider-sidebar.php';
?>


<div class="rider-page">


    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <header class="rider-topbar">


        <div class="rider-top-title">

            <div class="rider-top-title-icon">

                <i class="fas fa-gauge-high"></i>

            </div>


            <div>

                <h2>
                    Rider Dashboard
                </h2>

                <span>
                    Manage your delivery activities
                </span>

            </div>

        </div>


        <div class="rider-top-right">


            <div class="rider-top-user">

                <div class="rider-top-avatar">

                    <?= htmlspecialchars(
                        $initials,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <?= htmlspecialchars(
                    $rider['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


            <a
                href="rider-dashboard.php?logout=1"
                class="rider-logout"
            >

                <i class="fas fa-right-from-bracket"></i>

                Logout

            </a>

        </div>


    </header>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="rider-content">


        <!-- =================================================
             WELCOME
        ================================================== -->

        <section class="rider-welcome">


            <div>

                <h1>

                    Welcome,
                    <?= htmlspecialchars(
                        $rider['full_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </h1>


                <p>
                    Here's your rider overview for today.
                </p>

            </div>


            <div class="rider-date">

                <i class="far fa-calendar"></i>

                <?= date('l, d M Y') ?>

            </div>


        </section>


        <!-- =================================================
             ACCOUNT STATUS
        ================================================== -->

        <section class="rider-status">


            <div class="rider-status-left">


                <div
                    class="rider-status-icon
                    <?= $riderStatus !== 'active'
                        ? htmlspecialchars(
                            $riderStatus,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        : ''
                    ?>"
                >

                    <?php if ($riderStatus === 'active'): ?>

                        <i class="fas fa-check"></i>

                    <?php elseif ($riderStatus === 'pending'): ?>

                        <i class="fas fa-clock"></i>

                    <?php elseif ($riderStatus === 'blocked'): ?>

                        <i class="fas fa-ban"></i>

                    <?php else: ?>

                        <i class="fas fa-circle-exclamation"></i>

                    <?php endif; ?>

                </div>


                <div class="rider-status-text">

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


            <div
                class="rider-status-badge
                <?= $riderStatus !== 'active'
                    ? htmlspecialchars(
                        $riderStatus,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    : ''
                ?>"
            >

                <?= htmlspecialchars(
                    strtoupper(
                        $riderStatus
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


        </section>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="rider-stats">


            <div class="rider-stat-card">

                <div class="rider-stat-icon">

                    <i class="fas fa-box"></i>

                </div>


                <div class="rider-stat-label">
                    Available Orders
                </div>


                <div class="rider-stat-value">
                    0
                </div>


                <div class="rider-stat-note">
                    Orders waiting for pickup
                </div>

            </div>


            <div class="rider-stat-card">

                <div class="rider-stat-icon">

                    <i class="fas fa-motorcycle"></i>

                </div>


                <div class="rider-stat-label">
                    Active Delivery
                </div>


                <div class="rider-stat-value">
                    0
                </div>


                <div class="rider-stat-note">
                    Currently in progress
                </div>

            </div>


            <div class="rider-stat-card">

                <div class="rider-stat-icon">

                    <i class="fas fa-circle-check"></i>

                </div>


                <div class="rider-stat-label">
                    Completed Today
                </div>


                <div class="rider-stat-value">
                    0
                </div>


                <div class="rider-stat-note">
                    Successfully delivered
                </div>

            </div>


            <div class="rider-stat-card">

                <div class="rider-stat-icon">

                    <i class="fas fa-wallet"></i>

                </div>


                <div class="rider-stat-label">
                    Today's Earnings
                </div>


                <div class="rider-stat-value">
                    Rs. 0
                </div>


                <div class="rider-stat-note">
                    Delivery earnings
                </div>

            </div>


        </section>


        <!-- =================================================
             MAIN DASHBOARD
        ================================================== -->

        <div class="rider-main-grid">


            <!-- =============================================
                 ACTIVE DELIVERY
            ============================================== -->

            <section class="rider-panel">


                <div class="rider-panel-header">

                    <h2>
                        Active Delivery
                    </h2>

                    <span>
                        Current assignment
                    </span>

                </div>


                <?php if ($isActive): ?>


                    <div class="no-delivery">


                        <div class="no-delivery-icon">

                            <i class="fas fa-motorcycle"></i>

                        </div>


                        <h3>
                            No Active Delivery
                        </h3>


                        <p>
                            When an order is assigned to you,
                            pickup and customer delivery details
                            will appear here.
                        </p>


                    </div>


                <?php else: ?>


                    <div class="no-delivery">


                        <div class="no-delivery-icon">

                            <i class="fas fa-lock"></i>

                        </div>


                        <h3>
                            Deliveries Unavailable
                        </h3>


                        <p>
                            Your rider account must be active
                            before you can receive delivery orders.
                        </p>


                    </div>


                <?php endif; ?>


            </section>


            <!-- =============================================
                 RIDER PROFILE
            ============================================== -->

            <section class="rider-panel">


                <div class="rider-panel-header">

                    <h2>
                        Rider Profile
                    </h2>


                    <a
                        href="rider-profile.php"
                        class="rider-view-btn"
                    >

                        View Profile

                    </a>

                </div>


                <div class="profile-box">


                    <div class="profile-avatar">

                        <?= htmlspecialchars(
                            $initials,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <div>

                        <div class="profile-name">

                            <?= htmlspecialchars(
                                $rider['full_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div class="profile-role">

                            Humsafar Rider

                        </div>

                    </div>


                </div>


                <div class="profile-row">

                    <span class="profile-label">
                        Rider ID
                    </span>

                    <span class="profile-value">
                        #<?= (int)$rider['id'] ?>
                    </span>

                </div>


                <div class="profile-row">

                    <span class="profile-label">
                        Phone
                    </span>

                    <span class="profile-value">

                        <?= htmlspecialchars(
                            $rider['phone'] ?: 'Not added',
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

                        <?= htmlspecialchars(
                            $vehicleName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


                <div class="profile-row">

                    <span class="profile-label">
                        Status
                    </span>

                    <span class="profile-value">

                        <?= htmlspecialchars(
                            ucfirst($riderStatus),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


            </section>


        </div>


        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <section>


            <div
                class="rider-panel-header"
                style="margin-bottom:12px;"
            >

                <h2>
                    Quick Actions
                </h2>

                <span>
                    Rider tools
                </span>

            </div>


            <div class="quick-actions">


                <a
                    href="rider-orders.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <i class="fas fa-box-open"></i>

                    </div>


                    <div class="quick-action-text">

                        <strong>
                            Available Orders
                        </strong>

                        <span>
                            Find delivery orders
                        </span>

                    </div>

                </a>


                <a
                    href="rider-deliveries.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <i class="fas fa-route"></i>

                    </div>


                    <div class="quick-action-text">

                        <strong>
                            My Deliveries
                        </strong>

                        <span>
                            View delivery history
                        </span>

                    </div>

                </a>


                <a
                    href="rider-earnings.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <i class="fas fa-wallet"></i>

                    </div>


                    <div class="quick-action-text">

                        <strong>
                            Earnings
                        </strong>

                        <span>
                            Check your earnings
                        </span>

                    </div>

                </a>


                <a
                    href="rider-profile.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">

                        <i class="fas fa-user"></i>

                    </div>


                    <div class="quick-action-text">

                        <strong>
                            My Profile
                        </strong>

                        <span>
                            Manage your account
                        </span>

                    </div>

                </a>


            </div>


        </section>


        <!-- =================================================
             RIDER INFORMATION
        ================================================== -->

        <section
            class="rider-panel"
            style="margin-bottom:20px;"
        >


            <div class="rider-panel-header">

                <h2>
                    Rider Information
                </h2>

                <span>
                    Your account details
                </span>

            </div>


            <div
                style="
                    display:grid;
                    grid-template-columns:
                    repeat(3,1fr);
                    gap:15px;
                "
            >


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
                            $rider['email'] ?: 'Not added',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


                <div class="profile-row">

                    <span class="profile-label">
                        Address
                    </span>

                    <span class="profile-value">

                        <?= htmlspecialchars(
                            $rider['address'] ?: 'Not added',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


            </div>


        </section>


        <!-- =================================================
             RIDER TIPS
        ================================================== -->

        <section>


            <div
                class="rider-panel-header"
                style="margin-bottom:12px;"
            >

                <h2>
                    Rider Tips
                </h2>

                <span>
                    Delivery best practices
                </span>

            </div>


            <div class="rider-tips">


                <div class="tip">

                    <div class="tip-icon">

                        <i class="fas fa-location-dot"></i>

                    </div>


                    <h3>
                        Check Pickup Location
                    </h3>


                    <p>
                        Always verify the restaurant
                        pickup address before starting
                        your delivery.
                    </p>

                </div>


                <div class="tip">

                    <div class="tip-icon">

                        <i class="fas fa-phone"></i>

                    </div>


                    <h3>
                        Stay Connected
                    </h3>


                    <p>
                        Keep your phone available so
                        customers and Humsafar can
                        contact you when required.
                    </p>

                </div>


                <div class="tip">

                    <div class="tip-icon">

                        <i class="fas fa-shield-halved"></i>

                    </div>


                    <h3>
                        Deliver Safely
                    </h3>


                    <p>
                        Follow traffic rules and handle
                        every customer's order carefully.
                    </p>

                </div>


            </div>


        </section>


    </main>

</div>


</body>

</html>