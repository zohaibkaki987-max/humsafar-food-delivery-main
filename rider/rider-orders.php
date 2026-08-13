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

function riderOrderEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| GET RIDER
|--------------------------------------------------------------------------
*/

$rider = [
    'id' => $riderId,
    'full_name' => $_SESSION['rider_name'] ?? 'Rider',
    'email' => '',
    'phone' => '',
    'vehicle_type' => 'bike',
    'status' => 'pending'
];


$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        vehicle_type,
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

    }

}


/*
|--------------------------------------------------------------------------
| RIDER APPROVAL
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
            'approved',
            'active'
        ],
        true
    );


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
    function ($column) use ($orderColumns) {

        return in_array(
            $column,
            $orderColumns,
            true
        );

    };


/*
|--------------------------------------------------------------------------
| RIDER ASSIGNMENT COLUMN
|--------------------------------------------------------------------------
*/

$riderColumn = null;


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

        $riderColumn =
            $column;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| ORDER STATUS COLUMN
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
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| ACCEPT DELIVERY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset(
        $_POST['accept_delivery']
    )
) {

    $orderId =
        isset(
            $_POST['order_id']
        )
        ? (int)$_POST['order_id']
        : 0;


    if (!$isApproved) {

        $message =
            'Your rider account is not approved yet.';

        $messageType =
            'error';

    } elseif ($orderId <= 0) {

        $message =
            'Invalid order selected.';

        $messageType =
            'error';

    } elseif (
        $riderColumn === null ||
        $statusColumn === null
    ) {

        $message =
            'Rider assignment columns are not available in the orders table.';

        $messageType =
            'error';

    } else {


        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();


        try {


            /*
            |--------------------------------------------------------------------------
            | LOCK ORDER
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    *
                FROM orders
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    'Unable to prepare order query.'
                );

            }


            $stmt->bind_param(
                'i',
                $orderId
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            $order =
                $result->fetch_assoc();


            $stmt->close();


            if (!$order) {

                throw new Exception(
                    'Order not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK CURRENT ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $existingRider =
                isset(
                    $order[$riderColumn]
                )
                ? (int)$order[$riderColumn]
                : 0;


            if ($existingRider > 0) {

                if (
                    $existingRider === $riderId
                ) {

                    throw new Exception(
                        'You have already accepted this delivery.'
                    );

                }


                throw new Exception(
                    'This delivery has already been assigned to another rider.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK STATUS
            |--------------------------------------------------------------------------
            */

            $currentStatus =
                strtolower(
                    trim(
                        (string)(
                            $order[$statusColumn]
                            ?? ''
                        )
                    )
                );


            $allowedStatuses = [
                'ready_for_pickup',
                'ready for pickup',
                'ready'
            ];


            if (
                !in_array(
                    $currentStatus,
                    $allowedStatuses,
                    true
                )
            ) {

                throw new Exception(
                    'This order is no longer available for pickup.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | ASSIGN RIDER
            |--------------------------------------------------------------------------
            |
            | If the order status column supports rider_assigned,
            | we use that status.
            |
            */

            $newStatus =
                'rider_assigned';


            $sql = "
                UPDATE orders
                SET
                    `$riderColumn` = ?,
                    `$statusColumn` = ?
                WHERE id = ?
                AND (
                    `$riderColumn` IS NULL
                    OR `$riderColumn` = 0
                )
                LIMIT 1
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    'Unable to prepare rider assignment.'
                );

            }


            $stmt->bind_param(
                'isi',
                $riderId,
                $newStatus,
                $orderId
            );


            $stmt->execute();


            if (
                $stmt->affected_rows !== 1
            ) {

                $stmt->close();


                throw new Exception(
                    'This order was already taken by another rider.'
                );

            }


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | CREATE RIDER DELIVERY RECORD
            |--------------------------------------------------------------------------
            |
            | We inspect the rider_deliveries table first so this
            | page can work with the existing database structure.
            |
            */

            $deliveryTableExists = false;


            $tableCheck =
                $conn->query("
                    SHOW TABLES LIKE 'rider_deliveries'
                ");


            if (
                $tableCheck &&
                $tableCheck->num_rows > 0
            ) {

                $deliveryTableExists =
                    true;

            }


            if ($deliveryTableExists) {


                $deliveryColumns = [];


                $deliveryColumnsResult =
                    $conn->query(
                        "SHOW COLUMNS FROM rider_deliveries"
                    );


                if ($deliveryColumnsResult) {

                    while (
                        $column =
                        $deliveryColumnsResult->fetch_assoc()
                    ) {

                        $deliveryColumns[] =
                            $column['Field'];

                    }

                }


                $hasDeliveryColumn =
                    function ($column)
                    use ($deliveryColumns) {

                        return in_array(
                            $column,
                            $deliveryColumns,
                            true
                        );

                    };


                /*
                |--------------------------------------------------------------------------
                | DETECT DELIVERY COLUMNS
                |--------------------------------------------------------------------------
                */

                $deliveryRiderColumn = null;

                foreach (
                    [
                        'rider_id'
                    ]
                    as $column
                ) {

                    if (
                        $hasDeliveryColumn(
                            $column
                        )
                    ) {

                        $deliveryRiderColumn =
                            $column;

                        break;

                    }

                }


                $deliveryOrderColumn = null;

                foreach (
                    [
                        'order_id'
                    ]
                    as $column
                ) {

                    if (
                        $hasDeliveryColumn(
                            $column
                        )
                    ) {

                        $deliveryOrderColumn =
                            $column;

                        break;

                    }

                }


                $deliveryStatusColumn = null;

                foreach (
                    [
                        'status',
                        'delivery_status'
                    ]
                    as $column
                ) {

                    if (
                        $hasDeliveryColumn(
                            $column
                        )
                    ) {

                        $deliveryStatusColumn =
                            $column;

                        break;

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT DELIVERY RECORD
                |--------------------------------------------------------------------------
                */

                if (
                    $deliveryRiderColumn !== null &&
                    $deliveryOrderColumn !== null
                ) {


                    /*
                    |--------------------------------------------------------------
                    | Check whether a delivery already exists
                    |--------------------------------------------------------------
                    */

                    $checkSql = "
                        SELECT
                            id
                        FROM rider_deliveries
                        WHERE
                            `$deliveryRiderColumn` = ?
                        AND
                            `$deliveryOrderColumn` = ?
                        LIMIT 1
                    ";


                    $checkStmt =
                        $conn->prepare(
                            $checkSql
                        );


                    $existingDelivery = null;


                    if ($checkStmt) {

                        $checkStmt->bind_param(
                            'ii',
                            $riderId,
                            $orderId
                        );


                        $checkStmt->execute();


                        $checkResult =
                            $checkStmt->get_result();


                        $existingDelivery =
                            $checkResult->fetch_assoc();


                        $checkStmt->close();

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Create only if missing
                    |--------------------------------------------------------------------------
                    */

                    if (!$existingDelivery) {


                        if (
                            $deliveryStatusColumn !== null
                        ) {

                            $insertSql = "
                                INSERT INTO rider_deliveries
                                (
                                    `$deliveryRiderColumn`,
                                    `$deliveryOrderColumn`,
                                    `$deliveryStatusColumn`
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?
                                )
                            ";


                            $insertStmt =
                                $conn->prepare(
                                    $insertSql
                                );


                            if (!$insertStmt) {

                                throw new Exception(
                                    'Unable to create rider delivery.'
                                );

                            }


                            $deliveryStatus =
                                'assigned';


                            $insertStmt->bind_param(
                                'iis',
                                $riderId,
                                $orderId,
                                $deliveryStatus
                            );


                            $insertStmt->execute();


                            $insertStmt->close();


                        } else {


                            $insertSql = "
                                INSERT INTO rider_deliveries
                                (
                                    `$deliveryRiderColumn`,
                                    `$deliveryOrderColumn`
                                )
                                VALUES
                                (
                                    ?,
                                    ?
                                )
                            ";


                            $insertStmt =
                                $conn->prepare(
                                    $insertSql
                                );


                            if (!$insertStmt) {

                                throw new Exception(
                                    'Unable to create rider delivery.'
                                );

                            }


                            $insertStmt->bind_param(
                                'ii',
                                $riderId,
                                $orderId
                            );


                            $insertStmt->execute();


                            $insertStmt->close();

                        }

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            $message =
                'Delivery accepted successfully.';

            $messageType =
                'success';


        } catch (
            Throwable $e
        ) {


            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            $conn->rollback();


            $message =
                $e->getMessage();

            $messageType =
                'error';

        }

    }

}


/*
|--------------------------------------------------------------------------
| GET AVAILABLE ORDERS
|--------------------------------------------------------------------------
*/

$availableOrders = [];


if (
    $isApproved &&
    $riderColumn !== null &&
    $statusColumn !== null
) {


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | Ready-for-pickup orders only.
    |
    | An order is available only when no rider is assigned.
    |
    */

    $sql = "
        SELECT
            o.*
        FROM orders o
        WHERE
            LOWER(
                TRIM(
                    o.`$statusColumn`
                )
            ) IN (
                'ready_for_pickup',
                'ready for pickup',
                'ready'
            )
        AND (
            o.`$riderColumn` IS NULL
            OR o.`$riderColumn` = 0
        )
        ORDER BY
            o.id DESC
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

            $availableOrders[] =
                $row;

        }


        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| GET MY ACTIVE DELIVERIES
|--------------------------------------------------------------------------
*/

$myOrders = [];


if (
    $isApproved &&
    $riderColumn !== null &&
    $statusColumn !== null
) {


    $sql = "
        SELECT
            o.*
        FROM orders o
        WHERE
            o.`$riderColumn` = ?
        AND
            LOWER(
                TRIM(
                    o.`$statusColumn`
                )
            ) NOT IN (
                'delivered',
                'cancelled',
                'completed'
            )
        ORDER BY
            o.id DESC
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


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $myOrders[] =
                $row;

        }


        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| RESTAURANT CACHE
|--------------------------------------------------------------------------
*/

$restaurantCache = [];


function riderGetRestaurant(
    $conn,
    $restaurantId,
    &$restaurantCache
) {

    $restaurantId =
        (int)$restaurantId;


    if ($restaurantId <= 0) {

        return [
            'name' =>
                'Restaurant',

            'address' =>
                'Pickup address not available'
        ];

    }


    if (
        isset(
            $restaurantCache[
                $restaurantId
            ]
        )
    ) {

        return
            $restaurantCache[
                $restaurantId
            ];

    }


    $stmt =
        $conn->prepare("
            SELECT
                name,
                address
            FROM restaurants
            WHERE id = ?
            LIMIT 1
        ");


    $restaurant = [
        'name' =>
            'Restaurant',

        'address' =>
            'Pickup address not available'
    ];


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $restaurantId
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $row =
            $result->fetch_assoc();


        $stmt->close();


        if ($row) {

            $restaurant =
                $row;

        }

    }


    $restaurantCache[
        $restaurantId
    ] =
        $restaurant;


    return $restaurant;

}


/*
|--------------------------------------------------------------------------
| CUSTOMER ADDRESS CACHE
|--------------------------------------------------------------------------
*/

$addressCache = [];


function riderGetAddress(
    $conn,
    $addressId,
    &$addressCache
) {

    $addressId =
        (int)$addressId;


    if ($addressId <= 0) {

        return
            'Delivery address not available';

    }


    if (
        isset(
            $addressCache[
                $addressId
            ]
        )
    ) {

        return
            $addressCache[
                $addressId
            ];

    }


    $stmt =
        $conn->prepare("
            SELECT
                address_line,
                area,
                city,
                landmark
            FROM customer_addresses
            WHERE id = ?
            LIMIT 1
        ");


    $addressText =
        'Delivery address not available';


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $addressId
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $row =
            $result->fetch_assoc();


        $stmt->close();


        if ($row) {

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
                        $row[$field]
                    ) &&
                    trim(
                        (string)$row[$field]
                    ) !== ''
                ) {

                    $parts[] =
                        trim(
                            (string)$row[$field]
                        );

                }

            }


            if (!empty($parts)) {

                $addressText =
                    implode(
                        ', ',
                        $parts
                    );

            }

        }

    }


    $addressCache[
        $addressId
    ] =
        $addressText;


    return $addressText;

}


/*
|--------------------------------------------------------------------------
| ORDER VALUE HELPER
|--------------------------------------------------------------------------
*/

function riderOrderValue(
    $order,
    $columns,
    $default = ''
) {

    foreach (
        $columns
        as $column
    ) {

        if (
            isset(
                $order[$column]
            ) &&
            $order[$column] !== ''
        ) {

            return
                $order[$column];

        }

    }


    return $default;

}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

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
    Rider Orders - Humsafar
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

    background: #f7f7f9;

    color: #252525;
}


.rider-orders-page {

    margin-left: 223px;

    min-height: 100vh;

    padding: 30px;
}


.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap: 20px;

    margin-bottom: 24px;
}


.page-title h1 {

    margin: 0 0 6px;

    font-size: 27px;

    font-weight: 800;
}


.page-title p {

    margin: 0;

    color: #777;

    font-size: 13px;
}


.refresh-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        11px 15px;

    border-radius: 9px;

    background: #fff;

    border:
        1px solid #e7e7e7;

    color: #e00038;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;
}


.flash {

    padding:
        14px 17px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 12px;

    display: flex;

    align-items: center;

    gap: 10px;
}


.flash.success {

    background: #eaf8ef;

    border:
        1px solid #c7ead4;

    color: #17743b;
}


.flash.error {

    background: #fff0f2;

    border:
        1px solid #ffd0da;

    color: #a30029;
}


.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}


.summary-card {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 13px;

    padding: 18px;
}


.summary-label {

    color: #888;

    font-size: 10px;

    margin-bottom: 7px;
}


.summary-value {

    font-size: 24px;

    font-weight: 800;
}


.section-title {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    margin:
        0 0 13px;
}


.section-title h2 {

    margin: 0;

    font-size: 18px;
}


.section-title span {

    color: #999;

    font-size: 10px;
}


.orders-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 17px;

    margin-bottom: 30px;
}


.order-card {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    overflow: hidden;
}


.order-header {

    padding:
        15px 17px;

    border-bottom:
        1px solid #eeeeee;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 12px;
}


.order-number {

    font-size: 14px;

    font-weight: 800;
}


.status {

    padding:
        6px 9px;

    border-radius: 20px;

    background: #fff0f3;

    color: #e00038;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}


.order-body {

    padding: 17px;
}


.info-row {

    display: flex;

    gap: 10px;

    margin-bottom: 13px;
}


.info-row:last-child {

    margin-bottom: 0;
}


.info-icon {

    width: 32px;

    height: 32px;

    flex-shrink: 0;

    border-radius: 8px;

    background: #fff0f3;

    color: #e00038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;
}


.info-label {

    color: #999;

    display: block;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

    margin-bottom: 3px;
}


.info-value {

    color: #333;

    font-size: 11px;

    font-weight: 600;

    line-height: 1.5;
}


.order-footer {

    background: #fafafa;

    border-top:
        1px solid #eeeeee;

    padding:
        13px 17px;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 10px;
}


.order-total {

    font-size: 15px;

    font-weight: 800;
}


.accept-form {

    margin: 0;
}


.accept-btn {

    border: 0;

    cursor: pointer;

    padding:
        10px 14px;

    border-radius: 8px;

    background: #e00038;

    color: #fff;

    font-size: 10px;

    font-weight: 800;

    display: inline-flex;

    align-items: center;

    gap: 7px;
}


.accept-btn:hover {

    background: #c90032;
}


.active-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 13px;

    background: #e00038;

    color: #fff;

    border-radius: 8px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 800;
}


.empty {

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 55px 20px;

    text-align: center;

    margin-bottom: 30px;
}


.empty-icon {

    width: 64px;

    height: 64px;

    margin:
        0 auto 15px;

    border-radius: 50%;

    background: #fff0f3;

    color: #e00038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;
}


.empty h3 {

    margin:
        0 0 7px;

    font-size: 16px;
}


.empty p {

    margin: 0 auto;

    max-width: 450px;

    color: #888;

    font-size: 11px;

    line-height: 1.6;
}


@media (max-width: 950px) {

    .rider-orders-page {

        margin-left: 0;

        padding: 20px;
    }


    .orders-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .summary-grid {

        grid-template-columns: 1fr;
    }


    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .rider-orders-page {

        padding: 15px;
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


<main class="rider-orders-page">


<header class="page-header">

    <div class="page-title">

        <h1>
            Delivery Orders
        </h1>

        <p>
            Accept available orders and manage your deliveries.
        </p>

    </div>


    <a
        href="rider-orders.php"
        class="refresh-btn"
    >

        <i class="fas fa-rotate"></i>

        Refresh

    </a>

</header>


<?php if ($message !== ''): ?>

<div
    class="
        flash
        <?= $messageType === 'success'
            ? 'success'
            : 'error'
        ?>
    "
>

    <?php if ($messageType === 'success'): ?>

        <i class="fas fa-circle-check"></i>

    <?php else: ?>

        <i class="fas fa-circle-exclamation"></i>

    <?php endif; ?>


    <?= riderOrderEscape(
        $message
    ) ?>

</div>

<?php endif; ?>


<?php if (!$isApproved): ?>


<div class="flash error">

    <i class="fas fa-user-clock"></i>

    Your rider account is not approved/active.
    You cannot accept delivery orders yet.

</div>


<?php endif; ?>


<section class="summary-grid">


<div class="summary-card">

    <div class="summary-label">
        Available Orders
    </div>

    <div class="summary-value">

        <?= count(
            $availableOrders
        ) ?>

    </div>

</div>


<div class="summary-card">

    <div class="summary-label">
        My Active Deliveries
    </div>

    <div class="summary-value">

        <?= count(
            $myOrders
        ) ?>

    </div>

</div>


<div class="summary-card">

    <div class="summary-label">
        Rider Status
    </div>

    <div class="summary-value">

        <?= riderOrderEscape(
            ucfirst(
                $riderStatus
            )
        ) ?>

    </div>

</div>


</section>


<section>


<div class="section-title">

    <h2>
        Available Orders
    </h2>

    <span>
        Ready for pickup
    </span>

</div>


<?php if (!$isApproved): ?>


<div class="empty">

    <div class="empty-icon">

        <i class="fas fa-lock"></i>

    </div>


    <h3>
        Orders unavailable
    </h3>


    <p>
        Your rider account must be approved
        before available delivery orders can be accepted.
    </p>

</div>


<?php elseif (
    empty($availableOrders)
): ?>


<div class="empty">

    <div class="empty-icon">

        <i class="fas fa-box-open"></i>

    </div>


    <h3>
        No orders available
    </h3>


    <p>
        There are currently no restaurant orders
        ready for pickup. Please refresh the page
        when new orders become available.
    </p>

</div>


<?php else: ?>


<div class="orders-grid">


<?php foreach (
    $availableOrders
    as $order
): ?>


<?php

$orderId =
    (int)(
        $order['id']
        ?? 0
    );


$orderNumber =
    riderOrderValue(
        $order,
        [
            'order_number',
            'order_no',
            'reference'
        ],
        '#' . $orderId
    );


$orderStatus =
    riderOrderValue(
        $order,
        [
            $statusColumn,
            'order_status',
            'status'
        ],
        'Ready for Pickup'
    );


$orderTotal =
    (float)(
        riderOrderValue(
            $order,
            [
                'total',
                'grand_total',
                'order_total'
            ],
            0
        )
    );


$paymentMethod =
    riderOrderValue(
        $order,
        [
            'payment_method',
            'payment_type'
        ],
        'Not specified'
    );


$restaurantId =
    (int)(
        $order['restaurant_id']
        ?? 0
    );


$addressId =
    (int)(
        $order['address_id']
        ?? 0
    );


$restaurant =
    riderGetRestaurant(
        $conn,
        $restaurantId,
        $restaurantCache
    );


$deliveryAddress =
    riderGetAddress(
        $conn,
        $addressId,
        $addressCache
    );

?>


<article class="order-card">


<header class="order-header">

    <div class="order-number">

        <?= riderOrderEscape(
            $orderNumber
        ) ?>

    </div>


    <div class="status">

        <?= riderOrderEscape(
            $orderStatus
        ) ?>

    </div>

</header>


<div class="order-body">


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-store"></i>

    </div>


    <div>

        <span class="info-label">
            Restaurant
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $restaurant['name']
            ) ?>

            <br>

            <?= riderOrderEscape(
                $restaurant['address']
            ) ?>

        </div>

    </div>

</div>


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-location-dot"></i>

    </div>


    <div>

        <span class="info-label">
            Delivery Address
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $deliveryAddress
            ) ?>

        </div>

    </div>

</div>


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-credit-card"></i>

    </div>


    <div>

        <span class="info-label">
            Payment
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $paymentMethod
            ) ?>

        </div>

    </div>

</div>


</div>


<footer class="order-footer">


<div class="order-total">

    Rs.
    <?= number_format(
        $orderTotal,
        0
    ) ?>

</div>


<form
    method="POST"
    class="accept-form"
    onsubmit="
        return confirm(
            'Are you sure you want to accept this delivery?'
        );
    "
>

    <input
        type="hidden"
        name="order_id"
        value="<?= $orderId ?>"
    >


    <button
        type="submit"
        name="accept_delivery"
        value="1"
        class="accept-btn"
    >

        <i class="fas fa-motorcycle"></i>

        Accept Delivery

    </button>

</form>


</footer>


</article>


<?php endforeach; ?>


</div>


<?php endif; ?>


</section>


<section>


<div class="section-title">

    <h2>
        My Active Deliveries
    </h2>

    <span>
        Your accepted orders
    </span>

</div>


<?php if (
    empty($myOrders)
): ?>


<div class="empty">

    <div class="empty-icon">

        <i class="fas fa-motorcycle"></i>

    </div>


    <h3>
        No active deliveries
    </h3>


    <p>
        When you accept a delivery,
        it will appear here.
    </p>

</div>


<?php else: ?>


<div class="orders-grid">


<?php foreach (
    $myOrders
    as $order
): ?>


<?php

$orderId =
    (int)(
        $order['id']
        ?? 0
    );


$orderNumber =
    riderOrderValue(
        $order,
        [
            'order_number',
            'order_no',
            'reference'
        ],
        '#' . $orderId
    );


$orderStatus =
    riderOrderValue(
        $order,
        [
            $statusColumn,
            'order_status',
            'status'
        ],
        'Assigned'
    );


$orderTotal =
    (float)(
        riderOrderValue(
            $order,
            [
                'total',
                'grand_total',
                'order_total'
            ],
            0
        )
    );


$paymentMethod =
    riderOrderValue(
        $order,
        [
            'payment_method',
            'payment_type'
        ],
        'Not specified'
    );


$restaurantId =
    (int)(
        $order['restaurant_id']
        ?? 0
    );


$addressId =
    (int)(
        $order['address_id']
        ?? 0
    );


$restaurant =
    riderGetRestaurant(
        $conn,
        $restaurantId,
        $restaurantCache
    );


$deliveryAddress =
    riderGetAddress(
        $conn,
        $addressId,
        $addressCache
    );

?>


<article class="order-card">


<header class="order-header">

    <div class="order-number">

        <?= riderOrderEscape(
            $orderNumber
        ) ?>

    </div>


    <div class="status">

        <?= riderOrderEscape(
            $orderStatus
        ) ?>

    </div>

</header>


<div class="order-body">


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-store"></i>

    </div>


    <div>

        <span class="info-label">
            Pickup
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $restaurant['name']
            ) ?>

            <br>

            <?= riderOrderEscape(
                $restaurant['address']
            ) ?>

        </div>

    </div>

</div>


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-location-dot"></i>

    </div>


    <div>

        <span class="info-label">
            Customer
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $deliveryAddress
            ) ?>

        </div>

    </div>

</div>


<div class="info-row">

    <div class="info-icon">

        <i class="fas fa-credit-card"></i>

    </div>


    <div>

        <span class="info-label">
            Payment
        </span>

        <div class="info-value">

            <?= riderOrderEscape(
                $paymentMethod
            ) ?>

        </div>

    </div>

</div>


</div>


<footer class="order-footer">


<div class="order-total">

    Rs.
    <?= number_format(
        $orderTotal,
        0
    ) ?>

</div>


<a
    href="rider-delivery.php?order_id=<?= $orderId ?>"
    class="active-btn"
>

    <i class="fas fa-route"></i>

    Manage Delivery

</a>


</footer>


</article>


<?php endforeach; ?>


</div>


<?php endif; ?>


</section>


</main>


</body>

</html>