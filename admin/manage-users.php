<?php

session_start();

require_once __DIR__ . '/../includes/config.php';

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| ADMIN - MANAGE USERS
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
| ALSO ALLOW USERS WITH ADMIN ROLE
|--------------------------------------------------------------------------
*/

if (
    !$is_admin &&
    !empty($_SESSION['user_id']) &&
    tableExists($conn, 'users')
) {

    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare(
        "SELECT id, role, full_name, email, status
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
            ) === 'admin' &&
            strtolower(
                (string)($user['status'] ?? 'active')
            ) === 'active'
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

if (!$is_admin) {

    header(
        "Location: admin-login.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK USERS TABLE
|--------------------------------------------------------------------------
*/

if (!tableExists($conn, 'users')) {

    die(
        "Database table 'users' was not found."
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$admin_name =
    $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? 'Administrator';

$admin_user_id =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrf_token =
    $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$message = "";
$message_type = "";

/*
|--------------------------------------------------------------------------
| USER ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_token =
        $_POST['csrf_token']
        ?? '';

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {

        $message =
            "Security verification failed. Please try again.";

        $message_type =
            "error";

    } else {

        $user_id =
            (int)(
                $_POST['user_id']
                ?? 0
            );

        $action =
            $_POST['action']
            ?? '';

        $allowed_actions = [
            'activate',
            'block'
        ];

        if ($user_id <= 0) {

            $message =
                "Invalid user.";

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
                "Invalid user action.";

            $message_type =
                "error";

        } elseif (
            $admin_user_id > 0 &&
            $user_id === $admin_user_id &&
            $action === 'block'
        ) {

            $message =
                "You cannot block your own administrator account.";

            $message_type =
                "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | GET USER
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare(
                    "SELECT id, full_name, email, role, status
                     FROM users
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
                    "i",
                    $user_id
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                $target_user =
                    $result
                    ? $result->fetch_assoc()
                    : null;

                $stmt->close();

                if (!$target_user) {

                    $message =
                        "User not found.";

                    $message_type =
                        "error";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE STATUS
                    |--------------------------------------------------------------------------
                    */

                    $new_status =
                        $action === 'block'
                        ? 'blocked'
                        : 'active';

                    $stmt =
                        $conn->prepare(
                            "UPDATE users
                             SET status = ?
                             WHERE id = ?
                             LIMIT 1"
                        );

                    if (!$stmt) {

                        $message =
                            "Unable to prepare database query.";

                        $message_type =
                            "error";

                    } else {

                        $stmt->bind_param(
                            "si",
                            $new_status,
                            $user_id
                        );

                        if ($stmt->execute()) {

                            if (
                                $stmt->affected_rows > 0
                            ) {

                                if ($action === 'block') {

                                    $message =
                                        "User blocked successfully.";

                                } else {

                                    $message =
                                        "User activated successfully.";
                                }

                                $message_type =
                                    "success";

                            } else {

                                $message =
                                    "No changes were made.";

                                $message_type =
                                    "error";
                            }

                        } else {

                            $message =
                                "Unable to update user: "
                                . $stmt->error;

                            $message_type =
                                "error";
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$status_filter =
    $_GET['status']
    ?? 'all';

$role_filter =
    $_GET['role']
    ?? 'all';

$search =
    trim(
        $_GET['search']
        ?? ''
    );

$allowed_statuses = [
    'all',
    'active',
    'inactive',
    'blocked'
];

$allowed_roles = [
    'all',
    'admin',
    'customer',
    'restaurant',
    'delivery'
];

if (
    !in_array(
        $status_filter,
        $allowed_statuses,
        true
    )
) {

    $status_filter =
        'all';
}

if (
    !in_array(
        $role_filter,
        $allowed_roles,
        true
    )
) {

    $role_filter =
        'all';
}

/*
|--------------------------------------------------------------------------
| USER COUNTS
|--------------------------------------------------------------------------
*/

$counts = [
    'all'      => 0,
    'active'   => 0,
    'inactive' => 0,
    'blocked'  => 0
];

$count_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active') AS active_count,
        SUM(status = 'inactive') AS inactive_count,
        SUM(status = 'blocked') AS blocked_count
    FROM users
";

$count_result =
    $conn->query(
        $count_sql
    );

if ($count_result) {

    $count_row =
        $count_result->fetch_assoc();

    $counts['all'] =
        (int)(
            $count_row['total']
            ?? 0
        );

    $counts['active'] =
        (int)(
            $count_row['active_count']
            ?? 0
        );

    $counts['inactive'] =
        (int)(
            $count_row['inactive_count']
            ?? 0
        );

    $counts['blocked'] =
        (int)(
            $count_row['blocked_count']
            ?? 0
        );
}

/*
|--------------------------------------------------------------------------
| BUILD USER QUERY
|--------------------------------------------------------------------------
*/

$users = [];

$where = [];

$params = [];

$types = "";

/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status_filter !== 'all') {

    $where[] =
        "status = ?";

    $params[] =
        $status_filter;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

if ($role_filter !== 'all') {

    $where[] =
        "role = ?";

    $params[] =
        $role_filter;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] =
        "(
            full_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )";

    $like =
        '%' . $search . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| FINAL QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        full_name,
        email,
        phone,
        password,
        role,
        profile_image,
        status,
        created_at,
        updated_at
    FROM users
";

if (!empty($where)) {

    $sql .=
        " WHERE "
        . implode(
            " AND ",
            $where
        );
}

$sql .= "
    ORDER BY created_at DESC
";

/*
|--------------------------------------------------------------------------
| PREPARE
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare(
        $sql
    );

if (!$stmt) {

    die(
        "Database error: "
        . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| BIND DYNAMIC PARAMETERS
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    $bind_values = [];

    $bind_values[] =
        $types;

    foreach ($params as $key => $value) {

        $bind_values[] =
            &$params[$key];
    }

    call_user_func_array(
        [
            $stmt,
            'bind_param'
        ],
        $bind_values
    );
}

$stmt->execute();

$result =
    $stmt->get_result();

while (
    $row =
    $result->fetch_assoc()
) {

    $users[] =
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
        Manage Users | Humsafar
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
        input,
        select {
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

            padding:
                18px 11px;

            overflow-y: auto;
        }

        .profile {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                11px 12px;

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

            padding:
                8px 10px;

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
                repeat(4, minmax(0, 1fr));

            gap: 11px;

            margin-bottom: 18px;
        }

        .stat {

            min-height: 108px;

            padding:
                15px 10px;

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

        .stat.active-stat strong,
        .stat.active-stat span,
        .stat.inactive-stat strong,
        .stat.inactive-stat span,
        .stat.blocked-stat strong,
        .stat.blocked-stat span {

            color: #fff;
        }

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

        .filter-row {

            display: grid;

            grid-template-columns:
                1fr 180px;

            gap: 9px;

            margin-bottom: 9px;
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

        .role-select {

            width: 100%;

            height: 42px;

            padding:
                0 12px;

            border:
                1px solid #ddd;

            border-radius: 9px;

            background: #fff;

            outline: none;

            font-size: 11px;

            color: #555;
        }

        .role-select:focus {

            border-color: #ef174b;
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

        .reset-button {

            height: 42px;

            padding:
                0 15px;

            border:
                1px solid #ead6df;

            border-radius: 9px;

            background: #fff;

            color: #777;

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;
        }

        .reset-button:hover {

            color: #df0038;

            border-color: #efb6ca;

            background: #fff5f8;
        }

        /* =====================================================
           USER TABLE
        ===================================================== */

        .user-panel {

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

            min-width: 1050px;
        }

        th {

            padding:
                12px 13px;

            text-align: left;

            background: #fffafd;

            border-bottom:
                1px solid #f0e0e7;

            color: #888;

            font-size: 9px;

            text-transform: uppercase;

            letter-spacing: .5px;
        }

        td {

            padding:
                13px;

            border-bottom:
                1px solid #f5e9ee;

            vertical-align: middle;

            font-size: 10px;
        }

        tbody tr:hover {

            background: #fffafd;
        }

        tbody tr:last-child td {

            border-bottom: none;
        }

        .user-info {

            display: flex;

            align-items: center;

            gap: 10px;
        }

        .user-avatar {

            width: 38px;
            height: 38px;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #ef003c,
                    #f56b99
                );

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;

            font-weight: 900;

            overflow: hidden;

            flex-shrink: 0;
        }

        .user-avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .user-name {

            font-size: 11px;

            font-weight: 900;

            color: #333;
        }

        .user-id {

            margin-top: 3px;

            color: #aaa;

            font-size: 8px;
        }

        .contact-line {

            line-height: 1.9;

            color: #666;

            font-size: 9px;
        }

        .contact-line i {

            width: 15px;

            color: #ef003c;
        }

        .role-badge,
        .status-badge {

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

        .role-badge.admin {

            color: #6a1b9a;

            background: #f3e5f5;
        }

        .role-badge.customer {

            color: #00695c;

            background: #e0f2f1;
        }

        .role-badge.restaurant {

            color: #ad5b00;

            background: #fff1d7;
        }

        .role-badge.delivery {

            color: #1565c0;

            background: #e3f2fd;
        }

        .status-badge.active {

            color: #177245;

            background: #e7f8ed;
        }

        .status-badge.inactive {

            color: #996600;

            background: #fff4d6;
        }

        .status-badge.blocked {

            color: #b42318;

            background: #fff0ee;
        }

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

        .activate-btn {

            color: #177245;

            background: #e7f8ed;
        }

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

        .view-btn:hover {

            background: #ffe3ec;
        }

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

            max-width: 520px;

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

        .modal-user-head {

            display: flex;

            align-items: center;

            gap: 13px;

            padding-bottom: 15px;

            margin-bottom: 10px;

            border-bottom:
                1px solid #f4e8ed;
        }

        .modal-avatar {

            width: 55px;
            height: 55px;

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    #ef003c,
                    #f56b99
                );

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 19px;

            font-weight: 900;
        }

        .modal-user-head strong {

            display: block;

            font-size: 14px;
        }

        .modal-user-head span {

            display: block;

            margin-top: 4px;

            color: #999;

            font-size: 9px;
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
                    repeat(2, 1fr);
            }

            .filter-row {

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

            .search-button,
            .reset-button {

                width: 100%;
            }

            .topbar h1 {

                font-size: 20px;
            }

            .date {

                display: none;
            }

            .detail-row {

                grid-template-columns: 1fr;
                gap: 3px;
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
                    href="manage-users.php"
                >

                    <i class="fa-solid fa-users"></i>

                    <span>
                        Users
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
                    href="manage-restaurants.php"
                >

                    <i class="fa-solid fa-store"></i>

                    <span>
                        Manage Restaurants
                    </span>

                </a>

                <a
                    class="nav active"
                    href="manage-users.php"
                >

                    <i class="fa-solid fa-users"></i>

                    <span>
                        Manage Users
                    </span>

                </a>

                <a
                    class="nav"
                    href="manage-riders.php"
                >

                    <i class="fa-solid fa-person-biking"></i>

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

        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <h1>
                    Manage Users
                </h1>

                <p>
                    View and manage Humsafar customer and system users.
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
                    User Management
                </h2>

                <p>
                    Manage customers, administrators and other system users.
                </p>

            </div>

            <div class="page-header-icon">

                <i class="fa-solid fa-users"></i>

            </div>

        </section>


        <!-- STATS -->

        <section class="stats">

            <div class="stat">

                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <strong>
                    <?= $counts['all'] ?>
                </strong>

                <span>
                    Total Users
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
                    Active Users
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
                    Inactive Users
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
                    Blocked Users
                </span>

            </div>

        </section>


        <!-- FILTERS -->

        <section class="control-panel">

            <div class="tabs">

                <a
                    href="manage-users.php?status=all&role=<?= urlencode($role_filter) ?>"
                    class="tab <?= $status_filter === 'all' ? 'active' : '' ?>"
                >

                    <i class="fa-solid fa-layer-group"></i>

                    All

                    <span>
                        (<?= $counts['all'] ?>)
                    </span>

                </a>


                <a
                    href="manage-users.php?status=active&role=<?= urlencode($role_filter) ?>"
                    class="tab <?= $status_filter === 'active' ? 'active' : '' ?>"
                >

                    <i class="fa-solid fa-circle-check"></i>

                    Active

                    <span>
                        (<?= $counts['active'] ?>)
                    </span>

                </a>


                <a
                    href="manage-users.php?status=inactive&role=<?= urlencode($role_filter) ?>"
                    class="tab <?= $status_filter === 'inactive' ? 'active' : '' ?>"
                >

                    <i class="fa-solid fa-circle-pause"></i>

                    Inactive

                    <span>
                        (<?= $counts['inactive'] ?>)
                    </span>

                </a>


                <a
                    href="manage-users.php?status=blocked&role=<?= urlencode($role_filter) ?>"
                    class="tab <?= $status_filter === 'blocked' ? 'active' : '' ?>"
                >

                    <i class="fa-solid fa-ban"></i>

                    Blocked

                    <span>
                        (<?= $counts['blocked'] ?>)
                    </span>

                </a>

            </div>


            <div class="filter-row">

                <form
                    method="GET"
                    action="manage-users.php"
                    class="search-form"
                >

                    <input
                        type="hidden"
                        name="status"
                        value="<?= h($status_filter) ?>"
                    >

                    <div class="search-box">

                        <i class="fa-solid fa-search"></i>

                        <input
                            type="text"
                            name="search"
                            placeholder="Search name, email or phone..."
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


                <select
                    class="role-select"
                    onchange="changeRole(this.value)"
                >

                    <option
                        value="all"
                        <?= $role_filter === 'all' ? 'selected' : '' ?>
                    >
                        All Roles
                    </option>

                    <option
                        value="customer"
                        <?= $role_filter === 'customer' ? 'selected' : '' ?>
                    >
                        Customers
                    </option>

                    <option
                        value="admin"
                        <?= $role_filter === 'admin' ? 'selected' : '' ?>
                    >
                        Administrators
                    </option>

                    <option
                        value="restaurant"
                        <?= $role_filter === 'restaurant' ? 'selected' : '' ?>
                    >
                        Restaurant Users
                    </option>

                    <option
                        value="delivery"
                        <?= $role_filter === 'delivery' ? 'selected' : '' ?>
                    >
                        Delivery Users
                    </option>

                </select>

            </div>


            <?php if (
                $search !== ''
                || $role_filter !== 'all'
                || $status_filter !== 'all'
            ): ?>

                <a
                    href="manage-users.php"
                    class="reset-button"
                    style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        text-decoration:none;
                    "
                >

                    <i class="fa-solid fa-rotate-left"></i>

                    &nbsp;

                    Reset Filters

                </a>

            <?php endif; ?>

        </section>


        <!-- USERS TABLE -->

        <section class="user-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        System Users
                    </h2>

                    <span>
                        <?= count($users) ?>
                        user(s) shown
                    </span>

                </div>

                <span>

                    <?= ucfirst($status_filter) ?>

                    <?php if ($role_filter !== 'all'): ?>

                        /
                        <?= ucfirst($role_filter) ?>

                    <?php endif; ?>

                </span>

            </div>


            <?php if (!empty($users)): ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    User
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    Role
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

                        <?php foreach ($users as $user): ?>

                            <?php

                            $user_status =
                                strtolower(
                                    (string)(
                                        $user['status']
                                        ?? ''
                                    )
                                );

                            $user_role =
                                strtolower(
                                    (string)(
                                        $user['role']
                                        ?? ''
                                    )
                                );

                            $full_name =
                                trim(
                                    (string)(
                                        $user['full_name']
                                        ?? 'User'
                                    )
                                );

                            $initial =
                                strtoupper(
                                    substr(
                                        $full_name,
                                        0,
                                        1
                                    )
                                );

                            ?>

                            <tr>

                                <!-- USER -->

                                <td>

                                    <div class="user-info">

                                        <div class="user-avatar">

                                            <?php
                                            $profile_image =
                                                trim(
                                                    (string)(
                                                        $user['profile_image']
                                                        ?? ''
                                                    )
                                                );
                                            ?>

                                            <?php if ($profile_image !== ''): ?>

                                                <img
                                                    src="<?= h($profile_image) ?>"
                                                    alt="<?= h($full_name) ?>"
                                                    onerror="this.style.display='none';"
                                                >

                                            <?php else: ?>

                                                <?= h($initial ?: 'U') ?>

                                            <?php endif; ?>

                                        </div>

                                        <div>

                                            <div class="user-name">

                                                <?= h($full_name) ?>

                                            </div>

                                            <div class="user-id">

                                                ID #<?= (int)$user['id'] ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- CONTACT -->

                                <td>

                                    <div class="contact-line">

                                        <i class="fa-solid fa-envelope"></i>

                                        <?= h(
                                            $user['email']
                                            ?? ''
                                        ) ?>

                                        <br>

                                        <i class="fa-solid fa-phone"></i>

                                        <?= h(
                                            $user['phone']
                                            ?? ''
                                        ) ?>

                                    </div>

                                </td>


                                <!-- ROLE -->

                                <td>

                                    <span
                                        class="
                                            role-badge
                                            <?= h($user_role) ?>
                                        "
                                    >

                                        <?php

                                        if ($user_role === 'admin') {

                                            $role_icon =
                                                'fa-user-shield';

                                        } elseif (
                                            $user_role === 'restaurant'
                                        ) {

                                            $role_icon =
                                                'fa-store';

                                        } elseif (
                                            $user_role === 'delivery'
                                        ) {

                                            $role_icon =
                                                'fa-motorcycle';

                                        } else {

                                            $role_icon =
                                                'fa-user';
                                        }

                                        ?>

                                        <i
                                            class="fa-solid <?= h($role_icon) ?>"
                                        ></i>

                                        <?= h(
                                            $user_role ?: 'unknown'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="
                                            status-badge
                                            <?= h($user_status) ?>
                                        "
                                    >

                                        <?php if (
                                            $user_status === 'active'
                                        ): ?>

                                            <i class="fa-solid fa-circle-check"></i>

                                        <?php elseif (
                                            $user_status === 'blocked'
                                        ): ?>

                                            <i class="fa-solid fa-ban"></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-circle-pause"></i>

                                        <?php endif; ?>

                                        <?= h(
                                            $user_status ?: 'unknown'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- REGISTERED -->

                                <td>

                                    <span
                                        style="
                                            color:#777;
                                            font-size:9px;
                                        "
                                    >

                                        <?php if (
                                            !empty(
                                                $user['created_at']
                                            )
                                        ): ?>

                                            <?= h(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $user['created_at']
                                                    )
                                                )
                                            ) ?>

                                            <br>

                                            <small
                                                style="
                                                    color:#aaa;
                                                    font-size:8px;
                                                "
                                            >
                                                <?= h(
                                                    date(
                                                        'h:i A',
                                                        strtotime(
                                                            $user['created_at']
                                                        )
                                                    )
                                                ) ?>
                                            </small>

                                        <?php else: ?>

                                            N/A

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">

                                        <button
                                            type="button"
                                            class="view-btn"
                                            onclick='showUser(
                                                <?= json_encode(
                                                    $user,
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


                                        <?php if (
                                            $user_status === 'blocked'
                                            || $user_status === 'inactive'
                                        ): ?>

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Activate this user account?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= h($csrf_token) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= (int)$user['id'] ?>"
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

                                                    <i class="fa-solid fa-check"></i>

                                                    Activate

                                                </button>

                                            </form>

                                        <?php elseif (
                                            $user_status === 'active'
                                        ): ?>

                                            <?php if (
                                                !(
                                                    $admin_user_id > 0
                                                    && (int)$user['id'] === $admin_user_id
                                                )
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    class="action-form"
                                                    onsubmit="
                                                        return confirm(
                                                            'Block this user account?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= h($csrf_token) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= (int)$user['id'] ?>"
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

                                                        <i class="fa-solid fa-ban"></i>

                                                        Block

                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span
                                                    style="
                                                        color:#999;
                                                        font-size:8px;
                                                        padding:7px;
                                                    "
                                                >
                                                    Current Account
                                                </span>

                                            <?php endif; ?>

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

                    <i class="fa-solid fa-users-slash"></i>

                    <strong>
                        No users found
                    </strong>

                    <span>
                        Try changing your search or filter.
                    </span>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<!-- =====================================================
     USER DETAILS MODAL
====================================================== -->

<div
    class="modal"
    id="userModal"
    onclick="closeModalOutside(event)"
>

    <div class="modal-card">

        <div class="modal-header">

            <h3>
                User Details
            </h3>

            <button
                type="button"
                class="close-modal"
                onclick="closeUserModal()"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>

        <div
            class="modal-body"
            id="userModalBody"
        >

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

function changeRole(role)
{
    const url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'role',
        role
    );

    url.searchParams.set(
        'status',
        <?= json_encode($status_filter) ?>
    );

    <?php if ($search !== ''): ?>

    url.searchParams.set(
        'search',
        <?= json_encode($search) ?>
    );

    <?php endif; ?>

    window.location.href =
        url.toString();
}


/*
|--------------------------------------------------------------------------
| USER MODAL
|--------------------------------------------------------------------------
*/

function showUser(user)
{

    const modal =
        document.getElementById(
            'userModal'
        );

    const body =
        document.getElementById(
            'userModalBody'
        );

    const name =
        user.full_name
        || 'User';

    const initial =
        name
        .charAt(0)
        .toUpperCase();

    const role =
        user.role
        || 'Unknown';

    const status =
        user.status
        || 'Unknown';

    const created =
        user.created_at
        || 'N/A';

    const updated =
        user.updated_at
        || 'N/A';

    const email =
        user.email
        || 'N/A';

    const phone =
        user.phone
        || 'N/A';

    body.innerHTML = `

        <div class="modal-user-head">

            <div class="modal-avatar">
                ${escapeHtml(initial)}
            </div>

            <div>

                <strong>
                    ${escapeHtml(name)}
                </strong>

                <span>
                    User ID #${escapeHtml(String(user.id))}
                </span>

            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Full Name
            </div>

            <div class="detail-value">
                ${escapeHtml(name)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Email Address
            </div>

            <div class="detail-value">
                ${escapeHtml(email)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Phone
            </div>

            <div class="detail-value">
                ${escapeHtml(phone)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Account Role
            </div>

            <div class="detail-value">
                ${escapeHtml(role)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Account Status
            </div>

            <div class="detail-value">
                ${escapeHtml(status)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Registered
            </div>

            <div class="detail-value">
                ${escapeHtml(created)}
            </div>

        </div>


        <div class="detail-row">

            <div class="detail-label">
                Last Updated
            </div>

            <div class="detail-value">
                ${escapeHtml(updated)}
            </div>

        </div>

    `;

    modal.classList.add(
        'show'
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeUserModal()
{

    document
        .getElementById(
            'userModal'
        )
        .classList.remove(
            'show'
        );
}


function closeModalOutside(event)
{

    if (
        event.target.id ===
        'userModal'
    ) {

        closeUserModal();
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{

    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeUserModal();
        }

    }
);

</script>

</body>

</html>