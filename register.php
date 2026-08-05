<?php
require_once 'includes/session.php';
require_once 'includes/config.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = $first_name . " " . $last_name;

    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password != $confirm_password) {
        die("Passwords do not match.");
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email=? OR phone=?");
    $check->bind_param("ss", $email, $phone);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        die("Email or Phone already exists.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $role = "customer";

    $insert = $conn->prepare("INSERT INTO users(full_name,email,phone,password,role) VALUES(?,?,?,?,?)");
    $insert->bind_param("sssss",$full_name,$email,$phone,$hash,$role);

    if($insert->execute()){
        echo "<script>alert('Registration Successful');</script>";
    }else{
        echo "<script>alert('Registration Failed');</script>";
    }

}

?>

<!-- Sign-up -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Humsafar</title>
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
            <div class="user-actions">
                <a href="login.php" class="sign-in">Already have an account? Sign In</a>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-form">
            <h2>Join Humsafar</h2>
            <p>Create your account to start ordering</p>
            
            <form id="signup-form" method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form_options">
                    <label class="checkbox">
                        <input type="checkbox" name="terms" required>
                        <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="newsletter">
                        <span>Send me special offers and updates</span>
                    </label>
                </div>
                
                <button type="submit" class="btn-primary">Create Account</button>
                
                <div class="auth-divider">
                    <span>or continue with</span>
                </div>
                
                <div class="social-auth">
                    <button type="button" class="btn-social btn-google">
                        <i class="fab fa-google"></i>
                        Google
                    </button>
                    <button type="button" class="btn-social btn-facebook">
                        <i class="fab fa-facebook"></i>
                        Facebook
                    </button>
                </div>
                
                <div class="auth-switch">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>
            </form>
        </div>
    </div>

   <!-- <script src="js/script.js"></script> -->
</body>
</html>