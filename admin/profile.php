<?php
session_start();

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| ADMIN PROFILE PAGE
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: admin-login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$adminName = isset($_SESSION['admin_name'])
    ? $_SESSION['admin_name']
    : "Humsafar Administrator";

$adminEmail = isset($_SESSION['admin_email'])
    ? $_SESSION['admin_email']
    : "admin@humsafar.com";

$adminRole = "Administrator";
$adminStatus = "Active";

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| PROFILE UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"])
        ? $_POST["action"]
        : "";

    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    if ($action === "update_profile") {

        $name = isset($_POST["admin_name"])
            ? trim($_POST["admin_name"])
            : "";

        $email = isset($_POST["admin_email"])
            ? trim($_POST["admin_email"])
            : "";

        if ($name === "" || $email === "") {

            $error = "Please fill in all fields.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = "Please enter a valid email address.";

        } else {

            $_SESSION["admin_name"] = $name;
            $_SESSION["admin_email"] = $email;

            $adminName = $name;
            $adminEmail = $email;

            $success = "Profile updated successfully.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    if ($action === "change_password") {

        $currentPassword = isset($_POST["current_password"])
            ? $_POST["current_password"]
            : "";

        $newPassword = isset($_POST["new_password"])
            ? $_POST["new_password"]
            : "";

        $confirmPassword = isset($_POST["confirm_password"])
            ? $_POST["confirm_password"]
            : "";

        /*
        | Default password used by the current admin login.
        */
        $storedPassword = isset($_SESSION["admin_password"])
            ? $_SESSION["admin_password"]
            : "admin123";

        if (
            $currentPassword === "" ||
            $newPassword === "" ||
            $confirmPassword === ""
        ) {

            $error = "Please fill in all password fields.";

        } elseif ($currentPassword !== $storedPassword) {

            $error = "Current password is incorrect.";

        } elseif (strlen($newPassword) < 6) {

            $error = "New password must contain at least 6 characters.";

        } elseif ($newPassword !== $confirmPassword) {

            $error = "New password and confirm password do not match.";

        } else {

            $_SESSION["admin_password"] = $newPassword;

            $success = "Password changed successfully.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Profile | Humsafar</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {

            --primary: #d33d83;
            --primary-dark: #c12e73;
            --primary-light: #ef5a9d;

            --pink-bg: #fff0f7;
            --page-bg: #fff7fb;

            --border: #efd8e4;

            --text: #29232a;
            --muted: #806b76;

            --white: #ffffff;

        }

        body {

            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    #fff7fb 0%,
                    #ffeaf4 50%,
                    #fff8fb 100%
                );

            color: var(--text);

        }

        /* =========================================================
           TOP BAR
        ========================================================= */

        .topbar {

            height: 76px;

            background: #ffffff;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 35px;

            box-shadow:
                0 5px 20px
                rgba(190, 48, 119, 0.07);

        }

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

        }

        .brand-icon {

            width: 44px;

            height: 44px;

            border-radius: 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ffffff;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

            box-shadow:
                0 8px 20px
                rgba(207, 50, 125, 0.20);

        }

        .brand-title {

            font-size: 19px;

            font-weight: 800;

            color: #29232a;

        }

        .brand-subtitle {

            display: block;

            margin-top: 2px;

            font-size: 11px;

            color: #a0768d;

        }

        .admin-top {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 13px;

            font-weight: 700;

            color: #5e4b55;

        }

        .top-avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ffffff;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

        }

        /* =========================================================
           MAIN
        ========================================================= */

        .container {

            max-width: 1180px;

            margin: 0 auto;

            padding: 35px 25px 50px;

        }

        .heading {

            margin-bottom: 25px;

        }

        .heading-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 7px 13px;

            margin-bottom: 12px;

            border-radius: 30px;

            background: var(--pink-bg);

            border: 1px solid #f4d4e3;

            color: var(--primary);

            font-size: 11px;

            font-weight: 800;

        }

        .heading h1 {

            font-size: 30px;

            font-weight: 850;

            margin-bottom: 6px;

        }

        .heading p {

            color: var(--muted);

            font-size: 14px;

        }

        /* =========================================================
           ALERT
        ========================================================= */

        .alert {

            display: flex;

            align-items: center;

            gap: 9px;

            padding: 13px 15px;

            margin-bottom: 22px;

            border-radius: 11px;

            font-size: 12px;

        }

        .alert-success {

            color: #167b46;

            background: #ecfaf2;

            border: 1px solid #c8ecd7;

        }

        .alert-error {

            color: #c62828;

            background: #fff0f0;

            border: 1px solid #f1cccc;

        }

        /* =========================================================
           PROFILE HEADER
        ========================================================= */

        .profile-header {

            background: #ffffff;

            border: 1px solid var(--border);

            border-radius: 22px;

            padding: 28px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 24px;

            box-shadow:
                0 12px 35px
                rgba(190, 48, 119, 0.08);

        }

        .profile-user {

            display: flex;

            align-items: center;

            gap: 20px;

        }

        .profile-avatar {

            width: 88px;

            height: 88px;

            border-radius: 23px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ffffff;

            font-size: 34px;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

            box-shadow:
                0 10px 25px
                rgba(207, 50, 125, 0.22);

        }

        .profile-user h2 {

            font-size: 23px;

            margin-bottom: 5px;

        }

        .profile-user p {

            color: var(--muted);

            font-size: 13px;

            margin-bottom: 10px;

        }

        .active-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 11px;

            border-radius: 20px;

            background: #eafaf1;

            color: #16834b;

            font-size: 11px;

            font-weight: 800;

        }

        .active-dot {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #20a85a;

        }

        /* =========================================================
           GRID
        ========================================================= */

        .grid {

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 24px;

        }

        .card {

            background: #ffffff;

            border: 1px solid var(--border);

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 10px 30px
                rgba(190, 48, 119, 0.07);

        }

        .card-header {

            display: flex;

            align-items: center;

            gap: 12px;

            padding-bottom: 16px;

            margin-bottom: 22px;

            border-bottom: 1px solid #f2e5eb;

        }

        .card-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--pink-bg);

            color: var(--primary);

        }

        .card-header h3 {

            font-size: 17px;

            font-weight: 800;

        }

        .card-header p {

            margin-top: 3px;

            color: #9a8490;

            font-size: 11px;

        }

        /* =========================================================
           FORM
        ========================================================= */

        .form-group {

            margin-bottom: 17px;

        }

        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 12px;

            font-weight: 800;

            color: #493841;

        }

        .input-box {

            position: relative;

        }

        .input-icon {

            position: absolute;

            left: 14px;

            top: 50%;

            transform: translateY(-50%);

            color: #c55a8b;

            pointer-events: none;

            font-size: 14px;

        }

        .input {

            width: 100%;

            height: 47px;

            border: 1px solid #e7ccd9;

            border-radius: 10px;

            background: #ffffff;

            color: #40333b;

            outline: none;

            padding:
                0 42px 0 40px;

            font-size: 13px;

            transition: .2s ease;

        }

        .input:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(211, 61, 131, 0.10);

        }

        .password-toggle {

            position: absolute;

            right: 10px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #9e8792;

            cursor: pointer;

            padding: 6px;

        }

        .password-toggle:hover {

            color: var(--primary);

        }

        /* =========================================================
           BUTTON
        ========================================================= */

        .btn {

            width: 100%;

            height: 47px;

            border: none;

            border-radius: 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 800;

            color: #ffffff;

            background:
                linear-gradient(
                    135deg,
                    #ef5a9d,
                    #cf327d
                );

            box-shadow:
                0 8px 20px
                rgba(207, 50, 125, 0.17);

            transition: .2s ease;

        }

        .btn:hover {

            transform: translateY(-1px);

            box-shadow:
                0 11px 24px
                rgba(207, 50, 125, 0.24);

        }

        /* =========================================================
           ACCOUNT DETAILS
        ========================================================= */

        .details {

            display: flex;

            flex-direction: column;

            gap: 12px;

        }

        .detail {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 13px 14px;

            border-radius: 11px;

            background: #fff9fc;

            border: 1px solid #f3e5ec;

            gap: 15px;

        }

        .detail-left {

            display: flex;

            align-items: center;

            gap: 10px;

            min-width: 0;

        }

        .detail-left i {

            width: 18px;

            text-align: center;

            color: var(--primary);

        }

        .detail-label {

            color: #806b76;

            font-size: 11px;

        }

        .detail-value {

            color: #3d3038;

            font-size: 12px;

            font-weight: 800;

            text-align: right;

            word-break: break-word;

        }

        .green {

            color: #16834b;

        }

        /* =========================================================
           INFO BOX
        ========================================================= */

        .info-box {

            margin-top: 18px;

            padding: 14px;

            border-radius: 11px;

            background: #faf7f9;

            border: 1px solid #eee1e8;

            color: #86727c;

            font-size: 11px;

            line-height: 1.6;

        }

        .info-box i {

            color: var(--primary);

            margin-right: 5px;

        }

        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {

            margin-top: 30px;

            text-align: center;

            color: #a38d97;

            font-size: 11px;

        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 850px) {

            .grid {

                grid-template-columns: 1fr;

            }

        }

        @media (max-width: 600px) {

            .topbar {

                padding: 0 16px;

            }

            .admin-top span {

                display: none;

            }

            .container {

                padding:
                    25px 15px 40px;

            }

            .heading h1 {

                font-size: 25px;

            }

            .profile-header {

                padding: 21px;

            }

            .profile-user {

                align-items: flex-start;

            }

            .profile-avatar {

                width: 70px;

                height: 70px;

                font-size: 27px;

                border-radius: 18px;

            }

            .profile-user h2 {

                font-size: 19px;

            }

            .card {

                padding: 20px;

            }

            .detail {

                align-items: flex-start;

                flex-direction: column;

            }

            .detail-value {

                text-align: left;

            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     TOP BAR
========================================================= -->

<header class="topbar">

    <div class="brand">

        <div class="brand-icon">

            <i class="fa-solid fa-utensils"></i>

        </div>

        <div>

            <div class="brand-title">
                Humsafar
            </div>

            <span class="brand-subtitle">
                Food Delivery Admin
            </span>

        </div>

    </div>


    <div class="admin-top">

        <div class="top-avatar">

            <i class="fa-solid fa-user-shield"></i>

        </div>

        <span>
            <?php echo htmlspecialchars($adminName); ?>
        </span>

    </div>

</header>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="container">


    <div class="heading">

        <div class="heading-badge">

            <i class="fa-solid fa-user-shield"></i>

            ADMIN ACCOUNT

        </div>

        <h1>
            Admin Profile
        </h1>

        <p>
            Manage your Humsafar administrator profile and security.
        </p>

    </div>


    <!-- ALERTS -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            <?php echo htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         PROFILE HEADER
    ========================================================= -->

    <section class="profile-header">

        <div class="profile-user">

            <div class="profile-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>


            <div>

                <h2>
                    <?php echo htmlspecialchars($adminName); ?>
                </h2>

                <p>
                    <?php echo htmlspecialchars($adminEmail); ?>
                </p>

                <span class="active-badge">

                    <span class="active-dot"></span>

                    Active Account

                </span>

            </div>

        </div>

    </section>


    <!-- =========================================================
         GRID
    ========================================================= -->

    <div class="grid">


        <!-- =====================================================
             EDIT PROFILE
        ===================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <i class="fa-solid fa-user-pen"></i>

                </div>

                <div>

                    <h3>
                        Profile Information
                    </h3>

                    <p>
                        Update administrator information
                    </p>

                </div>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="update_profile"
                >


                <div class="form-group">

                    <label for="admin_name">
                        Admin Name
                    </label>

                    <div class="input-box">

                        <i class="fa-solid fa-user input-icon"></i>

                        <input
                            type="text"
                            id="admin_name"
                            name="admin_name"
                            class="input"
                            value="<?php echo htmlspecialchars($adminName); ?>"
                            placeholder="Enter admin name"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label for="admin_email">
                        Admin Email
                    </label>

                    <div class="input-box">

                        <i class="fa-solid fa-envelope input-icon"></i>

                        <input
                            type="email"
                            id="admin_email"
                            name="admin_email"
                            class="input"
                            value="<?php echo htmlspecialchars($adminEmail); ?>"
                            placeholder="Enter admin email"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    &nbsp;

                    Save Profile

                </button>

            </form>

        </section>


        <!-- =====================================================
             ACCOUNT DETAILS
        ===================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <i class="fa-solid fa-circle-info"></i>

                </div>

                <div>

                    <h3>
                        Account Details
                    </h3>

                    <p>
                        Administrator account information
                    </p>

                </div>

            </div>


            <div class="details">


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-user"></i>

                        <span class="detail-label">
                            Name
                        </span>

                    </div>

                    <span class="detail-value">

                        <?php echo htmlspecialchars($adminName); ?>

                    </span>

                </div>


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-envelope"></i>

                        <span class="detail-label">
                            Email
                        </span>

                    </div>

                    <span class="detail-value">

                        <?php echo htmlspecialchars($adminEmail); ?>

                    </span>

                </div>


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-user-shield"></i>

                        <span class="detail-label">
                            Role
                        </span>

                    </div>

                    <span class="detail-value">

                        <?php echo htmlspecialchars($adminRole); ?>

                    </span>

                </div>


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-circle-check"></i>

                        <span class="detail-label">
                            Status
                        </span>

                    </div>

                    <span class="detail-value green">

                        <?php echo htmlspecialchars($adminStatus); ?>

                    </span>

                </div>


            </div>


            <div class="info-box">

                <i class="fa-solid fa-shield-halved"></i>

                This account has administrator access to
                Humsafar restaurant management, rider management,
                payment verification and other admin features.

            </div>

        </section>


        <!-- =====================================================
             CHANGE PASSWORD
        ===================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <i class="fa-solid fa-key"></i>

                </div>

                <div>

                    <h3>
                        Change Password
                    </h3>

                    <p>
                        Update your administrator password
                    </p>

                </div>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >


                <!-- CURRENT PASSWORD -->

                <div class="form-group">

                    <label for="current_password">
                        Current Password
                    </label>

                    <div class="input-box">

                        <i class="fa-solid fa-lock input-icon"></i>

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="input"
                            placeholder="Enter current password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword(
                                'current_password',
                                'current_icon'
                            )"
                        >

                            <i
                                class="fa-solid fa-eye"
                                id="current_icon"
                            ></i>

                        </button>

                    </div>

                </div>


                <!-- NEW PASSWORD -->

                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <div class="input-box">

                        <i class="fa-solid fa-key input-icon"></i>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="input"
                            placeholder="Enter new password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword(
                                'new_password',
                                'new_icon'
                            )"
                        >

                            <i
                                class="fa-solid fa-eye"
                                id="new_icon"
                            ></i>

                        </button>

                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <div class="input-box">

                        <i class="fa-solid fa-lock input-icon"></i>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="input"
                            placeholder="Confirm new password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword(
                                'confirm_password',
                                'confirm_icon'
                            )"
                        >

                            <i
                                class="fa-solid fa-eye"
                                id="confirm_icon"
                            ></i>

                        </button>

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn"
                >

                    <i class="fa-solid fa-lock"></i>

                    &nbsp;

                    Change Password

                </button>

            </form>

        </section>


        <!-- =====================================================
             SECURITY
        ===================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-icon">

                    <i class="fa-solid fa-shield-halved"></i>

                </div>

                <div>

                    <h3>
                        Security
                    </h3>

                    <p>
                        Administrator security status
                    </p>

                </div>

            </div>


            <div class="details">


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-lock"></i>

                        <span class="detail-label">
                            Password Protection
                        </span>

                    </div>

                    <span class="detail-value green">
                        Enabled
                    </span>

                </div>


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-user-shield"></i>

                        <span class="detail-label">
                            Admin Access
                        </span>

                    </div>

                    <span class="detail-value green">
                        Active
                    </span>

                </div>


                <div class="detail">

                    <div class="detail-left">

                        <i class="fa-solid fa-database"></i>

                        <span class="detail-label">
                            Management Access
                        </span>

                    </div>

                    <span class="detail-value">
                        Full
                    </span>

                </div>


            </div>


            <div class="info-box">

                <i class="fa-solid fa-circle-info"></i>

                Use a strong password with letters,
                numbers and special characters to keep
                your administrator account secure.

            </div>

        </section>


    </div>


    <div class="footer">

        © <?php echo date("Y"); ?>
        Humsafar Food Delivery.
        All rights reserved.

    </div>


</main>


<script>

function togglePassword(inputId, iconId) {

    const input =
        document.getElementById(inputId);

    const icon =
        document.getElementById(iconId);

    if (!input || !icon) {
        return;
    }

    if (input.type === "password") {

        input.type = "text";

        icon.classList.remove("fa-eye");

        icon.classList.add("fa-eye-slash");

    } else {

        input.type = "password";

        icon.classList.remove("fa-eye-slash");

        icon.classList.add("fa-eye");

    }
}

</script>


</body>

</html>