<!-- Rider -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Rider - Humsafar</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="top-bar">
            <a href="index.html" class="logo">
                <i class="fas fa-utensils"></i>
                <h1>Humsafar for Riders</h1>
            </a>
            <div class="user-actions">
                <a href="index.html" class="sign-in">Back to Main Site</a>
            </div>
        </div>
    </header>

    <div class="rider-container">
        <div class="rider-hero">
            <div class="rider-hero-content">
                <h1>Earn on Your Schedule</h1>
                <p>Join Humsafar as a delivery rider and earn money with flexibility</p>
                <button class="btn-primary btn-large" id="apply-now-btn">Apply Now</button>
            </div>
        </div>

        <div class="features-section">
            <h2>Why Ride with Humsafar?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-clock"></i>
                    <h3>Flexible Hours</h3>
                    <p>Work when you want, as much or as little as you like</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Good Earnings</h3>
                    <p>Competitive pay with tips and bonuses</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Your Area</h3>
                    <p>Deliver in neighborhoods you know best</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Insurance</h3>
                    <p>Accident and liability insurance coverage</p>
                </div>
            </div>
        </div>

        <div class="requirements-section">
            <h2>Requirements</h2>
            <div class="requirements-list">
                <div class="requirement-item">
                    <i class="fas fa-check-circle"></i>
                    <span>18 years or older</span>
                </div>
                <div class="requirement-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Valid driver's license</span>
                </div>
                <div class="requirement-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Smartphone with internet</span>
                </div>
                <div class="requirement-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Vehicle (bike, scooter, or car)</span>
                </div>
                <div class="requirement-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Right to work in your country</span>
                </div>
            </div>
        </div>

        <div class="application-form-section">
            <div class="form-container">
                <h2>Rider Application</h2>
                <form id="rider-application-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rider-first-name">First Name *</label>
                            <input type="text" id="rider-first-name" name="first-name" required>
                        </div>
                        <div class="form-group">
                            <label for="rider-last-name">Last Name *</label>
                            <input type="text" id="rider-last-name" name="last-name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rider-email">Email Address *</label>
                        <input type="email" id="rider-email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="rider-phone">Phone Number *</label>
                        <input type="tel" id="rider-phone" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label for="rider-city">City *</label>
                        <input type="text" id="rider-city" name="city" required>
                    </div>

                    <div class="form-group">
                        <label for="vehicle-type">Vehicle Type *</label>
                        <select id="vehicle-type" name="vehicle-type" required>
                            <option value="">Select Vehicle</option>
                            <option value="bicycle">Bicycle</option>
                            <option value="scooter">Scooter</option>
                            <option value="motorcycle">Motorcycle</option>
                            <option value="car">Car</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="terms" required>
                            <span>I agree to the <a href="#">Rider Agreement</a> and <a href="#">Privacy Policy</a></span>
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
                <h3>Humsafar for Riders</h3>
                <ul>
                    <li><a href="#">Rider Help Center</a></li>
                    <li><a href="#">Earnings Calculator</a></li>
                    <li><a href="#">Safety Guidelines</a></li>
                    <li><a href="#">Rider Stories</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <ul>
                    <li><a href="#">Rider Support</a></li>
                    <li><a href="#">Application Status</a></li>
                    <li><a href="#">Emergency Contact</a></li>
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