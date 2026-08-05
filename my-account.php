<!-- My account -->
<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Humsafar</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header Section -->
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
                <li><a href="deals.html">Deals</a></li>
                <li><a href="my-account.html" class="active">My Account</a></li>
                <li><a href="#">Help</a></li>
            </ul>
        </nav>
    </header>

    <!-- Account Dashboard -->
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
                    <h1>Account Dashboard</h1>
                    <p>Welcome back, John! Here's your account overview.</p>
                </div>

            <div class="account-content">
                <div class="content-header">
                    <h1>Profile</h1>
                    <p>Manage your personal information and account settings</p>
                </div>

                <!-- Profile Form -->
                <div class="profile-form-container">
                    <form id="profile-form" class="profile-form">
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first-name">First Name</label>
                                    <input type="text" id="first-name" name="first-name" value="John" required>
                                </div>
                                <div class="form-group">
                                    <label for="last-name">Last Name</label>
                                    <input type="text" id="last-name" name="last-name" value="Doe" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="johndoe@email.com" required>
                                <button type="button" class="btn-change" id="change-email">Change</button>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="+1 234 567 8900" required>
                                <button type="button" class="btn-change" id="change-phone">Change</button>
                            </div>

                            <div class="form-group">
                                <label for="birthdate">Date of Birth</label>
                                <input type="date" id="birthdate" name="birthdate" value="1990-01-15">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Password & Security</h3>
                            <div class="form-group">
                                <label for="current-password">Current Password</label>
                                <input type="password" id="current-password" name="current-password">
                            </div>
                            <div class="form-group">
                                <label for="new-password">New Password</label>
                                <input type="password" id="new-password" name="new-password">
                            </div>
                            <div class="form-group">
                                <label for="confirm-password">Confirm New Password</label>
                                <input type="password" id="confirm-password" name="confirm-password">
                            </div>
                            <button type="button" class="btn-secondary" id="update-password">Update Password</button>
                        </div>

                        <div class="form-section">
                            <h3>Notification Preferences</h3>
                            <div class="preference-item">
                                <label class="checkbox">
                                    <input type="checkbox" name="order-updates" checked>
                                    <span>Order status updates</span>
                                </label>
                            </div>
                            <div class="preference-item">
                                <label class="checkbox">
                                    <input type="checkbox" name="promotions" checked>
                                    <span>Promotions and offers</span>
                                </label>
                            </div>
                            <div class="preference-item">
                                <label class="checkbox">
                                    <input type="checkbox" name="newsletter">
                                    <span>Newsletter subscription</span>
                                </label>
                            </div>
                            <div class="preference-item">
                                <label class="checkbox">
                                    <input type="checkbox" name="sms-notifications" checked>
                                    <span>SMS notifications</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Save Changes</button>
                            <button type="button" class="btn-secondary" id="cancel-changes">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Email Modal -->
    <div id="email-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Email Address</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="email-form">
                <div class="form-group">
                    <label for="new-email">New Email Address</label>
                    <input type="email" id="new-email" name="new-email" required>
                </div>
                <div class="form-group">
                    <label for="email-password">Current Password</label>
                    <input type="password" id="email-password" name="email-password" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Email</button>
                    <button type="button" class="btn-secondary close-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Phone Modal -->
    <div id="phone-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Phone Number</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="phone-form">
                <div class="form-group">
                    <label for="new-phone">New Phone Number</label>
                    <input type="tel" id="new-phone" name="new-phone" required>
                </div>
                <div class="form-group">
                    <label for="phone-password">Current Password</label>
                    <input type="password" id="phone-password" name="phone-password" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Phone</button>
                    <button type="button" class="btn-secondary close-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
                <!-- Main Content -->
            <div class="account-content">
                <div class="content-header">
                    <h1>My Addresses</h1>
                    <p>Manage your delivery addresses</p>
                </div>

                <!-- Add Address Button -->
                <div class="addresses-header">
                    <button class="btn-primary" id="add-address-btn">
                        <i class="fas fa-plus"></i> Add New Address
                    </button>
                </div>

                <!-- Addresses List -->
                <div class="addresses-list">
                    <div class="address-card">
                        <div class="address-header">
                            <h3>Home</h3>
                            <span class="default-badge">Default</span>
                        </div>
                        <div class="address-details">
                            <p>123 Main Street, Apt 4B</p>
                            <p>New York, NY 10001</p>
                            <p class="address-phone">Phone: +1 234 567 8900</p>
                            <p class="address-instructions">Delivery instructions: Ring bell twice</p>
                        </div>
                        <div class="address-actions">
                            <button class="btn-edit" data-address="1">Edit</button>
                            <button class="btn-delete" data-address="1">Delete</button>
                        </div>
                    </div>

                    <div class="address-card">
                        <div class="address-header">
                            <h3>Work</h3>
                        </div>
                        <div class="address-details">
                            <p>456 Business Avenue, Floor 12</p>
                            <p>New York, NY 10002</p>
                            <p class="address-phone">Phone: +1 234 567 8901</p>
                            <p class="address-instructions">Delivery instructions: Leave at reception</p>
                        </div>
                        <div class="address-actions">
                            <button class="btn-set-default" data-address="2">Set as Default</button>
                            <button class="btn-edit" data-address="2">Edit</button>
                            <button class="btn-delete" data-address="2">Delete</button>
                        </div>
                    </div>

                    <div class="address-card">
                        <div class="address-header">
                            <h3>Parents' House</h3>
                        </div>
                        <div class="address-details">
                            <p>789 Family Road</p>
                            <p>Brooklyn, NY 11201</p>
                            <p class="address-phone">Phone: +1 234 567 8902</p>
                        </div>
                        <div class="address-actions">
                            <button class="btn-set-default" data-address="3">Set as Default</button>
                            <button class="btn-edit" data-address="3">Edit</button>
                            <button class="btn-delete" data-address="3">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Address Modal -->
    <div id="address-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add New Address</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="address-form">
                <input type="hidden" id="address-id" name="address-id">
                <div class="form-group">
                    <label for="address-label">Address Label</label>
                    <input type="text" id="address-label" name="address-label" placeholder="Home, Work, etc." required>
                </div>
                <div class="form-group">
                    <label for="street-address">Street Address</label>
                    <input type="text" id="street-address" name="street-address" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="zipcode">ZIP Code</label>
                        <input type="text" id="zipcode" name="zipcode" required>
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" value="United States" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="instructions">Delivery Instructions (Optional)</label>
                    <textarea id="instructions" name="instructions" rows="3" placeholder="Ring bell, leave at door, etc."></textarea>
                </div>
                <div class="form-group">
                    <label class="checkbox">
                        <input type="checkbox" id="set-default" name="set-default">
                        <span>Set as default delivery address</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="save-address">Save Address</button>
                    <button type="button" class="btn-secondary close-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>



                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-info">
                            <h3>12</h3>
                            <p>Total Orders</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-info">
                            <h3>8</h3>
                            <p>Reviews Given</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>3</h3>
                            <p>Saved Addresses</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>5</h3>
                            <p>Favorite Restaurants</p>
                        </div>
                    </div>
                </div>


    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>Humsafar</h3>
                <ul>
                    <li><a href="About.html">About Us</a></li>
                    <li><a href="Rider.html">Careers</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>For Restaurants</h3>
                <ul>
                    <li><a href="restaurant-owner.html">Add Restaurant</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <ul>
                    <li><a href="#">Help & Support</a></li>
                    <li><a href="restaurants.html">Partner with us</a></li>
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