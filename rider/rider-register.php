<?php

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* =========================================================
   CREATE RIDERS TABLE
========================================================= */

$createTable = "
CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    cnic VARCHAR(15) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT NOT NULL,
    vehicle_type VARCHAR(30) NOT NULL DEFAULT 'Motorcycle',
    bike_number VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_cnic (cnic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($createTable);


/* =========================================================
   FORM VALUES
========================================================= */

$fullName      = '';
$cnic          = '';
$phone         = '';
$email         = '';
$address       = '';
$bikeNumber    = '';

$successMessage = '';
$errorMessage   = '';


/* =========================================================
   REGISTRATION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName =
        trim($_POST['full_name'] ?? '');

    $cnic =
        trim($_POST['cnic'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $address =
        trim($_POST['address'] ?? '');

    $bikeNumber =
        trim($_POST['bike_number'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';

    $terms =
        isset($_POST['terms']);


    /* =====================================================
       FORMAT CNIC
    ===================================================== */

    $cnicDigits =
        preg_replace('/[^0-9]/', '', $cnic);

    if (strlen($cnicDigits) === 13) {

        $cnic =
            substr($cnicDigits, 0, 5)
            . '-'
            . substr($cnicDigits, 5, 7)
            . '-'
            . substr($cnicDigits, 12, 1);
    }


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($fullName === '') {

        $errorMessage =
            'Please enter your full name.';

    } elseif (strlen($fullName) < 3) {

        $errorMessage =
            'Please enter a valid full name.';

    } elseif (
        !preg_match(
            '/^[0-9]{5}-[0-9]{7}-[0-9]{1}$/',
            $cnic
        )
    ) {

        $errorMessage =
            'Please enter CNIC in the format 12345-1234567-1.';

    } elseif ($phone === '') {

        $errorMessage =
            'Please enter your mobile number.';

    } elseif ($address === '') {

        $errorMessage =
            'Please enter your complete address.';

    } elseif ($bikeNumber === '') {

        $errorMessage =
            'Please enter your bike number.';

    } elseif ($password === '') {

        $errorMessage =
            'Please create a password.';

    } elseif (strlen($password) < 6) {

        $errorMessage =
            'Password must contain at least 6 characters.';

    } elseif ($password !== $confirmPassword) {

        $errorMessage =
            'Passwords do not match.';

    } elseif (!$terms) {

        $errorMessage =
            'Please accept the Rider Terms & Conditions.';
    }


    /* =====================================================
       CHECK CNIC
    ===================================================== */

    if ($errorMessage === '') {

        $check = $conn->prepare("
            SELECT id
            FROM riders
            WHERE cnic = ?
            LIMIT 1
        ");

        if (!$check) {

            $errorMessage =
                'Database error: ' . $conn->error;

        } else {

            $check->bind_param(
                "s",
                $cnic
            );

            $check->execute();

            $result =
                $check->get_result();

            if (
                $result &&
                $result->num_rows > 0
            ) {

                $errorMessage =
                    'This CNIC is already registered.';
            }

            $check->close();
        }
    }


    /* =====================================================
       INSERT RIDER
    ===================================================== */

    if ($errorMessage === '') {

        $hashedPassword =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        $vehicleType =
            'Motorcycle';

        $stmt = $conn->prepare("
            INSERT INTO riders
            (
                full_name,
                cnic,
                phone,
                email,
                address,
                vehicle_type,
                bike_number,
                password,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'pending'
            )
        ");

        if (!$stmt) {

            $errorMessage =
                'Database error: ' . $conn->error;

        } else {

            $stmt->bind_param(
                "ssssssss",
                $fullName,
                $cnic,
                $phone,
                $email,
                $address,
                $vehicleType,
                $bikeNumber,
                $hashedPassword
            );

           if ($stmt->execute()) {

           header("Location: rider-login.php");
                 exit;

            } else {
                if ($stmt->errno == 1062) {

                    $errorMessage =
                        'This CNIC is already registered.';

                } else {

                    $errorMessage =
                        'Registration failed: '
                        . $stmt->error;
                }
            }

            $stmt->close();
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

<title>
    Rider Registration | Humsafar
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f7f5f6;

    color: #29232a;
}

a {
    text-decoration: none;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 70px;

    background: #ffffff;

    border-bottom:
        1px solid #eee5e8;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 35px;
}


/* LOGO */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #29232a;

    font-size: 23px;

    font-weight: 800;
}

.logo-icon {

    width: 40px;

    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background: #ed0038;

    color: white;

    font-size: 16px;
}


/* BACK BUTTON */

.back-button {

    display: inline-flex;

    align-items: center;

    gap: 9px;

    padding:
        11px 18px;

    border:
        1px solid #ed0038;

    border-radius: 9px;

    background: #fff;

    color: #ed0038;

    font-size: 11px;

    font-weight: 800;

    box-shadow:
        0 4px 12px
        rgba(237,0,56,.08);

    transition:
        all .2s ease;
}

.back-button:hover {

    background: #ed0038;

    color: #fff;

    transform:
        translateY(-1px);

    box-shadow:
        0 8px 20px
        rgba(237,0,56,.18);
}


/* =========================================================
   MAIN
========================================================= */

.main {

    min-height:
        calc(100vh - 125px);

    padding:
        38px 20px 45px;

    display: flex;

    justify-content: center;

    align-items: flex-start;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    width:
        min(1100px, 100%);

    display: grid;

    grid-template-columns:
        42% 58%;

    background: #fff;

    border:
        1px solid #eee4e8;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 15px 45px
        rgba(40,20,28,.07);
}


/* =========================================================
   LEFT SIDE
========================================================= */

.left {

    position: relative;

    min-height: 760px;

    padding:
        58px 45px;

    background:
        linear-gradient(
            145deg,
            #ed0038,
            #fa578b
        );

    color: #fff;

    overflow: hidden;
}


.left:before {

    content: "";

    position: absolute;

    width: 280px;

    height: 280px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    top: -110px;

    right: -100px;
}


.left:after {

    content: "";

    position: absolute;

    width: 220px;

    height: 220px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

    bottom: -90px;

    left: -80px;
}


.left-content {

    position: relative;

    z-index: 2;
}


/* BADGE */

.badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        9px 13px;

    border:
        1px solid
        rgba(255,255,255,.25);

    background:
        rgba(255,255,255,.12);

    border-radius: 50px;

    font-size: 10px;

    font-weight: 800;

    margin-bottom: 23px;
}


/* LEFT HEADING */

.left h1 {

    margin:
        0 0 15px;

    font-size: 37px;

    line-height: 1.15;

    font-weight: 800;
}


.left-description {

    margin: 0;

    color:
        rgba(255,255,255,.88);

    font-size: 12px;

    line-height: 1.8;
}


/* =========================================================
   FEATURES
========================================================= */

.features {

    margin-top: 40px;

    display: grid;

    gap: 20px;
}


.feature {

    display: flex;

    gap: 13px;

    align-items: flex-start;
}


.feature-icon {

    width: 37px;

    height: 37px;

    flex:
        0 0 37px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background:
        rgba(255,255,255,.13);

    font-size: 13px;
}


.feature strong {

    display: block;

    margin-bottom: 4px;

    font-size: 11px;
}


.feature span {

    display: block;

    color:
        rgba(255,255,255,.76);

    font-size: 9px;

    line-height: 1.55;
}


/* MOTORCYCLE ICON */

.motorcycle-icon {

    position: absolute;

    z-index: 2;

    right: 35px;

    bottom: 35px;

    width: 115px;

    height: 115px;

    border-radius: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(255,255,255,.11);

    border:
        1px solid
        rgba(255,255,255,.13);

    font-size: 48px;
}


/* =========================================================
   RIGHT SIDE
========================================================= */

.right {

    padding:
        43px 45px;

    background: #fff;
}


/* HEADING */

.heading {

    margin-bottom: 26px;
}

.heading h2 {

    margin:
        0 0 7px;

    font-size: 29px;

    font-weight: 800;

    color: #29232a;
}

.heading p {

    margin: 0;

    color: #999;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding:
        12px 13px;

    border-radius: 8px;

    margin-bottom: 18px;

    display: flex;

    gap: 9px;

    font-size: 10px;

    line-height: 1.5;
}

.alert-success {

    color: #14733f;

    background: #edf9f2;

    border:
        1px solid #d2efdf;
}

.alert-error {

    color: #ad002c;

    background: #fff0f3;

    border:
        1px solid #ffd3dc;
}


/* =========================================================
   FORM SECTION
========================================================= */

.section {

    margin-bottom: 23px;
}


.section-heading {

    display: flex;

    align-items: center;

    gap: 8px;

    padding-bottom: 10px;

    margin-bottom: 15px;

    border-bottom:
        1px solid #f0eaec;

    color: #29232a;

    font-size: 12px;

    font-weight: 800;
}


.section-heading i {

    color: #ed0038;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.form-group.full {

    grid-column:
        1 / -1;
}


/* =========================================================
   LABEL
========================================================= */

label {

    display: block;

    margin-bottom: 6px;

    color: #51494d;

    font-size: 10px;

    font-weight: 700;
}


.required {

    color: #ed0038;
}


/* =========================================================
   INPUT
========================================================= */

.input,
.select,
.textarea {

    width: 100%;

    border:
        1px solid #e3dcdf;

    border-radius: 8px;

    outline: none;

    color: #3c3539;

    background: #fff;

    font-family: inherit;

    font-size: 11px;

    transition: .2s;
}


.input,
.select {

    height: 43px;

    padding:
        0 12px;
}


.textarea {

    min-height: 78px;

    padding:
        10px 12px;

    resize: vertical;
}


.input::placeholder,
.textarea::placeholder {

    color: #b4abad;
}


.input:focus,
.select:focus,
.textarea:focus {

    border-color:
        #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.06);
}


/* =========================================================
   CNIC HELP
========================================================= */

.help {

    margin-top: 5px;

    color: #aaa;

    font-size: 8px;
}


/* =========================================================
   PASSWORD
========================================================= */

.password-box {

    position: relative;
}


.password-box .input {

    padding-right: 40px;
}


.password-toggle {

    position: absolute;

    right: 11px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    color: #999;

    cursor: pointer;

    font-size: 10px;
}


/* =========================================================
   TERMS
========================================================= */

.terms {

    padding: 12px;

    background: #faf8f9;

    border:
        1px solid #eee6e9;

    border-radius: 8px;
}


.terms label {

    display: flex;

    align-items: flex-start;

    gap: 8px;

    margin: 0;

    font-size: 9px;

    line-height: 1.55;

    color: #777;
}


.terms input {

    margin-top: 2px;

    accent-color:
        #ed0038;
}


.terms a {

    color:
        #ed0038;

    font-weight: 800;
}


/* =========================================================
   BUTTON
========================================================= */

.register-button {

    width: 100%;

    height: 47px;

    margin-top: 17px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #fff;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    font-size: 11px;

    font-weight: 800;

    box-shadow:
        0 8px 20px
        rgba(237,0,56,.17);

    transition: .2s;
}


.register-button:hover {

    background: #d90034;

    transform:
        translateY(-1px);

    box-shadow:
        0 11px 25px
        rgba(237,0,56,.21);
}


/* =========================================================
   LOGIN
========================================================= */

.login {

    text-align: center;

    margin-top: 15px;

    color: #999;

    font-size: 9px;
}


.login a {

    color: #ed0038;

    font-weight: 800;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #29232a;

    color: #aaa;

    font-size: 9px;
}


.footer strong {

    color: #fff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .container {

        grid-template-columns: 1fr;
    }

    .left {

        min-height: 390px;
    }

    .motorcycle-icon {

        display: none;
    }
}


@media (max-width: 600px) {

    .header {

        height: 65px;

        padding:
            0 16px;
    }

    .logo {

        font-size: 20px;
    }

    .back-button {

        padding:
            9px 12px;

        font-size: 9px;
    }

    .main {

        padding:
            18px 10px 30px;
    }

    .left {

        min-height: 350px;

        padding:
            35px 25px;
    }

    .left h1 {

        font-size: 30px;
    }

    .right {

        padding:
            30px 22px;
    }

    .heading h2 {

        font-size: 25px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">


    <a
        href="../join-humsafar.php"
        class="logo"
    >

        <span class="logo-icon">

            <i class="fas fa-utensils"></i>

        </span>

        Humsafar

    </a>


    <a
        href="../join-humsafar.php"
        class="back-button"
    >

        <i class="fas fa-arrow-left"></i>

        Back to Humsafar

    </a>


</header>



<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


<div class="container">


    <!-- =====================================================
         LEFT PROMOTIONAL SECTION
    ====================================================== -->

    <section class="left">


        <div class="left-content">


            <div class="badge">

                <i class="fas fa-motorcycle"></i>

                Humsafar Rider Partner

            </div>


            <h1>

                Start Your Journey
                With Humsafar

            </h1>


            <p class="left-description">

                Join Humsafar as a delivery rider and
                become part of our growing food delivery
                network. Register your details and start
                your journey with us.

            </p>


            <div class="features">


                <!-- FEATURE 1 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-motorcycle"></i>

                    </div>

                    <div>

                        <strong>
                            Delivery Partner
                        </strong>

                        <span>
                            Deliver restaurant orders
                            directly to customers.
                        </span>

                    </div>

                </div>


                <!-- FEATURE 2 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-location-dot"></i>

                    </div>

                    <div>

                        <strong>
                            Easy Order Access
                        </strong>

                        <span>
                            Receive delivery orders
                            through the Humsafar system.
                        </span>

                    </div>

                </div>


                <!-- FEATURE 3 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-shield-halved"></i>

                    </div>

                    <div>

                        <strong>
                            Secure Account
                        </strong>

                        <span>
                            Your rider account is reviewed
                            by the Humsafar administration.
                        </span>

                    </div>

                </div>


                <!-- FEATURE 4 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-id-card"></i>

                    </div>

                    <div>

                        <strong>
                            CNIC Based Login
                        </strong>

                        <span>
                            Your CNIC number will be used
                            for secure rider login.
                        </span>

                    </div>

                </div>


            </div>


        </div>


        <div class="motorcycle-icon">

            <i class="fas fa-motorcycle"></i>

        </div>


    </section>



    <!-- =====================================================
         RIGHT FORM
    ====================================================== -->

    <section class="right">


        <div class="heading">

            <h2>
                Create Rider Account
            </h2>

            <p>
                Enter your information below to register
                as a Humsafar delivery rider.
            </p>

        </div>



        <!-- SUCCESS -->

        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success">

                <i class="fas fa-circle-check"></i>

                <div>

                    <?= e($successMessage) ?>

                    <br><br>

                    <a
                        href="rider-login.php"
                        style="
                            color:#14733f;
                            font-weight:800;
                        "
                    >
                        Go to Rider Login
                        <i class="fas fa-arrow-right"></i>
                    </a>

                </div>

            </div>

        <?php endif; ?>



        <!-- ERROR -->

        <?php if ($errorMessage !== ''): ?>

            <div class="alert alert-error">

                <i class="fas fa-circle-exclamation"></i>

                <span>
                    <?= e($errorMessage) ?>
                </span>

            </div>

        <?php endif; ?>



        <!-- =================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            action=""
            autocomplete="off"
        >


            <!-- =================================================
                 PERSONAL INFORMATION
            ================================================== -->

            <div class="section">


                <div class="section-heading">

                    <i class="fas fa-user"></i>

                    Personal Information

                </div>


                <div class="form-grid">


                    <!-- NAME -->

                    <div class="form-group full">

                        <label>

                            Full Name

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="full_name"
                            class="input"
                            value="<?= e($fullName) ?>"
                            placeholder="Enter your full name"
                            maxlength="150"
                            required
                        >

                    </div>



                    <!-- CNIC -->

                    <div class="form-group">

                        <label>

                            CNIC Number

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="cnic"
                            id="cnic"
                            class="input"
                            value="<?= e($cnic) ?>"
                            placeholder="12345-xxxxxxx-0"
                            maxlength="15"
                            inputmode="numeric"
                            required
                        >


                        <div class="help">

                            Format:
                            12345-1234567-1

                        </div>

                    </div>



                    <!-- PHONE -->

                    <div class="form-group">

                        <label>

                            Mobile Number

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="phone"
                            class="input"
                            value="<?= e($phone) ?>"
                            placeholder="03XXXXXXXXX"
                            maxlength="30"
                            required
                        >

                    </div>



                    <!-- EMAIL -->

                    <div class="form-group full">

                        <label>

                            Email Address

                            <span
                                style="
                                    color:#aaa;
                                    font-weight:500;
                                "
                            >
                                (Optional)
                            </span>

                        </label>


                        <input
                            type="email"
                            name="email"
                            class="input"
                            value="<?= e($email) ?>"
                            placeholder="Enter your email address"
                            maxlength="150"
                        >

                    </div>



                    <!-- ADDRESS -->

                    <div class="form-group full">

                        <label>

                            Complete Address

                            <span class="required">*</span>

                        </label>


                        <textarea
                            name="address"
                            class="textarea"
                            placeholder="Enter your complete current address"
                            maxlength="500"
                            required
                        ><?= e($address) ?></textarea>

                    </div>


                </div>

            </div>



            <!-- =================================================
                 VEHICLE
            ================================================== -->

            <div class="section">


                <div class="section-heading">

                    <i class="fas fa-motorcycle"></i>

                    Motorcycle Information

                </div>


                <div class="form-grid">


                    <!-- VEHICLE TYPE -->

                    <div class="form-group">

                        <label>

                            Vehicle Type

                            <span class="required">*</span>

                        </label>


                        <select
                            name="vehicle_type"
                            class="select"
                            required
                        >

                            <option
                                value="Motorcycle"
                                selected
                            >
                                Motorcycle
                            </option>

                        </select>

                    </div>



                    <!-- BIKE NUMBER -->

                    <div class="form-group">

                        <label>

                            Bike Number

                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            name="bike_number"
                            class="input"
                            value="<?= e($bikeNumber) ?>"
                            placeholder="e.g. ABC-123"
                            maxlength="50"
                            required
                        >

                    </div>


                </div>

            </div>



            <!-- =================================================
                 ACCOUNT SECURITY
            ================================================== -->

            <div class="section">


                <div class="section-heading">

                    <i class="fas fa-lock"></i>

                    Account Security

                </div>


                <div class="form-grid">


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label>

                            Password

                            <span class="required">*</span>

                        </label>


                        <div class="password-box">


                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="input"
                                placeholder="Minimum 6 characters"
                                minlength="6"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                data-target="password"
                            >

                                <i class="fas fa-eye"></i>

                            </button>


                        </div>

                    </div>



                    <!-- CONFIRM -->

                    <div class="form-group">

                        <label>

                            Confirm Password

                            <span class="required">*</span>

                        </label>


                        <div class="password-box">


                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                class="input"
                                placeholder="Re-enter password"
                                minlength="6"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirm_password"
                            >

                                <i class="fas fa-eye"></i>

                            </button>


                        </div>

                    </div>


                </div>

            </div>



            <!-- =================================================
                 TERMS
            ================================================== -->

            <div class="terms">


                <label>

                    <input
                        type="checkbox"
                        name="terms"
                        value="1"
                        required
                    >


                    <span>

                        I agree to Humsafar's
                        <a href="#">
                            Terms & Conditions
                        </a>
                        and Rider Policy.

                    </span>


                </label>


            </div>



            <!-- =================================================
                 SUBMIT
            ================================================== -->

            <button
                type="submit"
                class="register-button"
            >

                <i class="fas fa-user-plus"></i>

                Register as Rider

            </button>


        </form>



        <!-- LOGIN -->

        <div class="login">

            Already have a Rider account?

            <a href="rider-login.php">

                Login here

            </a>

        </div>


    </section>


</div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <strong>
        Humsafar
    </strong>

    &nbsp; Food Delivery &nbsp;•&nbsp;

    © <?= date('Y') ?>

</footer>



<script>

/* =========================================================
   CNIC AUTO FORMAT
========================================================= */

const cnicInput =
    document.getElementById('cnic');

if (cnicInput) {

    cnicInput.addEventListener(
        'input',
        function () {

            let digits =
                this.value.replace(
                    /[^0-9]/g,
                    ''
                );

            digits =
                digits.substring(
                    0,
                    13
                );


            if (digits.length <= 5) {

                this.value =
                    digits;

            }
            else if (digits.length <= 12) {

                this.value =
                    digits.substring(0, 5)
                    + '-'
                    + digits.substring(5);

            }
            else {

                this.value =
                    digits.substring(0, 5)
                    + '-'
                    + digits.substring(5, 12)
                    + '-'
                    + digits.substring(12);

            }

        }
    );

}


/* =========================================================
   PASSWORD SHOW / HIDE
========================================================= */

document
    .querySelectorAll('.password-toggle')
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const input =
                    document.getElementById(
                        this.dataset.target
                    );

                const icon =
                    this.querySelector('i');


                if (
                    input.type === 'password'
                ) {

                    input.type =
                        'text';

                    icon.classList.remove(
                        'fa-eye'
                    );

                    icon.classList.add(
                        'fa-eye-slash'
                    );

                }
                else {

                    input.type =
                        'password';

                    icon.classList.remove(
                        'fa-eye-slash'
                    );

                    icon.classList.add(
                        'fa-eye'
                    );
                }

            }
        );

    });


/* =========================================================
   PASSWORD MATCH
========================================================= */

const passwordInput =
    document.getElementById(
        'password'
    );

const confirmInput =
    document.getElementById(
        'confirm_password'
    );


function checkPasswords()
{
    if (
        confirmInput.value !== ''
        &&
        passwordInput.value !==
        confirmInput.value
    ) {

        confirmInput.style.borderColor =
            '#ed0038';

    }
    else {

        confirmInput.style.borderColor =
            '';
    }
}


if (passwordInput) {

    passwordInput.addEventListener(
        'input',
        checkPasswords
    );
}


if (confirmInput) {

    confirmInput.addEventListener(
        'input',
        checkPasswords
    );
}

</script>


</body>

</html>