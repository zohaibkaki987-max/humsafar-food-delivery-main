<?php

session_start();

require_once __DIR__ . '/../includes/config.php';

/*
|--------------------------------------------------------------------------
| Humsafar Food Delivery
| ADMIN - MANAGE RESTAURANTS
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function tableExists($connection, $table)
{
    $table = $connection->real_escape_string($table);

    $result = $connection->query(
        "SHOW TABLES LIKE '$table'"
    );

    return $result && $result->num_rows > 0;
}

function columnExists($connection, $table, $column)
{
    $stmt = $connection->prepare(
        "SHOW COLUMNS FROM `$table` LIKE ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "s",
        $column
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $exists = $result && $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

$is_admin = false;

if (!empty($_SESSION['admin_logged_in'])) {
    $is_admin = true;
}


/*
|--------------------------------------------------------------------------
| ALSO ALLOW USER ROLE ADMIN
|--------------------------------------------------------------------------
*/

if (
    !$is_admin &&
    !empty($_SESSION['user_id']) &&
    tableExists($conn, 'users')
) {

    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare(
        "SELECT role, full_name, email
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();

        if (
            $user &&
            strtolower(
                (string)($user['role'] ?? '')
            ) === 'admin'
        ) {

            $is_admin = true;

            $_SESSION['admin_logged_in'] = true;

            $_SESSION['admin_name'] =
                $user['full_name']
                ?? 'Administrator';

            $_SESSION['admin_email'] =
                $user['email']
                ?? '';
        }
    }
}


if (!$is_admin) {

    header(
        "Location: ../admin-login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK RESTAURANT TABLE
|--------------------------------------------------------------------------
*/

if (!tableExists($conn, 'restaurant_users')) {

    die(
        "Database table 'restaurant_users' was not found."
    );
}


/*
|--------------------------------------------------------------------------
| ADMIN NAME
|--------------------------------------------------------------------------
*/

$admin_name =
    $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$message = "";

$message_type = "";


/*
|--------------------------------------------------------------------------
| RESTAURANT ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $restaurant_id =
        (int)($_POST['restaurant_id'] ?? 0);

    $action =
        $_POST['action'] ?? '';

    $allowed_actions = [
        'approve',
        'reject',
        'block',
        'activate'
    ];

    if ($restaurant_id <= 0) {

        $message =
            "Invalid restaurant.";

        $message_type =
            "error";

    } elseif (
        !in_array(
            $action,
            $allowed_actions,
            true
        )
    ) {

        $message =
            "Invalid restaurant action.";

        $message_type =
            "error";

    } else {

        $new_status = "";

        if ($action === 'approve') {

            $new_status = 'active';

        } elseif ($action === 'reject') {

            $new_status = 'inactive';

        } elseif ($action === 'block') {

            $new_status = 'blocked';

        } elseif ($action === 'activate') {

            $new_status = 'active';
        }


        $stmt = $conn->prepare(
            "UPDATE restaurant_users
             SET status = ?
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $message =
                "Database error: "
                . $conn->error;

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "si",
                $new_status,
                $restaurant_id
            );

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    if ($action === 'approve') {

                        $message =
                            "Restaurant approved successfully.";

                    } elseif ($action === 'reject') {

                        $message =
                            "Restaurant rejected successfully.";

                    } elseif ($action === 'block') {

                        $message =
                            "Restaurant blocked successfully.";

                    } else {

                        $message =
                            "Restaurant activated successfully.";
                    }

                    $message_type =
                        "success";

                } else {

                    $message =
                        "No restaurant was updated.";

                    $message_type =
                        "error";
                }

            } else {

                $message =
                    "Unable to update restaurant status: "
                    . $stmt->error;

                $message_type =
                    "error";
            }

            $stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$filter =
    $_GET['status']
    ?? 'all';

$allowed_filters = [
    'all',
    'pending',
    'active',
    'inactive',
    'blocked'
];

if (
    !in_array(
        $filter,
        $allowed_filters,
        true
    )
) {

    $filter = 'all';
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| OPTIONAL PAYMENT STATUS COLUMN
|--------------------------------------------------------------------------
*/

$has_payment_status =
    columnExists(
        $conn,
        'restaurant_users',
        'payment_status'
    );


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$counts = [
    'all'      => 0,
    'pending'  => 0,
    'active'   => 0,
    'inactive' => 0,
    'blocked'  => 0
];


$count_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'active') AS active_count,
        SUM(status = 'inactive') AS inactive_count,
        SUM(status = 'blocked') AS blocked_count
    FROM restaurant_users
";

$count_result =
    $conn->query($count_sql);

if ($count_result) {

    $count_row =
        $count_result->fetch_assoc();

    $counts['all'] =
        (int)($count_row['total'] ?? 0);

    $counts['pending'] =
        (int)($count_row['pending_count'] ?? 0);

    $counts['active'] =
        (int)($count_row['active_count'] ?? 0);

    $counts['inactive'] =
        (int)($count_row['inactive_count'] ?? 0);

    $counts['blocked'] =
        (int)($count_row['blocked_count'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| BUILD RESTAURANT QUERY
|--------------------------------------------------------------------------
*/

$restaurants = [];

$select_payment = "";

if ($has_payment_status) {

    $select_payment =
        ", payment_status";
}


if ($filter === 'all') {

    if ($search !== '') {

        $stmt = $conn->prepare(
            "SELECT
                id,
                restaurant_name,
                full_name,
                email,
                phone,
                status,
                created_at,
                updated_at
                $select_payment
             FROM restaurant_users
             WHERE
                restaurant_name LIKE ?
                OR full_name LIKE ?
                OR email LIKE ?
                OR phone LIKE ?
             ORDER BY created_at DESC"
        );

        $like =
            '%' . $search . '%';

        $stmt->bind_param(
            "ssss",
            $like,
            $like,
            $like,
            $like
        );

    } else {

        $stmt = $conn->prepare(
            "SELECT
                id,
                restaurant_name,
                full_name,
                email,
                phone,
                status,
                created_at,
                updated_at
                $select_payment
             FROM restaurant_users
             ORDER BY created_at DESC"
        );
    }

} else {

    if ($search !== '') {

        $stmt = $conn->prepare(
            "SELECT
                id,
                restaurant_name,
                full_name,
                email,
                phone,
                status,
                created_at,
                updated_at
                $select_payment
             FROM restaurant_users
             WHERE
                status = ?
                AND (
                    restaurant_name LIKE ?
                    OR full_name LIKE ?
                    OR email LIKE ?
                    OR phone LIKE ?
                )
             ORDER BY created_at DESC"
        );

        $like =
            '%' . $search . '%';

        $stmt->bind_param(
            "sssss",
            $filter,
            $like,
            $like,
            $like,
            $like
        );

    } else {

        $stmt = $conn->prepare(
            "SELECT
                id,
                restaurant_name,
                full_name,
                email,
                phone,
                status,
                created_at,
                updated_at
                $select_payment
             FROM restaurant_users
             WHERE status = ?
             ORDER BY created_at DESC"
        );

        $stmt->bind_param(
            "s",
            $filter
        );
    }
}


if (!$stmt) {

    die(
        "Database error: "
        . $conn->error
    );
}


$stmt->execute();

$result =
    $stmt->get_result();


while (
    $row = $result->fetch_assoc()
) {

    $restaurants[] =
        $row;
}


$stmt->close();

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
        Manage Restaurants | Humsafar
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

        button,
        input {
            font-family: inherit;
        }

        .layout {
            min-height: 100vh;

            display: flex;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            position: fixed;

            inset: 0 auto 0 0;

            width: 218px;

            background: #fff;

            border-right:
                1px solid #f1dfe7;

            display: flex;

            flex-direction: column;

            z-index: 10;
        }

        .brand {
            padding:
                23px 20px 18px;

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

            color: #fff;

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

            overflow-y: auto;
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
            padding: 0 11px;

            margin: 15px 0 8px;

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

            margin-bottom: 18px;
        }

        .nav {
            display: flex;

            align-items: center;

            gap: 11px;

            min-height: 38px;

            padding: 8px 10px;

            border-radius: 8px;

            color: #fff;

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

            background: #fff;
        }

        .side-user {
            padding: 11px;

            margin:
                0 11px 15px;

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

            color: #fff;

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


        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            margin-left: 218px;

            width:
                calc(100% - 218px);

            padding:
                16px 28px 35px;
        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {
            min-height: 60px;

            background: #fff;

            border:
                1px solid #f0e4e9;

            box-shadow:
                0 4px 16px #0000000a;

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
            margin: 4px 0 0;

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


        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            padding:
                11px 14px;

            border-radius: 9px;

            margin-bottom: 16px;

            font-size: 10px;

            font-weight: 700;
        }

        .message.success {
            color: #177245;

            background: #e7f8ed;

            border:
                1px solid #c6ecd5;
        }

        .message.error {
            color: #b42318;

            background: #fff0ee;

            border:
                1px solid #f2c7c1;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {
            background:
                linear-gradient(
                    110deg,
                    #ee003d,
                    #f34882
                );

            color: #fff;

            border-radius: 17px;

            padding:
                20px 23px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 18px;

            box-shadow:
                0 9px 25px #ef003c1f;
        }

        .page-header h2 {
            margin: 0 0 5px;

            font-size: 20px;
        }

        .page-header p {
            margin: 0;

            font-size: 10px;

            opacity: .9;
        }

        .page-header-icon {
            width: 54px;
            height: 54px;

            border-radius: 15px;

            background: #ffffff2e;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;
        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap: 11px;

            margin-bottom: 18px;
        }

        .stat {
            min-height: 108px;

            padding: 15px 10px;

            border-radius: 13px;

            background: #fff;

            border:
                1px solid #f1dfe7;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-align: center;
        }

        .stat-icon {
            width: 36px;
            height: 36px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ef003c;

            background: #fff0f5;
        }

        .stat strong {
            display: block;

            margin-top: 7px;

            font-size: 20px;

            font-weight: 900;
        }

        .stat span {
            margin-top: 3px;

            color: #888;

            font-size: 9px;
        }

        .stat.pending {
            background:
                linear-gradient(
                    135deg,
                    #f28dde,
                    #f16b9b
                );

            color: #fff;

            border: 0;
        }

        .stat.active-stat {
            background:
                linear-gradient(
                    135deg,
                    #49c5ff,
                    #22cfe3
                );

            color: #fff;

            border: 0;
        }

        .stat.inactive-stat {
            background:
                linear-gradient(
                    135deg,
                    #ffd36b,
                    #ffad5c
                );

            color: #fff;

            border: 0;
        }

        .stat.blocked-stat {
            background:
                linear-gradient(
                    135deg,
                    #ff7171,
                    #ef4141
                );

            color: #fff;

            border: 0;
        }

        .stat.pending strong,
        .stat.pending span,
        .stat.active-stat strong,
        .stat.active-stat span,
        .stat.inactive-stat strong,
        .stat.inactive-stat span,
        .stat.blocked-stat strong,
        .stat.blocked-stat span {
            color: #fff;
        }

        .stat.pending .stat-icon,
        .stat.active-stat .stat-icon,
        .stat.inactive-stat .stat-icon,
        .stat.blocked-stat .stat-icon {
            background: #ffffff33;

            color: #fff;
        }


        /* =====================================================
           CONTROL PANEL
        ===================================================== */

        .control-panel {
            padding: 17px;

            margin-bottom: 18px;

            background: #fff;

            border:
                1px solid #f0dce5;

            border-radius: 15px;
        }

        .tabs {
            display: flex;

            flex-wrap: wrap;

            gap: 7px;

            margin-bottom: 15px;
        }

        .tab {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                8px 13px;

            border:
                1px solid #ead6df;

            border-radius: 8px;

            color: #666;

            background: #fff;

            font-size: 10px;

            font-weight: 800;
        }

        .tab:hover {
            color: #df0038;

            border-color: #efb6ca;

            background: #fff5f8;
        }

        .tab.active {
            color: #fff;

            border-color: transparent;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );
        }

        .search-form {
            display: flex;

            gap: 9px;
        }

        .search-box {
            position: relative;

            flex: 1;
        }

        .search-box i {
            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #999;

            pointer-events: none;
        }

        .search-box input {
            width: 100%;

            height: 42px;

            padding:
                0 13px 0 38px;

            border:
                1px solid #ddd;

            border-radius: 9px;

            outline: none;

            color: #333;

            background: #fff;

            font-size: 12px;
        }

        .search-box input:focus {
            border-color: #ef174b;

            box-shadow:
                0 0 0 3px
                rgba(239,23,75,.08);
        }

        .search-button {
            height: 42px;

            padding:
                0 18px;

            border: none;

            border-radius: 9px;

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            font-size: 12px;

            font-weight: 800;

            cursor: pointer;
        }


        /* =====================================================
           RESTAURANT TABLE
        ===================================================== */

        .restaurant-panel {
            background: #fff;

            border:
                1px solid #f0dce5;

            border-radius: 17px;

            overflow: hidden;

            box-shadow:
                0 7px 23px
                rgba(80,25,50,.055);
        }

        .panel-header {
            padding:
                18px 20px;

            border-bottom:
                1px solid #f2e5ea;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .panel-header h2 {
            margin: 0;

            font-size: 16px;

            font-weight: 900;
        }

        .panel-header span {
            color: #999;

            font-size: 10px;
        }

        .table-wrap {
            width: 100%;

            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
        }

        th {
            padding:
                12px 13px;

            text-align: left;

            color: #888;

            background: #fffafd;

            border-bottom:
                1px solid #f1e2e8;

            font-size: 9px;

            text-transform: uppercase;

            letter-spacing: .5px;
        }

        td {
            padding:
                13px;

            border-bottom:
                1px solid #f4e7ec;

            vertical-align: middle;

            font-size: 10px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fffafd;
        }


        /* =====================================================
           RESTAURANT INFO
        ===================================================== */

        .restaurant-info {
            display: flex;

            align-items: center;

            gap: 10px;
        }

        .restaurant-icon {
            width: 38px;
            height: 38px;

            flex-shrink: 0;

            border-radius: 10px;

            background: #fff0f5;

            color: #ef003c;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;
        }

        .restaurant-name {
            color: #292929;

            font-size: 11px;

            font-weight: 900;
        }

        .restaurant-id {
            margin-top: 3px;

            color: #aaa;

            font-size: 8px;
        }

        .owner-name {
            font-size: 10px;

            font-weight: 800;
        }

        .contact-line {
            margin-top: 3px;

            color: #777;

            font-size: 8px;

            line-height: 1.7;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 8px;

            font-weight: 900;

            text-transform: capitalize;
        }

        .status.pending {
            color: #996600;

            background: #fff4d6;
        }

        .status.active {
            color: #177245;

            background: #e7f8ed;
        }

        .status.inactive {
            color: #8a5a00;

            background: #fff0d4;
        }

        .status.blocked {
            color: #b42318;

            background: #fff0ee;
        }


        /* =====================================================
           PAYMENT STATUS
        ===================================================== */

        .payment-status {
            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 8px;

            font-weight: 800;

            text-transform: capitalize;
        }

        .payment-status.pending,
        .payment-status.submitted {
            color: #996600;

            background: #fff4d6;
        }

        .payment-status.approved,
        .payment-status.paid {
            color: #177245;

            background: #e7f8ed;
        }

        .payment-status.rejected {
            color: #b42318;

            background: #fff0ee;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {
            display: flex;

            flex-wrap: wrap;

            gap: 5px;
        }

        .action-form {
            margin: 0;
        }

        .action-btn {
            border: none;

            border-radius: 7px;

            padding:
                7px 9px;

            cursor: pointer;

            font-size: 8px;

            font-weight: 800;

            transition:
                transform .15s ease,
                opacity .15s ease;
        }

        .action-btn:hover {
            transform:
                translateY(-1px);

            opacity: .9;
        }

        .approve-btn,
        .activate-btn {
            color: #177245;

            background: #e7f8ed;
        }

        .reject-btn,
        .block-btn {
            color: #b42318;

            background: #fff0ee;
        }

        .view-btn {
            color: #df0038;

            background: #fff0f5;

            border: none;

            border-radius: 7px;

            padding:
                7px 9px;

            cursor: pointer;

            font-size: 8px;

            font-weight: 800;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {
            text-align: center;

            padding:
                55px 20px;

            color: #999;
        }

        .empty i {
            display: block;

            color: #ef003c;

            font-size: 35px;

            margin-bottom: 10px;
        }

        .empty strong {
            display: block;

            color: #555;

            font-size: 12px;
        }

        .empty span {
            display: block;

            margin-top: 5px;

            font-size: 9px;
        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal {
            position: fixed;

            inset: 0;

            background:
                rgba(30,10,20,.48);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 100;
        }

        .modal.show {
            display: flex;
        }

        .modal-card {
            width: 100%;

            max-width: 500px;

            max-height: 90vh;

            overflow-y: auto;

            background: #fff;

            border-radius: 17px;

            box-shadow:
                0 20px 60px
                rgba(0,0,0,.18);
        }

        .modal-header {
            padding:
                18px 20px;

            border-bottom:
                1px solid #f1e1e7;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;

            font-size: 16px;

            font-weight: 900;
        }

        .close-modal {
            width: 32px;
            height: 32px;

            border: none;

            border-radius: 8px;

            background: #fff0f5;

            color: #ef003c;

            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .detail-row {
            display: grid;

            grid-template-columns:
                140px 1fr;

            gap: 10px;

            padding:
                10px 0;

            border-bottom:
                1px solid #f4e8ed;

            font-size: 10px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #999;

            font-weight: 700;
        }

        .detail-value {
            color: #292929;

            font-weight: 800;

            word-break: break-word;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1150px) {

            .stats {
                grid-template-columns:
                    repeat(3, 1fr);
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

        @media (max-width: 600px) {

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .page-header {
                padding: 18px;
            }

            .page-header-icon {
                display: none;
            }

            .search-form {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
            }

            .topbar h1 {
                font-size: 20px;
            }

            .date {
                display: none;
            }
        }

    </style>

</head>

<body>
<?php include __DIR__ . '/admin-sidebar.php'; ?>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
<!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <h1>
                    Manage Restaurants
                </h1>

                <p>
                    Approve and manage Humsafar restaurant owners.
                </p>

            </div>

            <div class="date">

                <i class="fa-regular fa-calendar"></i>

                &nbsp;

                <?= h(date('d M Y')) ?>

            </div>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

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


        <!-- PAGE HEADER -->

        <section class="page-header">

            <div>

                <h2>
                    Restaurant Management
                </h2>

                <p>
                    Review applications, approve restaurants,
                    block accounts and manage restaurant status.
                </p>

            </div>

            <div class="page-header-icon">

                <i class="fa-solid fa-store"></i>

            </div>

        </section>


        <!-- =====================================================
             STATS
        ====================================================== -->

        <section class="stats">


            <div class="stat">

                <div class="stat-icon">

                    <i class="fa-solid fa-store"></i>

                </div>

                <strong>
                    <?= $counts['all'] ?>
                </strong>

                <span>
                    Total Restaurants
                </span>

            </div>


            <div class="stat pending">

                <div class="stat-icon">

                    <i class="fa-solid fa-clock"></i>

                </div>

                <strong>
                    <?= $counts['pending'] ?>
                </strong>

                <span>
                    Pending Approval
                </span>

            </div>


            <div class="stat active-stat">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>

                <strong>
                    <?= $counts['active'] ?>
                </strong>

                <span>
                    Active
                </span>

            </div>


            <div class="stat inactive-stat">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-pause"></i>

                </div>

                <strong>
                    <?= $counts['inactive'] ?>
                </strong>

                <span>
                    Inactive
                </span>

            </div>


            <div class="stat blocked-stat">

                <div class="stat-icon">

                    <i class="fa-solid fa-ban"></i>

                </div>

                <strong>
                    <?= $counts['blocked'] ?>
                </strong>

                <span>
                    Blocked
                </span>

            </div>

        </section>



        <!-- =====================================================
             FILTERS
        ====================================================== -->

        <section class="control-panel">


            <div class="tabs">


                <a
                    href="manage-restaurants.php?status=all"
                    class="
                        tab
                        <?= $filter === 'all'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    <i class="fa-solid fa-layer-group"></i>

                    All

                    <span>
                        (<?= $counts['all'] ?>)
                    </span>

                </a>


                <a
                    href="manage-restaurants.php?status=pending"
                    class="
                        tab
                        <?= $filter === 'pending'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    <i class="fa-solid fa-clock"></i>

                    Pending

                    <span>
                        (<?= $counts['pending'] ?>)
                    </span>

                </a>


                <a
                    href="manage-restaurants.php?status=active"
                    class="
                        tab
                        <?= $filter === 'active'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    <i class="fa-solid fa-circle-check"></i>

                    Active

                    <span>
                        (<?= $counts['active'] ?>)
                    </span>

                </a>


                <a
                    href="manage-restaurants.php?status=inactive"
                    class="
                        tab
                        <?= $filter === 'inactive'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    <i class="fa-solid fa-circle-pause"></i>

                    Inactive

                    <span>
                        (<?= $counts['inactive'] ?>)
                    </span>

                </a>


                <a
                    href="manage-restaurants.php?status=blocked"
                    class="
                        tab
                        <?= $filter === 'blocked'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    <i class="fa-solid fa-ban"></i>

                    Blocked

                    <span>
                        (<?= $counts['blocked'] ?>)
                    </span>

                </a>

            </div>


            <form
                method="GET"
                action="manage-restaurants.php"
                class="search-form"
            >

                <input
                    type="hidden"
                    name="status"
                    value="<?= h($filter) ?>"
                >

                <div class="search-box">

                    <i class="fa-solid fa-search"></i>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search restaurant, owner, email or phone..."
                        value="<?= h($search) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="search-button"
                >

                    <i class="fa-solid fa-search"></i>

                    Search

                </button>

            </form>

        </section>



        <!-- =====================================================
             RESTAURANTS TABLE
        ====================================================== -->

        <section class="restaurant-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Restaurant Accounts
                    </h2>

                    <span>
                        <?= count($restaurants) ?>
                        restaurant(s) shown
                    </span>

                </div>

                <span>
                    <?= ucfirst($filter) ?> records
                </span>

            </div>


            <?php if (!empty($restaurants)): ?>


                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Restaurant
                                </th>

                                <th>
                                    Owner
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    Status
                                </th>

                                <?php if ($has_payment_status): ?>

                                    <th>
                                        Payment
                                    </th>

                                <?php endif; ?>

                                <th>
                                    Registered
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($restaurants as $restaurant): ?>


                            <tr>


                                <!-- RESTAURANT -->

                                <td>

                                    <div class="restaurant-info">

                                        <div class="restaurant-icon">

                                            <i class="fa-solid fa-store"></i>

                                        </div>

                                        <div>

                                            <div class="restaurant-name">

                                                <?= h(
                                                    $restaurant['restaurant_name']
                                                    ?? 'Restaurant'
                                                ) ?>

                                            </div>

                                            <div class="restaurant-id">

                                                ID #<?= (int)$restaurant['id'] ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- OWNER -->

                                <td>

                                    <div class="owner-name">

                                        <?= h(
                                            $restaurant['full_name']
                                            ?? 'Owner'
                                        ) ?>

                                    </div>

                                </td>


                                <!-- CONTACT -->

                                <td>

                                    <div class="contact-line">

                                        <i class="fa-solid fa-envelope"></i>

                                        <?= h(
                                            $restaurant['email']
                                            ?? ''
                                        ) ?>

                                        <br>

                                        <i class="fa-solid fa-phone"></i>

                                        <?= h(
                                            $restaurant['phone']
                                            ?? ''
                                        ) ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php
                                    $status =
                                        strtolower(
                                            (string)(
                                                $restaurant['status']
                                                ?? ''
                                            )
                                        );
                                    ?>

                                    <span
                                        class="
                                            status
                                            <?= h($status) ?>
                                        "
                                    >

                                        <?php if ($status === 'pending'): ?>

                                            <i class="fa-solid fa-clock"></i>

                                        <?php elseif ($status === 'active'): ?>

                                            <i class="fa-solid fa-circle-check"></i>

                                        <?php elseif ($status === 'blocked'): ?>

                                            <i class="fa-solid fa-ban"></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-circle-pause"></i>

                                        <?php endif; ?>

                                        <?= h($status ?: 'unknown') ?>

                                    </span>

                                </td>


                                <!-- PAYMENT -->

                                <?php if ($has_payment_status): ?>

                                    <td>

                                        <?php
                                        $payment_status =
                                            strtolower(
                                                trim(
                                                    (string)(
                                                        $restaurant['payment_status']
                                                        ?? ''
                                                    )
                                                )
                                            );

                                        if ($payment_status === '') {
                                            $payment_status = 'not set';
                                        }
                                        ?>

                                        <span
                                            class="
                                                payment-status
                                                <?= h($payment_status) ?>
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    <?= in_array(
                                                        $payment_status,
                                                        [
                                                            'approved',
                                                            'paid'
                                                        ],
                                                        true
                                                    )
                                                        ? 'fa-circle-check'
                                                        : (
                                                            in_array(
                                                                $payment_status,
                                                                [
                                                                    'pending',
                                                                    'submitted'
                                                                ],
                                                                true
                                                            )
                                                                ? 'fa-clock'
                                                                : 'fa-credit-card'
                                                        )
                                                    ?>
                                                "
                                            ></i>

                                            <?= h($payment_status) ?>

                                        </span>

                                    </td>

                                <?php endif; ?>


                                <!-- DATE -->

                                <td>

                                    <span
                                        style="
                                            color:#777;
                                            font-size:9px;
                                        "
                                    >

                                        <?= !empty(
                                            $restaurant['created_at']
                                        )
                                            ? h(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $restaurant['created_at']
                                                    )
                                                )
                                            )
                                            : 'N/A'
                                        ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- VIEW -->

                                        <button
                                            type="button"
                                            class="view-btn"
                                            onclick='showRestaurant(
                                                <?= json_encode(
                                                    $restaurant,
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                                ) ?>
                                            )'
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                            View

                                        </button>


                                        <?php if ($status === 'pending'): ?>


                                            <!-- APPROVE -->

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Approve this restaurant?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="restaurant_id"
                                                    value="<?= (int)$restaurant['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        action-btn
                                                        approve-btn
                                                    "
                                                >

                                                    <i class="fa-solid fa-check"></i>

                                                    Approve

                                                </button>

                                            </form>


                                            <!-- REJECT -->

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Reject this restaurant?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="restaurant_id"
                                                    value="<?= (int)$restaurant['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        action-btn
                                                        reject-btn
                                                    "
                                                >

                                                    <i class="fa-solid fa-xmark"></i>

                                                    Reject

                                                </button>

                                            </form>


                                        <?php elseif ($status === 'active'): ?>


                                            <!-- BLOCK -->

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Block this restaurant?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="restaurant_id"
                                                    value="<?= (int)$restaurant['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="block"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        action-btn
                                                        block-btn
                                                    "
                                                >

                                                    <i class="fa-solid fa-ban"></i>

                                                    Block

                                                </button>

                                            </form>


                                        <?php elseif (
                                            $status === 'blocked'
                                            ||
                                            $status === 'inactive'
                                        ): ?>


                                            <!-- ACTIVATE -->

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Activate this restaurant?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="restaurant_id"
                                                    value="<?= (int)$restaurant['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        action-btn
                                                        activate-btn
                                                    "
                                                >

                                                    <i class="fa-solid fa-check"></i>

                                                    Activate

                                                </button>

                                            </form>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <i class="fa-solid fa-store-slash"></i>

                    <strong>
                        No restaurants found
                    </strong>

                    <span>

                        <?php if ($search !== ''): ?>

                            No restaurant matched your search.

                        <?php elseif ($filter === 'pending'): ?>

                            There are no pending restaurant applications.

                        <?php else: ?>

                            There are no restaurants in this section.

                        <?php endif; ?>

                    </span>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>



<!-- =========================================================
     RESTAURANT DETAILS MODAL
========================================================= -->

<div
    class="modal"
    id="restaurantModal"
    onclick="closeModalOutside(event)"
>

    <div
        class="modal-card"
        onclick="event.stopPropagation()"
    >

        <div class="modal-header">

            <h3>
                Restaurant Details
            </h3>

            <button
                type="button"
                class="close-modal"
                onclick="closeRestaurantModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <div
            class="modal-body"
            id="restaurantDetails"
        >

        </div>

    </div>

</div>



<script>

function showRestaurant(data)
{
    const modal =
        document.getElementById(
            "restaurantModal"
        );

    const details =
        document.getElementById(
            "restaurantDetails"
        );


    const restaurantName =
        data.restaurant_name || "Restaurant";

    const ownerName =
        data.full_name || "Owner";

    const email =
        data.email || "N/A";

    const phone =
        data.phone || "N/A";

    const status =
        data.status || "N/A";

    const created =
        data.created_at || "N/A";

    const updated =
        data.updated_at || "N/A";


    let html = "";


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Restaurant ID
            </div>
            <div class="detail-value">
                #${escapeHtml(data.id || "")}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Restaurant Name
            </div>
            <div class="detail-value">
                ${escapeHtml(restaurantName)}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Owner Name
            </div>
            <div class="detail-value">
                ${escapeHtml(ownerName)}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Email
            </div>
            <div class="detail-value">
                ${escapeHtml(email)}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Phone
            </div>
            <div class="detail-value">
                ${escapeHtml(phone)}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Status
            </div>
            <div class="detail-value">
                ${escapeHtml(status)}
            </div>
        </div>
    `;


    <?php if ($has_payment_status): ?>

    html += `
        <div class="detail-row">
            <div class="detail-label">
                Payment Status
            </div>
            <div class="detail-value">
                ${escapeHtml(
                    data.payment_status || "Not set"
                )}
            </div>
        </div>
    `;

    <?php endif; ?>


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Registered
            </div>
            <div class="detail-value">
                ${escapeHtml(created)}
            </div>
        </div>
    `;


    html += `
        <div class="detail-row">
            <div class="detail-label">
                Last Updated
            </div>
            <div class="detail-value">
                ${escapeHtml(updated)}
            </div>
        </div>
    `;


    details.innerHTML =
        html;

    modal.classList.add(
        "show"
    );
}


function closeRestaurantModal()
{
    document
        .getElementById(
            "restaurantModal"
        )
        .classList.remove(
            "show"
        );
}


function closeModalOutside(event)
{
    if (
        event.target.id ===
        "restaurantModal"
    ) {

        closeRestaurantModal();
    }
}


function escapeHtml(value)
{
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


document.addEventListener(
    "keydown",
    function(event)
    {
        if (
            event.key === "Escape"
        ) {

            closeRestaurantModal();
        }
    }
);

</script>


</body>

</html>