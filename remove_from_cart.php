<?php
require_once 'includes/config.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$user_id = $_SESSION['user_id'];
$cart_id = (int)$_GET['id'];

$stmt = $conn->prepare("DELETE FROM cart WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $cart_id, $user_id);
$stmt->execute();

header("Location: cart.php");
exit;
?>