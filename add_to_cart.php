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
$menu_item_id = (int)$_GET['id'];

// Check if already exists
$check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND menu_item_id=?");
$check->bind_param("ii", $user_id, $menu_item_id);
$check->execute();

$result = $check->get_result();

if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    $qty = $row['quantity'] + 1;

    $update = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
    $update->bind_param("ii", $qty, $row['id']);
    $update->execute();

} else {

    $insert = $conn->prepare("INSERT INTO cart(user_id,menu_item_id) VALUES(?,?)");
    $insert->bind_param("ii", $user_id, $menu_item_id);
    $insert->execute();

}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;