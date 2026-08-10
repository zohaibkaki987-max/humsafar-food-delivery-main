<?php
session_start();

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
| Humsafar Food Delivery
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| If already logged in, go directly to admin panel
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin-panel.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN CREDENTIALS
|--------------------------------------------------------------------------
| Change these credentials if required.
|--------------------------------------------------------------------------
*/
$admin_email = "admin@humsafar.com";
$admin_password = "admin123";

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = isset($_POST['email']) ? trim($_POST['email']) : "";
    $password = isset($_POST['password']) ? $_POST['password'] : "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($email === $admin_email && $password === $admin_password) {

        /*
        |--------------------------------------------------------------
        | Successful Login
        |--------------------------------------------------------------
        */
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $admin_email;
        $_SESSION['admin_name'] = "Humsafar Administrator";

        /*
        |--------------------------------------------------------------
        | Regenerate session ID for security
        |--------------------------------------------------------------
        */
        session_regenerate_id(true);

        header("Location: admin-panel.php");
        exit;

    } else {

        $error = "Invalid admin email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Humsafar</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #fff7fb 0%,
                    #ffeaf4 50%,
                    #fff8fb 100%
                );

            color: #333;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: #ffffff;

            border: 1px solid #f1dce7;

            border-radius: 24px;

            padding: 38px 35px;

            box-shadow:
                0 18px 50px
                rgba(190, 48, 119, 0.12);
        }

        .admin-icon {
            width: 72px;
            height: 72px;

            margin: 0 auto 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 20px;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

            color: #ffffff;

            font-size: 30px;

            box-shadow:
                0 10px 25px
                rgba(207, 50, 125, 0.25);
        }

        .login-header {
            text-align: center;

            margin-bottom: 28px;
        }

        .login-header h1 {
            margin: 0 0 8px;

            color: #29232a;

            font-size: 28px;

            font-weight: 800;
        }

        .login-header p {
            margin: 0;

            color: #777;

            font-size: 14px;

            line-height: 1.6;
        }

        .admin-badge {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            margin-bottom: 12px;

            padding: 7px 13px;

            border-radius: 30px;

            background: #fff0f7;

            color: #d33d83;

            font-size: 12px;

            font-weight: 700;
        }

        .alert {
            padding: 12px 14px;

            margin-bottom: 20px;

            border-radius: 10px;

            font-size: 13px;

            line-height: 1.5;
        }

        .alert-error {
            background: #fff0f0;

            border: 1px solid #f3caca;

            color: #c62828;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            color: #333;

            font-size: 13px;

            font-weight: 700;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;

            left: 14px;

            top: 50%;

            transform: translateY(-50%);

            color: #c55a8b;

            font-size: 15px;

            pointer-events: none;
        }

        .form-control {
            width: 100%;

            height: 48px;

            padding:
                0 14px 0 42px;

            border:
                1px solid #e7ccd9;

            border-radius: 10px;

            outline: none;

            background: #fff;

            color: #333;

            font-size: 14px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }

        .form-control:focus {
            border-color: #d33d83;

            box-shadow:
                0 0 0 3px
                rgba(211, 61, 131, 0.10);
        }

        .password-toggle {
            position: absolute;

            right: 13px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #999;

            cursor: pointer;

            font-size: 14px;

            padding: 5px;
        }

        .password-toggle:hover {
            color: #d33d83;
        }

        .login-button {
            width: 100%;

            height: 48px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

            color: #fff;

            font-size: 14px;

            font-weight: 800;

            cursor: pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .login-button:hover {
            transform: translateY(-1px);

            box-shadow:
                0 8px 20px
                rgba(207, 50, 125, 0.22);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .security-note {
            margin-top: 20px;

            padding: 12px 14px;

            border-radius: 10px;

            background: #faf7f9;

            border: 1px solid #eee1e8;

            color: #777;

            text-align: center;

            font-size: 11px;

            line-height: 1.5;
        }

        .back-link {
            display: block;

            margin-top: 20px;

            text-align: center;

            color: #d33d83;

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;
        }

        .back-link:hover {
            color: #b82b6b;

            text-decoration: underline;
        }

        .footer-text {
            margin-top: 18px;

            text-align: center;

            color: #999;

            font-size: 11px;
        }

        @media (max-width: 500px) {

            body {
                padding: 15px;
            }

            .login-card {
                padding: 30px 22px;

                border-radius: 20px;
            }

            .admin-icon {
                width: 64px;
                height: 64px;

                font-size: 26px;
            }

            .login-header h1 {
                font-size: 24px;
            }

        }

    </style>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="admin-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="login-header">

            <div class="admin-badge">
                <i class="fa-solid fa-lock"></i>
                Administrator Access
            </div>

            <h1>Admin Login</h1>

            <p>
                Login to manage Humsafar restaurants,
                payments and owner accounts.
            </p>

        </div>


        <?php if ($error !== ""): ?>

            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <div class="form-group">

                <label for="email">
                    Admin Email
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-envelope"></i>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter admin email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-lock"></i>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Enter admin password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Show password"
                    >
                        <i
                            class="fa-solid fa-eye"
                            id="passwordIcon"
                        ></i>
                    </button>

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >

                <i class="fa-solid fa-right-to-bracket"></i>

                &nbsp;

                Login to Admin Panel

            </button>

        </form>


        <div class="security-note">

            <i class="fa-solid fa-shield-halved"></i>

            This area is restricted to authorized
            Humsafar administrators only.

        </div>


        <a
            href="index.php"
            class="back-link"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Back to Humsafar
        </a>

    </div>


    <div class="footer-text">

        © <?php echo date("Y"); ?> Humsafar Food Delivery.
        All rights reserved.

    </div>

</div>


<script>

function togglePassword() {

    const passwordInput =
        document.getElementById("password");

    const passwordIcon =
        document.getElementById("passwordIcon");

    if (passwordInput.type === "password") {

        passwordInput.type = "text";

        passwordIcon.classList.remove("fa-eye");

        passwordIcon.classList.add("fa-eye-slash");

    } else {

        passwordInput.type = "password";

        passwordIcon.classList.remove("fa-eye-slash");

        passwordIcon.classList.add("fa-eye");

    }
}

</script>

</body>
</html>