<?php

require_once 'includes/config.php';
require_once 'includes/session.php';


/* =====================================================
   CHECK LOGIN
===================================================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}


$user_id = (int) $_SESSION['user_id'];


/* =====================================================
   CHECK ORDER ID
===================================================== */

if (
    !isset($_GET['order_id']) ||
    (int)$_GET['order_id'] <= 0
) {

    header("Location: my_orders.php");
    exit;

}


$order_id = (int) $_GET['order_id'];


/* =====================================================
   GET ORDER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        order_status

    FROM orders

    WHERE id = ?
    AND user_id = ?

    LIMIT 1
");


if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}


$stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();


$order = $result->fetch_assoc();


$stmt->close();


/* =====================================================
   CHECK ORDER EXISTS
===================================================== */

if (!$order) {

    header("Location: my_orders.php");
    exit;

}


$status = strtolower(
    trim(
        $order['order_status']
    )
);


/* =====================================================
   ALLOWED CANCELLATION STATUS
===================================================== */

$allowedStatuses = [
    'pending',
    'confirmed',
    'accepted'
];


if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    header(
        "Location: order_success.php?order_id=" .
        $order_id .
        "&cancel=not_allowed"
    );

    exit;

}


/* =====================================================
   CANCEL ORDER
===================================================== */

$update = $conn->prepare("
    UPDATE orders

    SET order_status = 'cancelled'

    WHERE id = ?
    AND user_id = ?
");


if (!$update) {

    die(
        "Database error: " .
        $conn->error
    );

}


$update->bind_param(
    "ii",
    $order_id,
    $user_id
);


$update->execute();


$update->close();


/* =====================================================
   REDIRECT
===================================================== */

header(
    "Location: order_success.php?order_id=" .
    $order_id .
    "&cancel=success"
);

exit;

?>