<?php
require_once 'includes/session.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : "";
?>

<!-- Home -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Humsafar - Food Delivery Service</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="top-bar">
            <a href="index.php" class="logo">
                <i class="fas fa-utensils"></i>
                <h1>Humsafar</h1>
            </a>
            <div class="search-bar">
                <input type="text" placeholder="Search for restaurants or food...">
                <i class="fas fa-search"></i>
            </div>
            <div class="user-actions">

<?php if($isLoggedIn){ ?>

    <a href="cart.php" id="cart-btn">
        <i class="fas fa-shopping-cart"></i> Cart
    </a>

    <span style="margin:0 15px;font-weight:bold;">
        👋 <?= htmlspecialchars($userName) ?>
    </span>

    <a href="logout.php" class="sign-up">
        Logout
    </a>

<?php } else { ?>

    <a href="login.php" class="sign-in">
        Sign In
    </a>

    <a href="register.php" class="sign-up">
        Sign Up
    </a>

<?php } ?>

</div>        </div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="restaurants.php">Restaurants</a></li>
                <li><a href="deals.php">Deals</a></li>
                <li><a href="my-account.php">My Account</a></li>
                <li><a href="#">Help</a></li>
                <li><a href="restaurant-owner.php">For Restaurants</a></li>
                <li><a href="rider.php">Become a Rider</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <h2>Humsafar Food delivery </h2>
        <p>Order your favorite food from local restaurants and have it delivered to your doorstep</p>
        <div class="location-input">
            <input type="text" placeholder="Enter your delivery address" id="delivery-address">
            <button id="find-food">Find Food</button>
        </div>
    </section>
    
    <?php include 'includes/categories.php'; ?>

<?php include 'includes/restaurants.php'; ?>

    <!-- App Download Section -->
    <section class="app-download">
        <div class="app-info">
            <h2>Get the Humsafar App</h2>
            <p>Download our app for faster ordering, personalized recommendations, and exclusive deals</p>
            <div class="app-buttons">
                <a href="#" class="app-btn">
                    <i class="fab fa-apple"></i>
                    <div>
                        <small>Download on the</small>
                        <div>App Store</div>
                    </div>
                </a>
                <a href="#" class="app-btn">
                    <i class="fab fa-google-play"></i>
                    <div>
                        <small>Get it on</small>
                        <div>Google Play</div>
                    </div>
                </a>
            </div>
        </div>
        <div class="app-image">
            <img src="https://cdn.pixabay.com/photo/2021/01/18/12/49/mobile-phone-5928029_1280.png" alt="Humsafar App">
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>Humsafar</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>For Foodies</h3>
                <ul>
                    <li><a href="#">Code of Conduct</a></li>
                    <li><a href="#">Community</a></li>
                    <li><a href="#">Blogger Help</a></li>
                    <li><a href="#">Mobile Apps</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>For Restaurants</h3>
                <ul>
                    <li><a href="restaurant-owner.php">Add Restaurant</a></li>
                    <li><a href="#">Business App</a></li>
                    <li><a href="#">Restaurant Widgets</a></li>
                    <li><a href="#">Products for Businesses</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <ul>
                    <li><a href="#">Help & Support</a></li>
                    <li><a href="#">Partner with us</a></li>
                    <li><a href="rider.php  ">Ride with us</a></li>
                </ul>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2023 Humsafar Food Delivery. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html> 