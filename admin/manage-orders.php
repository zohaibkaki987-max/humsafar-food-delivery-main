<?php
session_start();

require_once __DIR__ . '/../includes/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

$is_admin = !empty($_SESSION['admin_logged_in']);

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
        $user = $result ? $result->fetch_assoc() : null;

        $statement->close();

        if (
            $user &&
            strtolower((string)($user['role'] ?? '')) === 'admin'
        ) {

            $is_admin = true;

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name'] = $user['full_name'] ?? 'Administrator';
            $_SESSION['admin_email'] = $user['email'] ?? '';
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


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$message_type = '';

$allowed_statuses = [
    'pending',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'rider_assigned',
    'picked_up',
    'on_the_way',
    'delivered',
    'cancelled',
    'rejected'
];


/*
|--------------------------------------------------------------------------
| UPDATE ORDER STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | STATUS UPDATE
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_status') {

        $order_id = (int)($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['order_status'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if (
            $order_id <= 0 ||
            !in_array($new_status, $allowed_statuses, true)
        ) {

            $message = 'Invalid order status request.';
            $message_type = 'error';

        } else {

            /*
            |--------------------------------------------------------------------------
            | GET CURRENT ORDER
            |--------------------------------------------------------------------------
            */

            $old_status = '';

            $statement = $conn->prepare(
                "SELECT id, user_id, restaurant_id, order_status
                 FROM orders
                 WHERE id = ?
                 LIMIT 1"
            );

            if ($statement) {

                $statement->bind_param('i', $order_id);
                $statement->execute();

                $result = $statement->get_result();
                $order = $result ? $result->fetch_assoc() : null;

                $statement->close();

            } else {
                $order = null;
            }

            if (!$order) {

                $message = 'Order not found.';
                $message_type = 'error';

            } else {

                $old_status = $order['order_status'];

                /*
                |--------------------------------------------------------------------------
                | UPDATE ORDER
                |--------------------------------------------------------------------------
                */

                $statement = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?
                     WHERE id = ?
                     LIMIT 1"
                );

                if (!$statement) {

                    $message = 'Database error: ' . $conn->error;
                    $message_type = 'error';

                } else {

                    $statement->bind_param(
                        'si',
                        $new_status,
                        $order_id
                    );

                    if ($statement->execute()) {

                        $statement->close();

                        /*
                        |--------------------------------------------------------------------------
                        | ORDER STATUS HISTORY
                        |--------------------------------------------------------------------------
                        */

                        if (tableExists($conn, 'order_status_history')) {

                            $changed_by = !empty($_SESSION['user_id'])
                                ? (int)$_SESSION['user_id']
                                : null;

                            $changed_by_role = 'admin';

                            $history = $conn->prepare(
                                "INSERT INTO order_status_history
                                (
                                    order_id,
                                    status,
                                    changed_by,
                                    changed_by_role,
                                    note
                                )
                                VALUES (?, ?, ?, ?, ?)"
                            );

                            if ($history) {

                                $history->bind_param(
                                    'isiss',
                                    $order_id,
                                    $new_status,
                                    $changed_by,
                                    $changed_by_role,
                                    $note
                                );

                                $history->execute();
                                $history->close();
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CUSTOMER NOTIFICATION
                        |--------------------------------------------------------------------------
                        */

                        if (tableExists($conn, 'notifications')) {

                            $title = 'Order Status Updated';

                            $status_text = ucwords(
                                str_replace('_', ' ', $new_status)
                            );

                            $notification_message =
                                "Your order #{$order_id} status has been updated to {$status_text}.";

                            $notification_type = 'order_status';

                            $notification = $conn->prepare(
                                "INSERT INTO notifications
                                (
                                    user_id,
                                    role,
                                    title,
                                    message,
                                    type,
                                    reference_id
                                )
                                VALUES (?, 'customer', ?, ?, ?, ?)"
                            );

                            if ($notification) {

                                $notification->bind_param(
                                    'isssi',
                                    $order['user_id'],
                                    $title,
                                    $notification_message,
                                    $notification_type,
                                    $order_id
                                );

                                $notification->execute();
                                $notification->close();
                            }
                        }

                        $message =
                            'Order status updated successfully.';

                        $message_type = 'success';

                    } else {

                        $message =
                            'Unable to update order: '
                            . $statement->error;

                        $message_type = 'error';

                        $statement->close();
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ASSIGN RIDER
    |--------------------------------------------------------------------------
    */

    if ($action === 'assign_rider') {

        $order_id = (int)($_POST['order_id'] ?? 0);
        $rider_id = (int)($_POST['rider_id'] ?? 0);

        if ($order_id <= 0 || $rider_id <= 0) {

            $message = 'Please select a valid rider.';
            $message_type = 'error';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CHECK ORDER
            |--------------------------------------------------------------------------
            */

            $statement = $conn->prepare(
                "SELECT id, user_id, delivery_fee, order_status
                 FROM orders
                 WHERE id = ?
                 LIMIT 1"
            );

            $statement->bind_param('i', $order_id);
            $statement->execute();

            $result = $statement->get_result();
            $order = $result ? $result->fetch_assoc() : null;

            $statement->close();


            /*
            |--------------------------------------------------------------------------
            | CHECK RIDER
            |--------------------------------------------------------------------------
            */

            $rider_statement = $conn->prepare(
                "SELECT id, full_name, status
                 FROM riders
                 WHERE id = ?
                 LIMIT 1"
            );

            $rider_statement->bind_param('i', $rider_id);
            $rider_statement->execute();

            $rider_result = $rider_statement->get_result();
            $rider = $rider_result
                ? $rider_result->fetch_assoc()
                : null;

            $rider_statement->close();


            if (!$order) {

                $message = 'Order not found.';
                $message_type = 'error';

            } elseif (!$rider) {

                $message = 'Rider not found.';
                $message_type = 'error';

            } elseif ($rider['status'] !== 'active') {

                $message = 'Selected rider is not active.';
                $message_type = 'error';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK EXISTING DELIVERY
                |--------------------------------------------------------------------------
                */

                $existing = null;

                $check = $conn->prepare(
                    "SELECT id
                     FROM rider_deliveries
                     WHERE order_id = ?
                     LIMIT 1"
                );

                if ($check) {

                    $check->bind_param('i', $order_id);
                    $check->execute();

                    $check_result = $check->get_result();
                    $existing = $check_result
                        ? $check_result->fetch_assoc()
                        : null;

                    $check->close();
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE EXISTING DELIVERY
                |--------------------------------------------------------------------------
                */

                if ($existing) {

                    $delivery_id = (int)$existing['id'];

                    $update_delivery = $conn->prepare(
                        "UPDATE rider_deliveries
                         SET rider_id = ?,
                             status = 'assigned',
                             assigned_at = CURRENT_TIMESTAMP,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         LIMIT 1"
                    );

                    if ($update_delivery) {

                        $update_delivery->bind_param(
                            'ii',
                            $rider_id,
                            $delivery_id
                        );

                        $update_delivery->execute();
                        $update_delivery->close();
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE DELIVERY
                    |--------------------------------------------------------------------------
                    */

                    $delivery_fee = (float)$order['delivery_fee'];

                    $rider_earning = $delivery_fee;

                    $insert_delivery = $conn->prepare(
                        "INSERT INTO rider_deliveries
                        (
                            rider_id,
                            order_id,
                            status,
                            delivery_fee,
                            rider_earning,
                            assigned_at
                        )
                        VALUES (?, ?, 'assigned', ?, ?, CURRENT_TIMESTAMP)"
                    );

                    if ($insert_delivery) {

                        $insert_delivery->bind_param(
                            'iidd',
                            $rider_id,
                            $order_id,
                            $delivery_fee,
                            $rider_earning
                        );

                        $insert_delivery->execute();
                        $insert_delivery->close();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE ORDER STATUS
                |--------------------------------------------------------------------------
                */

                $new_status = 'rider_assigned';

                $update_order = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?
                     WHERE id = ?
                     LIMIT 1"
                );

                if ($update_order) {

                    $update_order->bind_param(
                        'si',
                        $new_status,
                        $order_id
                    );

                    $update_order->execute();
                    $update_order->close();
                }


                /*
                |--------------------------------------------------------------------------
                | STATUS HISTORY
                |--------------------------------------------------------------------------
                */

                if (tableExists($conn, 'order_status_history')) {

                    $changed_by = !empty($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : null;

                    $changed_by_role = 'admin';

                    $note = "Rider assigned: "
                        . ($rider['full_name'] ?? 'Rider');

                    $history = $conn->prepare(
                        "INSERT INTO order_status_history
                        (
                            order_id,
                            status,
                            changed_by,
                            changed_by_role,
                            note
                        )
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    if ($history) {

                        $history->bind_param(
                            'isiss',
                            $order_id,
                            $new_status,
                            $changed_by,
                            $changed_by_role,
                            $note
                        );

                        $history->execute();
                        $history->close();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if (tableExists($conn, 'notifications')) {

                    $title = 'Rider Assigned';

                    $notification_message =
                        "A rider has been assigned to your order #{$order_id}.";

                    $notification_type = 'rider_assigned';

                    $notification = $conn->prepare(
                        "INSERT INTO notifications
                        (
                            user_id,
                            role,
                            title,
                            message,
                            type,
                            reference_id
                        )
                        VALUES (?, 'customer', ?, ?, ?, ?)"
                    );

                    if ($notification) {

                        $notification->bind_param(
                            'isssi',
                            $order['user_id'],
                            $title,
                            $notification_message,
                            $notification_type,
                            $order_id
                        );

                        $notification->execute();
                        $notification->close();
                    }
                }

                $message =
                    'Rider assigned successfully.';

                $message_type = 'success';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$payment_filter = trim($_GET['payment'] ?? '');


/*
|--------------------------------------------------------------------------
| ORDER QUERY
|--------------------------------------------------------------------------
*/

$orders = [];

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.user_id,
        o.restaurant_id,
        o.address_id,
        o.payment_method,
        o.subtotal,
        o.delivery_fee,
        o.discount,
        o.total,
        o.order_status,
        o.customer_note,
        o.created_at,
        o.updated_at,

        u.full_name AS customer_name,
        u.phone AS customer_phone,

        r.name AS restaurant_name,

        rd.rider_id,
        rd.status AS rider_delivery_status,

        riders.full_name AS rider_name

    FROM orders o

    LEFT JOIN users u
        ON u.id = o.user_id

    LEFT JOIN restaurants r
        ON r.id = o.restaurant_id

    LEFT JOIN rider_deliveries rd
        ON rd.order_id = o.id

    LEFT JOIN riders
        ON riders.id = rd.rider_id

    WHERE 1 = 1
";


$params = [];
$types = '';


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            o.order_number LIKE ?
            OR u.full_name LIKE ?
            OR u.phone LIKE ?
            OR r.name LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'ssss';
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $status_filter !== '' &&
    in_array($status_filter, $allowed_statuses, true)
) {

    $sql .= " AND o.order_status = ? ";

    $params[] = $status_filter;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| PAYMENT FILTER
|--------------------------------------------------------------------------
*/

$allowed_payments = [
    'cash_on_delivery',
    'card',
    'online'
];

if (
    $payment_filter !== '' &&
    in_array($payment_filter, $allowed_payments, true)
) {

    $sql .= " AND o.payment_method = ? ";

    $params[] = $payment_filter;
    $types .= 's';
}


$sql .= "
    ORDER BY o.created_at DESC
";


$statement = $conn->prepare($sql);

if ($statement) {

    if (!empty($params)) {

        $statement->bind_param(
            $types,
            ...$params
        );
    }

    $statement->execute();

    $result = $statement->get_result();

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;
    }

    $statement->close();
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$total_orders = 0;
$pending_orders = 0;
$active_orders = 0;
$delivered_orders = 0;
$cancelled_orders = 0;
$total_sales = 0;

$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total_orders,

        SUM(
            order_status IN (
                'pending',
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'rider_assigned',
                'picked_up',
                'on_the_way'
            )
        ) AS active_orders,

        SUM(order_status = 'pending') AS pending_orders,

        SUM(order_status = 'delivered') AS delivered_orders,

        SUM(
            order_status IN ('cancelled', 'rejected')
        ) AS cancelled_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN order_status = 'delivered'
                    THEN total
                    ELSE 0
                END
            ),
            0
        ) AS total_sales

     FROM orders"
);

if ($stats_result) {

    $stats = $stats_result->fetch_assoc();

    $total_orders =
        (int)($stats['total_orders'] ?? 0);

    $pending_orders =
        (int)($stats['pending_orders'] ?? 0);

    $active_orders =
        (int)($stats['active_orders'] ?? 0);

    $delivered_orders =
        (int)($stats['delivered_orders'] ?? 0);

    $cancelled_orders =
        (int)($stats['cancelled_orders'] ?? 0);

    $total_sales =
        (float)($stats['total_sales'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| ACTIVE RIDERS
|--------------------------------------------------------------------------
*/

$riders = [];

if (tableExists($conn, 'riders')) {

    $rider_result = $conn->query(
        "SELECT
            id,
            full_name,
            phone,
            status,
            availability_status
         FROM riders
         WHERE status = 'active'
         ORDER BY full_name ASC"
    );

    if ($rider_result) {

        while ($rider_row = $rider_result->fetch_assoc()) {

            $riders[] = $rider_row;
        }
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

<title>Manage Orders | Humsafar</title>

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
select,
textarea {
    font-family: inherit;
}


/* =========================================================
   LAYOUT
========================================================= */

.layout {
    min-height: 100vh;
    display: flex;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 218px;

    background: #ffffff;

    border-right: 1px solid #f1dfe7;

    display: flex;
    flex-direction: column;

    z-index: 20;
}

.brand {
    padding: 23px 20px 18px;

    border-bottom: 1px solid #f2e2e8;
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

    border-radius: 50%;
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

    margin: 0 11px 15px;

    border: 1px solid #f2dce5;

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


/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 218px;

    width: calc(100% - 218px);

    padding: 16px 28px 35px;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    min-height: 60px;

    background: #ffffff;

    border: 1px solid #f0e4e9;

    box-shadow:
        0 4px 16px rgba(0,0,0,.04);

    padding: 10px 14px;

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
    border: 1px solid #f1ccd9;

    border-radius: 9px;

    padding: 9px 12px;

    color: #df0038;

    font-size: 10px;

    font-weight: 800;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {
    padding: 11px 14px;

    border-radius: 9px;

    margin-bottom: 16px;

    font-size: 10px;

    font-weight: 700;
}

.success {
    color: #177245;

    background: #e7f8ed;

    border: 1px solid #c6ecd5;
}

.error {
    color: #b42318;

    background: #fff0ee;

    border: 1px solid #f2c7c1;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 16px;
}

.page-header h2 {
    margin: 0;

    font-size: 20px;

    font-weight: 900;
}

.page-header p {
    margin: 4px 0 0;

    font-size: 10px;

    color: #999;
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 11px;

    margin-bottom: 18px;
}

.stat {
    min-height: 105px;

    padding: 15px 10px;

    border-radius: 13px;

    background: #ffffff;

    border: 1px solid #f1dfe7;

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

    margin-bottom: 7px;
}

.stat strong {
    font-size: 19px;

    line-height: 1;
}

.stat span {
    margin-top: 5px;

    color: #888;

    font-size: 9px;
}


/* =========================================================
   FILTERS
========================================================= */

.filter-card {
    background: #ffffff;

    border: 1px solid #f1dfe7;

    border-radius: 13px;

    padding: 15px;

    margin-bottom: 16px;
}

.filters {
    display: grid;

    grid-template-columns:
        1.6fr
        1fr
        1fr
        auto;

    gap: 10px;

    align-items: end;
}

.field label {
    display: block;

    font-size: 9px;

    font-weight: 800;

    color: #777;

    margin-bottom: 6px;
}

.field input,
.field select {
    width: 100%;

    height: 39px;

    padding: 0 11px;

    border: 1px solid #ead9e0;

    border-radius: 8px;

    outline: none;

    background: #ffffff;

    font-size: 10px;
}

.field input:focus,
.field select:focus {
    border-color: #ef003c;

    box-shadow:
        0 0 0 3px rgba(239,0,60,.07);
}

.filter-actions {
    display: flex;

    gap: 7px;
}

.btn {
    min-height: 39px;

    border: 0;

    border-radius: 8px;

    padding: 0 14px;

    cursor: pointer;

    font-size: 10px;

    font-weight: 800;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    transition: .2s;
}

.btn-primary {
    background: #ef003c;

    color: #ffffff;
}

.btn-primary:hover {
    background: #d90036;
}

.btn-light {
    background: #fff0f5;

    color: #e5003b;
}

.btn-light:hover {
    background: #ffe2ec;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {
    background: #ffffff;

    border: 1px solid #f1dfe7;

    border-radius: 13px;

    overflow: hidden;
}

.table-header {
    padding: 14px 16px;

    border-bottom: 1px solid #f1e5ea;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.table-header strong {
    font-size: 12px;
}

.table-header span {
    font-size: 9px;

    color: #999;
}

.table-wrapper {
    width: 100%;

    overflow-x: auto;
}

table {
    width: 100%;

    min-width: 1080px;

    border-collapse: collapse;
}

thead {
    background: #fff8fb;
}

th {
    padding: 12px 13px;

    text-align: left;

    color: #777;

    font-size: 8px;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing: .4px;

    white-space: nowrap;
}

td {
    padding: 12px 13px;

    border-top: 1px solid #f4e9ed;

    font-size: 9px;

    vertical-align: middle;
}

tr:hover td {
    background: #fffafd;
}

.order-number {
    color: #e6003b;

    font-weight: 900;

    white-space: nowrap;
}

.customer strong,
.restaurant strong {
    display: block;

    font-size: 10px;

    margin-bottom: 3px;
}

.customer small,
.restaurant small {
    color: #999;

    font-size: 8px;
}

.amount {
    font-weight: 900;

    color: #222;

    white-space: nowrap;
}

.payment {
    display: inline-flex;

    padding: 5px 8px;

    border-radius: 20px;

    background: #f5f5f5;

    font-size: 8px;

    font-weight: 800;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: 900;

    white-space: nowrap;
}

.status::before {
    content: "";

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background: currentColor;
}

.status-pending {
    color: #a56a00;
    background: #fff6dc;
}

.status-confirmed {
    color: #1565c0;
    background: #e8f2ff;
}

.status-preparing {
    color: #7b3fb2;
    background: #f4e9ff;
}

.status-ready_for_pickup {
    color: #8b5a00;
    background: #fff0ce;
}

.status-rider_assigned {
    color: #00695c;
    background: #e4f8f4;
}

.status-picked_up {
    color: #00695c;
    background: #e0f7f3;
}

.status-on_the_way {
    color: #0077a8;
    background: #e3f6ff;
}

.status-delivered {
    color: #177245;
    background: #e7f8ed;
}

.status-cancelled,
.status-rejected {
    color: #b42318;
    background: #fff0ee;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {
    display: flex;

    align-items: center;

    gap: 5px;

    flex-wrap: wrap;
}

.action-btn {
    width: 31px;
    height: 31px;

    border: 1px solid #eadce2;

    background: #ffffff;

    border-radius: 7px;

    color: #666;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 10px;

    transition: .2s;
}

.action-btn:hover {
    border-color: #ef003c;

    color: #ef003c;

    background: #fff0f5;
}

.action-btn.view {
    color: #e6003b;
}

.action-btn.assign {
    color: #16705c;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    padding: 55px 20px;

    text-align: center;

    color: #999;
}

.empty i {
    font-size: 30px;

    color: #ef003c;

    margin-bottom: 10px;
}

.empty strong {
    display: block;

    color: #444;

    font-size: 13px;

    margin-bottom: 5px;
}

.empty span {
    font-size: 9px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {
    position: fixed;

    inset: 0;

    background: rgba(22, 10, 15, .55);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 100;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;

    max-width: 460px;

    background: #ffffff;

    border-radius: 15px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.18);

    overflow: hidden;
}

.modal-header {
    padding: 15px 17px;

    border-bottom: 1px solid #f1e3e8;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.modal-header h3 {
    margin: 0;

    font-size: 14px;

    font-weight: 900;
}

.close {
    width: 30px;
    height: 30px;

    border: 0;

    border-radius: 7px;

    background: #fff0f5;

    color: #e9003d;

    cursor: pointer;
}

.modal-body {
    padding: 17px;
}

.modal-body label {
    display: block;

    font-size: 9px;

    font-weight: 800;

    color: #777;

    margin-bottom: 6px;
}

.modal-body select,
.modal-body textarea {
    width: 100%;

    border: 1px solid #ead9e0;

    border-radius: 8px;

    outline: none;

    padding: 10px;

    font-size: 10px;

    margin-bottom: 13px;
}

.modal-body textarea {
    min-height: 85px;

    resize: vertical;
}

.modal-footer {
    padding: 13px 17px;

    border-top: 1px solid #f1e3e8;

    display: flex;

    justify-content: flex-end;

    gap: 8px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .filters {
        grid-template-columns:
            1fr 1fr;
    }

    .filter-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 760px) {

    .sidebar {
        width: 70px;
    }

    .brand {
        padding: 16px 10px;
    }

    .brand-title,
    .brand-sub,
    .profile span,
    .label,
    .nav span,
    .side-user div:not(.side-user-icon) {
        display: none;
    }

    .brand a {
        justify-content: center;
    }

    .profile {
        justify-content: center;
    }

    .nav {
        justify-content: center;
        padding: 10px;
    }

    .side-user {
        justify-content: center;

        margin-left: 9px;
        margin-right: 9px;
    }

    .main {
        margin-left: 70px;

        width: calc(100% - 70px);

        padding: 12px;
    }

    .topbar h1 {
        font-size: 19px;
    }

    .date {
        display: none;
    }

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        grid-column: auto;
    }

    .page-header {
        align-items: flex-start;
    }
}

@media (max-width: 450px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        flex-direction: column;
    }

    .filter-actions .btn {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="layout">


<!-- =====================================================
     SIDEBAR
===================================================== -->

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
                    ADMINISTRATION
                </span>
            </div>

        </a>

    </div>


    <div class="side-content">

        <a
            href="profile.php"
            class="profile"
        >

            <i class="fa-solid fa-user-shield"></i>

            <span>
                <?php echo h($admin_name); ?>
            </span>

        </a>


        <div class="label">
            Dashboard
        </div>

        <div class="nav-box">

            <a
                href="admin-panel.php"
                class="nav"
            >
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

        </div>


        <div class="label">
            Management
        </div>

        <div class="nav-box">

            <a
                href="manage-users.php"
                class="nav"
            >
                <i class="fa-solid fa-users"></i>
                <span>Users</span>
            </a>

            <a
                href="manage-orders.php"
                class="nav active"
            >
                <i class="fa-solid fa-bag-shopping"></i>
                <span>Orders</span>
            </a>

            <a
                href="manage-restaurants.php"
                class="nav"
            >
                <i class="fa-solid fa-store"></i>
                <span>Restaurants</span>
            </a>

            <a
                href="manage-riders.php"
                class="nav"
            >
                <i class="fa-solid fa-motorcycle"></i>
                <span>Riders</span>
            </a>

            <a
                href="manage-menu.php"
                class="nav"
            >
                <i class="fa-solid fa-utensils"></i>
                <span>Menu</span>
            </a>

            <a
                href="manage-categories.php"
                class="nav"
            >
                <i class="fa-solid fa-layer-group"></i>
                <span>Categories</span>
            </a>

            <a
                href="manage-coupons.php"
                class="nav"
            >
                <i class="fa-solid fa-ticket"></i>
                <span>Coupons</span>
            </a>

        </div>


        <div class="label">
            Finance
        </div>

        <div class="nav-box">

            <a
                href="payments.php"
                class="nav"
            >
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a
                href="refunds.php"
                class="nav"
            >
                <i class="fa-solid fa-rotate-left"></i>
                <span>Refunds</span>
            </a>

            <a
                href="restaurant-payments.php"
                class="nav"
            >
                <i class="fa-solid fa-money-check-dollar"></i>
                <span>Restaurant Payments</span>
            </a>

            <a
                href="restaurant-payouts.php"
                class="nav"
            >
                <i class="fa-solid fa-money-bill-transfer"></i>
                <span>Restaurant Payouts</span>
            </a>

            <a
                href="rider-payouts.php"
                class="nav"
            >
                <i class="fa-solid fa-wallet"></i>
                <span>Rider Payouts</span>
            </a>

        </div>


        <div class="label">
            Support
        </div>

        <div class="nav-box">

            <a
                href="support-tickets.php"
                class="nav"
            >
                <i class="fa-solid fa-headset"></i>
                <span>Support Tickets</span>
            </a>

            <a
                href="notifications.php"
                class="nav"
            >
                <i class="fa-solid fa-bell"></i>
                <span>Notifications</span>
            </a>

        </div>

    </div>


    <div class="side-user">

        <div class="side-user-icon">
            <i class="fa-solid fa-user"></i>
        </div>

        <div>

            <strong>
                <?php echo h($admin_name); ?>
            </strong>

            <small>
                Administrator
            </small>

        </div>

    </div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                Manage Orders
            </h1>

            <p>
                View, monitor and manage all customer orders.
            </p>

        </div>

        <div class="date">

            <i class="fa-regular fa-calendar"></i>

            <?php echo date('d M Y'); ?>

        </div>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ''): ?>

        <div class="message <?php echo h($message_type); ?>">

            <?php if ($message_type === 'success'): ?>

                <i class="fa-solid fa-circle-check"></i>

            <?php else: ?>

                <i class="fa-solid fa-circle-exclamation"></i>

            <?php endif; ?>

            <?php echo h($message); ?>

        </div>

    <?php endif; ?>


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h2>
                All Orders
            </h2>

            <p>
                Manage order status, riders and order activity.
            </p>

        </div>

    </div>


    <!-- =================================================
         STATS
    ================================================= -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>

            <strong>
                <?php echo number_format($total_orders); ?>
            </strong>

            <span>
                Total Orders
            </span>

        </div>


        <div class="stat">

            <div class="stat-icon">
                <i class="fa-solid fa-clock"></i>
            </div>

            <strong>
                <?php echo number_format($pending_orders); ?>
            </strong>

            <span>
                Pending
            </span>

        </div>


        <div class="stat">

            <div class="stat-icon">
                <i class="fa-solid fa-truck-fast"></i>
            </div>

            <strong>
                <?php echo number_format($active_orders); ?>
            </strong>

            <span>
                Active Orders
            </span>

        </div>


        <div class="stat">

            <div class="stat-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <strong>
                <?php echo number_format($delivered_orders); ?>
            </strong>

            <span>
                Delivered
            </span>

        </div>


        <div class="stat">

            <div class="stat-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>

            <strong>
                Rs. <?php echo number_format($total_sales, 0); ?>
            </strong>

            <span>
                Delivered Sales
            </span>

        </div>


    </section>


    <!-- =================================================
         FILTERS
    ================================================= -->

    <div class="filter-card">

        <form
            method="GET"
            action="manage-orders.php"
        >

            <div class="filters">


                <div class="field">

                    <label>
                        Search Orders
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?php echo h($search); ?>"
                        placeholder="Order number, customer, phone or restaurant..."
                    >

                </div>


                <div class="field">

                    <label>
                        Order Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach ($allowed_statuses as $status): ?>

                            <option
                                value="<?php echo h($status); ?>"
                                <?php echo $status_filter === $status ? 'selected' : ''; ?>
                            >

                                <?php
                                echo h(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $status
                                        )
                                    )
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        Payment Method
                    </label>

                    <select name="payment">

                        <option value="">
                            All Payments
                        </option>

                        <?php foreach ($allowed_payments as $payment): ?>

                            <option
                                value="<?php echo h($payment); ?>"
                                <?php echo $payment_filter === $payment ? 'selected' : ''; ?>
                            >

                                <?php
                                echo h(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $payment
                                        )
                                    )
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-filter"></i>

                        Filter

                    </button>

                    <a
                        href="manage-orders.php"
                        class="btn btn-light"
                    >

                        <i class="fa-solid fa-rotate-left"></i>

                        Reset

                    </a>

                </div>


            </div>

        </form>

    </div>


    <!-- =================================================
         ORDERS TABLE
    ================================================= -->

    <div class="table-card">


        <div class="table-header">

            <strong>
                Orders List
            </strong>

            <span>
                <?php echo count($orders); ?> order(s) found
            </span>

        </div>


        <?php if (empty($orders)): ?>

            <div class="empty">

                <i class="fa-solid fa-box-open"></i>

                <strong>
                    No orders found
                </strong>

                <span>
                    Try changing your search or filter.
                </span>

            </div>

        <?php else: ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>
                            Order
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Restaurant
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Rider
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($orders as $order): ?>

                        <?php

                        $status =
                            $order['order_status'];

                        $status_label =
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $status
                                )
                            );

                        $payment_label =
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $order['payment_method']
                                )
                            );

                        ?>


                        <tr>


                            <!-- ORDER -->

                            <td>

                                <div class="order-number">

                                    #<?php echo h($order['order_number']); ?>

                                </div>

                                <small>
                                    ID: <?php echo (int)$order['id']; ?>
                                </small>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer">

                                    <strong>
                                        <?php
                                        echo h(
                                            $order['customer_name']
                                            ?: 'Unknown Customer'
                                        );
                                        ?>
                                    </strong>

                                    <small>

                                        <?php
                                        echo h(
                                            $order['customer_phone']
                                            ?: 'No phone'
                                        );
                                        ?>

                                    </small>

                                </div>

                            </td>


                            <!-- RESTAURANT -->

                            <td>

                                <div class="restaurant">

                                    <strong>

                                        <?php
                                        echo h(
                                            $order['restaurant_name']
                                            ?: 'Unknown Restaurant'
                                        );
                                        ?>

                                    </strong>

                                    <small>
                                        Restaurant ID:
                                        <?php echo (int)$order['restaurant_id']; ?>
                                    </small>

                                </div>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <span class="payment">

                                    <?php echo h($payment_label); ?>

                                </span>

                            </td>


                            <!-- TOTAL -->

                            <td>

                                <span class="amount">

                                    Rs.
                                    <?php
                                    echo number_format(
                                        (float)$order['total'],
                                        2
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?php echo h($status); ?>"
                                >

                                    <?php echo h($status_label); ?>

                                </span>

                            </td>


                            <!-- RIDER -->

                            <td>

                                <?php if (!empty($order['rider_name'])): ?>

                                    <strong>
                                        <?php
                                        echo h(
                                            $order['rider_name']
                                        );
                                        ?>
                                    </strong>

                                    <small>

                                        <?php
                                        echo h(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $order['rider_delivery_status']
                                                    ?? ''
                                                )
                                            )
                                        );
                                        ?>

                                    </small>

                                <?php else: ?>

                                    <span
                                        style="
                                            color:#999;
                                            font-size:8px;
                                        "
                                    >
                                        Not Assigned
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <strong style="font-size:9px;">

                                    <?php
                                    echo date(
                                        'd M Y',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    );
                                    ?>

                                </strong>

                                <small
                                    style="
                                        display:block;
                                        color:#999;
                                        font-size:8px;
                                        margin-top:3px;
                                    "
                                >

                                    <?php
                                    echo date(
                                        'h:i A',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    );
                                    ?>

                                </small>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW -->

                                    <a
                                        href="order-details.php?id=<?php echo (int)$order['id']; ?>"
                                        class="action-btn view"
                                        title="View Order"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                    </a>


                                    <!-- STATUS -->

                                    <button
                                        type="button"
                                        class="action-btn"
                                        title="Update Status"
                                        onclick="openStatusModal(
                                            <?php echo (int)$order['id']; ?>,
                                            '<?php echo h($status); ?>'
                                        )"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                    </button>


                                    <!-- ASSIGN RIDER -->

                                    <button
                                        type="button"
                                        class="action-btn assign"
                                        title="Assign Rider"
                                        onclick="openRiderModal(
                                            <?php echo (int)$order['id']; ?>
                                        )"
                                    >

                                        <i class="fa-solid fa-motorcycle"></i>

                                    </button>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>


    </div>


</main>

</div>


<!-- =====================================================
     STATUS MODAL
===================================================== -->

<div
    class="modal"
    id="statusModal"
>

    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Update Order Status
            </h3>

            <button
                type="button"
                class="close"
                onclick="closeModal('statusModal')"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form
            method="POST"
            action="manage-orders.php"
        >

            <div class="modal-body">

                <input
                    type="hidden"
                    name="action"
                    value="update_status"
                >

                <input
                    type="hidden"
                    name="order_id"
                    id="statusOrderId"
                >


                <label>
                    Order Status
                </label>

                <select
                    name="order_status"
                    id="statusSelect"
                    required
                >

                    <?php foreach ($allowed_statuses as $status): ?>

                        <option
                            value="<?php echo h($status); ?>"
                        >

                            <?php
                            echo h(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $status
                                    )
                                )
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <label>
                    Admin Note
                </label>

                <textarea
                    name="note"
                    placeholder="Optional note about this status change..."
                ></textarea>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeModal('statusModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-check"></i>

                    Update Status

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     RIDER MODAL
===================================================== -->

<div
    class="modal"
    id="riderModal"
>

    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Assign Rider
            </h3>

            <button
                type="button"
                class="close"
                onclick="closeModal('riderModal')"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form
            method="POST"
            action="manage-orders.php"
        >

            <div class="modal-body">

                <input
                    type="hidden"
                    name="action"
                    value="assign_rider"
                >

                <input
                    type="hidden"
                    name="order_id"
                    id="riderOrderId"
                >


                <label>
                    Select Rider
                </label>

                <select
                    name="rider_id"
                    required
                >

                    <option value="">
                        Select active rider
                    </option>

                    <?php foreach ($riders as $rider): ?>

                        <option
                            value="<?php echo (int)$rider['id']; ?>"
                        >

                            <?php
                            echo h(
                                $rider['full_name']
                            );
                            ?>

                            -

                            <?php
                            echo h(
                                $rider['phone']
                            );
                            ?>

                            <?php
                            if (
                                ($rider['availability_status'] ?? '')
                                === 'available'
                            ) {
                                echo ' - Available';
                            } elseif (
                                ($rider['availability_status'] ?? '')
                                === 'busy'
                            ) {
                                echo ' - Busy';
                            }
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <?php if (empty($riders)): ?>

                    <div
                        style="
                            background:#fff0f5;
                            color:#d90036;
                            padding:10px;
                            border-radius:8px;
                            font-size:9px;
                            margin-top:4px;
                        "
                    >

                        No active riders are available.

                    </div>

                <?php endif; ?>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeModal('riderModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    <?php echo empty($riders) ? 'disabled' : ''; ?>
                >

                    <i class="fa-solid fa-motorcycle"></i>

                    Assign Rider

                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| STATUS MODAL
|--------------------------------------------------------------------------
*/

function openStatusModal(orderId, currentStatus)
{
    document.getElementById('statusOrderId').value = orderId;

    const select =
        document.getElementById('statusSelect');

    select.value = currentStatus;

    document
        .getElementById('statusModal')
        .classList
        .add('show');
}


/*
|--------------------------------------------------------------------------
| RIDER MODAL
|--------------------------------------------------------------------------
*/

function openRiderModal(orderId)
{
    document.getElementById('riderOrderId').value = orderId;

    document
        .getElementById('riderModal')
        .classList
        .add('show');
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeModal(id)
{
    document
        .getElementById(id)
        .classList
        .remove('show');
}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE MODAL
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.modal').forEach(function(modal) {

    modal.addEventListener('click', function(event) {

        if (event.target === modal) {

            modal.classList.remove('show');

        }

    });

});


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {

        document
            .querySelectorAll('.modal.show')
            .forEach(function(modal) {

                modal.classList.remove('show');

            });

    }

});

</script>

</body>
</html>