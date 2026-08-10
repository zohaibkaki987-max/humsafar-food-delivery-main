<?php

require_once 'includes/session.php';
require_once 'includes/config.php';

/* =====================================================
   HUMSAFAR - RESTAURANT OWNER MANAGE RESTAURANT
===================================================== */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}


/* =====================================================
   HELPER
===================================================== */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =====================================================
   FIND LOGGED-IN OWNER
===================================================== */

$owner = null;

$owner_id = 0;

$possible_owner_ids = [
    $_SESSION['restaurant_owner_id'] ?? null,
    $_SESSION['restaurant_user_id'] ?? null,
    $_SESSION['owner_id'] ?? null
];

$possible_email = '';

if (!empty($_SESSION['restaurant_owner_email'])) {
    $possible_email = trim(
        (string)$_SESSION['restaurant_owner_email']
    );
}

if (
    $possible_email === '' &&
    !empty($_SESSION['email'])
) {
    $possible_email = trim(
        (string)$_SESSION['email']
    );
}


/* =====================================================
   FIND BY OWNER ID
===================================================== */

foreach ($possible_owner_ids as $possible_id) {

    if ((int)$possible_id <= 0) {
        continue;
    }

    $test_owner_id = (int)$possible_id;

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status,
            created_at,
            updated_at
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $test_owner_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $owner = $result->fetch_assoc();

        $stmt->close();

        if ($owner) {

            $owner_id =
                (int)$owner['id'];

            break;
        }
    }
}


/* =====================================================
   FIND BY EMAIL
===================================================== */

if (!$owner && $possible_email !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status,
            created_at,
            updated_at
        FROM restaurant_users
        WHERE email = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $possible_email
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $owner = $result->fetch_assoc();

        $stmt->close();

        if ($owner) {

            $owner_id =
                (int)$owner['id'];
        }
    }
}


/* =====================================================
   OWNER NOT FOUND
===================================================== */

if (!$owner) {

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
            Restaurant Owner Account Not Found
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: #fff8fb;
                font-family:
                    "Segoe UI",
                    Tahoma,
                    Geneva,
                    Verdana,
                    sans-serif;
            }

            .error-box {
                width: 100%;
                max-width: 520px;
                background: #fff;
                border-radius: 20px;
                padding: 40px 30px;
                text-align: center;
                box-shadow:
                    0 15px 50px rgba(0,0,0,.08);
                border: 1px solid #eee4e8;
            }

            .error-icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 20px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fff0f3;
                color: #e00038;
                font-size: 28px;
                font-weight: 900;
            }

            h1 {
                margin: 0 0 10px;
                font-size: 24px;
            }

            p {
                color: #777;
                line-height: 1.7;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                border-radius: 9px;
                background: #e00038;
                color: #fff;
                text-decoration: none;
                font-weight: 700;
            }

            .btn.secondary {
                background: #333;
                margin-left: 5px;
            }

        </style>

    </head>

    <body>

        <div class="error-box">

            <div class="error-icon">
                !
            </div>

            <h1>
                Restaurant Owner Account Not Found
            </h1>

            <p>
                Your restaurant owner account could not
                be found.
            </p>

            <a
                href="restaurant-owner-login.php"
                class="btn"
            >
                Login Again
            </a>

            <a
                href="restaurant-owner-dashboard.php"
                class="btn secondary"
            >
                Dashboard
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/* =====================================================
   VARIABLES
===================================================== */

$success_message = '';

$error_message = '';


$restaurant_name =
    $owner['restaurant_name'] ?? '';

$full_name =
    $owner['full_name'] ?? '';

$email =
    $owner['email'] ?? '';

$phone =
    $owner['phone'] ?? '';

$status =
    strtolower(
        trim(
            (string)(
                $owner['status'] ?? 'pending'
            )
        )
    );


/* =====================================================
   UPDATE RESTAURANT
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_restaurant'])
) {

    $new_restaurant_name =
        trim(
            (string)(
                $_POST['restaurant_name'] ?? ''
            )
        );

    $new_full_name =
        trim(
            (string)(
                $_POST['full_name'] ?? ''
            )
        );

    $new_phone =
        trim(
            (string)(
                $_POST['phone'] ?? ''
            )
        );


    /* -----------------------------------------------
       VALIDATION
    ------------------------------------------------ */

    if ($new_restaurant_name === '') {

        $error_message =
            'Restaurant name is required.';

    } elseif ($new_full_name === '') {

        $error_message =
            'Owner name is required.';

    } elseif ($new_phone === '') {

        $error_message =
            'Phone number is required.';

    } else {

        /* -------------------------------------------
           UPDATE DATABASE
        -------------------------------------------- */

        $stmt = $conn->prepare("
            UPDATE restaurant_users
            SET
                restaurant_name = ?,
                full_name = ?,
                phone = ?
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error_message =
                'Unable to prepare database query.';

        } else {

            $stmt->bind_param(
                "sssi",
                $new_restaurant_name,
                $new_full_name,
                $new_phone,
                $owner_id
            );


            if ($stmt->execute()) {

                $success_message =
                    'Restaurant information updated successfully.';

                $restaurant_name =
                    $new_restaurant_name;

                $full_name =
                    $new_full_name;

                $phone =
                    $new_phone;


                /*
                 * Keep session information updated
                 * where these session variables exist.
                 */

                $_SESSION['restaurant_owner_name'] =
                    $new_full_name;

                $_SESSION['full_name'] =
                    $new_full_name;

                $_SESSION['name'] =
                    $new_full_name;

            } else {

                $error_message =
                    'Unable to update restaurant information.';
            }

            $stmt->close();
        }
    }
}


/* =====================================================
   REFRESH OWNER DATA
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        restaurant_name,
        full_name,
        email,
        phone,
        status,
        created_at,
        updated_at
    FROM restaurant_users
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $owner_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $fresh_owner =
        $result->fetch_assoc();

    $stmt->close();

    if ($fresh_owner) {

        $owner =
            $fresh_owner;

        $restaurant_name =
            $owner['restaurant_name'] ?? '';

        $full_name =
            $owner['full_name'] ?? '';

        $email =
            $owner['email'] ?? '';

        $phone =
            $owner['phone'] ?? '';

        $status =
            strtolower(
                trim(
                    (string)(
                        $owner['status'] ?? 'pending'
                    )
                )
            );
    }
}


/* =====================================================
   STATUS TEXT
===================================================== */

$status_text =
    'Waiting for Approval';

$status_class =
    'pending';

if ($status === 'active') {

    $status_text =
        'Approved';

    $status_class =
        'active';

} elseif ($status === 'blocked') {

    $status_text =
        'Blocked';

    $status_class =
        'blocked';

} elseif ($status === 'inactive') {

    $status_text =
        'Inactive';

    $status_class =
        'inactive';
}


/* =====================================================
   INITIAL
===================================================== */

$initial =
    strtoupper(
        substr(
            $full_name !== ''
                ? $full_name
                : 'R',
            0,
            1
        )
    );


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
        Manage Restaurant - Humsafar
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background:
                #fff8fb;

            color:
                #292929;

            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
        }


        a {
            text-decoration:
                none;
        }


        .page {

            min-height:
                100vh;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .topbar {

            height:
                72px;

            background:
                #ffffff;

            border-bottom:
                1px solid #eee4e8;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 5%;

            position:
                sticky;

            top:
                0;

            z-index:
                100;
        }


        .logo {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            color:
                #e00038;

            font-size:
                23px;

            font-weight:
                900;
        }


        .logo i {

            font-size:
                25px;
        }


        .top-actions {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;
        }


        .back-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                10px 15px;

            border-radius:
                9px;

            color:
                #555;

            background:
                #f5f5f5;

            font-size:
                12px;

            font-weight:
                800;
        }


        .back-btn:hover {

            background:
                #e00038;

            color:
                #ffffff;
        }


        .logout-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                10px 15px;

            border-radius:
                9px;

            color:
                #e00038;

            background:
                #fff0f3;

            font-size:
                12px;

            font-weight:
                800;
        }


        .logout-btn:hover {

            color:
                #ffffff;

            background:
                #e00038;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .container {

            width:
                min(1050px, 92%);

            margin:
                0 auto;

            padding:
                35px 0 55px;
        }


        .heading {

            margin-bottom:
                25px;
        }


        .heading h1 {

            margin:
                0 0 7px;

            font-size:
                30px;
        }


        .heading p {

            margin:
                0;

            color:
                #777;

            font-size:
                14px;
        }


        /* =====================================================
           RESTAURANT PROFILE
        ===================================================== */

        .profile-card {

            background:
                linear-gradient(
                    135deg,
                    #e00038,
                    #f94f87
                );

            color:
                #ffffff;

            border-radius:
                18px;

            padding:
                27px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                25px;

            box-shadow:
                0 15px 35px
                rgba(224,0,56,.16);
        }


        .profile-left {

            display:
                flex;

            align-items:
                center;

            gap:
                17px;
        }


        .profile-icon {

            width:
                68px;

            height:
                68px;

            flex-shrink:
                0;

            border-radius:
                16px;

            background:
                rgba(255,255,255,.18);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                28px;
        }


        .profile-info h2 {

            margin:
                0 0 6px;

            font-size:
                24px;
        }


        .profile-info p {

            margin:
                4px 0;

            font-size:
                13px;

            opacity:
                .92;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            padding:
                10px 15px;

            border-radius:
                30px;

            font-size:
                12px;

            font-weight:
                900;

            white-space:
                nowrap;
        }


        .status.pending {

            background:
                #fff3d8;

            color:
                #a66b00;
        }


        .status.active {

            background:
                #e5f8ed;

            color:
                #16763e;
        }


        .status.blocked {

            background:
                #ffe7e7;

            color:
                #c32626;
        }


        .status.inactive {

            background:
                #eeeeee;

            color:
                #666;
        }


        /* =====================================================
           MESSAGES
        ===================================================== */

        .message {

            padding:
                14px 17px;

            border-radius:
                10px;

            margin-bottom:
                20px;

            font-size:
                13px;

            font-weight:
                700;
        }


        .success {

            background:
                #eaf9f0;

            color:
                #16763e;

            border:
                1px solid #ccefd9;
        }


        .error {

            background:
                #fff0f0;

            color:
                #c32626;

            border:
                1px solid #f5cccc;
        }


        /* =====================================================
           FORM CARD
        ===================================================== */

        .form-card {

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-radius:
                18px;

            box-shadow:
                0 10px 30px
                rgba(0,0,0,.04);

            overflow:
                hidden;
        }


        .form-header {

            padding:
                22px 25px;

            border-bottom:
                1px solid #eee4e8;
        }


        .form-header h2 {

            margin:
                0 0 5px;

            font-size:
                21px;
        }


        .form-header p {

            margin:
                0;

            color:
                #888;

            font-size:
                12px;

            line-height:
                1.6;
        }


        .form-body {

            padding:
                27px 25px;
        }


        .form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap:
                20px;
        }


        .field {

            display:
                flex;

            flex-direction:
                column;
        }


        .field.full {

            grid-column:
                1 / -1;
        }


        .field label {

            margin-bottom:
                8px;

            font-size:
                12px;

            font-weight:
                800;

            color:
                #555;
        }


        .field input {

            width:
                100%;

            height:
                46px;

            border:
                1px solid #ddd;

            border-radius:
                9px;

            padding:
                0 13px;

            font-size:
                13px;

            outline:
                none;

            background:
                #ffffff;

            transition:
                .2s ease;
        }


        .field input:focus {

            border-color:
                #e00038;

            box-shadow:
                0 0 0 3px
                rgba(224,0,56,.08);
        }


        .field input[readonly] {

            background:
                #f7f7f7;

            color:
                #777;

            cursor:
                not-allowed;
        }


        .field small {

            margin-top:
                6px;

            color:
                #999;

            font-size:
                11px;

            line-height:
                1.5;
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .form-actions {

            display:
                flex;

            justify-content:
                flex-end;

            gap:
                10px;

            margin-top:
                25px;

            padding-top:
                22px;

            border-top:
                1px solid #eee4e8;
        }


        .save-btn {

            border:
                none;

            cursor:
                pointer;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                13px 22px;

            border-radius:
                9px;

            background:
                #e00038;

            color:
                #ffffff;

            font-size:
                13px;

            font-weight:
                900;
        }


        .save-btn:hover {

            background:
                #c90032;
        }


        /* =====================================================
           ACCOUNT DETAILS
        ===================================================== */

        .details-card {

            margin-top:
                25px;

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-radius:
                18px;

            overflow:
                hidden;

            box-shadow:
                0 10px 30px
                rgba(0,0,0,.04);
        }


        .details-header {

            padding:
                20px 25px;

            border-bottom:
                1px solid #eee4e8;

            font-size:
                19px;

            font-weight:
                900;
        }


        .detail-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                16px 25px;

            border-bottom:
                1px solid #f1edf0;

            font-size:
                13px;
        }


        .detail-row:last-child {

            border-bottom:
                none;
        }


        .detail-label {

            color:
                #888;
        }


        .detail-value {

            color:
                #333;

            font-weight:
                800;

            text-align:
                right;

            word-break:
                break-word;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {

            text-align:
                center;

            padding:
                22px;

            background:
                #29232a;

            color:
                #aaa;

            font-size:
                12px;
        }


        footer strong {

            color:
                #ffffff;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 700px) {

            .topbar {

                height:
                    64px;

                padding:
                    0 16px;
            }


            .logo {

                font-size:
                    19px;
            }


            .back-btn span,
            .logout-btn span {

                display:
                    none;
            }


            .back-btn,
            .logout-btn {

                padding:
                    10px 11px;
            }


            .container {

                width:
                    94%;

                padding:
                    25px 0 40px;
            }


            .heading h1 {

                font-size:
                    25px;
            }


            .profile-card {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .profile-left {

                width:
                    100%;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .field.full {

                grid-column:
                    auto;
            }


            .form-body {

                padding:
                    22px 18px;
            }


            .form-header {

                padding:
                    20px 18px;
            }


            .detail-row {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap:
                    5px;

                padding:
                    15px 18px;
            }


            .detail-value {

                text-align:
                    left;
            }

        }

    </style>

</head>


<body>


<div class="page">


    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <header class="topbar">

        <a
            href="restaurant-owner-dashboard.php"
            class="logo"
        >

            <i class="fas fa-utensils"></i>

            <span>
                Humsafar
            </span>

        </a>


        <div class="top-actions">

            <a
                href="restaurant-owner-dashboard.php"
                class="back-btn"
            >

                <i class="fas fa-arrow-left"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="logout.php"
                class="logout-btn"
            >

                <i class="fas fa-right-from-bracket"></i>

                <span>
                    Logout
                </span>

            </a>

        </div>

    </header>



    <!-- =====================================================
         MAIN
    ===================================================== -->

    <main class="container">


        <!-- HEADING -->

        <section class="heading">

            <h1>
                Manage Restaurant
            </h1>

            <p>
                Update your restaurant and owner information
                from one place.
            </p>

        </section>



        <!-- =================================================
             RESTAURANT PROFILE
        ================================================= -->

        <section class="profile-card">

            <div class="profile-left">

                <div class="profile-icon">

                    <i class="fas fa-store"></i>

                </div>


                <div class="profile-info">

                    <h2>
                        <?= h($restaurant_name) ?>
                    </h2>

                    <p>
                        <i class="fas fa-user"></i>

                        <?= h($full_name) ?>

                    </p>

                    <p>
                        <i class="fas fa-phone"></i>

                        <?= h($phone) ?>

                    </p>

                </div>

            </div>


            <div
                class="
                    status
                    <?= h($status_class) ?>
                "
            >

                <?php if ($status === 'active'): ?>

                    <i class="fas fa-circle-check"></i>

                <?php elseif ($status === 'blocked'): ?>

                    <i class="fas fa-ban"></i>

                <?php else: ?>

                    <i class="fas fa-clock"></i>

                <?php endif; ?>

                <?= h($status_text) ?>

            </div>

        </section>



        <!-- =================================================
             MESSAGES
        ================================================= -->

        <?php if ($success_message !== ''): ?>

            <div class="message success">

                <i class="fas fa-circle-check"></i>

                <?= h($success_message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error_message !== ''): ?>

            <div class="message error">

                <i class="fas fa-circle-exclamation"></i>

                <?= h($error_message) ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             EDIT FORM
        ================================================= -->

        <section class="form-card">


            <div class="form-header">

                <h2>
                    Restaurant Information
                </h2>

                <p>
                    Update the information below and click
                    Save Changes.
                </p>

            </div>


            <div class="form-body">

                <form
                    method="POST"
                    action=""
                >

                    <div class="form-grid">


                        <!-- RESTAURANT NAME -->

                        <div class="field full">

                            <label
                                for="restaurant_name"
                            >
                                Restaurant Name
                            </label>

                            <input
                                type="text"
                                id="restaurant_name"
                                name="restaurant_name"
                                value="<?= h($restaurant_name) ?>"
                                maxlength="100"
                                required
                            >

                        </div>



                        <!-- OWNER NAME -->

                        <div class="field">

                            <label
                                for="full_name"
                            >
                                Owner Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?= h($full_name) ?>"
                                maxlength="100"
                                required
                            >

                        </div>



                        <!-- PHONE -->

                        <div class="field">

                            <label
                                for="phone"
                            >
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?= h($phone) ?>"
                                maxlength="30"
                                required
                            >

                        </div>



                        <!-- EMAIL -->

                        <div class="field full">

                            <label
                                for="email"
                            >
                                Login Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?= h($email) ?>"
                                readonly
                            >

                            <small>
                                Your login email cannot be
                                changed from this page.
                            </small>

                        </div>


                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            name="update_restaurant"
                            value="1"
                            class="save-btn"
                        >

                            <i class="fas fa-save"></i>

                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

        </section>



        <!-- =================================================
             ACCOUNT DETAILS
        ================================================= -->

        <section class="details-card">


            <div class="details-header">

                <i class="fas fa-circle-info"></i>

                Account Details

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Restaurant Owner ID
                </span>

                <span class="detail-value">
                    #<?= (int)$owner_id ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Restaurant
                </span>

                <span class="detail-value">
                    <?= h($restaurant_name) ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Owner
                </span>

                <span class="detail-value">
                    <?= h($full_name) ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Email
                </span>

                <span class="detail-value">
                    <?= h($email) ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Phone
                </span>

                <span class="detail-value">
                    <?= h($phone) ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Account Status
                </span>

                <span class="detail-value">

                    <?= h($status_text) ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Last Updated
                </span>

                <span class="detail-value">

                    <?php

                    if (!empty($owner['updated_at'])) {

                        echo h(
                            date(
                                'd M Y, h:i A',
                                strtotime(
                                    $owner['updated_at']
                                )
                            )
                        );

                    } else {

                        echo 'N/A';

                    }

                    ?>

                </span>

            </div>


        </section>


    </main>



    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer>

        <strong>
            Humsafar
        </strong>

        Food Delivery

        &nbsp;•&nbsp;

        Restaurant Owner Portal

        &nbsp;•&nbsp;

        © <?= date('Y') ?>

    </footer>


</div>


</body>

</html>