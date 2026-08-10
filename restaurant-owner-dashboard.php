<?php

require_once 'includes/session.php';
require_once 'includes/config.php';

/* =====================================================
   RESTAURANT OWNER DASHBOARD
   Humsafar Food Delivery
===================================================== */

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
   CHECK DATABASE CONNECTION
===================================================== */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}


/* =====================================================
   FIND LOGGED-IN RESTAURANT OWNER
===================================================== */

$owner = null;

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
   SEARCH BY RESTAURANT USER ID
===================================================== */

foreach ($possible_owner_ids as $possible_id) {

    if ((int)$possible_id <= 0) {
        continue;
    }

    $owner_id = (int)$possible_id;

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

        $result = $stmt->get_result();

        $owner = $result->fetch_assoc();

        $stmt->close();

        if ($owner) {
            break;
        }
    }
}


/* =====================================================
   SEARCH BY EMAIL
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
    }
}


/* =====================================================
   SEARCH THROUGH NORMAL USER SESSION
===================================================== */

if (!$owner && !empty($_SESSION['user_id'])) {

    $session_user_id = (int)$_SESSION['user_id'];

    if ($session_user_id > 0) {

        /*
         * First get the email from users table.
         * This keeps the customer users table separate
         * from restaurant_users.
         */

        $stmt = $conn->prepare("
            SELECT
                email,
                full_name
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $session_user_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $user_row = $result->fetch_assoc();

            $stmt->close();

            if ($user_row) {

                $user_email =
                    trim(
                        (string)($user_row['email'] ?? '')
                    );

                if ($user_email !== '') {

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
                            $user_email
                        );

                        $stmt->execute();

                        $result =
                            $stmt->get_result();

                        $owner =
                            $result->fetch_assoc();

                        $stmt->close();
                    }
                }
            }
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
            Restaurant Owner Dashboard - Humsafar
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
                font-family:
                    "Segoe UI",
                    Tahoma,
                    Geneva,
                    Verdana,
                    sans-serif;
                background: #fff8fb;
                color: #292929;
                padding: 20px;
            }

            .error-box {
                width: 100%;
                max-width: 520px;
                background: #ffffff;
                border-radius: 20px;
                padding: 40px 30px;
                text-align: center;
                box-shadow:
                    0 15px 50px
                    rgba(0,0,0,.08);
                border: 1px solid #f1e5e9;
            }

            .error-icon {
                width: 75px;
                height: 75px;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fff0f3;
                color: #e00038;
                font-size: 30px;
            }

            h1 {
                margin: 0 0 10px;
                font-size: 25px;
            }

            p {
                color: #777;
                line-height: 1.7;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 13px 22px;
                background: #e00038;
                color: #ffffff;
                border-radius: 10px;
                text-decoration: none;
                font-weight: 700;
            }

            .btn.secondary {
                background: #333;
                margin-left: 6px;
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
                We could not find your restaurant owner
                account in the restaurant_users table.
            </p>

            <p>
                Please login again or contact the Humsafar
                administrator.
            </p>

            <a
                href="restaurant-owner-login.php"
                class="btn"
            >
                Login Again
            </a>

            <a
                href="join-humsafar.php"
                class="btn secondary"
            >
                Back
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/* =====================================================
   OWNER ID
===================================================== */

$owner_id =
    (int)$owner['id'];


/* =====================================================
   PAYMENT STATUS SUPPORT
===================================================== */

/*
 * We first check whether payment_status exists.
 * This prevents the dashboard from crashing if the
 * column is not available.
 */

$has_payment_status = false;

$column_result = $conn->query("
    SHOW COLUMNS
    FROM restaurant_users
    LIKE 'payment_status'
");

if (
    $column_result &&
    $column_result->num_rows > 0
) {
    $has_payment_status = true;
}


/* =====================================================
   PAYMENT VARIABLES
===================================================== */

$payment_status = 'unpaid';

$transaction_id = '';

$payment_amount = 0;

$payment_date = null;


/* =====================================================
   GET PAYMENT INFORMATION
===================================================== */

if ($has_payment_status) {

    /*
     * payment_status exists.
     * We read it separately to avoid depending on
     * optional payment columns.
     */

    $stmt = $conn->prepare("
        SELECT
            payment_status
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

        $payment_row =
            $result->fetch_assoc();

        $stmt->close();

        if ($payment_row) {

            $payment_status =
                strtolower(
                    trim(
                        (string)(
                            $payment_row['payment_status']
                            ?? 'unpaid'
                        )
                    )
                );
        }
    }
}


/* =====================================================
   NORMALIZE PAYMENT STATUS
===================================================== */

$allowed_payment_statuses = [
    'unpaid',
    'pending',
    'submitted',
    'paid',
    'verified',
    'rejected'
];

if (
    !in_array(
        $payment_status,
        $allowed_payment_statuses,
        true
    )
) {
    $payment_status = 'unpaid';
}


/* =====================================================
   OWNER STATUS
===================================================== */

$owner_status =
    strtolower(
        trim(
            (string)(
                $owner['status']
                ?? 'pending'
            )
        )
    );


/* =====================================================
   DASHBOARD MESSAGE
===================================================== */

$dashboard_title =
    "Welcome, " .
    ($owner['full_name'] ?? 'Restaurant Owner');

$dashboard_message =
    "Manage your restaurant from your Humsafar partner dashboard.";


/* =====================================================
   DETERMINE APPROVAL STATE
===================================================== */

$approval_state = 'pending';


if ($owner_status === 'blocked') {

    $approval_state = 'blocked';

} elseif ($owner_status === 'inactive') {

    $approval_state = 'inactive';

} elseif ($owner_status === 'active') {

    $approval_state = 'active';

} else {

    $approval_state = 'pending';
}


/* =====================================================
   PAYMENT LABEL
===================================================== */

$payment_label =
    'Payment Required';

$payment_class =
    'payment-unpaid';

$payment_icon =
    'fa-credit-card';

if ($payment_status === 'pending') {

    $payment_label =
        'Payment Verification Pending';

    $payment_class =
        'payment-pending';

    $payment_icon =
        'fa-clock';

} elseif ($payment_status === 'submitted') {

    $payment_label =
        'Payment Submitted';

    $payment_class =
        'payment-pending';

    $payment_icon =
        'fa-hourglass-half';

} elseif (
    $payment_status === 'paid' ||
    $payment_status === 'verified'
) {

    $payment_label =
        'Payment Verified';

    $payment_class =
        'payment-paid';

    $payment_icon =
        'fa-circle-check';

} elseif ($payment_status === 'rejected') {

    $payment_label =
        'Payment Rejected';

    $payment_class =
        'payment-rejected';

    $payment_icon =
        'fa-circle-xmark';
}


/* =====================================================
   APPROVAL LABEL
===================================================== */

$approval_label =
    'Waiting for Admin Approval';

$approval_icon =
    'fa-clock';

$approval_class =
    'approval-pending';


if ($approval_state === 'active') {

    $approval_label =
        'Restaurant Approved';

    $approval_icon =
        'fa-circle-check';

    $approval_class =
        'approval-active';

} elseif ($approval_state === 'blocked') {

    $approval_label =
        'Restaurant Blocked';

    $approval_icon =
        'fa-ban';

    $approval_class =
        'approval-blocked';

} elseif ($approval_state === 'inactive') {

    $approval_label =
        'Restaurant Inactive';

    $approval_icon =
        'fa-eye-slash';

    $approval_class =
        'approval-inactive';
}


/* =====================================================
   DASHBOARD CARDS
===================================================== */

$orders_count = 0;

$menu_count = 0;

$restaurant_count = 0;


/*
 * The current restaurant management tables are not
 * assumed here because their exact structure was not
 * confirmed in the existing project files.
 *
 * Therefore the dashboard safely shows the account
 * status without generating SQL errors for unknown
 * tables.
 */


/* =====================================================
   CURRENT DATE
===================================================== */

$current_date =
    date('d M Y');


/* =====================================================
   OWNER DISPLAY NAME
===================================================== */

$owner_name =
    $owner['full_name']
    ?? 'Restaurant Owner';

$restaurant_name =
    $owner['restaurant_name']
    ?? 'Your Restaurant';

$owner_email =
    $owner['email']
    ?? '';

$owner_phone =
    $owner['phone']
    ?? '';

$registered_date =
    !empty($owner['created_at'])
        ? date(
            'd M Y',
            strtotime(
                $owner['created_at']
            )
        )
        : 'N/A';


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
        Restaurant Owner Dashboard - Humsafar
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- EXISTING HUMSAFAR CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/css_header.css"
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


        .dashboard-page {

            min-height:
                100vh;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .dashboard-header {

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


        .dashboard-logo {

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


        .dashboard-logo i {

            font-size:
                25px;
        }


        .header-right {

            display:
                flex;

            align-items:
                center;

            gap:
                16px;
        }


        .owner-mini {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            color:
                #555;

            font-size:
                13px;

            font-weight:
                700;
        }


        .owner-avatar {

            width:
                40px;

            height:
                40px;

            border-radius:
                50%;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            color:
                #ffffff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-weight:
                900;
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

            background:
                #fff0f3;

            color:
                #e00038;

            font-size:
                12px;

            font-weight:
                800;
        }


        .logout-btn:hover {

            background:
                #e00038;

            color:
                #ffffff;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .dashboard-main {

            width:
                min(1200px, 92%);

            margin:
                0 auto;

            padding:
                35px 0 55px;
        }


        .welcome-section {

            margin-bottom:
                25px;
        }


        .welcome-section h1 {

            margin:
                0 0 7px;

            font-size:
                30px;

            line-height:
                1.2;
        }


        .welcome-section p {

            margin:
                0;

            color:
                #777;

            font-size:
                14px;
        }


        /* =====================================================
           RESTAURANT CARD
        ===================================================== */

        .restaurant-card {

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

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;

            box-shadow:
                0 15px 35px
                rgba(224,0,56,.18);

            margin-bottom:
                25px;
        }


        .restaurant-info h2 {

            margin:
                0 0 7px;

            font-size:
                25px;
        }


        .restaurant-info p {

            margin:
                4px 0;

            font-size:
                13px;

            opacity:
                .92;
        }


        .restaurant-icon {

            width:
                70px;

            height:
                70px;

            flex-shrink:
                0;

            border-radius:
                17px;

            background:
                rgba(255,255,255,.18);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                30px;
        }


        /* =====================================================
           STATUS GRID
        ===================================================== */

        .status-grid {

            display:
                grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap:
                20px;

            margin-bottom:
                25px;
        }


        .status-card {

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-radius:
                16px;

            padding:
                22px;

            display:
                flex;

            align-items:
                center;

            gap:
                16px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.035);
        }


        .status-icon {

            width:
                52px;

            height:
                52px;

            flex-shrink:
                0;

            border-radius:
                13px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                20px;
        }


        .status-card h3 {

            margin:
                0 0 5px;

            font-size:
                12px;

            color:
                #888;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;
        }


        .status-card strong {

            display:
                block;

            font-size:
                17px;
        }


        /* =====================================================
           STATUS COLORS
        ===================================================== */

        .payment-unpaid
        .status-icon {

            background:
                #fff1f3;

            color:
                #e00038;
        }


        .payment-pending
        .status-icon {

            background:
                #fff7e6;

            color:
                #d88a00;
        }


        .payment-paid
        .status-icon {

            background:
                #eaf9f0;

            color:
                #168447;
        }


        .payment-rejected
        .status-icon {

            background:
                #fff0f0;

            color:
                #d52c2c;
        }


        .approval-pending
        .status-icon {

            background:
                #fff7e6;

            color:
                #d88a00;
        }


        .approval-active
        .status-icon {

            background:
                #eaf9f0;

            color:
                #168447;
        }


        .approval-blocked
        .status-icon {

            background:
                #fff0f0;

            color:
                #d52c2c;
        }


        .approval-inactive
        .status-icon {

            background:
                #f1f1f1;

            color:
                #777;
        }


        /* =====================================================
           PAYMENT NOTICE
        ===================================================== */

        .payment-notice {

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-left:
                5px solid #e00038;

            border-radius:
                15px;

            padding:
                24px;

            margin-bottom:
                25px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.035);
        }


        .payment-notice h2 {

            margin:
                0 0 8px;

            font-size:
                21px;
        }


        .payment-notice p {

            margin:
                6px 0;

            color:
                #777;

            line-height:
                1.7;

            font-size:
                13px;
        }


        .payment-button {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            margin-top:
                12px;

            padding:
                12px 19px;

            background:
                #e00038;

            color:
                #ffffff;

            border-radius:
                9px;

            font-size:
                13px;

            font-weight:
                800;
        }


        .payment-button:hover {

            background:
                #c90032;
        }


        .success-payment {

            border-left-color:
                #168447;
        }


        .success-payment .payment-button {

            background:
                #168447;
        }


        .waiting-payment {

            border-left-color:
                #d88a00;
        }


        .waiting-payment .payment-button {

            background:
                #555;
        }


        /* =====================================================
           APPROVAL MESSAGE
        ===================================================== */

        .approval-box {

            background:
                #ffffff;

            border-radius:
                16px;

            padding:
                25px;

            margin-bottom:
                25px;

            border:
                1px solid #eee4e8;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.035);
        }


        .approval-box h2 {

            margin:
                0 0 8px;

            font-size:
                20px;
        }


        .approval-box p {

            margin:
                0;

            color:
                #777;

            font-size:
                13px;

            line-height:
                1.7;
        }


        .approval-active-box {

            border-left:
                5px solid #168447;
        }


        .approval-pending-box {

            border-left:
                5px solid #d88a00;
        }


        .approval-blocked-box {

            border-left:
                5px solid #d52c2c;
        }


        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .section-title {

            margin:
                0 0 16px;

            font-size:
                21px;
        }


        .quick-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                18px;

            margin-bottom:
                30px;
        }


        .quick-card {

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-radius:
                15px;

            padding:
                23px;

            transition:
                .2s ease;

            box-shadow:
                0 7px 22px
                rgba(0,0,0,.035);
        }


        .quick-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 12px 30px
                rgba(0,0,0,.07);
        }


        .quick-icon {

            width:
                48px;

            height:
                48px;

            border-radius:
                12px;

            background:
                #fff0f3;

            color:
                #e00038;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                19px;

            margin-bottom:
                15px;
        }


        .quick-card h3 {

            margin:
                0 0 7px;

            font-size:
                16px;
        }


        .quick-card p {

            margin:
                0;

            color:
                #888;

            font-size:
                12px;

            line-height:
                1.6;
        }


        .quick-card.disabled {

            opacity:
                .55;

            cursor:
                not-allowed;
        }


        /* =====================================================
           ACCOUNT INFORMATION
        ===================================================== */

        .account-card {

            background:
                #ffffff;

            border:
                1px solid #eee4e8;

            border-radius:
                16px;

            overflow:
                hidden;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.035);
        }


        .account-header {

            padding:
                20px 22px;

            border-bottom:
                1px solid #eee4e8;

            font-size:
                18px;

            font-weight:
                800;
        }


        .account-row {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                20px;

            padding:
                16px 22px;

            border-bottom:
                1px solid #f1edf0;

            font-size:
                13px;
        }


        .account-row:last-child {

            border-bottom:
                none;
        }


        .account-label {

            color:
                #888;
        }


        .account-value {

            color:
                #333;

            font-weight:
                700;

            text-align:
                right;

            word-break:
                break-word;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .dashboard-footer {

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


        .dashboard-footer strong {

            color:
                #ffffff;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            .status-grid {

                grid-template-columns:
                    1fr;
            }

            .quick-grid {

                grid-template-columns:
                    1fr 1fr;
            }

            .restaurant-card {

                align-items:
                    flex-start;
            }

        }


        @media (max-width: 600px) {

            .dashboard-header {

                height:
                    64px;

                padding:
                    0 18px;
            }


            .dashboard-logo {

                font-size:
                    19px;
            }


            .owner-mini span {

                display:
                    none;
            }


            .logout-btn {

                padding:
                    9px 10px;
            }


            .logout-btn span {

                display:
                    none;
            }


            .dashboard-main {

                width:
                    94%;

                padding:
                    25px 0 40px;
            }


            .welcome-section h1 {

                font-size:
                    25px;
            }


            .restaurant-card {

                padding:
                    22px;

                flex-direction:
                    column;
            }


            .restaurant-icon {

                width:
                    55px;

                height:
                    55px;
            }


            .quick-grid {

                grid-template-columns:
                    1fr;
            }


            .account-row {

                flex-direction:
                    column;

                gap:
                    5px;
            }


            .account-value {

                text-align:
                    left;
            }

        }

    </style>

</head>


<body>


<div class="dashboard-page">


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="dashboard-header">

        <a
            href="restaurant-owner-dashboard.php"
            class="dashboard-logo"
        >

            <i class="fas fa-utensils"></i>

            <span>
                Humsafar
            </span>

        </a>


        <div class="header-right">

            <div class="owner-mini">

                <div class="owner-avatar">

                    <?= h(
                        strtoupper(
                            substr(
                                $owner_name,
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>

                <span>
                    <?= h($owner_name) ?>
                </span>

            </div>


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

    <main class="dashboard-main">


        <!-- WELCOME -->

        <section class="welcome-section">

            <h1>
                <?= h($dashboard_title) ?>
            </h1>

            <p>
                <?= h($dashboard_message) ?>
            </p>

        </section>



        <!-- =================================================
             RESTAURANT
        ================================================= -->

        <section class="restaurant-card">

            <div class="restaurant-info">

                <h2>
                    <?= h($restaurant_name) ?>
                </h2>

                <p>
                    <i class="fas fa-user"></i>
                    Owner:
                    <?= h($owner_name) ?>
                </p>

                <p>
                    <i class="fas fa-calendar"></i>
                    Registered:
                    <?= h($registered_date) ?>
                </p>

            </div>


            <div class="restaurant-icon">

                <i class="fas fa-store"></i>

            </div>

        </section>



        <!-- =================================================
             STATUS
        ================================================= -->

        <div class="status-grid">


            <!-- PAYMENT STATUS -->

            <div
                class="
                    status-card
                    <?= h($payment_class) ?>
                "
            >

                <div class="status-icon">

                    <i
                        class="
                            fas
                            <?= h($payment_icon) ?>
                        "
                    ></i>

                </div>


                <div>

                    <h3>
                        Payment Status
                    </h3>

                    <strong>
                        <?= h($payment_label) ?>
                    </strong>

                </div>

            </div>



            <!-- APPROVAL STATUS -->

            <div
                class="
                    status-card
                    <?= h($approval_class) ?>
                "
            >

                <div class="status-icon">

                    <i
                        class="
                            fas
                            <?= h($approval_icon) ?>
                        "
                    ></i>

                </div>


                <div>

                    <h3>
                        Restaurant Status
                    </h3>

                    <strong>
                        <?= h($approval_label) ?>
                    </strong>

                </div>

            </div>

        </div>



        <!-- =================================================
             PAYMENT AREA
        ================================================= -->

        <?php if (!$has_payment_status): ?>

            <section class="payment-notice">

                <h2>
                    <i class="fas fa-credit-card"></i>
                    Partner Payment
                </h2>

                <p>
                    Your restaurant owner account is ready,
                    but the payment status system has not yet
                    been connected to your database.
                </p>

                <p>
                    Once the payment_status field is connected,
                    you will be able to submit your payment
                    information from the payment page.
                </p>

            </section>


        <?php elseif (
            $payment_status === 'unpaid'
        ): ?>

            <section class="payment-notice">

                <h2>
                    <i class="fas fa-credit-card"></i>
                    Restaurant Partner Fee
                </h2>

                <p>
                    Your restaurant registration is received.
                    To continue the approval process, please
                    submit your partner fee payment.
                </p>

                <p>
                    After submitting the payment, the Humsafar
                    administrator will verify it.
                </p>

                <a
                    href="restaurant-owner-payment.php"
                    class="payment-button"
                >

                    <i class="fas fa-arrow-right"></i>

                    Submit Payment

                </a>

            </section>


        <?php elseif (
            $payment_status === 'rejected'
        ): ?>

            <section class="payment-notice">

                <h2>
                    <i class="fas fa-circle-xmark"></i>
                    Payment Rejected
                </h2>

                <p>
                    Your submitted payment could not be
                    verified by the Humsafar administrator.
                </p>

                <p>
                    Please submit your payment again with
                    the correct transaction details.
                </p>

                <a
                    href="restaurant-owner-payment.php"
                    class="payment-button"
                >

                    <i class="fas fa-rotate-right"></i>

                    Submit Payment Again

                </a>

            </section>


        <?php elseif (
            $payment_status === 'pending' ||
            $payment_status === 'submitted'
        ): ?>

            <section
                class="
                    payment-notice
                    waiting-payment
                "
            >

                <h2>
                    <i class="fas fa-clock"></i>
                    Payment Verification Pending
                </h2>

                <p>
                    Your payment information has been
                    submitted successfully.
                </p>

                <p>
                    Please wait while the Humsafar
                    administrator verifies your payment.
                </p>

                <a
                    href="restaurant-owner-payment.php"
                    class="payment-button"
                >

                    <i class="fas fa-eye"></i>

                    View Payment

                </a>

            </section>


        <?php else: ?>

            <section
                class="
                    payment-notice
                    success-payment
                "
            >

                <h2>
                    <i class="fas fa-circle-check"></i>
                    Payment Verified
                </h2>

                <p>
                    Your partner fee payment has been
                    verified successfully.
                </p>

                <p>
                    Your restaurant is now waiting for
                    final administrator approval.
                </p>

            </section>

        <?php endif; ?>



        <!-- =================================================
             APPROVAL AREA
        ================================================= -->

        <?php if (
            $approval_state === 'pending'
        ): ?>

            <section
                class="
                    approval-box
                    approval-pending-box
                "
            >

                <h2>
                    <i class="fas fa-hourglass-half"></i>

                    Waiting for Approval
                </h2>

                <p>
                    Your restaurant is currently waiting
                    for administrator approval. Your
                    restaurant will be visible to customers
                    after the account has been approved.
                </p>

            </section>


        <?php elseif (
            $approval_state === 'active'
        ): ?>

            <section
                class="
                    approval-box
                    approval-active-box
                "
            >

                <h2>
                    <i class="fas fa-circle-check"></i>

                    Restaurant Approved
                </h2>

                <p>
                    Congratulations! Your restaurant owner
                    account has been approved. You can now
                    manage your restaurant, menu and orders
                    through the partner system.
                </p>

            </section>


        <?php elseif (
            $approval_state === 'blocked'
        ): ?>

            <section
                class="
                    approval-box
                    approval-blocked-box
                "
            >

                <h2>
                    <i class="fas fa-ban"></i>

                    Restaurant Account Blocked
                </h2>

                <p>
                    Your restaurant owner account is currently
                    blocked by the administrator. Please
                    contact Humsafar administration for
                    assistance.
                </p>

            </section>


        <?php else: ?>

            <section
                class="
                    approval-box
                    approval-blocked-box
                "
            >

                <h2>
                    <i class="fas fa-eye-slash"></i>

                    Restaurant Account Inactive
                </h2>

                <p>
                    Your restaurant account is currently
                    inactive. Please contact the Humsafar
                    administrator.
                </p>

            </section>

        <?php endif; ?>



        <!-- =================================================
             QUICK MANAGEMENT
        ================================================= -->

        <h2 class="section-title">

            Restaurant Management

        </h2>


        <div class="quick-grid">


            <!-- RESTAURANT -->

            <div
                class="
                    quick-card
                    <?php
                    if ($approval_state !== 'active') {
                        echo 'disabled';
                    }
                    ?>
                "
            >

                <div class="quick-icon">

                    <i class="fas fa-store"></i>

                </div>

                <h3>
                    Manage Restaurant
                </h3>

                <p>
                    Update restaurant information,
                    timing, address and other details.
                </p>

            </div>



            <!-- MENU -->

            <div
                class="
                    quick-card
                    <?php
                    if ($approval_state !== 'active') {
                        echo 'disabled';
                    }
                    ?>
                "
            >

                <div class="quick-icon">

                    <i class="fas fa-utensils"></i>

                </div>

                <h3>
                    Manage Menu
                </h3>

                <p>
                    Add food items, prices, categories
                    and menu details.
                </p>

            </div>



            <!-- ORDERS -->

            <div
                class="
                    quick-card
                    <?php
                    if ($approval_state !== 'active') {
                        echo 'disabled';
                    }
                    ?>
                "
            >

                <div class="quick-icon">

                    <i class="fas fa-receipt"></i>

                </div>

                <h3>
                    Manage Orders
                </h3>

                <p>
                    View incoming customer orders and
                    manage their status.
                </p>

            </div>

        </div>



        <!-- =================================================
             ACCOUNT INFORMATION
        ================================================= -->

        <section class="account-card">

            <div class="account-header">

                <i class="fas fa-user-circle"></i>

                Account Information

            </div>


            <div class="account-row">

                <span class="account-label">
                    Restaurant Owner ID
                </span>

                <span class="account-value">
                    #<?= $owner_id ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Restaurant Name
                </span>

                <span class="account-value">
                    <?= h($restaurant_name) ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Owner Name
                </span>

                <span class="account-value">
                    <?= h($owner_name) ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Email
                </span>

                <span class="account-value">
                    <?= h($owner_email) ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Phone
                </span>

                <span class="account-value">
                    <?= h($owner_phone) ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Registered On
                </span>

                <span class="account-value">
                    <?= h($registered_date) ?>
                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Account Status
                </span>

                <span class="account-value">

                    <?= h(
                        ucfirst(
                            $owner_status
                        )
                    ) ?>

                </span>

            </div>


            <div class="account-row">

                <span class="account-label">
                    Payment Status
                </span>

                <span class="account-value">

                    <?= h(
                        ucfirst(
                            $payment_status
                        )
                    ) ?>

                </span>

            </div>


        </section>


    </main>



    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="dashboard-footer">

        <strong>
            Humsafar
        </strong>

        Food Delivery

        &nbsp;•&nbsp;

        Restaurant Owner Dashboard

        &nbsp;•&nbsp;

        <?= h($current_date) ?>

    </footer>


</div>


</body>

</html>