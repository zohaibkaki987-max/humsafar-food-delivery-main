<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/customer-header.php';


/*
|--------------------------------------------------------------------------
| CUSTOMER DATA
|--------------------------------------------------------------------------
*/

$customer = null;

$customerId = 0;


/*
|--------------------------------------------------------------------------
| CUSTOMER SESSION
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['customer_id'])) {

    $customerId =
        (int) $_SESSION['customer_id'];

} elseif (isset($_SESSION['user_id'])) {

    $customerId =
        (int) $_SESSION['user_id'];

} elseif (isset($_SESSION['id'])) {

    $customerId =
        (int) $_SESSION['id'];

}


/*
|--------------------------------------------------------------------------
| GET CUSTOMER
|--------------------------------------------------------------------------
*/

if ($customerId > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $customerId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $customer =
            $result->fetch_assoc();

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| SESSION FALLBACK
|--------------------------------------------------------------------------
*/

if (!$customer) {

    $customer = [

        'id' =>
            $customerId,

        'name' =>
            $_SESSION['customer_name']
            ??
            $_SESSION['name']
            ??
            'Customer',

        'email' =>
            $_SESSION['customer_email']
            ??
            $_SESSION['email']
            ??
            '',

        'phone' =>
            $_SESSION['customer_phone']
            ??
            $_SESSION['phone']
            ??
            '',

        'created_at' =>
            ''

    ];

}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function accountH($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CUSTOMER NAME
|--------------------------------------------------------------------------
*/

$customerName =
    trim(
        $customer['name']
        ??
        'Customer'
    );


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$nameParts =
    preg_split(
        '/\s+/',
        $customerName
    );

$initials = '';

foreach (
    array_slice(
        $nameParts,
        0,
        2
    ) as $part
) {

    if ($part !== '') {

        $initials .=
            strtoupper(
                substr(
                    $part,
                    0,
                    1
                )
            );

    }

}


if ($initials === '') {

    $initials = 'C';

}


/*
|--------------------------------------------------------------------------
| MEMBER SINCE
|--------------------------------------------------------------------------
*/

$memberSince = 'Customer';

if (
    !empty(
        $customer['created_at']
    )
) {

    $date =
        strtotime(
            $customer['created_at']
        );

    if ($date !== false) {

        $memberSince =
            date(
                'd M Y',
                $date
            );

    }

}


/*
|--------------------------------------------------------------------------
| ORDER STATISTICS
|--------------------------------------------------------------------------
*/

$totalOrders = 0;
$openOrders = 0;
$deliveredOrders = 0;
$cancelledOrders = 0;


if ($customerId > 0) {

    $stmt = $conn->prepare("
        SELECT

            COUNT(*) AS total_orders,

            SUM(
                CASE
                    WHEN order_status IN (
                        'pending',
                        'confirmed',
                        'accepted',
                        'preparing',
                        'ready',
                        'ready_for_pickup',
                        'picked_up',
                        'out_for_delivery',
                        'on_the_way'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS open_orders,

            SUM(
                CASE
                    WHEN order_status IN (
                        'delivered',
                        'completed'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS delivered_orders,

            SUM(
                CASE
                    WHEN order_status IN (
                        'cancelled',
                        'canceled'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS cancelled_orders

        FROM orders

        WHERE user_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $customerId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $stats =
            $result->fetch_assoc();

        $stmt->close();


        if ($stats) {

            $totalOrders =
                (int)
                (
                    $stats['total_orders']
                    ??
                    0
                );

            $openOrders =
                (int)
                (
                    $stats['open_orders']
                    ??
                    0
                );

            $deliveredOrders =
                (int)
                (
                    $stats['delivered_orders']
                    ??
                    0
                );

            $cancelledOrders =
                (int)
                (
                    $stats['cancelled_orders']
                    ??
                    0
                );

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
        My Account - Humsafar
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: #f5f6fa;

            color: #333;

            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .account-container {

            max-width: 1100px;

            margin: 35px auto;

            padding:
                0 20px 60px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADING
        |--------------------------------------------------------------------------
        */

        .account-heading {

            margin-bottom: 25px;

        }


        .account-heading h1 {

            margin: 0;

            color: #ed0038;

            font-size: 32px;

            font-weight: 800;

        }


        .account-heading p {

            margin:
                7px 0 0;

            color: #ed0038;

            font-size: 14px;

        }


        /*
        |--------------------------------------------------------------------------
        | PROFILE
        |--------------------------------------------------------------------------
        */

        .profile-card {

            position: relative;

            overflow: hidden;

            display: flex;

            align-items: center;

            gap: 20px;

            padding: 25px;

            margin-bottom: 20px;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.06);

        }


        .profile-avatar {

            width: 82px;

            height: 82px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #ed0038;

            color: #fff;

            border-radius: 50%;

            font-size: 27px;

            font-weight: 800;

        }


        .profile-info h2 {

            margin: 0;

            color: #222;

            font-size: 24px;

            font-weight: 800;

        }


        .profile-info p {

            margin:
                5px 0 0;

            color: #777;

            font-size: 13px;

        }


        .member-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            margin-top: 10px;

            padding:
                6px 10px;

            background: #fff1f5;

            color: #ed0038;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 750;

        }


        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        .account-stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 20px;

        }


        .stat-card {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 17px;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 15px;

            box-shadow:
                0 7px 22px
                rgba(0,0,0,.04);

        }


        .stat-icon {

            width: 44px;

            height: 44px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff1f5;

            color: #ed0038;

            border-radius: 11px;

            font-size: 17px;

        }


        .stat-number {

            color: #222;

            font-size: 20px;

            font-weight: 800;

        }


        .stat-label {

            margin-top: 2px;

            color: #888;

            font-size: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | GRID
        |--------------------------------------------------------------------------
        */

        .account-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | SECTION
        |--------------------------------------------------------------------------
        */

        .account-section {

            overflow: hidden;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.05);

        }


        .section-header {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                18px 20px;

            border-bottom:
                1px solid #eee;

        }


        .section-icon {

            width: 35px;

            height: 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff1f5;

            color: #ed0038;

            border-radius: 9px;

            font-size: 14px;

        }


        .section-header h3 {

            margin: 0;

            color: #222;

            font-size: 16px;

            font-weight: 800;

        }


        /*
        |--------------------------------------------------------------------------
        | INFORMATION
        |--------------------------------------------------------------------------
        */

        .information {

            padding:
                5px 20px 15px;

        }


        .info-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            padding:
                15px 0;

            border-bottom:
                1px solid #f0f0f0;

        }


        .info-row:last-child {

            border-bottom: 0;

        }


        .info-label {

            color: #888;

            font-size: 11px;

        }


        .info-value {

            max-width: 65%;

            color: #333;

            font-size: 13px;

            font-weight: 700;

            text-align: right;

            word-break: break-word;

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIONS
        |--------------------------------------------------------------------------
        */

        .actions {

            padding:
                15px 20px 20px;

        }


        .action {

            display: flex;

            align-items: center;

            gap: 12px;

            width: 100%;

            padding: 13px;

            margin-bottom: 10px;

            background: #fafafa;

            color: #333;

            border:
                1px solid #eee;

            border-radius: 11px;

            text-decoration: none;

            transition: .2s;

        }


        .action:last-child {

            margin-bottom: 0;

        }


        .action:hover {

            background: #fff1f5;

            color: #ed0038;

            border-color: #ffd0da;

        }


        .action-icon {

            width: 39px;

            height: 39px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff;

            color: #ed0038;

            border-radius: 9px;

            font-size: 14px;

        }


        .action-content {

            flex: 1;

        }


        .action-title {

            display: block;

            color: inherit;

            font-size: 13px;

            font-weight: 750;

        }


        .action-description {

            display: block;

            margin-top: 2px;

            color: #888;

            font-size: 10px;

        }


        .action-arrow {

            color: #aaa;

            font-size: 11px;

        }


        /*
        |--------------------------------------------------------------------------
        | MY ADDRESS
        |--------------------------------------------------------------------------
        */

        .address-action {

            border-color: #f4d0d9;

            background:
                linear-gradient(
                    90deg,
                    #fff8fa,
                    #fafafa
                );

        }


        .address-action .action-icon {

            background: #fff1f5;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        */

        .logout-action {

            color: #d32b3b;

        }


        .logout-action .action-icon {

            color: #d32b3b;

            background: #fff0f1;

        }


        .logout-action:hover {

            color: #d32b3b;

            background: #fff0f1;

            border-color: #ffd4d8;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .account-stats {

                grid-template-columns:
                    repeat(2,1fr);

            }

        }


        @media (max-width: 700px) {

            .account-container {

                margin-top: 25px;

                padding:
                    0 12px 40px;

            }


            .account-grid {

                grid-template-columns: 1fr;

            }


            .profile-card {

                padding: 20px;

            }


            .profile-avatar {

                width: 70px;

                height: 70px;

                font-size: 23px;

            }


            .profile-info h2 {

                font-size: 20px;

            }

        }


        @media (max-width: 500px) {

            .account-heading h1 {

                font-size: 27px;

            }


            .account-stats {

                gap: 10px;

            }


            .stat-card {

                padding: 12px;

                gap: 8px;

            }


            .stat-icon {

                width: 37px;

                height: 37px;

            }


            .stat-number {

                font-size: 17px;

            }


            .stat-label {

                font-size: 9px;

            }


            .profile-card {

                align-items:
                    flex-start;

            }


            .info-row {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap: 5px;

            }


            .info-value {

                max-width: 100%;

                text-align: left;

            }

        }

    </style>

</head>


<body>


<main class="account-container">


    <!-- =====================================================
         PAGE HEADING
    ====================================================== -->

    <div class="account-heading">

        <h1>
            My Account
        </h1>

        <p>
            Manage your Humsafar customer account.
        </p>

    </div>


    <!-- =====================================================
         PROFILE
    ====================================================== -->

    <div class="profile-card">

        <div class="profile-avatar">

            <?php

            echo accountH(
                $initials
            );

            ?>

        </div>


        <div class="profile-info">

            <h2>

                <?php

                echo accountH(
                    $customerName
                );

                ?>

            </h2>


            <p>

                <?php

                echo accountH(
                    $customer['email']
                    ?? ''
                );

                ?>

            </p>


            <div class="member-badge">

                <i
                    class="fas fa-user-check"
                ></i>

                Member since

                <?php

                echo accountH(
                    $memberSince
                );

                ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="account-stats">


        <!-- TOTAL ORDERS -->

        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-receipt"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $totalOrders;
                    ?>

                </div>

                <div class="stat-label">
                    Total Orders
                </div>

            </div>

        </div>


        <!-- OPEN ORDERS -->

        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-clock"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $openOrders;
                    ?>

                </div>

                <div class="stat-label">
                    Open Orders
                </div>

            </div>

        </div>


        <!-- DELIVERED -->

        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-circle-check"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $deliveredOrders;
                    ?>

                </div>

                <div class="stat-label">
                    Delivered
                </div>

            </div>

        </div>


        <!-- CANCELLED -->

        <div class="stat-card">

            <div class="stat-icon">

                <i
                    class="fas fa-circle-xmark"
                ></i>

            </div>


            <div>

                <div class="stat-number">

                    <?php
                    echo $cancelledOrders;
                    ?>

                </div>

                <div class="stat-label">
                    Cancelled
                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         ACCOUNT CONTENT
    ====================================================== -->

    <div class="account-grid">


        <!-- =================================================
             ACCOUNT INFORMATION
        ================================================== -->

        <section class="account-section">


            <div class="section-header">

                <div class="section-icon">

                    <i
                        class="fas fa-user"
                    ></i>

                </div>


                <h3>
                    Account Information
                </h3>

            </div>


            <div class="information">


                <!-- FULL NAME -->

                <div class="info-row">

                    <div class="info-label">
                        Full Name
                    </div>


                    <div class="info-value">

                        <?php

                        echo accountH(
                            $customerName
                        );

                        ?>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="info-row">

                    <div class="info-label">
                        Email Address
                    </div>


                    <div class="info-value">

                        <?php

                        echo accountH(
                            $customer['email']
                            ??
                            'Not provided'
                        );

                        ?>

                    </div>

                </div>


                <!-- PHONE -->

                <div class="info-row">

                    <div class="info-label">
                        Phone Number
                    </div>


                    <div class="info-value">

                        <?php

                        echo accountH(
                            $customer['phone']
                            ??
                            'Not provided'
                        );

                        ?>

                    </div>

                </div>


                <!-- CUSTOMER ID -->

                <div class="info-row">

                    <div class="info-label">
                        Customer ID
                    </div>


                    <div class="info-value">

                        #

                        <?php

                        echo (int)
                            $customer['id'];

                        ?>

                    </div>

                </div>


                <!-- MEMBER SINCE -->

                <div class="info-row">

                    <div class="info-label">
                        Member Since
                    </div>


                    <div class="info-value">

                        <?php

                        echo accountH(
                            $memberSince
                        );

                        ?>

                    </div>

                </div>


            </div>

        </section>


        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <section class="account-section">


            <div class="section-header">

                <div class="section-icon">

                    <i
                        class="fas fa-bolt"
                    ></i>

                </div>


                <h3>
                    Quick Actions
                </h3>

            </div>


            <div class="actions">


                <!-- =================================================
                     MY ORDERS
                ================================================== -->

                <a
                    href="my_orders.php"
                    class="action"
                >

                    <div class="action-icon">

                        <i
                            class="fas fa-receipt"
                        ></i>

                    </div>


                    <div class="action-content">

                        <span class="action-title">
                            My Orders
                        </span>

                        <span class="action-description">
                            View and track your orders
                        </span>

                    </div>


                    <i
                        class="
                            fas
                            fa-chevron-right
                            action-arrow
                        "
                    ></i>

                </a>


                <!-- =================================================
                     MY ADDRESS
                     REPLACES RESTAURANTS + DEALS
                ================================================== -->

                <a
                    href="customer/manage-addresses.php"
                    class="
                        action
                        address-action
                    "
                >

                    <div class="action-icon">

                        <i
                            class="fas fa-location-dot"
                        ></i>

                    </div>


                    <div class="action-content">

                        <span class="action-title">
                            My Address
                        </span>

                        <span class="action-description">
                            Add, edit and manage your delivery addresses
                        </span>

                    </div>


                    <i
                        class="
                            fas
                            fa-chevron-right
                            action-arrow
                        "
                    ></i>

                </a>


                <!-- =================================================
                     MY CART
                ================================================== -->

                <a
                    href="cart.php"
                    class="action"
                >

                    <div class="action-icon">

                        <i
                            class="fas fa-shopping-cart"
                        ></i>

                    </div>


                    <div class="action-content">

                        <span class="action-title">
                            My Cart
                        </span>

                        <span class="action-description">
                            Review your selected items
                        </span>

                    </div>


                    <i
                        class="
                            fas
                            fa-chevron-right
                            action-arrow
                        "
                    ></i>

                </a>


                <!-- =================================================
                     PAYMENTS
                ================================================== -->

                <a
                    href="checkout.php"
                    class="action"
                >

                    <div class="action-icon">

                        <i
                            class="fas fa-credit-card"
                        ></i>

                    </div>


                    <div class="action-content">

                        <span class="action-title">
                            Payments
                        </span>

                        <span class="action-description">
                            Continue to payment and checkout
                        </span>

                    </div>


                    <i
                        class="
                            fas
                            fa-chevron-right
                            action-arrow
                        "
                    ></i>

                </a>


                <!-- =================================================
                     LOGOUT
                ================================================== -->

                <a
                    href="logout.php"
                    class="
                        action
                        logout-action
                    "
                >

                    <div class="action-icon">

                        <i
                            class="
                                fas
                                fa-right-from-bracket
                            "
                        ></i>

                    </div>


                    <div class="action-content">

                        <span class="action-title">
                            Logout
                        </span>

                        <span class="action-description">
                            Sign out of your account
                        </span>

                    </div>


                    <i
                        class="
                            fas
                            fa-chevron-right
                            action-arrow
                        "
                    ></i>

                </a>


            </div>

        </section>


    </div>


</main>


</body>

</html>