<?php

session_start();

/*
|--------------------------------------------------------------------------
| HUMSAFAR RIDER REGISTRATION
|--------------------------------------------------------------------------
| Existing riders table is used.
|
| riders columns:
| id
| full_name
| email
| phone
| cnic
| password
| vehicle_type
| bike_number
| address
| status
| created_at
| updated_at
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';


/*
|--------------------------------------------------------------------------
| CHECK DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['rider_logged_in']) &&
    $_SESSION['rider_logged_in'] === true
) {
    header("Location: rider-dashboard.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = "";

$full_name = "";
$email = "";
$phone = "";
$cnic = "";
$address = "";
$bike_number = "";


/*
|--------------------------------------------------------------------------
| REGISTRATION PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $cnic = trim($_POST["cnic"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $bike_number = trim($_POST["bike_number"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $terms = isset($_POST["terms"]);


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $full_name === "" ||
        $phone === "" ||
        $email === "" ||
        $cnic === "" ||
        $address === "" ||
        $bike_number === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!$terms) {

        $error = "Please agree to the Rider Terms & Conditions.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Password and confirm password do not match.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL / PHONE / CNIC
        |--------------------------------------------------------------------------
        */

        $check_sql = "
            SELECT
                id,
                email,
                phone,
                cnic
            FROM riders
            WHERE email = ?
               OR phone = ?
               OR cnic = ?
            LIMIT 1
        ";


        $check_stmt = $conn->prepare($check_sql);


        if (!$check_stmt) {

            $error = "Unable to process registration. Please try again.";

        } else {

            $check_stmt->bind_param(
                "sss",
                $email,
                $phone,
                $cnic
            );


            if (!$check_stmt->execute()) {

                $error = "Unable to check rider information.";

            } else {

                $check_result = $check_stmt->get_result();

                $existing_rider = $check_result->fetch_assoc();


                if ($existing_rider) {

                    if ($existing_rider["email"] === $email) {

                        $error =
                            "This email address is already registered.";

                    } elseif ($existing_rider["phone"] === $phone) {

                        $error =
                            "This phone number is already registered.";

                    } elseif ($existing_rider["cnic"] === $cnic) {

                        $error =
                            "This CNIC is already registered.";

                    } else {

                        $error =
                            "A rider account with these details already exists.";
                    }

                }

            }


            $check_stmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE ACCOUNT
        |--------------------------------------------------------------------------
        */

        if ($error === "") {

            /*
            |--------------------------------------------------------------------------
            | PASSWORD HASH
            |--------------------------------------------------------------------------
            */

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /*
            |--------------------------------------------------------------------------
            | FIXED VEHICLE TYPE
            |--------------------------------------------------------------------------
            */

            $vehicle_type = "bike";


            /*
            |--------------------------------------------------------------------------
            | DEFAULT RIDER STATUS
            |--------------------------------------------------------------------------
            */

            $status = "pending";


            /*
            |--------------------------------------------------------------------------
            | INSERT INTO EXISTING RIDERS TABLE
            |--------------------------------------------------------------------------
            */

            $insert_sql = "
                INSERT INTO riders
                (
                    full_name,
                    email,
                    phone,
                    cnic,
                    password,
                    vehicle_type,
                    bike_number,
                    address,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";


            $insert_stmt = $conn->prepare($insert_sql);


            if (!$insert_stmt) {

                $error =
                    "Unable to create rider account. Please try again.";

            } else {

                $insert_stmt->bind_param(
                    "sssssssss",
                    $full_name,
                    $email,
                    $phone,
                    $cnic,
                    $hashed_password,
                    $vehicle_type,
                    $bike_number,
                    $address,
                    $status
                );


                if ($insert_stmt->execute()) {

                    /*
                    |--------------------------------------------------------------------------
                    | NEW RIDER ID
                    |--------------------------------------------------------------------------
                    */

                    $rider_id = (int)$conn->insert_id;


                    /*
                    |--------------------------------------------------------------------------
                    | AUTOMATIC LOGIN
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    $_SESSION["rider_logged_in"] = true;

                    $_SESSION["rider_id"] = $rider_id;

                    $_SESSION["rider_name"] = $full_name;

                    $_SESSION["rider_email"] = $email;

                    $_SESSION["rider_phone"] = $phone;

                    $_SESSION["rider_cnic"] = $cnic;

                    $_SESSION["rider_vehicle"] = $vehicle_type;

                    $_SESSION["rider_bike_number"] = $bike_number;

                    $_SESSION["rider_status"] = $status;


                    $insert_stmt->close();


                    /*
                    |--------------------------------------------------------------------------
                    | GO TO RIDER DASHBOARD
                    |--------------------------------------------------------------------------
                    */

                    header("Location: rider-dashboard.php");
                    exit;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | DATABASE ERROR
                    |--------------------------------------------------------------------------
                    */

                    $error =
                        "Unable to create rider account. Please try again.";

                    $insert_stmt->close();
                }
            }
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

    <title>Become a Rider | Humsafar</title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


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
                #fff8fb;

            color: #292929;

            min-height: 100vh;
        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .top-header {

            height: 59px;

            background: #ffffff;

            border-bottom:
                1px solid #eeeeee;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 25px;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 8px;

            color: #ed0038;

            font-size: 23px;

            font-weight: 800;

            text-decoration: none;
        }


        .brand i {

            font-size: 20px;
        }


        .back-button {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            height: 37px;

            padding:
                0 15px;

            border:
                1px solid #f1c3d2;

            border-radius: 9px;

            color: #df0038;

            background: #ffffff;

            text-decoration: none;

            font-size: 12px;

            font-weight: 700;

            transition: .2s ease;
        }


        .back-button:hover {

            background: #fff2f6;

            border-color: #ed0038;
        }


        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .page {

            min-height:
                calc(100vh - 59px);

            display: flex;

            justify-content: center;

            align-items: flex-start;

            padding:
                44px 20px 55px;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN CARD
        |--------------------------------------------------------------------------
        */

        .register-card {

            width: 100%;

            max-width: 1085px;

            min-height: 650px;

            background: #ffffff;

            border-radius: 23px;

            overflow: hidden;

            display: grid;

            grid-template-columns:
                50% 50%;

            box-shadow:
                0 15px 45px
                rgba(210, 0, 60, .10);
        }


        /*
        |--------------------------------------------------------------------------
        | LEFT SIDE
        |--------------------------------------------------------------------------
        */

        .left-side {

            position: relative;

            overflow: hidden;

            padding:
                265px 47px 45px;

            color: #ffffff;

            background:
                linear-gradient(
                    145deg,
                    #f5003d 0%,
                    #f61759 53%,
                    #f62d73 100%
                );
        }


        .left-side::after {

            content: "";

            position: absolute;

            width: 430px;

            height: 430px;

            right: -160px;

            bottom: -210px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.035);
        }


        .left-content {

            position: relative;

            z-index: 2;

            max-width: 490px;
        }


        .rider-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                8px 15px;

            border:
                1px solid
                rgba(255,255,255,.35);

            border-radius: 25px;

            background:
                rgba(255,255,255,.10);

            font-size: 11px;

            font-weight: 700;

            margin-bottom: 20px;
        }


        .left-side h1 {

            margin:
                0 0 13px;

            color: #ffffff;

            font-size: 38px;

            line-height: 1.1;

            font-weight: 850;

            letter-spacing: -.7px;
        }


        .left-side p {

            margin:
                0 0 23px;

            max-width: 470px;

            color: #ffffff;

            font-size: 13px;

            line-height: 1.8;

            font-weight: 500;
        }


        .benefits {

            display: flex;

            flex-direction: column;

            gap: 11px;
        }


        .benefit {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 12px;

            font-weight: 700;
        }


        .benefit-icon {

            width: 30px;

            height: 30px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            border-radius: 8px;

            background:
                rgba(255,255,255,.18);

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | RIGHT SIDE
        |--------------------------------------------------------------------------
        */

        .right-side {

            padding:
                43px 40px 35px;

            background: #ffffff;
        }


        .right-heading {

            margin-bottom:
                22px;
        }


        .right-heading h2 {

            margin:
                0 0 7px;

            color: #292929;

            font-size: 27px;

            line-height: 1.15;

            font-weight: 850;
        }


        .right-heading p {

            margin: 0;

            color: #777777;

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error-box {

            display: flex;

            align-items: flex-start;

            gap: 8px;

            margin-bottom:
                18px;

            padding:
                11px 13px;

            background: #fff1f4;

            border:
                1px solid #ffc6d4;

            border-radius: 8px;

            color: #b0002d;

            font-size: 11px;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | FORM GRID
        |--------------------------------------------------------------------------
        */

        .form-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                15px 14px;
        }


        .form-group {

            min-width: 0;
        }


        .full {

            grid-column:
                1 / -1;
        }


        .form-group label {

            display: block;

            margin-bottom: 6px;

            color: #292929;

            font-size: 11.5px;

            font-weight: 800;
        }


        .required {

            color: #ed0038;
        }


        /*
        |--------------------------------------------------------------------------
        | INPUTS
        |--------------------------------------------------------------------------
        */

        .input-wrap {

            position: relative;
        }


        .input-wrap > i {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #ed0038;

            font-size: 13px;

            pointer-events: none;

            z-index: 2;
        }


        .input {

            width: 100%;

            height: 45px;

            padding:
                0 12px 0 40px;

            border:
                1px solid #dddddd;

            border-radius: 9px;

            background: #ffffff;

            outline: none;

            color: #333333;

            font-size: 12px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }


        .input:focus {

            border-color: #ed0038;

            box-shadow:
                0 0 0 3px
                rgba(237,0,56,.08);
        }


        textarea.input {

            height: 80px;

            padding:
                13px 12px 10px 40px;

            resize: vertical;
        }


        /*
        |--------------------------------------------------------------------------
        | VEHICLE
        |--------------------------------------------------------------------------
        */

        .vehicle-card {

            width: 100%;

            height: 49px;

            border:
                1px solid #ed0038;

            border-radius: 9px;

            background:
                #fff8fa;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 13px;
        }


        .vehicle-left {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .vehicle-icon {

            width: 30px;

            height: 30px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff0f4;

            border-radius: 7px;

            color: #ed0038;

            font-size: 13px;
        }


        .vehicle-name {

            font-size: 12px;

            font-weight: 800;

            color: #292929;
        }


        .vehicle-sub {

            display: block;

            margin-top: 2px;

            color: #999999;

            font-size: 9px;

            font-weight: 500;
        }


        .vehicle-check {

            color: #ed0038;

            font-size: 16px;
        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD
        |--------------------------------------------------------------------------
        */

        .password-input {

            padding-right:
                40px;
        }


        .show-password {

            position: absolute;

            right: 8px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 30px;

            height: 30px;

            border: none;

            background: transparent;

            color: #ed0038;

            cursor: pointer;

            border-radius: 7px;
        }


        .show-password:hover {

            background: #fff0f4;
        }


        .password-note {

            margin:
                5px 0 0;

            color: #999999;

            font-size: 9.5px;
        }


        /*
        |--------------------------------------------------------------------------
        | TERMS
        |--------------------------------------------------------------------------
        */

        .terms-row {

            display: flex;

            align-items: flex-start;

            gap: 8px;

            margin:
                18px 0 14px;

            color: #777777;

            font-size: 10px;

            line-height: 1.5;
        }


        .terms-row input {

            width: 14px;

            height: 14px;

            margin:
                1px 0 0;

            accent-color: #ed0038;

            flex-shrink: 0;

            cursor: pointer;
        }


        .terms-row a {

            color: #ed0038;

            text-decoration: none;

            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .create-button {

            width: 100%;

            height: 48px;

            border: none;

            border-radius: 9px;

            background:
                linear-gradient(
                    100deg,
                    #f5003d,
                    #f52e74
                );

            color: #ffffff;

            font-size: 13px;

            font-weight: 850;

            cursor: pointer;

            box-shadow:
                0 7px 17px
                rgba(237,0,56,.18);

            transition: .2s ease;
        }


        .create-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 22px
                rgba(237,0,56,.24);
        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN
        |--------------------------------------------------------------------------
        */

        .login-line {

            margin-top:
                15px;

            text-align:
                center;

            color:
                #888888;

            font-size:
                11px;
        }


        .login-line a {

            color:
                #ed0038;

            font-weight:
                800;

            text-decoration:
                none;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {

            .register-card {

                grid-template-columns:
                    1fr;
            }


            .left-side {

                padding:
                    45px 35px;
            }


            .left-side h1 {

                font-size:
                    31px;
            }


            .right-side {

                padding:
                    35px;
            }
        }


        @media (max-width: 600px) {

            .top-header {

                padding:
                    0 15px;
            }


            .page {

                padding:
                    20px 12px 30px;
            }


            .register-card {

                border-radius:
                    17px;
            }


            .left-side {

                padding:
                    35px 25px;
            }


            .right-side {

                padding:
                    28px 20px;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .full {

                grid-column:
                    auto;
            }


            .left-side h1 {

                font-size:
                    28px;
            }


            .right-heading h2 {

                font-size:
                    24px;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="top-header">


    <a
        href="../index.php"
        class="brand"
    >

        <i class="fa-solid fa-utensils"></i>

        Humsafar

    </a>


    <a
        href="rider-login.php"
        class="back-button"
    >

        <i class="fa-solid fa-arrow-left"></i>

        Back to Humsafar

    </a>


</header>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="page">


    <div class="register-card">


        <!-- =====================================================
             LEFT SIDE
        ====================================================== -->

        <section class="left-side">


            <div class="left-content">


                <div class="rider-badge">

                    <i class="fa-solid fa-motorcycle"></i>

                    Humsafar Rider Partner

                </div>


                <h1>
                    Ride With Humsafar
                </h1>


                <p>

                    Join Humsafar as a delivery rider,
                    deliver food to customers and earn
                    with every successful delivery.

                </p>


                <div class="benefits">


                    <div class="benefit">

                        <span class="benefit-icon">

                            <i class="fa-solid fa-motorcycle"></i>

                        </span>

                        Delivery work with your bike

                    </div>


                    <div class="benefit">

                        <span class="benefit-icon">

                            <i class="fa-solid fa-location-dot"></i>

                        </span>

                        Deliver orders in your area

                    </div>


                    <div class="benefit">

                        <span class="benefit-icon">

                            <i class="fa-solid fa-clock"></i>

                        </span>

                        Flexible working opportunity

                    </div>


                    <div class="benefit">

                        <span class="benefit-icon">

                            <i class="fa-solid fa-user-shield"></i>

                        </span>

                        Secure rider account

                    </div>


                </div>


            </div>


        </section>


        <!-- =====================================================
             RIGHT SIDE
        ====================================================== -->

        <section class="right-side">


            <div class="right-heading">

                <h2>
                    Become a Rider
                </h2>

                <p>
                    Create your Humsafar rider account.
                </p>

            </div>


            <?php if ($error !== ""): ?>

                <div class="error-box">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        ?>

                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                autocomplete="off"
            >


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label for="full_name">

                            Full Name

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-user"></i>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                class="input"
                                placeholder="Enter your full name"
                                value="<?php echo htmlspecialchars(
                                    $full_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label for="phone">

                            Phone Number

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-phone"></i>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                class="input"
                                placeholder="03XXXXXXXXX"
                                value="<?php echo htmlspecialchars(
                                    $phone,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">

                            Email Address

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-envelope"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="input"
                                placeholder="Enter your email"
                                value="<?php echo htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- CNIC -->

                    <div class="form-group">

                        <label for="cnic">

                            CNIC Number

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-id-card"></i>

                            <input
                                type="text"
                                id="cnic"
                                name="cnic"
                                class="input"
                                placeholder="XXXXX-XXXXXXX-X"
                                value="<?php echo htmlspecialchars(
                                    $cnic,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                maxlength="15"
                                required
                            >

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="form-group full">

                        <label for="address">

                            Address

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-location-dot"></i>

                            <textarea
                                id="address"
                                name="address"
                                class="input"
                                placeholder="Enter your complete address"
                                required
                            ><?php echo htmlspecialchars(
                                $address,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                    </div>


                    <!-- VEHICLE -->

                    <div class="form-group full">

                        <label>

                            Vehicle Type

                        </label>


                        <div class="vehicle-card">


                            <div class="vehicle-left">


                                <div class="vehicle-icon">

                                    <i class="fa-solid fa-motorcycle"></i>

                                </div>


                                <div>

                                    <div class="vehicle-name">

                                        Bike

                                    </div>

                                    <span class="vehicle-sub">

                                        Delivery vehicle

                                    </span>

                                </div>


                            </div>


                            <i
                                class="fa-solid fa-circle-check vehicle-check"
                            ></i>


                        </div>


                    </div>


                    <!-- BIKE NUMBER -->

                    <div class="form-group full">

                        <label for="bike_number">

                            Bike Number

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-motorcycle"></i>

                            <input
                                type="text"
                                id="bike_number"
                                name="bike_number"
                                class="input"
                                placeholder="Enter your bike number"
                                value="<?php echo htmlspecialchars(
                                    $bike_number,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">

                            Password

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-lock"></i>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="input password-input"
                                placeholder="Create password"
                                required
                            >


                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword(
                                    'password',
                                    'passwordIcon'
                                )"
                            >

                                <i
                                    class="fa-solid fa-eye"
                                    id="passwordIcon"
                                ></i>

                            </button>

                        </div>


                        <div class="password-note">

                            Password must be at least 6 characters.

                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">

                            Confirm Password

                            <span class="required">*</span>

                        </label>


                        <div class="input-wrap">

                            <i class="fa-solid fa-shield-halved"></i>


                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="input password-input"
                                placeholder="Confirm password"
                                required
                            >


                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword(
                                    'confirm_password',
                                    'confirmPasswordIcon'
                                )"
                            >

                                <i
                                    class="fa-solid fa-eye"
                                    id="confirmPasswordIcon"
                                ></i>

                            </button>

                        </div>

                    </div>


                </div>


                <!-- TERMS -->

                <div class="terms-row">


                    <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        value="1"
                        required
                    >


                    <label for="terms">

                        I agree to the Humsafar

                        <a href="#">
                            Rider Terms & Conditions
                        </a>

                        and

                        <a href="#">
                            Rider Partner Policy
                        </a>.

                    </label>


                </div>


                <!-- CREATE ACCOUNT -->

                <button
                    type="submit"
                    class="create-button"
                >

                    <i class="fa-solid fa-motorcycle"></i>

                    &nbsp;

                    Create Rider Account

                </button>


            </form>


            <div class="login-line">

                Already have a rider account?

                <a href="rider-login.php">
                    Login
                </a>

            </div>


        </section>


    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| SHOW / HIDE PASSWORD
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| CNIC AUTO FORMAT
|--------------------------------------------------------------------------
*/

const cnicInput =
    document.getElementById("cnic");


if (cnicInput) {

    cnicInput.addEventListener(
        "input",
        function () {

            let value =
                this.value.replace(
                    /[^0-9]/g,
                    ""
                );


            value =
                value.substring(
                    0,
                    13
                );


            if (value.length > 12) {

                value =
                    value.substring(0, 5)
                    + "-"
                    + value.substring(5, 12)
                    + "-"
                    + value.substring(12);

            } else if (value.length > 5) {

                value =
                    value.substring(0, 5)
                    + "-"
                    + value.substring(5);

            }


            this.value = value;

        }
    );

}


/*
|--------------------------------------------------------------------------
| FORM PASSWORD CHECK
|--------------------------------------------------------------------------
*/

const form =
    document.querySelector("form");


if (form) {

    form.addEventListener(
        "submit",
        function(event) {

            const password =
                document.getElementById(
                    "password"
                ).value;

            const confirmPassword =
                document.getElementById(
                    "confirm_password"
                ).value;


            if (password !== confirmPassword) {

                event.preventDefault();

                alert(
                    "Password and confirm password do not match."
                );

                return false;
            }


            return true;

        }
    );

}

</script>


</body>

</html>