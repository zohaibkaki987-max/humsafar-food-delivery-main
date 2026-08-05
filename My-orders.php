<!-- My Order -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Humsafar</title>
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
                <a href="my-cart.html" id="cart-btn"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-count">2</span></a>
                <a href="login.html" class="sign-in">Logout</a>
            </div>
        </div>
        <nav>
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="restaurants.html">Restaurants</a></li>
                <li><a href="#">Deals</a></li>
                <li><a href="my-account.html">My Account</a></li>
                <li><a href="#">Help</a></li>
            </ul>
        </nav>
    </header>

    <div class="account-dashboard">
        <div class="dashboard-container">
            <!-- Sidebar -->
            <div class="account-sidebar">
                <div class="user-profile-card">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-info">
                        <h3>John Doe</h3>
                        <p>johndoe@email.com</p>
                        <span class="user-since">Member since 2023</span>
                    </div>
                </div>
                
                <nav class="account-nav">
                    <ul>
                        <li><a href="my-account.html" class="nav-item active">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a></li>
                        <li><a href="my-orders.html" class="nav-item">
                            <i class="fas fa-shopping-bag"></i>
                            My Orders
                        <li><a href="#" class="nav-item">
                            <i class="fas fa-tag"></i>
                            Vouchers
                        </a></li>
                    </ul>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="account-content">
                <div class="content-header">
                    <h1>My Orders</h1>
                    <p>Your order history and tracking</p>
                </div>

                <!-- Order Filters -->
                <div class="order-filters">
                    <button class="filter-btn active">All Orders</button>
                    <button class="filter-btn">Pending</button>
                    <button class="filter-btn">Preparing</button>
                    <button class="filter-btn">Delivered</button>
                    <button class="filter-btn">Cancelled</button>
                </div>

                <!-- Orders List -->
                <div class="orders-list">
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Pizza Palace</h3>
                                <p>Order #ORD001 • Dec 15, 2023 • 6:30 PM</p>
                            </div>
                            <div class="order-status delivered">Delivered</div>
                        </div>
                        <div class="order-items">
                            <div class="item-list">
                                <span>1x Margherita Pizza</span>
                                <span>1x Garlic Bread</span>
                                <span>2x Coke</span>
                            </div>
                            <div class="order-total">$24.99</div>
                        </div>
                        <div class="order-actions">
                            <button class="btn-primary">Reorder</button>
                            <button class="btn-secondary">View Details</button>
                            <button class="btn-secondary">Rate Order</button>
                        </div>
                    </div>

                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Burger Hub</h3>
                                <p>Order #ORD002 • Dec 15, 2023 • 6:15 PM</p>
                            </div>
                            <div class="order-status preparing">Preparing</div>
                        </div>
                        <div class="order-items">
                            <div class="item-list">
                                <span>1x Classic Burger</span>
                                <span>1x French Fries</span>
                                <span>1x Chocolate Milkshake</span>
                            </div>
                            <div class="order-total">$18.50</div>
                        </div>
                        <div class="order-actions">
                            <button class="btn-primary">Track Order</button>
                            <button class="btn-secondary">View Details</button>
                            <button class="btn-secondary">Contact Support</button>
                        </div>
                    </div>

                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Sushi Express</h3>
                                <p>Order #ORD003 • Dec 12, 2023 • 7:45 PM</p>
                            </div>
                            <div class="order-status delivered">Delivered</div>
                        </div>
                        <div class="order-items">
                            <div class="item-list">
                                <span>1x California Roll</span>
                                <span>1x Spicy Tuna Roll</span>
                                <span>2x Miso Soup</span>
                            </div>
                            <div class="order-total">$32.75</div>
                        </div>
                        <div class="order-actions">
                            <button class="btn-primary">Reorder</button>
                            <button class="btn-secondary">View Details</button>
                            <button class="btn-secondary">Rate Order</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <!-- Same footer as my-account.html -->
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>