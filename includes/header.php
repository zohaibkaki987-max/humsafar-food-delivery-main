<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : "";

$cartCount = 0;

if ($isLoggedIn) {

    $user_id = $_SESSION['user_id'];

    $cart = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id=$user_id");

    if($cart && $row = $cart->fetch_assoc()){
        $cartCount = $row['total'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Humsafar Food Delivery</title>

<link rel="stylesheet" href="css/header.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

<header class="main-header">

    <div class="container">

        <div class="header-top">

            <a href="index.php" class="logo">

                <i class="fas fa-utensils"></i>

                <span>Humsafar</span>

            </a>

            <div class="search-box">

                <input type="text"
                placeholder="Search restaurants or food...">

                <button>

                    <i class="fas fa-search"></i>

                </button>

            </div>

            <div class="header-right">

<?php if($isLoggedIn){ ?>

<a href="cart.php" class="cart-btn">

<i class="fas fa-shopping-cart"></i>

Cart

<?php if($cartCount>0){ ?>

<span class="cart-badge">

<?php echo $cartCount; ?>

</span>

<?php } ?>

</a>

<span class="username">

👋 <?php echo htmlspecialchars($userName); ?>

</span>

<a href="logout.php" class="logout-btn">

Logout

</a>

<?php }else{ ?>

<a href="login.php" class="login-btn">

Login

</a>

<a href="register.php" class="register-btn">

Register

</a>

<?php } ?>

            </div>

        </div>

        <nav>
    <ul>

        <li><a href="index.php">Home</a></li>

        <li><a href="restaurants.php">Restaurants</a></li>

        <li><a href="#">Deals</a></li>

        <li><a href="cart.php">Cart</a></li>

        <li><a href="my-account.php">My Account</a></li>

    </ul>
</nav>
    </div>

</header>