<!--Restaurant-owner-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Owner - Humsafar</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="top-bar">
            <a href="index.html" class="logo">
                <i class="fas fa-utensils"></i>
                <h1>Humsafar for Restaurants</h1>
            </a>
            <div class="user-actions">
                <a href="index.html" class="sign-in">Back to Main Site</a>
            </div>
        </div>
    </header>

    <div class="owner-container">
        <div class="owner-hero">
            <div class="owner-hero-content">
                <h1>Grow Your Restaurant Business</h1>
                <p>Join Humsafar and reach thousands of hungry customers in your area</p>
                <button class="btn-primary btn-large" id="join-now-btn">Join Now</button>
            </div>
        </div>

        <div class="benefits-section">
            <h2>Why Partner with Humsafar?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-users"></i>
                    <h3>Reach More Customers</h3>
                    <p>Access thousands of potential customers in your delivery area</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Increase Revenue</h3>
                    <p>Boost your sales with additional delivery and takeaway orders</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-cog"></i>
                    <h3>Easy Management</h3>
                    <p>Manage your menu, orders, and pricing from one simple dashboard</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Payments</h3>
                    <p>Get paid securely and on time with our reliable payment system</p>
                </div>
            </div>
        </div>

        <div class="registration-form-section">
            <div class="form-container">
                <h2>Register Your Restaurant</h2>
                <form id="restaurant-registration-form">
                    <div class="form-group">
                        <label for="restaurant-name">Restaurant Name *</label>
                        <input type="text" id="restaurant-name" name="restaurant-name" required>
                    </div>

                    <div class="form-group">
                        <label for="cuisine-type">Cuisine Type *</label>
                        <select id="cuisine-type" name="cuisine-type" required>
                            <option value="">Select Cuisine</option>
                            <option value="italian">Italian</option>
                            <option value="asian">Asian</option>
                            <option value="mexican">Mexican</option>
                            <option value="indian">Indian</option>
                            <option value="american">American</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="address">Restaurant Address *</label>
                        <input type="text" id="address" name="address" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Restaurant Description</label>
                        <textarea id="description" name="description" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="terms" required>
                            <span>I agree to the <a href="#">Partner Terms</a> and <a href="#">Privacy Policy</a></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary btn-large">Submit Application</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>Humsafar for Restaurants</h3>
                <ul>
                    <li><a href="#">Partner Help Center</a></li>
                    <li><a href="#">Pricing</a></li>
                    <li><a href="#">Success Stories</a></li>
                    <li><a href="#">Resources</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <ul>
                    <li><a href="#">Partner Support</a></li>
                    <li><a href="#">Sales Inquiry</a></li>
                    <li><a href="#">Technical Help</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2023 Humsafar Food Delivery. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>