<?php
session_start();

require_once __DIR__ . '/../includes/config.php';

/*
|--------------------------------------------------------------------------
| HUMSAFAR ADMIN - MANAGE RIDERS
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

/* ---------------------------------------------------------
   HELPERS
--------------------------------------------------------- */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

    $stmt->bind_param("s", $column);
    $stmt->execute();

    $result = $stmt->get_result();

    $exists = $result && $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


/* ---------------------------------------------------------
   ADMIN AUTHENTICATION
--------------------------------------------------------- */

$is_admin = !empty($_SESSION['admin_logged_in']);

if (
    !$is_admin &&
    !empty($_SESSION['user_id']) &&
    tableExists($conn, 'users')
) {
    $user_id = (int)$_SESSION['user_id'];

    if ($user_id > 0) {

        $stmt = $conn->prepare(
            "SELECT role, full_name, email
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        if ($stmt) {

            $stmt->bind_param("i", $user_id);
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
}

if (!$is_admin) {

    header("Location: ../admin-login.php");
    exit;
}

$admin_name =
    $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? 'Administrator';


/* ---------------------------------------------------------
   CHECK RIDERS TABLE
--------------------------------------------------------- */

if (!tableExists($conn, 'riders')) {
    die(
        '<div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Riders table not found</h2>
            <p>Please create the <strong>riders</strong> table in phpMyAdmin.</p>
        </div>'
    );
}


/* ---------------------------------------------------------
   MESSAGE
--------------------------------------------------------- */

$message = '';
$message_type = '';


/* ---------------------------------------------------------
   RIDER STATUS ACTION
--------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rider_id =
        isset($_POST['rider_id'])
        ? (int)$_POST['rider_id']
        : 0;

    $action =
        isset($_POST['action'])
        ? trim($_POST['action'])
        : '';

    $allowed_actions = [
        'approve',
        'reject',
        'block',
        'activate'
    ];

    if (
        $rider_id <= 0 ||
        !in_array($action, $allowed_actions, true)
    ) {

        $message =
            'Invalid rider management request.';

        $message_type = 'error';

    } else {

        /*
        |------------------------------------------------------
        | Convert admin action to database status
        |------------------------------------------------------
        */

        if ($action === 'approve') {

            $new_status = 'active';

        } elseif ($action === 'reject') {

            $new_status = 'inactive';

        } elseif ($action === 'block') {

            $new_status = 'blocked';

        } else {

            $new_status = 'active';
        }


        $stmt = $conn->prepare(
            "UPDATE riders
             SET status = ?
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $message =
                'Database error: '
                . $conn->error;

            $message_type = 'error';

        } else {

            $stmt->bind_param(
                "si",
                $new_status,
                $rider_id
            );

            if ($stmt->execute()) {

                if ($action === 'approve') {

                    $message =
                        'Rider approved successfully.';

                } elseif ($action === 'reject') {

                    $message =
                        'Rider rejected successfully.';

                } elseif ($action === 'block') {

                    $message =
                        'Rider blocked successfully.';

                } else {

                    $message =
                        'Rider activated successfully.';
                }

                $message_type = 'success';

            } else {

                $message =
                    'Unable to update rider status: '
                    . $stmt->error;

                $message_type = 'error';
            }

            $stmt->close();
        }
    }
}


/* ---------------------------------------------------------
   FILTERS
--------------------------------------------------------- */

$status_filter =
    isset($_GET['status'])
    ? strtolower(trim($_GET['status']))
    : 'all';

$allowed_statuses = [
    'all',
    'pending',
    'active',
    'inactive',
    'blocked'
];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

$search =
    isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


/* ---------------------------------------------------------
   RIDER COUNTS
--------------------------------------------------------- */

$total_riders = 0;
$pending_riders = 0;
$active_riders = 0;
$inactive_riders = 0;
$blocked_riders = 0;

$count_query = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'active') AS active_count,
        SUM(status = 'inactive') AS inactive_count,
        SUM(status = 'blocked') AS blocked_count
     FROM riders"
);

if ($count_query) {

    $counts = $count_query->fetch_assoc();

    $total_riders =
        (int)($counts['total'] ?? 0);

    $pending_riders =
        (int)($counts['pending_count'] ?? 0);

    $active_riders =
        (int)($counts['active_count'] ?? 0);

    $inactive_riders =
        (int)($counts['inactive_count'] ?? 0);

    $blocked_riders =
        (int)($counts['blocked_count'] ?? 0);
}


/* ---------------------------------------------------------
   GET RIDERS
--------------------------------------------------------- */

$riders = [];


/*
| We use the fields that actually exist in the current
| riders table.
|
| id
| full_name
| email
| phone
| cnic
| password
| vehicle_type
| bike_number
| address
| status
| created_at
| updated_at
*/

$sql = "
    SELECT
        id,
        full_name,
        email,
        phone,
        cnic,
        vehicle_type,
        bike_number,
        address,
        status,
        created_at,
        updated_at
    FROM riders
";

$where = [];
$params = [];
$types = "";


/* STATUS FILTER */

if ($status_filter !== 'all') {

    $where[] = "status = ?";

    $params[] = $status_filter;

    $types .= "s";
}


/* SEARCH */

if ($search !== '') {

    $where[] = "
        (
            full_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
            OR cnic LIKE ?
            OR bike_number LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sssss";
}


/* WHERE */

if (!empty($where)) {

    $sql .=
        " WHERE "
        . implode(" AND ", $where);
}


/* ORDER */

$sql .= "
    ORDER BY
        CASE status
            WHEN 'pending' THEN 1
            WHEN 'active' THEN 2
            WHEN 'inactive' THEN 3
            WHEN 'blocked' THEN 4
            ELSE 5
        END,
        created_at DESC
";


$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $riders[] = $row;
    }

    $stmt->close();
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
        Manage Riders | Humsafar Admin
    </title>

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

        button,
        input,
        select {
            font-family: inherit;
        }


        /* =====================================================
           LAYOUT
        ===================================================== */

        .layout {
            min-height: 100vh;

            display: flex;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;
            bottom: 0;

            width: 218px;

            background: #ffffff;

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

            margin:
                15px 0 8px;

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

            transition: .2s;
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

            background: #ffffff;

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

            margin:
                4px 0 0;

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
                12px 14px;

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

        .page-banner {

            border-radius: 17px;

            padding:
                22px 24px;

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
                #ef003c1f;
        }

        .page-banner h2 {

            margin: 0 0 6px;

            font-size: 20px;
        }

        .page-banner p {

            margin: 0;

            font-size: 10px;

            opacity: .9;
        }

        .page-banner-icon {

            width: 55px;
            height: 55px;

            border-radius: 15px;

            background: #ffffff2e;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;
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

            min-height: 105px;

            padding: 14px 10px;

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

            margin-top: 7px;

            font-size: 20px;
        }

        .stat span {

            margin-top: 4px;

            color: #888;

            font-size: 9px;
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

        .stat.pending {

            background:
                linear-gradient(
                    135deg,
                    #f28dde,
                    #f16b9b
                );

            color: #ffffff;

            border: 0;
        }

        .stat.active {

            background:
                linear-gradient(
                    135deg,
                    #49c5ff,
                    #22cfe3
                );

            color: #ffffff;

            border: 0;
        }

        .stat.blocked {

            background:
                linear-gradient(
                    135deg,
                    #ff7a72,
                    #ed3d59
                );

            color: #ffffff;

            border: 0;
        }

        .stat.pending span,
        .stat.active span,
        .stat.blocked span {

            color: #ffffff;
        }

        .stat.pending .stat-icon,
        .stat.active .stat-icon,
        .stat.blocked .stat-icon {

            color: #ffffff;

            background: #ffffff33;
        }


        /* =====================================================
           PANEL
        ===================================================== */

        .panel {

            background: #ffffff;

            border:
                1px solid #f1dfe7;

            border-radius: 15px;

            overflow: hidden;
        }

        .panel-head {

            padding:
                19px 20px;

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
                3px 0 0;

            color: #999;

            font-size: 9px;
        }


        /* =====================================================
           FILTER BAR
        ===================================================== */

        .filter-area {

            padding: 15px;

            background: #fffafd;

            border-bottom:
                1px solid #f3e4ea;
        }

        .filter-row {

            display: flex;

            gap: 8px;

            align-items: center;

            flex-wrap: wrap;
        }

        .search-box {

            flex: 1;

            min-width: 260px;

            position: relative;
        }

        .search-box i {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #ef003c;

            font-size: 12px;
        }

        .search-box input {

            width: 100%;

            height: 42px;

            padding:
                0 12px 0 38px;

            border:
                1px solid #ead4df;

            border-radius: 9px;

            outline: none;

            background: #ffffff;

            font-size: 10px;
        }

        .search-box input:focus {

            border-color: #ef003c;

            box-shadow:
                0 0 0 3px
                #ef003c12;
        }

        .filter-select {

            height: 42px;

            min-width: 145px;

            padding:
                0 12px;

            border:
                1px solid #ead4df;

            border-radius: 9px;

            background: #ffffff;

            outline: none;

            color: #444;

            font-size: 10px;

            font-weight: 700;
        }

        .filter-button {

            height: 42px;

            padding:
                0 15px;

            border: none;

            border-radius: 9px;

            background: #ef003c;

            color: #ffffff;

            cursor: pointer;

            font-size: 10px;

            font-weight: 800;
        }

        .filter-button:hover {

            background: #d90036;
        }

        .reset-button {

            height: 42px;

            padding:
                0 14px;

            border:
                1px solid #ef003c;

            border-radius: 9px;

            background: #ffffff;

            color: #ef003c;

            font-size: 10px;

            font-weight: 800;

            display: inline-flex;

            align-items: center;
        }

        .reset-button:hover {

            background: #fff0f5;
        }


        /* =====================================================
           RIDER TABLE
        ===================================================== */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1050px;
        }

        thead {

            background: #fff6fa;
        }

        th {

            padding:
                13px 12px;

            text-align: left;

            color: #777;

            font-size: 8px;

            font-weight: 900;

            text-transform: uppercase;

            letter-spacing: .5px;

            border-bottom:
                1px solid #f2e2e8;
        }

        td {

            padding:
                13px 12px;

            border-bottom:
                1px solid #f5e8ed;

            vertical-align: middle;

            font-size: 9px;
        }

        tbody tr {

            transition: .15s;
        }

        tbody tr:hover {

            background: #fffafd;
        }

        .rider-info {

            display: flex;

            align-items: center;

            gap: 9px;

            min-width: 175px;
        }

        .rider-avatar {

            width: 36px;
            height: 36px;

            flex-shrink: 0;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #ef003c,
                    #f44b83
                );

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;
        }

        .rider-name {

            font-size: 10px;

            font-weight: 900;

            color: #292929;
        }

        .rider-id {

            margin-top: 3px;

            color: #999;

            font-size: 8px;
        }

        .contact-main {

            color: #444;

            font-size: 9px;

            font-weight: 700;
        }

        .contact-sub {

            margin-top: 3px;

            color: #999;

            font-size: 8px;
        }

        .cnic {

            font-weight: 800;

            color: #555;
        }

        .bike {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 8px;

            border-radius: 7px;

            background: #fff0f5;

            color: #df0038;

            font-size: 8px;

            font-weight: 800;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 8px;

            border-radius: 30px;

            font-size: 8px;

            font-weight: 900;

            text-transform: capitalize;
        }

        .status-dot {

            width: 6px;
            height: 6px;

            border-radius: 50%;
        }

        .status.pending {

            color: #9a6700;

            background: #fff6d8;
        }

        .status.pending .status-dot {

            background: #e7a600;
        }

        .status.active {

            color: #177245;

            background: #e7f8ed;
        }

        .status.active .status-dot {

            background: #21a35b;
        }

        .status.inactive {

            color: #666;

            background: #f1f1f1;
        }

        .status.inactive .status-dot {

            background: #888;
        }

        .status.blocked {

            color: #b42318;

            background: #fff0ee;
        }

        .status.blocked .status-dot {

            background: #e00038;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {

            display: flex;

            flex-wrap: wrap;

            gap: 5px;

            min-width: 210px;
        }

        .action-btn {

            border: none;

            border-radius: 7px;

            padding:
                7px 9px;

            cursor: pointer;

            font-size: 8px;

            font-weight: 800;

            transition: .15s;
        }

        .action-btn:hover {

            transform:
                translateY(-1px);
        }

        .approve-btn {

            color: #177245;

            background: #e7f8ed;
        }

        .approve-btn:hover {

            background: #d5f2df;
        }

        .reject-btn {

            color: #b42318;

            background: #fff0ee;
        }

        .reject-btn:hover {

            background: #ffe2de;
        }

        .block-btn {

            color: #b42318;

            background: #ffe6eb;
        }

        .block-btn:hover {

            background: #ffd4dc;
        }

        .activate-btn {

            color: #177245;

            background: #e7f8ed;
        }

        .activate-btn:hover {

            background: #d5f2df;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            padding:
                55px 20px;

            text-align: center;

            color: #999;
        }

        .empty-icon {

            width: 60px;
            height: 60px;

            margin:
                0 auto 13px;

            border-radius: 17px;

            background: #fff0f5;

            color: #ef003c;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;
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
           FOOTER INFO
        ===================================================== */

        .table-footer {

            padding:
                13px 16px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            color: #999;

            font-size: 9px;

            background: #fffafd;

            border-top:
                1px solid #f3e4ea;
        }

        .record-count strong {

            color: #555;
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

                padding:
                    12px;
            }

            .topbar h1 {

                font-size: 20px;
            }

            .page-banner h2 {

                font-size: 16px;
            }

            .filter-row {

                flex-direction: column;

                align-items: stretch;
            }

            .search-box {

                min-width: 100%;
            }

            .filter-select,
            .filter-button,
            .reset-button {

                width: 100%;
            }
        }

        @media (max-width: 520px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .page-banner {

                padding: 17px;
            }

            .page-banner-icon {

                display: none;
            }

            .date {

                display: none;
            }
        }

    </style>

</head>


<body>

<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

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
                    class="nav"
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
                    class="nav active"
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
                    class="nav active"
                    href="manage-riders.php"
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


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <div class="topbar">

            <div>

                <h1>
                    Manage Riders
                </h1>

                <p>
                    Approve, activate and manage Humsafar delivery riders.
                </p>

            </div>


            <div class="date">

                <i class="fa-regular fa-calendar"></i>

                &nbsp;

                <?= h(date('d M Y')) ?>

            </div>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ''): ?>

            <div class="message <?= h($message_type) ?>">

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


        <!-- PAGE BANNER -->

        <section class="page-banner">

            <div>

                <h2>
                    Rider Management
                </h2>

                <p>
                    Review rider applications and control rider account status.
                </p>

            </div>

            <div class="page-banner-icon">

                <i class="fa-solid fa-motorcycle"></i>

            </div>

        </section>


        <!-- =================================================
             STATS
        ================================================== -->

        <section class="stats">

            <div class="stat">

                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <strong>
                    <?= $total_riders ?>
                </strong>

                <span>
                    Total Riders
                </span>

            </div>


            <div class="stat pending">

                <div class="stat-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <strong>
                    <?= $pending_riders ?>
                </strong>

                <span>
                    Pending Approval
                </span>

            </div>


            <div class="stat active">

                <div class="stat-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <strong>
                    <?= $active_riders ?>
                </strong>

                <span>
                    Active Riders
                </span>

            </div>


            <div class="stat">

                <div class="stat-icon">
                    <i class="fa-solid fa-user-slash"></i>
                </div>

                <strong>
                    <?= $inactive_riders ?>
                </strong>

                <span>
                    Inactive Riders
                </span>

            </div>


            <div class="stat blocked">

                <div class="stat-icon">
                    <i class="fa-solid fa-ban"></i>
                </div>

                <strong>
                    <?= $blocked_riders ?>
                </strong>

                <span>
                    Blocked Riders
                </span>

            </div>

        </section>


        <!-- =================================================
             RIDER LIST PANEL
        ================================================== -->

        <section class="panel">


            <div class="panel-head">

                <div class="panel-icon">

                    <i class="fa-solid fa-motorcycle"></i>

                </div>

                <div>

                    <h2>
                        Rider Accounts
                    </h2>

                    <p>
                        View complete rider information and manage approvals.
                    </p>

                </div>

            </div>


            <!-- FILTER -->

            <div class="filter-area">

                <form
                    method="GET"
                    action=""
                    class="filter-row"
                >

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search by name, email, phone, CNIC or bike number..."
                        >

                    </div>


                    <select
                        name="status"
                        class="filter-select"
                    >

                        <option
                            value="all"
                            <?= $status_filter === 'all'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            All Riders
                        </option>

                        <option
                            value="pending"
                            <?= $status_filter === 'pending'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Pending
                        </option>

                        <option
                            value="active"
                            <?= $status_filter === 'active'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $status_filter === 'inactive'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Inactive
                        </option>

                        <option
                            value="blocked"
                            <?= $status_filter === 'blocked'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Blocked
                        </option>

                    </select>


                    <button
                        type="submit"
                        class="filter-button"
                    >

                        <i class="fa-solid fa-filter"></i>

                        &nbsp;

                        Filter

                    </button>


                    <a
                        href="manage-riders.php"
                        class="reset-button"
                    >

                        <i class="fa-solid fa-rotate-left"></i>

                        &nbsp;

                        Reset

                    </a>

                </form>

            </div>


            <!-- TABLE -->

            <?php if (!empty($riders)): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Rider
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    CNIC
                                </th>

                                <th>
                                    Vehicle
                                </th>

                                <th>
                                    Address
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Registered
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($riders as $rider): ?>

                            <?php

                            $status =
                                strtolower(
                                    trim(
                                        (string)(
                                            $rider['status']
                                            ?? 'pending'
                                        )
                                    )
                                );

                            if (
                                !in_array(
                                    $status,
                                    [
                                        'pending',
                                        'active',
                                        'inactive',
                                        'blocked'
                                    ],
                                    true
                                )
                            ) {
                                $status = 'pending';
                            }


                            $name =
                                trim(
                                    (string)(
                                        $rider['full_name']
                                        ?? 'Rider'
                                    )
                                );

                            $initial =
                                strtoupper(
                                    substr(
                                        $name,
                                        0,
                                        1
                                    )
                                );

                            ?>

                            <tr>


                                <!-- RIDER -->

                                <td>

                                    <div class="rider-info">

                                        <div class="rider-avatar">

                                            <?= h($initial) ?>

                                        </div>

                                        <div>

                                            <div class="rider-name">

                                                <?= h($name) ?>

                                            </div>

                                            <div class="rider-id">

                                                Rider #<?= (int)$rider['id'] ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- CONTACT -->

                                <td>

                                    <div class="contact-main">

                                        <i class="fa-solid fa-phone"></i>

                                        <?= h(
                                            $rider['phone']
                                            ?? ''
                                        ) ?>

                                    </div>

                                    <div class="contact-sub">

                                        <i class="fa-solid fa-envelope"></i>

                                        <?= h(
                                            $rider['email']
                                            ?? ''
                                        ) ?>

                                    </div>

                                </td>


                                <!-- CNIC -->

                                <td>

                                    <div class="cnic">

                                        <i class="fa-solid fa-id-card"></i>

                                        <?= h(
                                            $rider['cnic']
                                            ?? ''
                                        ) ?>

                                    </div>

                                </td>


                                <!-- VEHICLE -->

                                <td>

                                    <div class="bike">

                                        <i class="fa-solid fa-motorcycle"></i>

                                        <?= h(
                                            $rider['vehicle_type']
                                            ?? 'bike'
                                        ) ?>

                                    </div>

                                    <?php if (
                                        !empty(
                                            $rider['bike_number']
                                        )
                                    ): ?>

                                        <div
                                            class="contact-sub"
                                            style="margin-top:6px;"
                                        >

                                            Bike No:
                                            <strong>

                                                <?= h(
                                                    $rider['bike_number']
                                                ) ?>

                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- ADDRESS -->

                                <td>

                                    <div
                                        class="contact-sub"
                                        style="
                                            max-width:180px;
                                            line-height:1.5;
                                        "
                                    >

                                        <i
                                            class="fa-solid fa-location-dot"
                                        ></i>

                                        <?= h(
                                            $rider['address']
                                            ?? ''
                                        ) ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= h($status) ?>"
                                    >

                                        <span class="status-dot"></span>

                                        <?= h($status) ?>

                                    </span>

                                </td>


                                <!-- CREATED -->

                                <td>

                                    <div class="contact-sub">

                                        <?= !empty(
                                            $rider['created_at']
                                        )
                                            ? h(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $rider['created_at']
                                                    )
                                                )
                                            )
                                            : '-'
                                        ?>

                                    </div>

                                    <?php if (
                                        !empty(
                                            $rider['created_at']
                                        )
                                    ): ?>

                                        <div
                                            class="contact-sub"
                                            style="margin-top:2px;"
                                        >

                                            <?= h(
                                                date(
                                                    'h:i A',
                                                    strtotime(
                                                        $rider['created_at']
                                                    )
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <?php if (
                                            $status === 'pending'
                                        ): ?>

                                            <!-- APPROVE -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Approve this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn approve-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-check"
                                                    ></i>

                                                    Approve

                                                </button>

                                            </form>


                                            <!-- REJECT -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Reject this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn reject-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-xmark"
                                                    ></i>

                                                    Reject

                                                </button>

                                            </form>


                                        <?php elseif (
                                            $status === 'active'
                                        ): ?>

                                            <!-- BLOCK -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Block this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="block"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn block-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-ban"
                                                    ></i>

                                                    Block

                                                </button>

                                            </form>


                                            <!-- DEACTIVATE -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Deactivate this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn reject-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-user-slash"
                                                    ></i>

                                                    Deactivate

                                                </button>

                                            </form>


                                        <?php elseif (
                                            $status === 'inactive'
                                        ): ?>

                                            <!-- ACTIVATE -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Activate this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn activate-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-check"
                                                    ></i>

                                                    Activate

                                                </button>

                                            </form>


                                            <!-- BLOCK -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Block this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="block"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn block-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-ban"
                                                    ></i>

                                                    Block

                                                </button>

                                            </form>


                                        <?php elseif (
                                            $status === 'blocked'
                                        ): ?>

                                            <!-- ACTIVATE -->

                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Activate this rider?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="rider_id"
                                                    value="<?= (int)$rider['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn activate-btn"
                                                >

                                                    <i
                                                        class="fa-solid fa-unlock"
                                                    ></i>

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


                <div class="table-footer">

                    <div class="record-count">

                        Showing

                        <strong>
                            <?= count($riders) ?>
                        </strong>

                        rider(s)

                    </div>

                    <div>

                        Humsafar Rider Management

                    </div>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">

                        <i
                            class="fa-solid fa-motorcycle"
                        ></i>

                    </div>

                    <strong>
                        No riders found
                    </strong>

                    <span>

                        There are no riders matching
                        your current search or filter.

                    </span>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>

</body>

</html>