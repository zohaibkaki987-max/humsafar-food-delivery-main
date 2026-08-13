<?php

session_start();

require_once '../includes/config.php';


/*
|--------------------------------------------------------------------------
| RIDER AUTHENTICATION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function riderDashboardEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| GET RIDER
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| RIDER STATUS
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| VEHICLE
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| AVAILABILITY TABLE
|--------------------------------------------------------------------------
*/

$conn->query("
    CREATE TABLE IF NOT EXISTS rider_availability (
        id INT(11) NOT NULL AUTO_INCREMENT,
        rider_id INT(11) NOT NULL,
        available_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY rider_id (rider_id),
        KEY available_date (available_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


/*
|--------------------------------------------------------------------------
| SAVE AVAILABILITY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_availability'])
) {

    $availabilityDate =
        trim(
            $_POST['available_date']
            ?? ''
        );

    $startTime =
        trim(
            $_POST['start_time']
            ?? ''
        );

    $endTime =
        trim(
            $_POST['end_time']
            ?? ''
        );


    if (!$isApproved) {

        $errorMessage =
            'Your rider account must be approved before setting availability.';

    } elseif (
        $availabilityDate === '' ||
        $startTime === '' ||
        $endTime === ''
    ) {

        $errorMessage =
            'Please select date, start time and end time.';

    } elseif (
        $availabilityDate < date('Y-m-d')
    ) {

        $errorMessage =
            'You cannot select a previous date.';

    } elseif (
        $startTime >= $endTime
    ) {

        $errorMessage =
            'End time must be later than start time.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | CHECK OVERLAPPING SCHEDULE
        |--------------------------------------------------------------------------
        */

        $checkStmt =
            $conn->prepare("
                SELECT id
                FROM rider_availability
                WHERE rider_id = ?
                AND available_date = ?
                AND start_time < ?
                AND end_time > ?
                LIMIT 1
            ");


        $overlapFound = false;


        if ($checkStmt) {

            $checkStmt->bind_param(
                'isss',
                $riderId,
                $availabilityDate,
                $endTime,
                $startTime
            );

            $checkStmt->execute();

            $checkResult =
                $checkStmt->get_result();

            $overlapFound =
                $checkResult->num_rows > 0;

            $checkStmt->close();
        }


        if ($overlapFound) {

            $errorMessage =
                'You already have an availability schedule during this time.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | SAVE
            |--------------------------------------------------------------------------
            */

            $insertStmt =
                $conn->prepare("
                    INSERT INTO rider_availability
                    (
                        rider_id,
                        available_date,
                        start_time,
                        end_time
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


            if ($insertStmt) {

                $insertStmt->bind_param(
                    'isss',
                    $riderId,
                    $availabilityDate,
                    $startTime,
                    $endTime
                );


                if (
                    $insertStmt->execute()
                ) {

                    $successMessage =
                        'Your availability schedule has been saved successfully.';

                } else {

                    $errorMessage =
                        'Unable to save availability schedule.';

                }


                $insertStmt->close();

            } else {

                $errorMessage =
                    'Unable to prepare availability request.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE AVAILABILITY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_availability'])
) {

    $availabilityId =
        isset(
            $_POST['availability_id']
        )
        ? (int)$_POST['availability_id']
        : 0;


    if ($availabilityId > 0) {

        $deleteStmt =
            $conn->prepare("
                DELETE FROM rider_availability
                WHERE id = ?
                AND rider_id = ?
                LIMIT 1
            ");


        if ($deleteStmt) {

            $deleteStmt->bind_param(
                'ii',
                $availabilityId,
                $riderId
            );


            if (
                $deleteStmt->execute()
            ) {

                $successMessage =
                    'Availability schedule removed.';

            } else {

                $errorMessage =
                    'Unable to remove availability schedule.';
            }


            $deleteStmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTERS
|--------------------------------------------------------------------------
*/

$availableOrders = 0;
$activeDeliveries = 0;
$completedToday = 0;
$totalCompleted = 0;


/*
|--------------------------------------------------------------------------
| DETECT ORDER COLUMNS
|--------------------------------------------------------------------------
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
    function ($column)
    use ($orderColumns) {

        return in_array(
            $column,
            $orderColumns,
            true
        );
    };


/*
|--------------------------------------------------------------------------
| RIDER COLUMN
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| STATUS COLUMN
|--------------------------------------------------------------------------
*/

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
| AVAILABLE ORDERS
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
            (int)(
                $row['total']
                ?? 0
            );

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| ACTIVE DELIVERIES
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
            (int)(
                $row['total']
                ?? 0
            );

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| COMPLETED TODAY
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
            (int)(
                $row['total']
                ?? 0
            );

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| TOTAL COMPLETED
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
            (int)(
                $row['total']
                ?? 0
            );

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| TODAY'S EARNINGS
|--------------------------------------------------------------------------
*/

$todayEarnings = 0;

$earningsTableExists = false;


$tableCheck =
    $conn->query("
        SHOW TABLES LIKE 'rider_earnings'
    ");


if (
    $tableCheck &&
    $tableCheck->num_rows > 0
) {

    $earningsTableExists = true;
}


if ($earningsTableExists) {

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
        function ($column)
        use ($earningsColumns) {

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


    if (
        $hasEarningColumn(
            'rider_id'
        )
    ) {

        $earningRiderColumn =
            'rider_id';
    }


    $earningDateColumn = null;


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

            $earningDateColumn =
                $column;

            break;
        }
    }


    if (
        $earningAmountColumn !== null &&
        $earningRiderColumn !== null &&
        $earningDateColumn !== null
    ) {

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
                `$earningDateColumn`
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


/*
|--------------------------------------------------------------------------
| UPCOMING AVAILABILITY
|--------------------------------------------------------------------------
*/

$availabilityList = [];


$availabilityStmt =
    $conn->prepare("
        SELECT
            id,
            available_date,
            start_time,
            end_time
        FROM rider_availability
        WHERE rider_id = ?
        AND available_date >= CURDATE()
        ORDER BY
            available_date ASC,
            start_time ASC
        LIMIT 10
    ");


if ($availabilityStmt) {

    $availabilityStmt->bind_param(
        'i',
        $riderId
    );

    $availabilityStmt->execute();

    $availabilityResult =
        $availabilityStmt->get_result();


    while (
        $row =
        $availabilityResult->fetch_assoc()
    ) {

        $availabilityList[] =
            $row;
    }


    $availabilityStmt->close();
}


/*
|--------------------------------------------------------------------------
| TODAY AVAILABILITY
|--------------------------------------------------------------------------
*/

$todayAvailability = null;


$todayStmt =
    $conn->prepare("
        SELECT
            start_time,
            end_time
        FROM rider_availability
        WHERE rider_id = ?
        AND available_date = CURDATE()
        AND start_time <= CURTIME()
        AND end_time >= CURTIME()
        ORDER BY start_time ASC
        LIMIT 1
    ");


if ($todayStmt) {

    $todayStmt->bind_param(
        'i',
        $riderId
    );

    $todayStmt->execute();

    $todayResult =
        $todayStmt->get_result();

    $todayAvailability =
        $todayResult->fetch_assoc();

    $todayStmt->close();
}


$isAvailableNow =
    $todayAvailability !== null;


/*
|--------------------------------------------------------------------------
| ACTIVE ORDER
|--------------------------------------------------------------------------
*/

$activeOrder = null;


if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT
            *
        FROM orders
        WHERE `$riderOrderColumn` = ?
        AND LOWER(
            TRIM(`$statusColumn`)
        ) NOT IN (
            'delivered',
            'cancelled',
            'completed'
        )
        ORDER BY id DESC
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


/*
|--------------------------------------------------------------------------
| AVAILABLE ORDERS PREVIEW
|--------------------------------------------------------------------------
*/

$availableOrderList = [];


if (
    $isApproved &&
    $riderOrderColumn !== null &&
    $statusColumn !== null
) {

    $sql = "
        SELECT
            id,
            order_number,
            restaurant_id,
            order_status,
            total,
            payment_method,
            created_at
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
        ORDER BY id DESC
        LIMIT 5
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->execute();

        $result =
            $stmt->get_result();


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $availableOrderList[] =
                $row;
        }


        $stmt->close();
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

    background: #f7f7f9;

    color: #222;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.rider-dashboard {

    margin-left: 223px;

    min-height: 100vh;

    padding: 30px;
}


.top-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 25px;

    gap: 20px;
}


.welcome h1 {

    margin: 0 0 6px;

    font-size: 28px;

    font-weight: 800;
}


.welcome p {

    margin: 0;

    color: #777;

    font-size: 12px;
}


.profile-box {

    display: flex;

    align-items: center;

    gap: 10px;

    background: #fff;

    padding:
        9px 13px;

    border:
        1px solid #eee;

    border-radius: 10px;
}


.profile-avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #e9003f;

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 13px;

    font-weight: 800;
}


.profile-name {

    font-size: 11px;

    font-weight: 800;
}


.profile-status {

    font-size: 9px;

    color: #777;

    margin-top: 3px;
}


.alert {

    padding:
        14px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 11px;
}


.alert.success {

    background: #eaf8ef;

    border:
        1px solid #c9e8d3;

    color: #176d38;
}


.alert.error {

    background: #fff0f3;

    border:
        1px solid #ffd0da;

    color: #a0002b;
}


.account-status {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        15px 18px;

    margin-bottom: 22px;

    border-radius: 12px;

    background: #fff;

    border:
        1px solid #eee;
}


.status-dot {

    width: 11px;

    height: 11px;

    border-radius: 50%;

    background: #16a34a;
}


.status-dot.inactive {

    background: #dc2626;
}


.account-status strong {

    font-size: 12px;
}


.account-status span {

    display: block;

    margin-top: 3px;

    color: #777;

    font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| STAT CARDS
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}


.stat-card {

    background: #fff;

    border:
        1px solid #eee;

    border-radius: 13px;

    padding: 18px;
}


.stat-icon {

    width: 38px;

    height: 38px;

    border-radius: 9px;

    background: #fff0f3;

    color: #e9003f;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 12px;
}


.stat-label {

    color: #888;

    font-size: 9px;

    text-transform: uppercase;

    font-weight: 700;
}


.stat-number {

    margin-top: 5px;

    font-size: 24px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| GRID
|--------------------------------------------------------------------------
*/

.dashboard-grid {

    display: grid;

    grid-template-columns:
        1.35fr 1fr;

    gap: 20px;

    margin-bottom: 22px;
}


.card {

    background: #fff;

    border:
        1px solid #eee;

    border-radius: 14px;

    overflow: hidden;
}


.card-header {

    padding:
        17px 18px;

    border-bottom:
        1px solid #eee;

    display: flex;

    justify-content:
        space-between;

    align-items: center;
}


.card-header h2 {

    margin: 0;

    font-size: 16px;
}


.card-header span {

    color: #999;

    font-size: 9px;
}


.card-body {

    padding: 18px;
}


/*
|--------------------------------------------------------------------------
| AVAILABILITY
|--------------------------------------------------------------------------
*/

.availability-status {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    padding:
        12px 13px;

    margin-bottom: 17px;

    border-radius: 9px;

    background: #fafafa;

    border:
        1px solid #eee;
}


.availability-status strong {

    font-size: 11px;
}


.availability-status span {

    font-size: 9px;

    font-weight: 700;

    color: #e9003f;
}


.availability-form {

    display: grid;

    grid-template-columns:
        1fr 1fr 1fr;

    gap: 10px;
}


.form-group label {

    display: block;

    font-size: 9px;

    font-weight: 700;

    color: #666;

    margin-bottom: 6px;
}


.form-group input {

    width: 100%;

    padding:
        10px;

    border:
        1px solid #ddd;

    border-radius: 8px;

    outline: none;

    font-size: 10px;
}


.form-group input:focus {

    border-color: #e9003f;
}


.save-btn {

    grid-column:
        1 / -1;

    border: 0;

    padding:
        11px;

    border-radius: 8px;

    background: #e9003f;

    color: #fff;

    font-size: 10px;

    font-weight: 800;

    cursor: pointer;
}


/*
|--------------------------------------------------------------------------
| SCHEDULE
|--------------------------------------------------------------------------
*/

.schedule-list {

    margin-top: 18px;
}


.schedule-title {

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 9px;
}


.schedule-item {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 10px;

    padding:
        11px 12px;

    border:
        1px solid #eee;

    border-radius: 9px;

    margin-bottom: 8px;
}


.schedule-date {

    font-size: 10px;

    font-weight: 800;
}


.schedule-time {

    color: #777;

    font-size: 9px;

    margin-top: 3px;
}


.delete-btn {

    border: 0;

    background: #fff0f3;

    color: #e9003f;

    width: 29px;

    height: 29px;

    border-radius: 7px;

    cursor: pointer;
}


.no-schedule {

    color: #999;

    font-size: 10px;

    padding: 10px 0;
}


/*
|--------------------------------------------------------------------------
| ACTIVE DELIVERY
|--------------------------------------------------------------------------
*/

.active-delivery {

    border:
        1px solid #eee;

    border-radius: 10px;

    padding: 14px;
}


.active-order-number {

    font-size: 14px;

    font-weight: 800;

    margin-bottom: 8px;
}


.active-status {

    display: inline-block;

    background: #fff0f3;

    color: #e9003f;

    border-radius: 20px;

    padding:
        5px 8px;

    font-size: 8px;

    font-weight: 800;

    text-transform: uppercase;

    margin-bottom: 14px;
}


.active-info {

    display: grid;

    gap: 10px;
}


.active-row {

    display: flex;

    gap: 9px;
}


.active-row i {

    color: #e9003f;

    width: 15px;

    margin-top: 2px;
}


.active-row div {

    font-size: 10px;

    line-height: 1.5;
}


.active-label {

    display: block;

    color: #999;

    font-size: 8px;

    text-transform: uppercase;

    font-weight: 800;
}


.delivery-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 15px;

    padding:
        10px 13px;

    border-radius: 8px;

    background: #e9003f;

    color: #fff;

    text-decoration: none;

    font-size: 9px;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/

.order-list {

    display: grid;

    gap: 9px;
}


.order-item {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

    padding:
        12px;

    border:
        1px solid #eee;

    border-radius: 9px;
}


.order-left strong {

    display: block;

    font-size: 11px;
}


.order-left span {

    display: block;

    color: #888;

    font-size: 9px;

    margin-top: 4px;
}


.order-right {

    text-align: right;
}


.order-price {

    font-size: 11px;

    font-weight: 800;
}


.order-ready {

    display: block;

    margin-top: 4px;

    color: #e9003f;

    font-size: 8px;

    font-weight: 800;
}


.view-orders {

    display: inline-flex;

    margin-top: 13px;

    color: #e9003f;

    font-size: 10px;

    font-weight: 800;

    text-decoration: none;
}


/*
|--------------------------------------------------------------------------
| QUICK INFO
|--------------------------------------------------------------------------
*/

.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;
}


.quick-card {

    padding: 14px;

    border:
        1px solid #eee;

    border-radius: 10px;
}


.quick-card small {

    display: block;

    color: #999;

    font-size: 8px;

    text-transform: uppercase;
}


.quick-card strong {

    display: block;

    margin-top: 5px;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    padding:
        25px 10px;

    text-align: center;

    color: #999;

    font-size: 10px;
}


.empty i {

    display: block;

    color: #e9003f;

    font-size: 25px;

    margin-bottom: 9px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 1050px
) {

    .rider-dashboard {

        margin-left: 0;
    }


    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .dashboard-grid {

        grid-template-columns:
            1fr;
    }
}


@media (
    max-width: 650px
) {

    .rider-dashboard {

        padding: 15px;
    }


    .top-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .stats {

        grid-template-columns:
            1fr;
    }


    .availability-form {

        grid-template-columns:
            1fr;
    }


    .quick-grid {

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


<main class="rider-dashboard">


<div class="top-header">


<div class="welcome">

    <h1>
        Welcome,
        <?= riderDashboardEscape(
            $rider['full_name']
        ) ?>
    </h1>

    <p>
        Manage your deliveries and availability from here.
    </p>

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


        <div class="profile-status">

            <?= riderDashboardEscape(
                $vehicleName
            ) ?>

        </div>

    </div>

</div>


</div>


<?php if ($successMessage !== ''): ?>

<div class="alert success">

    <i class="fas fa-circle-check"></i>

    <?= riderDashboardEscape(
        $successMessage
    ) ?>

</div>

<?php endif; ?>


<?php if ($errorMessage !== ''): ?>

<div class="alert error">

    <i class="fas fa-circle-exclamation"></i>

    <?= riderDashboardEscape(
        $errorMessage
    ) ?>

</div>

<?php endif; ?>


<div class="account-status">


<div
    class="
        status-dot
        <?= !$isApproved
            ? 'inactive'
            : ''
        ?>
    "
></div>


<div>

    <strong>
        <?= riderDashboardEscape(
            $statusTitle
        ) ?>
    </strong>

    <span>
        <?= riderDashboardEscape(
            $statusMessage
        ) ?>
    </span>

</div>


</div>


<!-- =========================================================
     STATS
========================================================= -->


<section class="stats">


<div class="stat-card">

    <div class="stat-icon">

        <i class="fas fa-box-open"></i>

    </div>

    <div class="stat-label">
        Available Orders
    </div>

    <div class="stat-number">

        <?= $availableOrders ?>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <i class="fas fa-motorcycle"></i>

    </div>

    <div class="stat-label">
        Active Deliveries
    </div>

    <div class="stat-number">

        <?= $activeDeliveries ?>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <i class="fas fa-check-circle"></i>

    </div>

    <div class="stat-label">
        Completed Today
    </div>

    <div class="stat-number">

        <?= $completedToday ?>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <i class="fas fa-wallet"></i>

    </div>

    <div class="stat-label">
        Today's Earnings
    </div>

    <div class="stat-number">

        Rs.
        <?= number_format(
            $todayEarnings,
            0
        ) ?>

    </div>

</div>


</section>


<!-- =========================================================
     MAIN GRID
========================================================= -->


<div class="dashboard-grid">


<!-- =========================================================
     AVAILABILITY
========================================================= -->


<section class="card">


<div class="card-header">

    <h2>
        My Availability
    </h2>

    <span>
        Schedule your working time
    </span>

</div>


<div class="card-body">


<div class="availability-status">


<div>

    <strong>
        Availability Now
    </strong>

</div>


<span>

<?php if ($isAvailableNow): ?>

    <i class="fas fa-circle"></i>
    Available Now

<?php else: ?>

    <i class="fas fa-circle"></i>
    Not Available Now

<?php endif; ?>

</span>


</div>


<form
    method="POST"
    class="availability-form"
>


<div class="form-group">

    <label>
        Available Date
    </label>

    <input
        type="date"
        name="available_date"
        min="<?= date('Y-m-d') ?>"
        required
    >

</div>


<div class="form-group">

    <label>
        Start Time
    </label>

    <input
        type="time"
        name="start_time"
        required
    >

</div>


<div class="form-group">

    <label>
        End Time
    </label>

    <input
        type="time"
        name="end_time"
        required
    >

</div>


<button
    type="submit"
    name="save_availability"
    value="1"
    class="save-btn"
>

    <i class="fas fa-calendar-plus"></i>

    Save Availability

</button>


</form>


<div class="schedule-list">


<div class="schedule-title">

    Upcoming Availability

</div>


<?php if (
    empty($availabilityList)
): ?>


<div class="no-schedule">

    No availability schedule added yet.

</div>


<?php else: ?>


<?php foreach (
    $availabilityList
    as $schedule
): ?>


<div class="schedule-item">


<div>

    <div class="schedule-date">

        <?= date(
            'd M Y',
            strtotime(
                $schedule['available_date']
            )
        ) ?>

    </div>


    <div class="schedule-time">

        <?= date(
            'h:i A',
            strtotime(
                $schedule['start_time']
            )
        ) ?>

        -

        <?= date(
            'h:i A',
            strtotime(
                $schedule['end_time']
            )
        ) ?>

    </div>

</div>


<form
    method="POST"
>


    <input
        type="hidden"
        name="availability_id"
        value="<?= (int)$schedule['id'] ?>"
    >


    <button
        type="submit"
        name="delete_availability"
        value="1"
        class="delete-btn"
        title="Delete"
        onclick="
            return confirm(
                'Remove this availability schedule?'
            );
        "
    >

        <i class="fas fa-trash"></i>

    </button>


</form>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


</div>


</section>


<!-- =========================================================
     ACTIVE DELIVERY
========================================================= -->


<section class="card">


<div class="card-header">

    <h2>
        Active Delivery
    </h2>

    <span>
        Current order
    </span>

</div>


<div class="card-body">


<?php if (
    !$activeOrder
): ?>


<div class="empty">

    <i class="fas fa-motorcycle"></i>

    No active delivery right now.

</div>


<?php else: ?>


<?php

$activeOrderNumber =
    $activeOrder['order_number']
    ?? (
        '#' .
        (int)(
            $activeOrder['id']
            ?? 0
        )
    );


$activeOrderStatus =
    $activeOrder[
        $statusColumn
    ]
    ?? 'Assigned';


$activeTotal =
    (float)(
        $activeOrder['total']
        ?? 0
    );


$activePayment =
    $activeOrder[
        'payment_method'
    ]
    ?? 'Not specified';

?>


<div class="active-delivery">


<div class="active-order-number">

    <?= riderDashboardEscape(
        $activeOrderNumber
    ) ?>

</div>


<div class="active-status">

    <?= riderDashboardEscape(
        $activeOrderStatus
    ) ?>

</div>


<div class="active-info">


<div class="active-row">

    <i class="fas fa-money-bill"></i>

    <div>

        <span class="active-label">
            Order Total
        </span>

        Rs.
        <?= number_format(
            $activeTotal,
            0
        ) ?>

    </div>

</div>


<div class="active-row">

    <i class="fas fa-credit-card"></i>

    <div>

        <span class="active-label">
            Payment
        </span>

        <?= riderDashboardEscape(
            $activePayment
        ) ?>

    </div>

</div>


</div>


<a
    href="rider-delivery.php?order_id=<?= (int)$activeOrder['id'] ?>"
    class="delivery-link"
>

    <i class="fas fa-route"></i>

    Manage Delivery

</a>


</div>


<?php endif; ?>


</div>


</section>


</div>


<!-- =========================================================
     AVAILABLE ORDERS
========================================================= -->


<section class="card">


<div class="card-header">

    <h2>
        Available Orders
    </h2>

    <span>
        Ready for Pickup
    </span>

</div>


<div class="card-body">


<?php if (
    empty($availableOrderList)
): ?>


<div class="empty">

    <i class="fas fa-box-open"></i>

    No Ready for Pickup orders available right now.

</div>


<?php else: ?>


<div class="order-list">


<?php foreach (
    $availableOrderList
    as $availableOrder
): ?>


<div class="order-item">


<div class="order-left">

    <strong>

        <?= riderDashboardEscape(
            $availableOrder[
                'order_number'
            ]
        ) ?>

    </strong>


    <span>

        Restaurant ID:
        <?= (int)(
            $availableOrder[
                'restaurant_id'
            ]
        ) ?>

    </span>


    <span>

        <?= riderDashboardEscape(
            $availableOrder[
                'payment_method'
            ]
            ?? ''
        ) ?>

    </span>

</div>


<div class="order-right">

    <div class="order-price">

        Rs.
        <?= number_format(
            (float)(
                $availableOrder[
                    'total'
                ]
            ),
            0
        ) ?>

    </div>


    <span class="order-ready">

        Ready for Pickup

    </span>

</div>


</div>


<?php endforeach; ?>


</div>


<a
    href="rider-orders.php"
    class="view-orders"
>

    View All Orders
    <i class="fas fa-arrow-right"></i>

</a>


<?php endif; ?>


</div>


</section>


<br>


<!-- =========================================================
     QUICK INFO
========================================================= -->


<section class="card">


<div class="card-header">

    <h2>
        Rider Information
    </h2>

</div>


<div class="card-body">


<div class="quick-grid">


<div class="quick-card">

    <small>
        Vehicle
    </small>

    <strong>
        <?= riderDashboardEscape(
            $vehicleName
        ) ?>
    </strong>

</div>


<div class="quick-card">

    <small>
        Completed Deliveries
    </small>

    <strong>
        <?= $totalCompleted ?>
    </strong>

</div>


<div class="quick-card">

    <small>
        Today's Date
    </small>

    <strong>
        <?= date(
            'd M Y'
        ) ?>
    </strong>

</div>


</div>


</div>


</section>


</main>


</body>

</html>