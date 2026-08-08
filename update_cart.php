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


/* =====================================================
   CHECK REQUEST METHOD
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: cart.php");
    exit;

}


/* =====================================================
   CHECK REQUIRED DATA
===================================================== */

if (
    !isset($_POST['cart_id']) ||
    !isset($_POST['quantity'])
) {

    header("Location: cart.php");
    exit;

}


/* =====================================================
   GET DATA
===================================================== */

$user_id = (int) $_SESSION['user_id'];

$cart_id = (int) $_POST['cart_id'];

$quantity = (int) $_POST['quantity'];


/* =====================================================
   VALIDATE DATA
===================================================== */

if ($user_id <= 0 || $cart_id <= 0 || $quantity < 1) {

    header("Location: cart.php");
    exit;

}


/* =====================================================
   CHECK CART ITEM BELONGS TO USER
===================================================== */

$check = $conn->prepare("
    SELECT id
    FROM cart
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");


if (!$check) {

    die("Database error: " . $conn->error);

}


$check->bind_param(
    "ii",
    $cart_id,
    $user_id
);

$check->execute();

$result = $check->get_result();


if ($result->num_rows === 0) {

    $check->close();

    header("Location: cart.php");
    exit;

}


$check->close();


/* =====================================================
   UPDATE QUANTITY
===================================================== */

$stmt = $conn->prepare("
    UPDATE cart
    SET quantity = ?
    WHERE id = ?
    AND user_id = ?
");


if (!$stmt) {

    die("Database error: " . $conn->error);

}


$stmt->bind_param(
    "iii",
    $quantity,
    $cart_id,
    $user_id
);


/* =====================================================
   EXECUTE UPDATE
===================================================== */

if (!$stmt->execute()) {

    die("Unable to update cart: " . $stmt->error);

}


$stmt->close();


/* =====================================================
   RETURN TO CART
===================================================== */

header("Location: cart.php?" . time());
exit;
?>