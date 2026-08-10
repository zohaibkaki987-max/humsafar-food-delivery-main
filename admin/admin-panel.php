<?php
session_start();

require_once __DIR__ . '/../includes/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists($connection, $table)
{
    $table = $connection->real_escape_string($table);

    $result = $connection->query("SHOW TABLES LIKE '$table'");

    return $result && $result->num_rows > 0;
}

function columnExists($connection, $table, $column)
{
    $statement = $connection->prepare(
        "SHOW COLUMNS FROM `$table` LIKE ?"
    );

    if (!$statement) {
        return false;
    }

    $statement->bind_param('s', $column);
    $statement->execute();

    $result = $statement->get_result();

    $exists = $result && $result->num_rows > 0;

    $statement->close();

    return $exists;
}


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

$is_admin = !empty($_SESSION['admin_logged_in']);


/*
|--------------------------------------------------------------------------
| OPTIONAL DATABASE ADMIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !$is_admin &&
    !empty($_SESSION['user_id']) &&
    tableExists($conn, 'users')
) {
    $user_id = (int)$_SESSION['user_id'];

    $statement = $conn->prepare(
        "SELECT role, full_name, email
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    if ($statement) {

        $statement->bind_param('i', $user_id);

        $statement->execute();

        $result = $statement->get_result();

        $user = $result
            ? $result->fetch_assoc()
            : null;

        $statement->close();

        if (
            $user &&
            strtolower((string)($user['role'] ?? '')) === 'admin'
        ) {

            $is_admin = true;

            $_SESSION['admin_logged_in'] = true;

            $_SESSION['admin_name'] =
                $user['full_name'] ?? 'Administrator';

            $_SESSION['admin_email'] =
                $user['email'] ?? '';
        }
    }
}


/*
|--------------------------------------------------------------------------
| NOT ADMIN
|--------------------------------------------------------------------------
*/

if (!$is_admin) {

    header("Location: ../admin-login.php");

    exit;
}


$admin_name =
    $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? 'Administrator';


$message = '';
$message_type = '';


/*
|--------------------------------------------------------------------------
| APPROVE / REJECT / BLOCK / ACTIVATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entity =
        $_POST['entity']
        ?? '';

    $id =
        (int)($_POST['id'] ?? 0);

    $action =
        $_POST['action']
        ?? '';


    if (
        $id <= 0 ||
        !in_array(
            $entity,
            ['restaurant', 'rider'],
            true
        ) ||
        !in_array(
            $action,
            [
                'approve',
                'reject',
                'block',
                'activate'
            ],
            true
        )
    ) {

        $message =
            'Invalid management request.';

        $message_type =
            'error';

    } else {

        $table =
            $entity === 'restaurant'
                ? 'restaurant_users'
                : 'riders';


        if (!tableExists($conn, $table)) {

            $message =
                "$table table was not found.";

            $message_type =
                'error';

        } else {

            if ($action === 'reject') {

                $new_status = 'inactive';

            } elseif ($action === 'block') {

                $new_status = 'blocked';

            } else {

                $new_status = 'active';
            }


            $statement = $conn->prepare(
                "UPDATE `$table`
                 SET status = ?
                 WHERE id = ?
                 LIMIT 1"
            );


            if (!$statement) {

                $message =
                    'Database error: ' . $conn->error;

                $message_type =
                    'error';

            } else {

                $statement->bind_param(
                    'si',
                    $new_status,
                    $id
                );


                if ($statement->execute()) {

                    if ($action === 'approve') {

                        $message =
                            ucfirst($entity)
                            . ' approved successfully.';

                    } elseif ($action === 'reject') {

                        $message =
                            ucfirst($entity)
                            . ' rejected successfully.';

                    } elseif ($action === 'block') {

                        $message =
                            ucfirst($entity)
                            . ' blocked successfully.';

                    } else {

                        $message =
                            ucfirst($entity)
                            . ' activated successfully.';
                    }


                    $message_type =
                        'success';

                } else {

                    $message =
                        'Unable to update status: '
                        . $statement->error;

                    $message_type =
                        'error';
                }


                $statement->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| RESTAURANT COUNTS
|--------------------------------------------------------------------------
*/

$restaurant_total = 0;
$restaurant_pending = 0;
$restaurant_active = 0;
$restaurant_inactive = 0;
$restaurant_blocked = 0;


if (tableExists($conn, 'restaurant_users')) {

    $result = $conn->query(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'pending') AS pending_count,
            SUM(status = 'active') AS active_count,
            SUM(status = 'inactive') AS inactive_count,
            SUM(status = 'blocked') AS blocked_count
         FROM restaurant_users"
    );


    if ($result) {

        $data =
            $result->fetch_assoc();

        $restaurant_total =
            (int)($data['total'] ?? 0);

        $restaurant_pending =
            (int)($data['pending_count'] ?? 0);

        $restaurant_active =
            (int)($data['active_count'] ?? 0);

        $restaurant_inactive =
            (int)($data['inactive_count'] ?? 0);

        $restaurant_blocked =
            (int)($data['blocked_count'] ?? 0);
    }
}


/*
|--------------------------------------------------------------------------
| RIDER COUNTS
|--------------------------------------------------------------------------
*/

$rider_total = 0;
$rider_pending = 0;
$rider_active = 0;
$rider_inactive = 0;
$rider_blocked = 0;


if (tableExists($conn, 'riders')) {

    $result = $conn->query(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'pending') AS pending_count,
            SUM(status = 'active') AS active_count,
            SUM(status = 'inactive') AS inactive_count,
            SUM(status = 'blocked') AS blocked_count
         FROM riders"
    );


    if ($result) {

        $data =
            $result->fetch_assoc();

        $rider_total =
            (int)($data['total'] ?? 0);

        $rider_pending =
            (int)($data['pending_count'] ?? 0);

        $rider_active =
            (int)($data['active_count'] ?? 0);

        $rider_inactive =
            (int)($data['inactive_count'] ?? 0);

        $rider_blocked =
            (int)($data['blocked_count'] ?? 0);
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT PENDING
|--------------------------------------------------------------------------
*/

$payment_pending = 0;


if (
    tableExists($conn, 'restaurant_users') &&
    columnExists(
        $conn,
        'restaurant_users',
        'payment_status'
    )
) {

    $result = $conn->query(
        "SELECT COUNT(*) AS total
         FROM restaurant_users
         WHERE LOWER(TRIM(payment_status))
         IN ('pending','submitted')"
    );


    if ($result) {

        $data =
            $result->fetch_assoc();

        $payment_pending =
            (int)($data['total'] ?? 0);
    }
}


/*
|--------------------------------------------------------------------------
| PENDING RESTAURANTS
|--------------------------------------------------------------------------
*/

$restaurants = [];


if (tableExists($conn, 'restaurant_users')) {

    $statement = $conn->prepare(
        "SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status,
            created_at
         FROM restaurant_users
         WHERE status = 'pending'
         ORDER BY created_at DESC
         LIMIT 8"
    );


    if ($statement) {

        $statement->execute();

        $result =
            $statement->get_result();


        while ($row = $result->fetch_assoc()) {

            $restaurants[] =
                $row;
        }


        $statement->close();
    }
}


/*
|--------------------------------------------------------------------------
| PENDING RIDERS
|--------------------------------------------------------------------------
*/

$riders = [];


if (tableExists($conn, 'riders')) {

    $statement = $conn->prepare(
        "SELECT *
         FROM riders
         WHERE status = 'pending'
         ORDER BY created_at DESC
         LIMIT 8"
    );


    if ($statement) {

        $statement->execute();

        $result =
            $statement->get_result();


        while ($row = $result->fetch_assoc()) {

            $riders[] =
                $row;
        }


        $statement->close();
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
    Admin Panel | Humsafar
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

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


button {
    font-family: inherit;
}


/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

.layout {

    min-height: 100vh;

    display: flex;
}


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 218px;

    background: #ffffff;

    border-right:
        1px solid #f1dfe7;

    display: flex;

    flex-direction: column;

    z-index: 5;
}


.brand {

    padding:
        23px
        20px
        18px;

    border-bottom:
        1px solid #f2e2e8;
}


.brand a {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #ef003c;
}


.brand-icon {

    width: 38px;

    height: 38px;

    border-radius: 11px;

    background: #ef003c;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;
}


.brand-title {

    font-size: 17px;

    font-weight: 900;
}


.brand-sub {

    display: block;

    margin-top: 5px;

    color: #999;

    font-size: 8px;

    letter-spacing: 1.2px;

    font-weight: 800;
}


.side-content {

    flex: 1;

    padding: 18px 11px;

    overflow: auto;
}


.profile {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 11px 12px;

    border-radius: 9px;

    color: #e00038;

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 15px;
}


.profile:hover {

    background: #fff0f5;
}


.profile i {

    width: 30px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #f1f1f1;
}


.label {

    padding:
        0
        11px;

    margin:
        15px
        0
        8px;

    color: #aaa;

    font-size: 8px;

    font-weight: 900;

    letter-spacing: 1px;

    text-transform: uppercase;
}


.nav-box {

    background:
        linear-gradient(
            180deg,
            #f5003f,
            #f44b83
        );

    padding: 7px;

    border-radius: 0;

    margin-bottom: 18px;
}


.nav {

    display: flex;

    align-items: center;

    gap: 11px;

    min-height: 38px;

    padding: 8px 10px;

    border-radius: 8px;

    color: #ffffff;

    font-size: 10px;

    font-weight: 700;
}


.nav i {

    width: 17px;

    text-align: center;
}


.nav:hover,
.nav.active {

    color: #e9003d;

    background: #ffffff;
}


/*
|--------------------------------------------------------------------------
| SIDEBAR ADMIN
|--------------------------------------------------------------------------
*/

.side-user {

    padding: 11px;

    margin:
        0
        11px
        15px;

    border:
        1px solid #f2dce5;

    border-radius: 11px;

    display: flex;

    align-items: center;

    gap: 9px;

    background: #fffafd;
}


.side-user-icon {

    width: 31px;

    height: 31px;

    border-radius: 50%;

    background: #ef003c;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;
}


.side-user strong {

    display: block;

    font-size: 10px;
}


.side-user small {

    color: #999;

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 218px;

    width:
        calc(100% - 218px);

    padding:
        16px
        28px
        35px;
}


/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

.topbar {

    min-height: 60px;

    background: #ffffff;

    border:
        1px solid #f0e4e9;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.04);

    padding:
        10px 14px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 16px;
}


.topbar h1 {

    margin: 0;

    font-size: 25px;

    font-weight: 900;
}


.topbar p {

    margin:
        4px
        0
        0;

    color: #999;

    font-size: 10px;
}


.date {

    border:
        1px solid #f1ccd9;

    border-radius: 9px;

    padding:
        9px 12px;

    color: #df0038;

    font-size: 10px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    padding:
        11px
        14px;

    border-radius: 9px;

    margin-bottom: 16px;

    font-size: 10px;

    font-weight: 700;
}


.success {

    color: #177245;

    background: #e7f8ed;

    border:
        1px solid #c6ecd5;
}


.error {

    color: #b42318;

    background: #fff0ee;

    border:
        1px solid #f2c7c1;
}


/*
|--------------------------------------------------------------------------
| WELCOME
|--------------------------------------------------------------------------
*/

.welcome {

    min-height: 92px;

    border-radius: 17px;

    padding:
        20px
        23px;

    color: #ffffff;

    background:
        linear-gradient(
            110deg,
            #ee003d,
            #f34882
        );

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 18px;

    box-shadow:
        0 9px 25px
        rgba(239,0,60,.12);
}


.welcome h2 {

    margin:
        0
        0
        6px;

    font-size: 19px;
}


.welcome p {

    margin: 0;

    font-size: 10px;

    opacity: .9;
}


.welcome-icon {

    width: 54px;

    height: 54px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.18);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 24px;
}


/*
|--------------------------------------------------------------------------
| STAT CARDS
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(6, minmax(0, 1fr));

    gap: 11px;

    margin-bottom: 18px;
}


.stat {

    min-height: 118px;

    padding: 16px 10px;

    border-radius: 13px;

    background: #ffffff;

    border:
        1px solid #f1dfe7;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;
}


.stat strong {

    display: block;

    margin-top: 8px;

    font-size: 20px;
}


.stat span {

    margin-top: 4px;

    color: #888;

    font-size: 9px;
}


.stat-icon {

    width: 37px;

    height: 37px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ef003c;

    background: #fff0f5;
}


.stat.pink {

    background:
        linear-gradient(
            135deg,
            #f28dde,
            #f16b9b
        );

    color: #ffffff;

    border: 0;
}


.stat.blue {

    background:
        linear-gradient(
            135deg,
            #49c5ff,
            #22cfe3
        );

    color: #ffffff;

    border: 0;
}


.stat.green {

    background:
        linear-gradient(
            135deg,
            #52e58f,
            #3de4c0
        );

    color: #ffffff;

    border: 0;
}


.stat.pink strong,
.stat.pink span,
.stat.blue strong,
.stat.blue span,
.stat.green strong,
.stat.green span {

    color: #ffffff;
}


.stat.pink .stat-icon,
.stat.blue .stat-icon,
.stat.green .stat-icon {

    background:
        rgba(255,255,255,.20);

    color: #ffffff;
}


/*
|--------------------------------------------------------------------------
| PANELS
|--------------------------------------------------------------------------
*/

.panel {

    background: #ffffff;

    border:
        1px solid #f1dfe7;

    border-radius: 15px;

    margin-bottom: 18px;

    overflow: hidden;
}


.panel-head {

    padding: 20px;

    border-bottom:
        1px solid #f3e4ea;

    display: flex;

    align-items: center;

    gap: 12px;
}


.panel-icon {

    width: 40px;

    height: 40px;

    border-radius: 11px;

    background: #fff0f5;

    color: #ef003c;

    display: flex;

    align-items: center;

    justify-content: center;
}


.panel-head h2 {

    margin: 0;

    font-size: 15px;

    font-weight: 900;
}


.panel-head p {

    margin:
        3px
        0
        0;

    color: #999;

    font-size: 9px;
}


.view {

    margin-left: auto;

    color: #df0038;

    font-size: 9px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| QUICK MANAGEMENT
|--------------------------------------------------------------------------
*/

.quick {

    padding: 13px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 9px;
}


.quick-card {

    min-height: 58px;

    border:
        1px solid #f0dce5;

    border-radius: 10px;

    padding: 9px;

    display: flex;

    align-items: center;

    gap: 10px;

    color: #292929;

    transition: .2s;
}


.quick-card:hover {

    transform:
        translateY(-2px);

    border-color: #ef003c;

    box-shadow:
        0 7px 18px
        rgba(239,0,60,.08);
}


.quick-card i {

    width: 31px;

    height: 31px;

    border-radius: 9px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ef003c;

    background: #fff0f5;
}


.quick-card strong {

    display: block;

    font-size: 10px;
}


.quick-card small {

    color: #999;

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| TWO COLUMN GRID
|--------------------------------------------------------------------------
*/

.grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 18px;
}


/*
|--------------------------------------------------------------------------
| LIST
|--------------------------------------------------------------------------
*/

.list {

    padding: 10px;
}


.item {

    padding: 12px;

    border:
        1px solid #f0e0e6;

    border-radius: 11px;

    margin-bottom: 8px;
}


.item:last-child {

    margin-bottom: 0;
}


.item-top {

    display: flex;

    justify-content: space-between;

    gap: 10px;
}


.title {

    font-size: 11px;

    font-weight: 900;
}


.owner {

    margin-top: 3px;

    color: #666;

    font-size: 9px;
}


.contact {

    margin-top: 6px;

    color: #888;

    font-size: 8px;

    line-height: 1.8;
}


.contact i {

    color: #ef003c;

    width: 13px;
}


.actions {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;

    margin-top: 9px;
}


.action {

    border: 0;

    border-radius: 7px;

    padding:
        7px 9px;

    cursor: pointer;

    font-size: 8px;

    font-weight: 800;
}


.approve,
.activate {

    background: #e7f8ed;

    color: #177245;
}


.reject,
.block {

    background: #fff0ee;

    color: #b42318;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding:
        30px
        15px;

    color: #999;
}


.empty i {

    display: block;

    color: #ef003c;

    font-size: 27px;

    margin-bottom: 8px;
}


.empty strong {

    display: block;

    color: #555;

    font-size: 11px;
}


.empty span {

    display: block;

    margin-top: 4px;

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1150px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .quick {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 800px) {

    .sidebar {

        width: 70px;
    }

    .brand-title,
    .brand-sub,
    .label,
    .profile span,
    .nav span,
    .side-user div:not(.side-user-icon) {

        display: none;
    }

    .brand {

        padding: 15px;
    }

    .brand a,
    .profile,
    .nav,
    .side-user {

        justify-content: center;
    }

    .main {

        margin-left: 70px;

        width:
            calc(100% - 70px);

        padding: 12px;
    }
}


@media (max-width: 520px) {

    .stats,
    .quick {

        grid-template-columns:
            1fr 1fr;
    }

    .topbar h1 {

        font-size: 20px;
    }

    .welcome h2 {

        font-size: 15px;
    }
}

</style>

</head>


<body>


<div class="layout">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


    <div class="brand">

        <a href="admin-panel.php">

            <div class="brand-icon">

                <i class="fa-solid fa-utensils"></i>

            </div>


            <div>

                <div class="brand-title">
                    Humsafar
                </div>

                <span class="brand-sub">
                    ADMIN PANEL
                </span>

            </div>

        </a>

    </div>



    <div class="side-content">


        <a
            href="profile.php"
            class="profile"
        >

            <i class="fa-solid fa-circle-user"></i>

            <span>
                Profile
            </span>

        </a>


        <div class="label">
            Main Menu
        </div>


        <div class="nav-box">


            <a
                class="nav active"
                href="admin-panel.php"
            >

                <i class="fa-solid fa-gauge-high"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                class="nav"
                href="manage-restaurants.php"
            >

                <i class="fa-solid fa-store"></i>

                <span>
                    Restaurants
                </span>

            </a>


            <a
                class="nav"
                href="../admin-restaurant-approvals.php?status=pending"
            >

                <i class="fa-solid fa-credit-card"></i>

                <span>
                    Payment Verification
                </span>

            </a>


            <a
                class="nav"
                href="manage-riders.php"
            >

                <i class="fa-solid fa-motorcycle"></i>

                <span>
                    Riders
                </span>

            </a>


        </div>


        <div class="label">
            Management
        </div>


        <div class="nav-box">


            <a
                class="nav"
                href="manage-restaurants.php?status=pending"
            >

                <i class="fa-solid fa-store"></i>

                <span>
                    Manage Restaurants
                </span>

            </a>


            <a
                class="nav"
                href="manage-riders.php?status=pending"
            >

                <i class="fa-solid fa-users"></i>

                <span>
                    Manage Riders
                </span>

            </a>


            <a
                class="nav"
                href="orders.php"
            >

                <i class="fa-solid fa-bag-shopping"></i>

                <span>
                    Orders
                </span>

            </a>


            <a
                class="nav"
                href="settings.php"
            >

                <i class="fa-solid fa-gear"></i>

                <span>
                    Settings
                </span>

            </a>


        </div>


    </div>


    <div class="side-user">


        <div class="side-user-icon">

            <i class="fa-solid fa-user-shield"></i>

        </div>


        <div>

            <strong>
                <?= h($admin_name) ?>
            </strong>

            <small>
                Super Administrator
            </small>

        </div>


    </div>


</aside>



<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main">


    <div class="topbar">


        <div>

            <h1>
                Dashboard Overview
            </h1>

            <p>
                Manage Humsafar Food Delivery from one place.
            </p>

        </div>


        <div class="date">

            <i class="fa-regular fa-calendar"></i>

            &nbsp;

            <?= h(date('d M Y')) ?>

        </div>


    </div>



    <?php if ($message !== ''): ?>

        <div
            class="message <?= h($message_type) ?>"
        >

            <i
                class="fa-solid
                <?= $message_type === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation'
                ?>"
            ></i>

            &nbsp;

            <?= h($message) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <section class="welcome">


        <div>

            <h2>
                Welcome back,
                <?= h($admin_name) ?>
                👋
            </h2>

            <p>
                Keep an eye on restaurants,
                payments and riders.
            </p>

        </div>


        <div class="welcome-icon">

            <i class="fa-solid fa-shield-halved"></i>

        </div>


    </section>



    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">

                <i class="fa-solid fa-store"></i>

            </div>

            <strong>
                <?= $restaurant_total ?>
            </strong>

            <span>
                Total Restaurants
            </span>

        </div>



        <div class="stat pink">

            <div class="stat-icon">

                <i class="fa-solid fa-clock"></i>

            </div>

            <strong>
                <?= $restaurant_pending ?>
            </strong>

            <span>
                Pending Approval
            </span>

        </div>



        <div class="stat blue">

            <div class="stat-icon">

                <i class="fa-solid fa-circle-check"></i>

            </div>

            <strong>
                <?= $restaurant_active ?>
            </strong>

            <span>
                Active Restaurants
            </span>

        </div>



        <div class="stat green">

            <div class="stat-icon">

                <i class="fa-solid fa-credit-card"></i>

            </div>

            <strong>
                <?= $payment_pending ?>
            </strong>

            <span>
                Payment Pending
            </span>

        </div>



        <div class="stat">

            <div class="stat-icon">

                <i class="fa-solid fa-motorcycle"></i>

            </div>

            <strong>
                <?= $rider_total ?>
            </strong>

            <span>
                Total Riders
            </span>

        </div>



        <div class="stat">

            <div class="stat-icon">

                <i class="fa-solid fa-person-biking"></i>

            </div>

            <strong>
                <?= $rider_active ?>
            </strong>

            <span>
                Active Riders
            </span>

        </div>


    </section>



    <!-- =====================================================
         QUICK MANAGEMENT
    ====================================================== -->

    <section class="panel">


        <div class="panel-head">


            <div class="panel-icon">

                <i class="fa-solid fa-bolt"></i>

            </div>


            <div>

                <h2>
                    Quick Management
                </h2>

                <p>
                    Frequently used admin controls
                </p>

            </div>


        </div>



        <div class="quick">


            <a
                class="quick-card"
                href="manage-restaurants.php?status=pending"
            >

                <i class="fa-solid fa-store"></i>

                <div>

                    <strong>
                        Restaurants
                    </strong>

                    <small>
                        <?= $restaurant_pending ?>
                        waiting
                    </small>

                </div>

            </a>



            <a
                class="quick-card"
                href="../admin-restaurant-approvals.php?status=pending"
            >

                <i class="fa-solid fa-credit-card"></i>

                <div>

                    <strong>
                        Payments
                    </strong>

                    <small>
                        <?= $payment_pending ?>
                        pending
                    </small>

                </div>

            </a>



            <a
                class="quick-card"
                href="manage-riders.php?status=pending"
            >

                <i class="fa-solid fa-motorcycle"></i>

                <div>

                    <strong>
                        Riders
                    </strong>

                    <small>
                        <?= $rider_pending ?>
                        pending
                    </small>

                </div>

            </a>



            <a
                class="quick-card"
                href="orders.php"
            >

                <i class="fa-solid fa-bag-shopping"></i>

                <div>

                    <strong>
                        Orders
                    </strong>

                    <small>
                        Manage orders
                    </small>

                </div>

            </a>


        </div>


    </section>



    <!-- =====================================================
         RESTAURANT + RIDER MANAGEMENT
    ====================================================== -->

    <div class="grid">


        <!-- =================================================
             RESTAURANTS
        ================================================== -->

        <section class="panel">


            <div class="panel-head">


                <div class="panel-icon">

                    <i class="fa-solid fa-store"></i>

                </div>


                <div>

                    <h2>
                        Restaurant Management
                    </h2>

                    <p>
                        Approve and manage restaurant owners
                    </p>

                </div>


                <a
                    class="view"
                    href="manage-restaurants.php"
                >
                    View All
                </a>


            </div>



            <div class="list">


                <?php if ($restaurants): ?>


                    <?php foreach ($restaurants as $restaurant): ?>


                        <div class="item">


                            <div class="item-top">


                                <div>


                                    <div class="title">

                                        <?= h(
                                            $restaurant['restaurant_name']
                                            ?? 'Restaurant'
                                        ) ?>

                                    </div>


                                    <div class="owner">

                                        <?= h(
                                            $restaurant['full_name']
                                            ?? 'Owner'
                                        ) ?>

                                    </div>


                                    <div class="contact">


                                        <i
                                            class="fa-solid fa-envelope"
                                        ></i>

                                        <?= h(
                                            $restaurant['email']
                                            ?? ''
                                        ) ?>


                                        <br>


                                        <i
                                            class="fa-solid fa-phone"
                                        ></i>

                                        <?= h(
                                            $restaurant['phone']
                                            ?? ''
                                        ) ?>


                                    </div>


                                </div>


                                <span
                                    class="action approve"
                                >
                                    Pending
                                </span>


                            </div>



                            <div class="actions">


                                <!-- APPROVE -->

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="entity"
                                        value="restaurant"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$restaurant['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve"
                                    >


                                    <button
                                        type="submit"
                                        class="action approve"
                                        onclick="
                                            return confirm(
                                                'Approve this restaurant?'
                                            );
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-check"
                                        ></i>

                                        Approve

                                    </button>

                                </form>



                                <!-- REJECT -->

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="entity"
                                        value="restaurant"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$restaurant['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    >


                                    <button
                                        type="submit"
                                        class="action reject"
                                        onclick="
                                            return confirm(
                                                'Reject this restaurant?'
                                            );
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-xmark"
                                        ></i>

                                        Reject

                                    </button>

                                </form>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="empty">

                        <i
                            class="fa-solid fa-store"
                        ></i>

                        <strong>
                            No pending restaurants
                        </strong>

                        <span>
                            All restaurant applications
                            are currently handled.
                        </span>

                    </div>


                <?php endif; ?>


            </div>


        </section>



        <!-- =================================================
             RIDERS
        ================================================== -->

        <section class="panel">


            <div class="panel-head">


                <div class="panel-icon">

                    <i
                        class="fa-solid fa-motorcycle"
                    ></i>

                </div>


                <div>

                    <h2>
                        Rider Management
                    </h2>

                    <p>
                        Approve and manage delivery riders
                    </p>

                </div>


                <a
                    class="view"
                    href="manage-riders.php"
                >
                    View All
                </a>


            </div>



            <div class="list">


                <?php if ($riders): ?>


                    <?php foreach ($riders as $rider): ?>


                        <div class="item">


                            <div class="item-top">


                                <div>


                                    <div class="title">

                                        <?= h(
                                            $rider['full_name']
                                            ?? 'Rider'
                                        ) ?>

                                    </div>


                                    <div class="owner">

                                        Rider #

                                        <?= (int)(
                                            $rider['id']
                                            ?? 0
                                        ) ?>


                                        <?php if (
                                            !empty(
                                                $rider['vehicle_type']
                                            )
                                        ): ?>

                                            •
                                            <?= h(
                                                $rider['vehicle_type']
                                            ) ?>

                                        <?php endif; ?>


                                    </div>


                                    <div class="contact">


                                        <i
                                            class="fa-solid fa-phone"
                                        ></i>

                                        <?= h(
                                            $rider['phone']
                                            ?? ''
                                        ) ?>


                                        <br>


                                        <i
                                            class="fa-solid fa-envelope"
                                        ></i>

                                        <?= h(
                                            $rider['email']
                                            ?? ''
                                        ) ?>


                                        <?php if (
                                            isset($rider['cnic']) &&
                                            $rider['cnic'] !== ''
                                        ): ?>

                                            <br>

                                            <i
                                                class="fa-solid fa-id-card"
                                            ></i>

                                            CNIC:

                                            <?= h(
                                                $rider['cnic']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            isset($rider['bike_number']) &&
                                            $rider['bike_number'] !== ''
                                        ): ?>

                                            <br>

                                            <i
                                                class="fa-solid fa-motorcycle"
                                            ></i>

                                            Bike:

                                            <?= h(
                                                $rider['bike_number']
                                            ) ?>

                                        <?php endif; ?>


                                    </div>


                                </div>


                                <span
                                    class="action approve"
                                >
                                    Pending
                                </span>


                            </div>



                            <div class="actions">


                                <!-- APPROVE RIDER -->

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="entity"
                                        value="rider"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$rider['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve"
                                    >


                                    <button
                                        type="submit"
                                        class="action approve"
                                        onclick="
                                            return confirm(
                                                'Approve this rider?'
                                            );
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-check"
                                        ></i>

                                        Approve

                                    </button>

                                </form>



                                <!-- REJECT RIDER -->

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="entity"
                                        value="rider"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$rider['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    >


                                    <button
                                        type="submit"
                                        class="action reject"
                                        onclick="
                                            return confirm(
                                                'Reject this rider?'
                                            );
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-xmark"
                                        ></i>

                                        Reject

                                    </button>

                                </form>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="empty">

                        <i
                            class="fa-solid fa-motorcycle"
                        ></i>

                        <strong>
                            No pending riders
                        </strong>

                        <span>
                            All rider applications
                            are currently handled.
                        </span>

                    </div>


                <?php endif; ?>


            </div>


        </section>


    </div>



    <!-- =====================================================
         APPROVAL SUMMARY
    ====================================================== -->

    <section class="panel">


        <div class="panel-head">


            <div class="panel-icon">

                <i
                    class="fa-solid fa-chart-simple"
                ></i>

            </div>


            <div>

                <h2>
                    Approval Summary
                </h2>

                <p>
                    Current restaurant and rider account states
                </p>

            </div>


        </div>



        <div class="quick">


            <div class="quick-card">

                <i
                    class="fa-solid fa-store"
                ></i>

                <div>

                    <strong>
                        Restaurants
                    </strong>

                    <small>

                        <?= $restaurant_active ?>
                        active

                        •
                        <?= $restaurant_pending ?>
                        pending

                    </small>

                </div>

            </div>



            <div class="quick-card">

                <i
                    class="fa-solid fa-store-slash"
                ></i>

                <div>

                    <strong>
                        Restaurant Issues
                    </strong>

                    <small>

                        <?= $restaurant_inactive ?>
                        inactive

                        •
                        <?= $restaurant_blocked ?>
                        blocked

                    </small>

                </div>

            </div>



            <div class="quick-card">

                <i
                    class="fa-solid fa-motorcycle"
                ></i>

                <div>

                    <strong>
                        Riders
                    </strong>

                    <small>

                        <?= $rider_active ?>
                        active

                        •
                        <?= $rider_pending ?>
                        pending

                    </small>

                </div>

            </div>



            <div class="quick-card">

                <i
                    class="fa-solid fa-user-slash"
                ></i>

                <div>

                    <strong>
                        Rider Issues
                    </strong>

                    <small>

                        <?= $rider_inactive ?>
                        inactive

                        •
                        <?= $rider_blocked ?>
                        blocked

                    </small>

                </div>

            </div>


        </div>


    </section>


</main>


</div>


</body>

</html>