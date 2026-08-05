<?php
require_once 'includes/session.php';
require_once 'includes/config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows==1){

        $user=$result->fetch_assoc();

        if(password_verify($password,$user['password'])){

            $_SESSION['user_id']=$user['id'];
            $_SESSION['name']=$user['full_name'];
            $_SESSION['role']=$user['role'];

            switch($user['role']){

                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;

                case 'restaurant':
                    header("Location: restaurant/dashboard.php");
                    break;

                case 'delivery':
                    header("Location: delivery/dashboard.php");
                    break;

                default:
                    header("Location: index.php");

            }

            exit();

        }else{
            $error="Invalid Password";
        }

    }else{

        $error="Account not found";

    }

}
?>

<!-- log in -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Humsafar</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="top-bar">
            <a href="index.php" class="logo">
                <i class="fas fa-utensils"></i>
                <h1>Humsafar</h1>
            </a>
            <div class="user-actions">
                <a href="register.php" class="sign-up">Don't have an account? Sign Up</a>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-form">
            <h2>Welcome Back</h2>
            <p>Sign in to your Humsafar account</p>
            
            <form id="login-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                <?php if($error!=""){ ?>
                <p style="color:red;text-align:center;">
                <?= $error ?>
                </p>
                <?php } ?>
                
                <button type="submit" class="btn-primary">Sign In</button>
                
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
                    <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                </div>
            </form>
        </div>
    </div>

     <!-- <script src="js/script.js"></script> --> 
</body>
</html>