<?php

session_start();

require_once '../includes/config.php';

/*
|--------------------------------------------------------------------------
| HUMSAFAR RIDER LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}


/*
|--------------------------------------------------------------------------
| IF ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['rider_logged_in']) &&
    $_SESSION['rider_logged_in'] === true
) {
    header("Location: rider-dashboard.php");
    exit;
}


$error = "";

$cnic = "";


/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cnic = trim($_POST["cnic"] ?? "");
    $password = $_POST["password"] ?? "";


    if ($cnic === "" || $password === "") {

        $error = "Please enter your CNIC and password.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | FIND RIDER BY CNIC
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                email,
                phone,
                cnic,
                password,
                vehicle_type,
                bike_number,
                status
            FROM riders
            WHERE cnic = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error = "Database error. Please try again.";

        } else {

            $stmt->bind_param("s", $cnic);

            $stmt->execute();

            $result = $stmt->get_result();

            $rider = $result->fetch_assoc();

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | CHECK RIDER
            |--------------------------------------------------------------------------
            */

            if (!$rider) {

                $error = "Invalid CNIC or password.";

            } elseif (
                !password_verify(
                    $password,
                    $rider["password"]
                )
            ) {

                $error = "Invalid CNIC or password.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | LOGIN SUCCESS
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                $_SESSION["rider_logged_in"] = true;

                $_SESSION["rider_id"] =
                    (int)$rider["id"];

                $_SESSION["rider_name"] =
                    $rider["full_name"];

                $_SESSION["rider_email"] =
                    $rider["email"];

                $_SESSION["rider_phone"] =
                    $rider["phone"];

                $_SESSION["rider_cnic"] =
                    $rider["cnic"];

                $_SESSION["rider_status"] =
                    $rider["status"];

                $_SESSION["rider_vehicle"] =
                    $rider["vehicle_type"];

                $_SESSION["rider_bike_number"] =
                    $rider["bike_number"];


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                header("Location: rider-dashboard.php");
                exit;
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

    <title>
        Rider Login | Humsafar
    </title>


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
                linear-gradient(
                    135deg,
                    #fff8fb 0%,
                    #ffeaf1 50%,
                    #fff8fb 100%
                );

            color: #292929;

            min-height: 100vh;

            display: flex;

            flex-direction: column;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {

            height: 70px;

            background: #ffffff;

            border-top:
                3px solid #ef0038;

            border-bottom:
                1px solid #eeeeee;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 6%;

        }


        .logo {

            display: flex;

            align-items: center;

            gap: 9px;

            color: #ed0038;

            font-size: 23px;

            font-weight: 800;

        }


        .logo i {
            font-size: 22px;
        }


        .back-link {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding:
                9px 15px;

            border:
                1px solid #f1c8d7;

            border-radius: 9px;

            color: #d90035;

            font-size: 13px;

            font-weight: 700;

            transition: .2s ease;

        }


        .back-link:hover {

            background: #fff0f5;

            border-color: #ed0038;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                40px 20px;

        }


        .login-card {

            width: 100%;

            max-width: 430px;

            background: #ffffff;

            border:
                1px solid #f1d9e2;

            border-radius: 22px;

            padding:
                38px 35px;

            box-shadow:
                0 18px 50px
                rgba(180, 35, 85, .12);

        }


        /*
        |--------------------------------------------------------------------------
        | ICON
        |--------------------------------------------------------------------------
        */

        .login-icon {

            width: 72px;

            height: 72px;

            margin:
                0 auto 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 20px;

            color: #ffffff;

            font-size: 29px;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            box-shadow:
                0 10px 25px
                rgba(237, 0, 56, .22);

        }


        /*
        |--------------------------------------------------------------------------
        | HEADING
        |--------------------------------------------------------------------------
        */

        .heading {

            text-align: center;

            margin-bottom: 27px;

        }


        .badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                7px 13px;

            margin-bottom: 12px;

            border-radius: 30px;

            background: #fff0f4;

            color: #df0038;

            font-size: 11px;

            font-weight: 800;

        }


        .heading h1 {

            margin:
                0 0 8px;

            color: #292929;

            font-size: 28px;

            font-weight: 800;

        }


        .heading p {

            margin: 0;

            color: #777777;

            font-size: 13px;

            line-height: 1.6;

        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {

            padding:
                12px 14px;

            margin-bottom:
                19px;

            border-radius: 9px;

            border:
                1px solid #ffc3d1;

            background: #fff0f3;

            color: #a40027;

            font-size: 12.5px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {

            margin-bottom:
                18px;

        }


        .form-group label {

            display: block;

            margin-bottom:
                7px;

            color: #333333;

            font-size: 12.5px;

            font-weight: 700;

        }


        .input-wrapper {

            position: relative;

        }


        .input-wrapper > i {

            position: absolute;

            left: 14px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #e00038;

            font-size: 14px;

            pointer-events: none;

        }


        .form-control {

            width: 100%;

            height: 48px;

            padding:
                0 48px 0 42px;

            border:
                1px solid #dddddd;

            border-radius: 9px;

            outline: none;

            background: #ffffff;

            color: #333333;

            font-size: 13px;

            transition: .2s ease;

        }


        .form-control:focus {

            border-color:
                #ed0038;

            box-shadow:
                0 0 0 3px
                rgba(237, 0, 56, .08);

        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        .password-toggle {

            position: absolute;

            right: 9px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 34px;

            height: 34px;

            border: none;

            background: transparent;

            color: #999999;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 7px;

        }


        .password-toggle:hover {

            color: #ed0038;

            background: #fff0f4;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN BUTTON
        |--------------------------------------------------------------------------
        */

        .login-button {

            width: 100%;

            height: 48px;

            border: none;

            border-radius: 9px;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            color: #ffffff;

            font-size: 13px;

            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 7px 18px
                rgba(237, 0, 56, .18);

            transition: .2s ease;

        }


        .login-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 24px
                rgba(237, 0, 56, .25);

        }


        /*
        |--------------------------------------------------------------------------
        | REGISTER LINK
        |--------------------------------------------------------------------------
        */

        .register-text {

            margin-top:
                21px;

            text-align:
                center;

            color:
                #777777;

            font-size:
                12.5px;

        }


        .register-text a {

            color:
                #e00038;

            font-weight:
                800;

        }


        /*
        |--------------------------------------------------------------------------
        | INFO
        |--------------------------------------------------------------------------
        */

        .security-note {

            margin-top:
                20px;

            padding:
                12px 14px;

            border-radius:
                9px;

            background:
                #faf7f9;

            border:
                1px solid #eee1e8;

            color:
                #777777;

            text-align:
                center;

            font-size:
                11px;

            line-height:
                1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {

            padding:
                17px 20px;

            background:
                #29232a;

            color:
                #aaaaaa;

            text-align:
                center;

            font-size:
                11px;

        }


        .footer strong {

            color:
                #ffffff;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 500px) {

            .header {

                height:
                    62px;

                padding:
                    0 16px;

            }


            .logo {

                font-size:
                    20px;

            }


            .back-link {

                padding:
                    8px 10px;

                font-size:
                    11px;

            }


            .main {

                padding:
                    25px 14px;

            }


            .login-card {

                padding:
                    30px 22px;

                border-radius:
                    18px;

            }


            .login-icon {

                width:
                    64px;

                height:
                    64px;

                font-size:
                    26px;

            }


            .heading h1 {

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

<header class="header">


    <a
        href="../join-humsafar.php"
        class="logo"
    >

        <i class="fas fa-utensils"></i>

        <span>
            Humsafar
        </span>

    </a>


    <a
        href="rider-register.php"
        class="back-link"
    >

        <i class="fas fa-user-plus"></i>

        Register

    </a>


</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <div class="login-card">


        <div class="login-icon">

            <i class="fas fa-motorcycle"></i>

        </div>


        <div class="heading">


            <div class="badge">

                <i class="fas fa-shield-halved"></i>

                RIDER PARTNER

            </div>


            <h1>
                Rider Login
            </h1>


            <p>
                Login to your Humsafar rider account
                using your CNIC and password.
            </p>


        </div>


        <?php if ($error !== ""): ?>

            <div class="error">

                <i class="fas fa-circle-exclamation"></i>

                &nbsp;

                <?php

                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="off"
        >


            <!-- CNIC -->

            <div class="form-group">

                <label for="cnic">
                    CNIC Number
                </label>


                <div class="input-wrapper">

                    <i class="fas fa-id-card"></i>


                    <input
                        type="text"
                        id="cnic"
                        name="cnic"
                        class="form-control"
                        placeholder="XXXXX-XXXXXXX-X"
                        value="<?php echo htmlspecialchars(
                            $cnic,
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
                </label>


                <div class="input-wrapper">

                    <i class="fas fa-lock"></i>


                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Show password"
                    >

                        <i
                            class="fas fa-eye"
                            id="passwordIcon"
                        ></i>

                    </button>


                </div>

            </div>


            <!-- LOGIN -->

            <button
                type="submit"
                class="login-button"
            >

                <i class="fas fa-right-to-bracket"></i>

                &nbsp;

                Login to Rider Account

            </button>


        </form>


        <!-- REGISTER -->

        <div class="register-text">

            Don't have a rider account?

            <a href="rider-register.php">
                Register as Rider
            </a>

        </div>


        <!-- SECURITY -->

        <div class="security-note">

            <i class="fas fa-shield-halved"></i>

            &nbsp;

            Your rider account information is protected
            by Humsafar's secure login system.

        </div>


    </div>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <strong>
        Humsafar
    </strong>

    Food Delivery

    &nbsp;•&nbsp;

    Rider Partner Portal

    &nbsp;•&nbsp;

    © <?php echo date("Y"); ?>

</footer>


<script>

function togglePassword() {

    const input =
        document.getElementById("password");

    const icon =
        document.getElementById("passwordIcon");


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