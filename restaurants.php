<!-- Restaurant -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurants - Humsafar</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="top-bar">
            <a href="index.html" class="logo">
                <i class="fas fa-utensils"></i>
                <h1>Humsafar</h1>
            </a>
            <div class="search-bar">
                <input type="text" placeholder="Search for restaurants or food...">
                <i class="fas fa-search"></i>
            </div>
            <div class="user-actions">
                <a href="#" id="cart-btn"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="login.html" class="sign-in">Sign In</a>
            </div>
        </div>
        <nav>
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="restaurants.html" class="active">Restaurants</a></li>
                <li><a href="deals.html">Deals</a></li>
                <li><a href="my-account.html">My Account</a></li>
                <li><a href="#">Help</a></li>
            </ul>
        </nav>
    </header>

    <div class="page-header">
        <h1>All Restaurants</h1>
        <p>Discover the best restaurants in your area</p>
    </div>

    <div class="filters-container">
        <div class="filters">
            <div class="filter-group">
                <label>Sort by:</label>
                <select id="sort-by">
                    <option value="rating">Rating</option>
                    <option value="delivery-time">Delivery Time</option>
                    <option value="name">Name</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Price Range:</label>
                <select id="price-range">
                    <option value="all">All</option>
                    <option value="$">$</option>
                    <option value="$$">$$</option>
                    <option value="$$$">$$$</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Cuisine:</label>
                <select id="cuisine">
                    <option value="all">All Cuisines</option>
                    <option value="italian">Italian</option>
                    <option value="asian">Asian</option>
                    <option value="mexican">Mexican</option>
                    <option value="indian">Indian</option>
                </select>
            </div>
        </div>
    </div>

    <div class="restaurants-grid" id="restaurants-grid">
        <!-- Restaurants will be loaded by JavaScript -->
    </div>

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
                    <li><a href="restaurant-owner.html">Add Restaurant</a></li>
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
                    <li><a href="rider.html">Ride with us</a></li>
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