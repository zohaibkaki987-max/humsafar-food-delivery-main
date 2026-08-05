<!-- Cart -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Humsafar</title>
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
                <a href="my-cart.html" id="cart-btn" class="active"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-count">2</span></a>
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

    <div class="cart-container">
        <div class="cart-content">
            <!-- Cart Items -->
            <div class="cart-items">
                <div class="cart-header">
                    <h1>Your Cart</h1>
                    <p>2 items from Pizza Palace</p>
                </div>

                <div class="restaurant-info">
                    <div class="restaurant-avatar">
                        <i class="fas fa-pizza-slice"></i>
                    </div>
                    <div class="restaurant-details">
                        <h3>Pizza Palace</h3>
                        <p>Italian • Pizza • Pasta</p>
                        <span class="delivery-time">25-35 min • Free delivery</span>
                    </div>
                </div>

                <div class="cart-items-list">
                    <div class="cart-item">
                        <div class="item-image">
                            <i class="fas fa-pizza-slice"></i>
                        </div>
                        <div class="item-details">
                            <h4>Margherita Pizza</h4>
                            <p>Classic pizza with tomato sauce and mozzarella</p>
                            <span class="item-price">$14.99</span>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn minus">-</button>
                            <span class="quantity">1</span>
                            <button class="quantity-btn plus">+</button>
                        </div>
                        <div class="item-total">$14.99</div>
                        <button class="item-remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    <div class="cart-item">
                        <div class="item-image">
                            <i class="fas fa-bread-slice"></i>
                        </div>
                        <div class="item-details">
                            <h4>Garlic Bread</h4>
                            <p>Freshly baked bread with garlic butter</p>
                            <span class="item-price">$5.99</span>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn minus">-</button>
                            <span class="quantity">1</span>
                            <button class="quantity-btn plus">+</button>
                        </div>
                        <div class="item-total">$5.99</div>
                        <button class="item-remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Add More Items -->
                <div class="add-more-section">
                    <button class="btn-secondary">
                        <i class="fas fa-plus"></i> Add More Items
                    </button>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-card">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal (2 items)</span>
                        <span>$20.98</span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span class="free">FREE</span>
                    </div>
                    <div class="summary-row">
                        <span>Service Fee</span>
                        <span>$1.50</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax</span>
                        <span>$2.10</span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>$24.58</span>
                    </div>

                    <!-- Delivery Address -->
                    <div class="delivery-address">
                        <h4>Delivery Address</h4>
                        <div class="address-card small">
                            <div class="address-header">
                                <span class="address-label">Home</span>
                                <span class="default-badge">Default</span>
                            </div>
                            <p>123 Main Street, Apt 4B, New York, NY 10001</p>
                            <button class="btn-change-address">Change</button>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="payment-method">
                        <h4>Payment Method</h4>
                        <div class="payment-option">
                            <input type="radio" id="credit-card" name="payment" checked>
                            <label for="credit-card">
                                <i class="fas fa-credit-card"></i>
                                Credit/Debit Card
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" id="paypal" name="payment">
                            <label for="paypal">
                                <i class="fab fa-paypal"></i>
                                PayPal
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" id="cash" name="payment">
                            <label for="cash">
                                <i class="fas fa-money-bill"></i>
                                Cash on Delivery
                            </label>
                        </div>
                    </div>

                    <!-- Apply Voucher -->
                    <div class="voucher-section">
                        <div class="voucher-input">
                            <input type="text" placeholder="Enter voucher code">
                            <button class="btn-apply">Apply</button>
                        </div>
                    </div>

                    <!-- Checkout Button -->
                    <button class="btn-checkout">
                        <i class="fas fa-lock"></i>
                        Proceed to Checkout - $24.58
                    </button>

                    <p class="security-note">
                        <i class="fas fa-shield-alt"></i>
                        Your payment information is secure and encrypted
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Address Modal -->
    <div id="change-address-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Select Delivery Address</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="addresses-list-modal">
                <div class="address-option selected">
                    <div class="address-radio">
                        <input type="radio" name="delivery-address" checked>
                    </div>
                    <div class="address-details">
                        <h4>Home</h4>
                        <p>123 Main Street, Apt 4B</p>
                        <p>New York, NY 10001</p>
                        <span class="default-badge">Default</span>
                    </div>
                </div>
                <div class="address-option">
                    <div class="address-radio">
                        <input type="radio" name="delivery-address">
                    </div>
                    <div class="address-details">
                        <h4>Work</h4>
                        <p>456 Business Avenue, Floor 12</p>
                        <p>New York, NY 10002</p>
                    </div>
                </div>
                <div class="address-option">
                    <div class="address-radio">
                        <input type="radio" name="delivery-address">
                    </div>
                    <div class="address-details">
                        <h4>Parents' House</h4>
                        <p>789 Family Road</p>
                        <p>Brooklyn, NY 11201</p>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" id="confirm-address">Confirm Address</button>
                <button class="btn-secondary close-modal">Cancel</button>
                <a href="my-addresses.html" class="btn-link">Manage Addresses</a>
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