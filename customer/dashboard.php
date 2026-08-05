<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Humsafar</title>

    <style>
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#f5f5f5;
        }

        .navbar{
            background:#ff5722;
            color:#fff;
            padding:15px 30px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .navbar a{
            color:#fff;
            text-decoration:none;
            font-weight:bold;
        }

        .container{
            width:90%;
            margin:30px auto;
        }

        .card{
            background:#fff;
            padding:25px;
            border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.1);
        }

        h2{
            margin-top:0;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Humsafar Customer</h2>

    <div>
        Welcome,
        <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>

        |
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="card">
        <h2>Customer Dashboard</h2>

        <p>You are successfully logged in.</p>

        <p>This dashboard will later include:</p>

        <ul>
            <li>🍔 Browse Restaurants</li>
            <li>🛒 Cart</li>
            <li>📦 My Orders</li>
            <li>❤️ Favourite Restaurants</li>
            <li>📍 Delivery Tracking</li>
            <li>👤 My Profile</li>
        </ul>

    </div>

</div>

</body>
</html>