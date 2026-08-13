<?php

session_start();

require_once '../includes/config.php';


/* =========================================================
   RIDER AUTHENTICATION
========================================================= */

if (
    !isset($_SESSION['rider_logged_in']) ||
    $_SESSION['rider_logged_in'] !== true
) {
    header('Location: rider-login.php');
    exit;
}


$riderId = isset($_SESSION['rider_id'])
    ? (int) $_SESSION['rider_id']
    : 0;


if ($riderId <= 0) {

    session_unset();
    session_destroy();

    header('Location: rider-login.php');
    exit;
}


/* =========================================================
   HELPER
========================================================= */

function riderDashboardEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   LOGOUT
========================================================= */

if (
    isset($_GET['logout']) &&
    $_GET['logout'] === '1'
) {

    unset(
        $_SESSION['rider_logged_in'],
        $_SESSION['rider_id'],
        $_SESSION['rider_name'],
        $_SESSION['rider_email'],
        $_SESSION['rider_phone'],
        $_SESSION['rider_vehicle']
    );

    header('Location: rider-login.php');
    exit;
}


/* =========================================================
   GET RIDER
========================================================= */

$rider = [
    'id' => $riderId,
    'full_name' => 'Rider',
    'email' => '',
    'phone' => '',
    'vehicle_type' => 'bike',
    'address' => '',
    'status' => 'pending'
];


$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        vehicle_type,
        address,
        status
    FROM riders
    WHERE id = ?
    LIMIT 1
");


if ($stmt) {

    $stmt->bind_param(
        'i',
        $riderId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $databaseRider =
        $result->fetch_assoc();

    $stmt->close();


    if ($databaseRider) {

        $rider =
            $databaseRider;

        $_SESSION['rider_name'] =
            $rider['full_name'];

        $_SESSION['rider_email'] =
            $rider['email'];

        $_SESSION['rider_phone'] =
            $rider['phone'];

        $_SESSION['rider_vehicle'] =
            $rider['vehicle_type'];

    }

}


/* =========================================================
   RIDER STATUS
========================================================= */

$riderStatus =
    strtolower(
        trim(
            (string)$rider['status']
        )
    );


$isApproved =
    in_array(
        $riderStatus,
        [
            'active',
            'approved'
        ],
        true
    );


if ($riderStatus === 'pending') {

    $statusTitle =
        'Waiting for Approval';

    $statusMessage =
        'Your rider account is waiting for admin approval.';

} elseif ($riderStatus === 'blocked') {

    $statusTitle =
        'Account Blocked';

    $statusMessage =
        'Your rider account has been blocked. Please contact administration.';

} elseif (!$isApproved) {

    $statusTitle =
        'Account Inactive';

    $statusMessage =
        'Your rider account is currently inactive.';

} else {

    $statusTitle =
        'Account Active';

    $statusMessage =
        'Your rider account is active and ready for delivery orders.';

}


/* =========================================================
   VEHICLE
========================================================= */

$vehicleType =
    strtolower(
        trim(
            (string)$rider['vehicle_type']
        )
    );


$vehicleNames = [

    'bike' =>
        'Bike',

    'motorbike' =>
        'Motorbike',

    'motorcycle' =>
        'Motorcycle',

    'scooter' =>
        'Scooter',

    'car' =>
        'Car',

    'van' =>
        'Van'

];


$vehicleName =
    $vehicleNames[$vehicleType]
    ?? ucfirst(
        $vehicleType ?: 'Bike'
    );


/* =========================================================
   INITIALS
========================================================= */

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            (string)$rider['full_name']
        )
    );


$initials = '';


if (!empty($nameParts[0])) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );

}


if (!empty($nameParts[1])) {

    $initials .=
        strtoupper(
            substr(
                $nameParts[1],
                0,
                1
            )
        );

}


if ($initials === '') {

    $initials = 'R';

}


/* =========================================================
   DASHBOARD COUNTERS
========================================================= */

$availableOrders = 0;
$activeDeliveries = 0;
$completedToday = 0;
$totalCompleted = 0;
$todayEarnings = 0;


/*
|--------------------------------------------------------------------------
| Available orders
|--------------------------------------------------------------------------
|
| Orders ready for pickup and not assigned to a rider.
| If your orders table has rider_id, this query uses it.
|
*/

$orderColumns = [];

$columnsResult =
    $conn->query(
        "SHOW COLUMNS FROM orders"
    );


if ($columnsResult) {

    while (
        $column =
        $columnsResult->fetch_assoc()
    ) {

        $orderColumns[] =
            $column['Field'];

    }

}


$hasOrderColumn =
    function ($column) use ($orderColumns) {

        return in_array(
            $column,
            $orderColumns,
            true
        );

    };


$riderOrderColumn = null;


foreach (
    [
        'rider_id',
        'delivery_rider_id',
        'assigned_rider_id'
    ]
    as $column
) {

    if (
        $hasOrderColumn(
            $column
        )
    ) {

        $riderOrderColumn =
            $column;

        break;

    }

}


$statusColumn = null;


foreach (
    [
        'order_status',
        'status'
    ]
    as $column
) {

    if (
        $hasOrderColumn(
            $column
        )
    ) {

        $statusColumn =
            $column;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| Available orders count
|--------------------------------------------------------------------------
*/

if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(
            TRIM(`$statusColumn`)
        ) IN (
            'ready_for_pickup',
            'ready for pickup',
            'ready'
        )
        AND (
            `$riderOrderColumn` IS NULL
            OR `$riderOrderColumn` = 0
        )
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $availableOrders =
            (int)($row['total'] ?? 0);

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| Assigned active orders
|--------------------------------------------------------------------------
*/

if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM orders
        WHERE `$riderOrderColumn` = ?
        AND LOWER(
            TRIM(`$statusColumn`)
        ) NOT IN (
            'delivered',
            'cancelled',
            'completed'
        )
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $riderId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $activeDeliveries =
            (int)($row['total'] ?? 0);

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| Completed orders today
|--------------------------------------------------------------------------
*/

if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM orders
        WHERE `$riderOrderColumn` = ?
        AND LOWER(
            TRIM(`$statusColumn`)
        ) IN (
            'delivered',
            'completed'
        )
        AND DATE(
            COALESCE(
                updated_at,
                created_at
            )
        ) = CURDATE()
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $riderId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $completedToday =
            (int)($row['total'] ?? 0);

        $stmt->close();

    }


    /*
    |--------------------------------------------------------------------------
    | Total completed
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT COUNT(*) AS total
        FROM orders
        WHERE `$riderOrderColumn` = ?
        AND LOWER(
            TRIM(`$statusColumn`)
        ) IN (
            'delivered',
            'completed'
        )
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $riderId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $totalCompleted =
            (int)($row['total'] ?? 0);

        $stmt->close();

    }

}


/* =========================================================
   RIDER EARNINGS
========================================================= */

$earningsColumns = [];

$earningsResult =
    $conn->query(
        "SHOW COLUMNS FROM rider_earnings"
    );


if ($earningsResult) {

    while (
        $column =
        $earningsResult->fetch_assoc()
    ) {

        $earningsColumns[] =
            $column['Field'];

    }

}


$hasEarningColumn =
    function ($column) use ($earningsColumns) {

        return in_array(
            $column,
            $earningsColumns,
            true
        );

    };


$earningAmountColumn = null;


foreach (
    [
        'amount',
        'earning',
        'rider_earning',
        'delivery_fee'
    ]
    as $column
) {

    if (
        $hasEarningColumn(
            $column
        )
    ) {

        $earningAmountColumn =
            $column;

        break;

    }

}


$earningRiderColumn = null;


foreach (
    [
        'rider_id'
    ]
    as $column
) {

    if (
        $hasEarningColumn(
            $column
        )
    ) {

        $earningRiderColumn =
            $column;

        break;

    }

}


if (
    $isApproved &&
    $earningAmountColumn !== null &&
    $earningRiderColumn !== null
) {

    $dateColumn = null;


    foreach (
        [
            'created_at',
            'earned_at',
            'created_on',
            'date'
        ]
        as $column
    ) {

        if (
            $hasEarningColumn(
                $column
            )
        ) {

            $dateColumn =
                $column;

            break;

        }

    }


    if ($dateColumn !== null) {

        $sql = "
            SELECT
                COALESCE(
                    SUM(
                        `$earningAmountColumn`
                    ),
                    0
                ) AS total
            FROM rider_earnings
            WHERE `$earningRiderColumn` = ?
            AND DATE(
                `$dateColumn`
            ) = CURDATE()
        ";


        $stmt =
            $conn->prepare($sql);


        if ($stmt) {

            $stmt->bind_param(
                'i',
                $riderId
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $row =
                $result->fetch_assoc();

            $todayEarnings =
                (float)(
                    $row['total']
                    ?? 0
                );

            $stmt->close();

        }

    }

}


/* =========================================================
   ACTIVE DELIVERY DETAILS
========================================================= */

$activeOrder = null;


if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT
            o.*
        FROM orders o
        WHERE o.`$riderOrderColumn` = ?
        AND LOWER(
            TRIM(
                o.`$statusColumn`
            )
        ) NOT IN (
            'delivered',
            'cancelled',
            'completed'
        )
        ORDER BY o.id DESC
        LIMIT 1
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $riderId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $activeOrder =
            $result->fetch_assoc();

        $stmt->close();

    }

}


/* =========================================================
   ACTIVE ORDER DISPLAY DATA
========================================================= */

$activeOrderNumber = '';
$activeOrderStatus = '';
$activeOrderTotal = 0;
$activePaymentMethod = '';
$activeRestaurantId = 0;
$activeAddressId = 0;


if ($activeOrder) {

    $activeOrderNumber =
        $activeOrder['order_number']
        ?? (
            '#' .
            (int)$activeOrder['id']
        );


    $activeOrderStatus =
        $activeOrder[$statusColumn]
        ?? 'Assigned';


    $activeOrderTotal =
        (float)(
            $activeOrder['total']
            ?? 0
        );


    $activePaymentMethod =
        $activeOrder['payment_method']
        ?? '';


    $activeRestaurantId =
        (int)(
            $activeOrder['restaurant_id']
            ?? 0
        );


    $activeAddressId =
        (int)(
            $activeOrder['address_id']
            ?? 0
        );

}


/* =========================================================
   RESTAURANT DETAILS
========================================================= */

$restaurantName = 'Restaurant';
$restaurantAddress = 'Pickup address not available';


if ($activeRestaurantId > 0) {

    $stmt =
        $conn->prepare("
            SELECT
                name,
                address
            FROM restaurants
            WHERE id = ?
            LIMIT 1
        ");


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $activeRestaurantId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $restaurant =
            $result->fetch_assoc();

        $stmt->close();


        if ($restaurant) {

            $restaurantName =
                $restaurant['name']
                ?? 'Restaurant';

            $restaurantAddress =
                $restaurant['address']
                ?? 'Pickup address not available';

        }

    }

}


/* =========================================================
   CUSTOMER ADDRESS
========================================================= */

$customerAddress =
    'Delivery address not available';


if ($activeAddressId > 0) {

    /*
     * Current customer address system
     */

    $stmt =
        $conn->prepare("
            SELECT
                address_line,
                city,
                area,
                landmark
            FROM customer_addresses
            WHERE id = ?
            LIMIT 1
        ");


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $activeAddressId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $address =
            $result->fetch_assoc();

        $stmt->close();


        if ($address) {

            $parts = [];


            foreach (
                [
                    'address_line',
                    'area',
                    'city',
                    'landmark'
                ]
                as $field
            ) {

                if (
                    isset(
                        $address[$field]
                    ) &&
                    trim(
                        (string)$address[$field]
                    ) !== ''
                ) {

                    $parts[] =
                        trim(
                            (string)$address[$field]
                        );

                }

            }


            if (!empty($parts)) {

                $customerAddress =
                    implode(
                        ', ',
                        $parts
                    );

            }

        }

    }

}


/* =========================================================
   PAGE
========================================================= */

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
    Rider Dashboard - Humsafar
</title>


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

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f6f7f9;

    color: #252525;
}


.rider-page {

    min-height: 100vh;

    padding-left: 223px;
}


.rider-topbar {

    height: 72px;

    background: #fff;

    border-bottom:
        1px solid #eeeeee;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    padding:
        0 30px;

    position: sticky;

    top: 0;

    z-index: 100;
}


.rider-top-title {

    display: flex;

    align-items: center;

    gap: 12px;
}


.rider-top-title-icon {

    width: 40px;

    height: 40px;

    border-radius: 10px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;
}


.rider-top-title h2 {

    margin: 0;

    font-size: 18px;

    font-weight: 800;
}


.rider-top-title span {

    display: block;

    margin-top: 3px;

    color: #999;

    font-size: 10px;
}


.rider-top-right {

    display: flex;

    align-items: center;

    gap: 14px;
}


.rider-top-user {

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 13px;

    font-weight: 700;
}


.rider-top-avatar {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    background: #ffd900;

    color: #111;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 900;
}


.rider-logout {

    height: 36px;

    padding:
        0 13px;

    border-radius: 8px;

    background: #fff0f3;

    border:
        1px solid #ffd4df;

    color: #ed0038;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 7px;

    font-size: 12px;

    font-weight: 700;
}


.rider-content {

    padding: 28px;

    max-width: 1500px;

    margin: 0 auto;
}


.rider-welcome {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 20px;

    margin-bottom: 22px;
}


.rider-welcome h1 {

    margin: 0;

    font-size: 27px;

    font-weight: 800;
}


.rider-welcome p {

    margin:
        6px 0 0;

    color: #777;

    font-size: 13px;
}


.rider-date {

    padding:
        10px 14px;

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 9px;

    color: #777;

    font-size: 11px;

    font-weight: 700;
}


.rider-status {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 18px 20px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 20px;
}


.rider-status-left {

    display: flex;

    align-items: center;

    gap: 13px;
}


.rider-status-icon {

    width: 44px;

    height: 44px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eaf8ef;

    color: #198842;
}


.rider-status-icon.pending {

    background: #fff7df;

    color: #d79a00;
}


.rider-status-icon.blocked {

    background: #fff0f2;

    color: #e00038;
}


.rider-status-text h3 {

    margin:
        0 0 4px;

    font-size: 15px;
}


.rider-status-text p {

    margin: 0;

    color: #777;

    font-size: 11px;
}


.rider-status-badge {

    padding:
        8px 13px;

    border-radius: 20px;

    background: #eaf8ef;

    color: #198842;

    font-size: 10px;

    font-weight: 800;
}


.rider-status-badge.pending {

    background: #fff7df;

    color: #bd8600;
}


.rider-status-badge.blocked {

    background: #fff0f2;

    color: #e00038;
}


.rider-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}


.rider-stat-card {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 19px;
}


.rider-stat-icon {

    width: 42px;

    height: 42px;

    border-radius: 10px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 13px;
}


.rider-stat-label {

    color: #888;

    font-size: 11px;

    margin-bottom: 6px;
}


.rider-stat-value {

    color: #252525;

    font-size: 24px;

    font-weight: 800;
}


.rider-stat-note {

    margin-top: 5px;

    color: #aaa;

    font-size: 10px;
}


.rider-main-grid {

    display: grid;

    grid-template-columns:
        1.55fr 1fr;

    gap: 20px;

    margin-bottom: 20px;
}


.rider-panel {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 21px;
}


.rider-panel-header {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    margin-bottom: 17px;
}


.rider-panel-header h2 {

    margin: 0;

    font-size: 17px;
}


.rider-panel-header span {

    color: #999;

    font-size: 10px;
}


.delivery-box {

    border:
        1px solid #f0f0f0;

    border-radius: 11px;

    overflow: hidden;
}


.delivery-header {

    padding: 15px 17px;

    background: #fffafa;

    border-bottom:
        1px solid #eeeeee;

    display: flex;

    justify-content:
        space-between;

    align-items: center;
}


.delivery-order {

    font-size: 14px;

    font-weight: 800;
}


.delivery-status {

    padding:
        6px 10px;

    border-radius: 20px;

    background: #fff0f3;

    color: #ed0038;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}


.delivery-body {

    padding: 17px;
}


.delivery-row {

    display: flex;

    gap: 12px;

    margin-bottom: 16px;
}


.delivery-row:last-child {

    margin-bottom: 0;
}


.delivery-icon {

    width: 34px;

    height: 34px;

    flex-shrink: 0;

    border-radius: 9px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;
}


.delivery-label {

    color: #999;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    margin-bottom: 3px;
}


.delivery-value {

    color: #333;

    font-size: 12px;

    line-height: 1.5;

    font-weight: 600;
}


.delivery-footer {

    padding:
        13px 17px;

    background: #fafafa;

    border-top:
        1px solid #eeeeee;

    display: flex;

    justify-content:
        space-between;

    align-items: center;
}


.delivery-total {

    font-size: 15px;

    font-weight: 800;
}


.delivery-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 13px;

    background: #ed0038;

    color: #fff;

    border-radius: 8px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;
}


.no-delivery {

    text-align: center;

    padding:
        45px 20px;
}


.no-delivery-icon {

    width: 62px;

    height: 62px;

    margin:
        0 auto 15px;

    border-radius: 50%;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;
}


.no-delivery h3 {

    margin:
        0 0 7px;

    font-size: 16px;
}


.no-delivery p {

    margin: 0 auto;

    max-width: 400px;

    color: #888;

    font-size: 11px;

    line-height: 1.6;
}


.profile-box {

    display: flex;

    align-items: center;

    gap: 12px;

    padding-bottom: 16px;

    margin-bottom: 14px;

    border-bottom:
        1px solid #eeeeee;
}


.profile-avatar {

    width: 48px;

    height: 48px;

    border-radius: 50%;

    background: #ffd900;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 900;

    font-size: 13px;
}


.profile-name {

    font-size: 14px;

    font-weight: 800;
}


.profile-role {

    color: #999;

    font-size: 10px;

    margin-top: 3px;
}


.profile-row {

    display: flex;

    justify-content:
        space-between;

    gap: 15px;

    padding: 10px 0;

    border-bottom:
        1px solid #f2f2f2;
}


.profile-row:last-child {

    border-bottom: 0;
}


.profile-label {

    color: #999;

    font-size: 10px;
}


.profile-value {

    color: #333;

    font-size: 10px;

    font-weight: 700;

    text-align: right;
}


.quick-actions {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}


.quick-action {

    min-height: 78px;

    padding: 13px;

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 12px;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 11px;

    transition: .18s ease;
}


.quick-action:hover {

    border-color: #ffc5d2;

    transform:
        translateY(-1px);
}


.quick-action-icon {

    width: 39px;

    height: 39px;

    border-radius: 9px;

    background: #fff0f3;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;
}


.quick-action-text strong {

    display: block;

    color: #333;

    font-size: 11px;

    margin-bottom: 4px;
}


.quick-action-text span {

    color: #999;

    font-size: 9px;
}


@media (max-width: 1100px) {

    .rider-stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .quick-actions {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 900px) {

    .rider-page {

        padding-left: 0;
    }

    .rider-main-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .rider-top-user {

        display: none;
    }

    .rider-content {

        padding: 18px 13px;
    }

    .rider-welcome {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .rider-stats {

        grid-template-columns:
            1fr 1fr;
    }

    .quick-actions {

        grid-template-columns:
            1fr 1fr;
    }

}


@media (max-width: 430px) {

    .rider-stats {

        grid-template-columns:
            1fr;
    }

    .quick-actions {

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| EXISTING RIDER SIDEBAR
|--------------------------------------------------------------------------
*/

include_once 'rider-sidebar.php';

?>


<div class="rider-page">


<header class="rider-topbar">

    <div class="rider-top-title">

        <div class="rider-top-title-icon">

            <i class="fas fa-gauge-high"></i>

        </div>

        <div>

            <h2>
                Rider Dashboard
            </h2>

            <span>
                Manage your delivery activities
            </span>

        </div>

    </div>


    <div class="rider-top-right">

        <div class="rider-top-user">

            <div class="rider-top-avatar">

                <?= riderDashboardEscape(
                    $initials
                ) ?>

            </div>

            <?= riderDashboardEscape(
                $rider['full_name']
            ) ?>

        </div>


        <a
            href="rider-dashboard.php?logout=1"
            class="rider-logout"
        >

            <i class="fas fa-right-from-bracket"></i>

            Logout

        </a>

    </div>

</header>


<main class="rider-content">


<section class="rider-welcome">

    <div>

        <h1>

            Welcome,
            <?= riderDashboardEscape(
                $rider['full_name']
            ) ?>

        </h1>

        <p>
            Here's your rider overview for today.
        </p>

    </div>


    <div class="rider-date">

        <i class="far fa-calendar"></i>

        <?= date('l, d M Y') ?>

    </div>

</section>


<section class="rider-status">

    <div class="rider-status-left">

        <div
            class="
                rider-status-icon
                <?= $riderStatus !== 'active'
                    ? riderDashboardEscape(
                        $riderStatus
                    )
                    : ''
                ?>
            "
        >

            <?php if ($isApproved): ?>

                <i class="fas fa-check"></i>

            <?php elseif ($riderStatus === 'pending'): ?>

                <i class="fas fa-clock"></i>

            <?php elseif ($riderStatus === 'blocked'): ?>

                <i class="fas fa-ban"></i>

            <?php else: ?>

                <i class="fas fa-circle-exclamation"></i>

            <?php endif; ?>

        </div>


        <div class="rider-status-text">

            <h3>
                <?= riderDashboardEscape(
                    $statusTitle
                ) ?>
            </h3>

            <p>
                <?= riderDashboardEscape(
                    $statusMessage
                ) ?>
            </p>

        </div>

    </div>


    <div
        class="
            rider-status-badge
            <?= $riderStatus !== 'active'
                ? riderDashboardEscape(
                    $riderStatus
                )
                : ''
            ?>
        "
    >

        <?= riderDashboardEscape(
            strtoupper(
                $riderStatus
            )
        ) ?>

    </div>

</section>


<section class="rider-stats">


<div class="rider-stat-card">

    <div class="rider-stat-icon">

        <i class="fas fa-box-open"></i>

    </div>

    <div class="rider-stat-label">
        Available Orders
    </div>

    <div class="rider-stat-value">
        <?= (int)$availableOrders ?>
    </div>

    <div class="rider-stat-note">
        Ready for pickup
    </div>

</div>


<div class="rider-stat-card">

    <div class="rider-stat-icon">

        <i class="fas fa-motorcycle"></i>

    </div>

    <div class="rider-stat-label">
        Active Delivery
    </div>

    <div class="rider-stat-value">
        <?= (int)$activeDeliveries ?>
    </div>

    <div class="rider-stat-note">
        Currently assigned
    </div>

</div>


<div class="rider-stat-card">

    <div class="rider-stat-icon">

        <i class="fas fa-circle-check"></i>

    </div>

    <div class="rider-stat-label">
        Completed Today
    </div>

    <div class="rider-stat-value">
        <?= (int)$completedToday ?>
    </div>

    <div class="rider-stat-note">
        Successfully delivered
    </div>

</div>


<div class="rider-stat-card">

    <div class="rider-stat-icon">

        <i class="fas fa-wallet"></i>

    </div>

    <div class="rider-stat-label">
        Today's Earnings
    </div>

    <div class="rider-stat-value">

        Rs.
        <?= number_format(
            $todayEarnings,
            0
        ) ?>

    </div>

    <div class="rider-stat-note">
        Delivery earnings
    </div>

</div>


</section>


<div class="rider-main-grid">


<section class="rider-panel">


    <div class="rider-panel-header">

        <h2>
            Active Delivery
        </h2>

        <span>
            Current assignment
        </span>

    </div>


    <?php if ($activeOrder): ?>


        <div class="delivery-box">


            <div class="delivery-header">

                <div class="delivery-order">

                    <?= riderDashboardEscape(
                        $activeOrderNumber
                    ) ?>

                </div>


                <div class="delivery-status">

                    <?= riderDashboardEscape(
                        $activeOrderStatus
                    ) ?>

                </div>

            </div>


            <div class="delivery-body">


                <div class="delivery-row">

                    <div class="delivery-icon">

                        <i class="fas fa-store"></i>

                    </div>


                    <div>

                        <div class="delivery-label">
                            Pickup Restaurant
                        </div>

                        <div class="delivery-value">

                            <?= riderDashboardEscape(
                                $restaurantName
                            ) ?>

                            <br>

                            <?= riderDashboardEscape(
                                $restaurantAddress
                            ) ?>

                        </div>

                    </div>

                </div>


                <div class="delivery-row">

                    <div class="delivery-icon">

                        <i class="fas fa-location-dot"></i>

                    </div>


                    <div>

                        <div class="delivery-label">
                            Customer Delivery Address
                        </div>

                        <div class="delivery-value">

                            <?= riderDashboardEscape(
                                $customerAddress
                            ) ?>

                        </div>

                    </div>

                </div>


                <div class="delivery-row">

                    <div class="delivery-icon">

                        <i class="fas fa-credit-card"></i>

                    </div>


                    <div>

                        <div class="delivery-label">
                            Payment Method
                        </div>

                        <div class="delivery-value">

                            <?= riderDashboardEscape(
                                $activePaymentMethod
                                ?: 'Not specified'
                            ) ?>

                        </div>

                    </div>

                </div>


            </div>


            <div class="delivery-footer">

                <div class="delivery-total">

                    Rs.
                    <?= number_format(
                        $activeOrderTotal,
                        0
                    ) ?>

                </div>


                <a
                    href="rider-orders.php"
                    class="delivery-btn"
                >

                    <i class="fas fa-route"></i>

                    Manage Delivery

                </a>

            </div>


        </div>


    <?php else: ?>


        <div class="no-delivery">

            <div class="no-delivery-icon">

                <i class="fas fa-motorcycle"></i>

            </div>


            <h3>

                <?php if (!$isApproved): ?>

                    Deliveries Unavailable

                <?php else: ?>

                    No Active Delivery

                <?php endif; ?>

            </h3>


            <p>

                <?php if (!$isApproved): ?>

                    Your rider account must be active
                    before you can receive delivery orders.

                <?php else: ?>

                    When an order is assigned to you,
                    pickup and customer delivery details
                    will appear here.

                <?php endif; ?>

            </p>

        </div>


    <?php endif; ?>


</section>


<section class="rider-panel">


    <div class="rider-panel-header">

        <h2>
            Rider Profile
        </h2>


        <a
            href="rider-profile.php"
            style="
                color:#ed0038;
                text-decoration:none;
                font-size:11px;
                font-weight:800;
            "
        >
            View Profile
        </a>

    </div>


    <div class="profile-box">


        <div class="profile-avatar">

            <?= riderDashboardEscape(
                $initials
            ) ?>

        </div>


        <div>

            <div class="profile-name">

                <?= riderDashboardEscape(
                    $rider['full_name']
                ) ?>

            </div>


            <div class="profile-role">

                Humsafar Rider

            </div>

        </div>


    </div>


    <div class="profile-row">

        <span class="profile-label">
            Rider ID
        </span>

        <span class="profile-value">

            #<?= (int)$rider['id'] ?>

        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Phone
        </span>

        <span class="profile-value">

            <?= riderDashboardEscape(
                $rider['phone']
                ?: 'Not added'
            ) ?>

        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Vehicle
        </span>

        <span class="profile-value">

            <?= riderDashboardEscape(
                $vehicleName
            ) ?>

        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Completed
        </span>

        <span class="profile-value">

            <?= (int)$totalCompleted ?>

        </span>

    </div>


</section>


</div>


<section>

    <div
        class="rider-panel-header"
        style="margin-bottom:12px;"
    >

        <h2>
            Quick Actions
        </h2>

        <span>
            Rider tools
        </span>

    </div>


    <div class="quick-actions">


        <a
            href="rider-orders.php"
            class="quick-action"
        >

            <div class="quick-action-icon">

                <i class="fas fa-box-open"></i>

            </div>


            <div class="quick-action-text">

                <strong>
                    Available Orders
                </strong>

                <span>
                    Find delivery orders
                </span>

            </div>

        </a>


        <a
            href="rider-deliveries.php"
            class="quick-action"
        >

            <div class="quick-action-icon">

                <i class="fas fa-route"></i>

            </div>


            <div class="quick-action-text">

                <strong>
                    My Deliveries
                </strong>

                <span>
                    View delivery history
                </span>

            </div>

        </a>


        <a
            href="rider-earnings.php"
            class="quick-action"
        >

            <div class="quick-action-icon">

                <i class="fas fa-wallet"></i>

            </div>


            <div class="quick-action-text">

                <strong>
                    Earnings
                </strong>

                <span>
                    Check your earnings
                </span>

            </div>

        </a>


        <a
            href="rider-profile.php"
            class="quick-action"
        >

            <div class="quick-action-icon">

                <i class="fas fa-user"></i>

            </div>


            <div class="quick-action-text">

                <strong>
                    My Profile
                </strong>

                <span>
                    Manage your account
                </span>

            </div>

        </a>


    </div>

</section>


<section class="rider-panel">

    <div class="rider-panel-header">

        <h2>
            Rider Information
        </h2>

        <span>
            Your account details
        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Name
        </span>

        <span class="profile-value">

            <?= riderDashboardEscape(
                $rider['full_name']
            ) ?>

        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Email
        </span>

        <span class="profile-value">

            <?= riderDashboardEscape(
                $rider['email']
                ?: 'Not added'
            ) ?>

        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Address
        </span>

        <span class="profile-value">

            <?= riderDashboardEscape(
                $rider['address']
                ?: 'Not added'
            ) ?>

        </span>

    </div>


</section>


</main>

</div>


</body>

</html>