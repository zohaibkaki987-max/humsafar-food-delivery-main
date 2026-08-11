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
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   VARIABLES
========================================================= */

$cnic = '';

$errorMessage = '';


/* =========================================================
   LOGIN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cnic =
        trim($_POST['cnic'] ?? '');

    $password =
        $_POST['password'] ?? '';


    /* =====================================================
       FORMAT CNIC
    ===================================================== */

    $digits =
        preg_replace(
            '/[^0-9]/',
            '',
            $cnic
        );


    if (strlen($digits) === 13) {

        $cnic =
            substr($digits, 0, 5)
            . '-'
            . substr($digits, 5, 7)
            . '-'
            . substr($digits, 12, 1);
    }


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        !preg_match(
            '/^[0-9]{5}-[0-9]{7}-[0-9]{1}$/',
            $cnic
        )
    ) {

        $errorMessage =
            'Please enter a valid CNIC in the format 12345-1234567-1.';

    } elseif ($password === '') {

        $errorMessage =
            'Please enter your password.';

    } else {


        /* =================================================
           FIND RIDER
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                cnic,
                phone,
                password,
                status
            FROM riders
            WHERE cnic = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $errorMessage =
                'Database error: ' . $conn->error;

        } else {


            $stmt->bind_param(
                "s",
                $cnic
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            /* =============================================
               RIDER NOT FOUND
            ============================================= */

            if (
                !$result ||
                $result->num_rows === 0
            ) {

                $errorMessage =
                    'No rider account was found with this CNIC.';

            } else {


                $rider =
                    $result->fetch_assoc();


                /* =========================================
                   PASSWORD CHECK ONLY
                   
                   STATUS IS NOT CHECKED HERE.
                ========================================= */

                if (
                    !password_verify(
                        $password,
                        $rider['password']
                    )
                ) {

                    $errorMessage =
                        'Incorrect password. Please try again.';

                } else {


                    /* =====================================
                       LOGIN SESSION
                    ===================================== */

                    $_SESSION['rider_logged_in'] =
                        true;


                    $_SESSION['rider_id'] =
                        $rider['id'];


                    $_SESSION['rider_name'] =
                        $rider['full_name'];


                    $_SESSION['rider_cnic'] =
                        $rider['cnic'];


                    $_SESSION['rider_phone'] =
                        $rider['phone'];


                    /*
                     * Status dashboard par use hoga.
                     *
                     * pending
                     * approved
                     * rejected
                     */

                    $_SESSION['rider_status'] =
                        $rider['status'];


                    /* =====================================
                       DIRECT DASHBOARD
                    ===================================== */

                    header(
                        'Location: rider-dashboard.php'
                    );

                    exit;
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
    Rider Login | Humsafar
</title>


<!-- FONT AWESOME -->

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


/* =========================================================
   LOGO
========================================================= */

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

    color: #ffffff;

    font-size: 16px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-button {

    display: inline-flex;

    align-items: center;

    gap: 9px;

    padding:
        11px 18px;

    border:
        1px solid #ed0038;

    border-radius: 9px;

    background: #ffffff;

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

    color: #ffffff;

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
        min(1000px, 100%);

    display: grid;

    grid-template-columns:
        45% 55%;

    background: #ffffff;

    border:
        1px solid #eee4e8;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 15px 45px
        rgba(40,20,28,.07);
}


/* =========================================================
   LEFT PROMOTIONAL AREA
========================================================= */

.left {

    position: relative;

    min-height: 610px;

    padding:
        58px 45px;

    background:
        linear-gradient(
            145deg,
            #ed0038,
            #fa578b
        );

    color: #ffffff;

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


/* =========================================================
   BADGE
========================================================= */

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


/* =========================================================
   LEFT TITLE
========================================================= */

.left h1 {

    margin:
        0 0 15px;

    font-size: 38px;

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


/* =========================================================
   MOTORCYCLE ICON
========================================================= */

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
   RIGHT AREA
========================================================= */

.right {

    padding:
        65px 55px;

    background: #ffffff;

    display: flex;

    flex-direction: column;

    justify-content: center;
}


/* =========================================================
   HEADING
========================================================= */

.heading {

    margin-bottom: 28px;
}


.heading h2 {

    margin:
        0 0 7px;

    font-size: 30px;

    font-weight: 800;

    color: #29232a;
}


.heading p {

    margin: 0;

    color: #999999;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   ERROR
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


.alert-error {

    color: #ad002c;

    background: #fff0f3;

    border:
        1px solid #ffd3dc;
}


/* =========================================================
   LABEL
========================================================= */

label {

    display: block;

    margin-bottom: 7px;

    color: #51494d;

    font-size: 11px;

    font-weight: 700;
}


.required {

    color: #ed0038;
}


/* =========================================================
   INPUT
========================================================= */

.input {

    width: 100%;

    height: 46px;

    padding:
        0 13px;

    border:
        1px solid #e3dcdf;

    border-radius: 8px;

    outline: none;

    color: #3c3539;

    background: #ffffff;

    font-family: inherit;

    font-size: 12px;

    transition:
        all .2s ease;
}


.input::placeholder {

    color: #b4abad;
}


.input:focus {

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

    margin-top: 6px;

    color: #aaaaaa;

    font-size: 8px;
}


/* =========================================================
   PASSWORD
========================================================= */

.password-box {

    position: relative;
}


.password-box .input {

    padding-right: 42px;
}


.password-toggle {

    position: absolute;

    right: 12px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    color: #999999;

    cursor: pointer;

    font-size: 11px;
}


/* =========================================================
   LOGIN OPTIONS
========================================================= */

.login-options {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-top: 12px;
}


.remember {

    display: flex;

    align-items: center;

    gap: 7px;

    margin: 0;

    color: #888888;

    font-size: 9px;

    font-weight: 500;
}


.remember input {

    accent-color: #ed0038;
}


.forgot {

    color: #ed0038;

    font-size: 9px;

    font-weight: 800;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-button {

    width: 100%;

    height: 48px;

    margin-top: 22px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #ffffff;

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

    transition:
        all .2s ease;
}


.login-button:hover {

    background: #d90034;

    transform:
        translateY(-1px);

    box-shadow:
        0 11px 25px
        rgba(237,0,56,.21);
}


/* =========================================================
   REGISTER
========================================================= */

.register {

    text-align: center;

    margin-top: 18px;

    color: #999999;

    font-size: 9px;
}


.register a {

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

    color: #aaaaaa;

    font-size: 9px;
}


.footer strong {

    color: #ffffff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .container {

        grid-template-columns: 1fr;
    }


    .left {

        min-height: 400px;
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
            35px 22px;
    }


    .heading h2 {

        font-size: 25px;
    }


    .login-options {

        align-items: flex-start;

        gap: 10px;
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
         LEFT SIDE
    ====================================================== -->

    <section class="left">


        <div class="left-content">


            <div class="badge">

                <i class="fas fa-motorcycle"></i>

                Humsafar Rider Partner

            </div>


            <h1>

                Welcome Back,
                Rider!

            </h1>


            <p class="left-description">

                Login to your Humsafar rider account
                and manage your deliveries from one
                convenient place.

            </p>


            <div class="features">


                <!-- FEATURE 1 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-box"></i>

                    </div>


                    <div>

                        <strong>
                            Manage Deliveries
                        </strong>

                        <span>
                            View and manage your assigned
                            customer orders.
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
                            Track Your Orders
                        </strong>

                        <span>
                            Keep track of your active
                            delivery assignments.
                        </span>

                    </div>

                </div>


                <!-- FEATURE 3 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-clock"></i>

                    </div>


                    <div>

                        <strong>
                            Stay Updated
                        </strong>

                        <span>
                            Receive the latest order and
                            delivery updates.
                        </span>

                    </div>

                </div>


                <!-- FEATURE 4 -->

                <div class="feature">

                    <div class="feature-icon">

                        <i class="fas fa-shield-halved"></i>

                    </div>


                    <div>

                        <strong>
                            Secure Login
                        </strong>

                        <span>
                            Your rider account is protected
                            with your CNIC and password.
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
         RIGHT SIDE
    ====================================================== -->

    <section class="right">


        <div class="heading">

            <h2>
                Rider Login
            </h2>


            <p>
                Login using your registered CNIC
                number and password.
            </p>

        </div>



        <!-- =================================================
             ERROR MESSAGE
        ================================================== -->

        <?php if ($errorMessage !== ''): ?>

            <div class="alert alert-error">

                <i class="fas fa-circle-exclamation"></i>

                <span>
                    <?= e($errorMessage) ?>
                </span>

            </div>

        <?php endif; ?>



        <!-- =================================================
             LOGIN FORM
        ================================================== -->

        <form
            method="POST"
            action=""
            autocomplete="off"
        >


            <!-- CNIC -->

            <div
                style="
                    margin-bottom:18px;
                "
            >

                <label>

                    CNIC Number

                    <span class="required">
                        *
                    </span>

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
                    autocomplete="username"
                    required
                >


                <div class="help">

                    Format:
                    12345-1234567-1

                </div>

            </div>



            <!-- PASSWORD -->

            <div>

                <label>

                    Password

                    <span class="required">
                        *
                    </span>

                </label>


                <div class="password-box">


                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="input"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                    >

                        <i class="fas fa-eye"></i>

                    </button>


                </div>

            </div>



            <!-- OPTIONS -->

            <div class="login-options">


                <label class="remember">

                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                    >

                    Remember me

                </label>


                <a
                    href="#"
                    class="forgot"
                    onclick="
                        alert('Please contact Humsafar administration for password assistance.');
                        return false;
                    "
                >

                    Forgot Password?

                </a>


            </div>



            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-button"
            >

                <i class="fas fa-right-to-bracket"></i>

                Login as Rider

            </button>


        </form>



        <!-- REGISTER -->

        <div class="register">

            Don't have a Rider account?

            <a href="rider-register.php">

                Register Now

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
            else if (
                digits.length <= 12
            ) {

                this.value =
                    digits.substring(
                        0,
                        5
                    )
                    + '-'
                    + digits.substring(
                        5
                    );

            }
            else {

                this.value =
                    digits.substring(
                        0,
                        5
                    )
                    + '-'
                    + digits.substring(
                        5,
                        12
                    )
                    + '-'
                    + digits.substring(
                        12
                    );

            }

        }
    );

}


/* =========================================================
   PASSWORD SHOW / HIDE
========================================================= */

const password =
    document.getElementById(
        'password'
    );


const passwordToggle =
    document.getElementById(
        'passwordToggle'
    );


if (
    password &&
    passwordToggle
) {

    passwordToggle.addEventListener(
        'click',
        function () {

            const icon =
                this.querySelector('i');


            if (
                password.type ===
                'password'
            ) {

                password.type =
                    'text';


                icon.classList.remove(
                    'fa-eye'
                );


                icon.classList.add(
                    'fa-eye-slash'
                );

            }
            else {

                password.type =
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

}

</script>


</body>

</html>