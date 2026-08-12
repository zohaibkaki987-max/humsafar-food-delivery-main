<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR - RESTAURANT OWNER ORDER MANAGEMENT
| File:
| restaurant/restaurant-owner-orders.php
|
| Auto Rider Assignment:
| Restaurant accepts order -> preparing -> nearest available rider
| is assigned immediately. Food preparation does not wait for "Ready".
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   HELPER
========================================================= */
function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =========================================================
   DATABASE CHECK
========================================================= */
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

/* =========================================================
   FIND OWNER
========================================================= */
$owner = null;
$ownerId = 0;
$ownerEmail = '';

if (!empty($_SESSION['restaurant_owner_id'])) {
    $ownerId = (int)$_SESSION['restaurant_owner_id'];
}

if ($ownerId <= 0 && !empty($_SESSION['restaurant_user_id'])) {
    $ownerId = (int)$_SESSION['restaurant_user_id'];
}

if ($ownerId <= 0 && !empty($_SESSION['owner_id'])) {
    $ownerId = (int)$_SESSION['owner_id'];
}

if (!empty($_SESSION['restaurant_owner_email'])) {
    $ownerEmail = trim((string)$_SESSION['restaurant_owner_email']);
}

if ($ownerEmail === '' && !empty($_SESSION['email'])) {
    $ownerEmail = trim((string)$_SESSION['email']);
}

/* =========================================================
   OWNER BY ID
========================================================= */
if ($ownerId > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $ownerId);
        $stmt->execute();

        $owner = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

/* =========================================================
   OWNER BY EMAIL
========================================================= */
if (!$owner && $ownerEmail !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status
        FROM restaurant_users
        WHERE email = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("s", $ownerEmail);
        $stmt->execute();

        $owner = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

/* =========================================================
   OWNER NOT FOUND
========================================================= */
if (!$owner) {
    header('Location: restaurant-owner-login.php');
    exit;
}

/* =========================================================
   OWNER DATA
========================================================= */
$ownerId = (int)$owner['id'];

$ownerName = trim(
    (string)$owner['full_name']
);

$restaurantName = trim(
    (string)$owner['restaurant_name']
);

$ownerStatus = strtolower(
    trim(
        (string)$owner['status']
    )
);

/* =========================================================
   APPROVAL CHECK
========================================================= */
$isApproved = in_array(
    $ownerStatus,
    array(
        'approved',
        'active'
    ),
    true
);

if (!$isApproved) {
    header('Location: restaurant-owner-dashboard.php');
    exit;
}

/* =========================================================
   FIND RESTAURANT
   Includes latitude/longitude added for rider matching.
========================================================= */
$restaurant = null;
$restaurantId = 0;

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        status,
        latitude,
        longitude
    FROM restaurants
    WHERE name = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "s",
        $restaurantName
    );

    $stmt->execute();

    $restaurant =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

if ($restaurant) {
    $restaurantId = (int)$restaurant['id'];
}

/* =========================================================
   MESSAGES
========================================================= */
$successMessage = '';
$errorMessage = '';

/* =========================================================
   AUTO FIND NEAREST RIDER
========================================================= */
function autoAssignNearestRider(
    mysqli $conn,
    int $orderId,
    int $restaurantId,
    string &$infoMessage,
    string &$errorMessage
): bool {

    $infoMessage = '';
    $errorMessage = '';

    if ($orderId <= 0 || $restaurantId <= 0) {
        $errorMessage = 'Invalid order or restaurant.';
        return false;
    }

    /* ---------------------------------------------------------
       Get restaurant coordinates
    --------------------------------------------------------- */
    $stmt = $conn->prepare("
        SELECT
            latitude,
            longitude
        FROM restaurants
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $errorMessage = 'Could not prepare restaurant location query.';
        return false;
    }

    $stmt->bind_param(
        "i",
        $restaurantId
    );

    $stmt->execute();

    $restaurant =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (
        !$restaurant ||
        $restaurant['latitude'] === null ||
        $restaurant['longitude'] === null ||
        $restaurant['latitude'] === '' ||
        $restaurant['longitude'] === ''
    ) {

        $errorMessage =
            'Restaurant location is not set. Please add latitude and longitude first.';

        return false;
    }

    $restaurantLat =
        (float)$restaurant['latitude'];

    $restaurantLng =
        (float)$restaurant['longitude'];

    /* ---------------------------------------------------------
       Transaction
    --------------------------------------------------------- */
    $conn->begin_transaction();

    try {

        /* -----------------------------------------------------
           Confirm order belongs to restaurant
        ----------------------------------------------------- */
        $stmt = $conn->prepare("
            SELECT
                id,
                order_status,
                delivery_fee,
                total
            FROM orders
            WHERE id = ?
              AND restaurant_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not prepare order query.'
            );
        }

        $stmt->bind_param(
            "ii",
            $orderId,
            $restaurantId
        );

        $stmt->execute();

        $order =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$order) {
            throw new Exception(
                'Order not found.'
            );
        }

        /* -----------------------------------------------------
           Do not create duplicate active assignment
        ----------------------------------------------------- */
        $stmt = $conn->prepare("
            SELECT
                id,
                rider_id,
                status
            FROM rider_deliveries
            WHERE order_id = ?
              AND status IN (
                  'assigned',
                  'accepted',
                  'picked_up',
                  'on_the_way'
              )
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not check existing rider assignment.'
            );
        }

        $stmt->bind_param(
            "i",
            $orderId
        );

        $stmt->execute();

        $existingAssignment =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($existingAssignment) {

            $conn->commit();

            $infoMessage =
                'A rider is already assigned to this order.';

            return true;
        }

        /* -----------------------------------------------------
           Find nearest available rider
           Requirements:
           - active/approved account
           - availability_status = available
           - latest rider location
           - no active delivery
        ----------------------------------------------------- */
        $sql = "
            SELECT
                r.id AS rider_id,
                r.full_name,
                r.phone,
                rl.latitude AS rider_latitude,
                rl.longitude AS rider_longitude,

                (
                    6371 * ACOS(
                        LEAST(
                            1,
                            GREATEST(
                                -1,
                                COS(RADIANS(?))
                                * COS(RADIANS(rl.latitude))
                                * COS(
                                    RADIANS(rl.longitude)
                                    - RADIANS(?)
                                )
                                + SIN(RADIANS(?))
                                * SIN(RADIANS(rl.latitude))
                            )
                        )
                    )
                ) AS distance_km

            FROM riders r

            INNER JOIN rider_locations rl
                ON rl.id = (
                    SELECT
                        rl2.id
                    FROM rider_locations rl2
                    WHERE rl2.rider_id = r.id
                    ORDER BY rl2.id DESC
                    LIMIT 1
                )

            WHERE LOWER(
                      TRIM(r.status)
                  ) IN (
                      'active',
                      'approved'
                  )

              AND LOWER(
                      TRIM(r.availability_status)
                  ) = 'available'

              AND rl.latitude IS NOT NULL
              AND rl.longitude IS NOT NULL

              AND NOT EXISTS (
                    SELECT 1
                    FROM rider_deliveries rd
                    WHERE rd.rider_id = r.id
                      AND rd.status IN (
                          'assigned',
                          'accepted',
                          'picked_up',
                          'on_the_way'
                      )
              )

            ORDER BY
                distance_km ASC,
                r.id ASC

            LIMIT 1

            FOR UPDATE
        ";

        $stmt =
            $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Could not prepare rider matching query: ' .
                $conn->error
            );
        }

        $stmt->bind_param(
            "ddd",
            $restaurantLat,
            $restaurantLng,
            $restaurantLat
        );

        $stmt->execute();

        $rider =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        /* -----------------------------------------------------
           No rider available
        ----------------------------------------------------- */
        if (!$rider) {

            $conn->commit();

            $infoMessage =
                'Order is preparing. No available rider was found right now.';

            return false;
        }

        $riderId =
            (int)$rider['rider_id'];

        $deliveryFee =
            isset($order['delivery_fee'])
                ? (float)$order['delivery_fee']
                : 0.00;

        /*
         * Initial earning rule:
         * rider earning = delivery fee.
         * We can change this later to a fixed/percentage rule.
         */
        $riderEarning =
            $deliveryFee;

        /* -----------------------------------------------------
           Create rider delivery
        ----------------------------------------------------- */
        $stmt = $conn->prepare("
            INSERT INTO rider_deliveries
            (
                rider_id,
                order_id,
                status,
                delivery_fee,
                rider_earning,
                assigned_at
            )
            VALUES
            (
                ?,
                ?,
                'assigned',
                ?,
                ?,
                NOW()
            )
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not prepare rider assignment.'
            );
        }

        $stmt->bind_param(
            "iidd",
            $riderId,
            $orderId,
            $deliveryFee,
            $riderEarning
        );

        if (!$stmt->execute()) {
            throw new Exception(
                'Could not create rider assignment: ' .
                $stmt->error
            );
        }

        $stmt->close();

        /* -----------------------------------------------------
           Mark rider busy
        ----------------------------------------------------- */
        $stmt = $conn->prepare("
            UPDATE riders
            SET availability_status = 'busy'
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not prepare rider availability update.'
            );
        }

        $stmt->bind_param(
            "i",
            $riderId
        );

        if (!$stmt->execute()) {
            throw new Exception(
                'Could not mark rider busy: ' .
                $stmt->error
            );
        }

        $stmt->close();

        /* -----------------------------------------------------
           Mark order as rider_assigned
        ----------------------------------------------------- */
        $assignedStatus =
            'rider_assigned';

        $stmt = $conn->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
              AND restaurant_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Could not prepare order assignment update.'
            );
        }

        $stmt->bind_param(
            "sii",
            $assignedStatus,
            $orderId,
            $restaurantId
        );

        if (!$stmt->execute()) {
            throw new Exception(
                'Could not update order status: ' .
                $stmt->error
            );
        }

        $stmt->close();

        /* -----------------------------------------------------
           Done
        ----------------------------------------------------- */
        $conn->commit();

        $distance =
            number_format(
                (float)$rider['distance_km'],
                2
            );

        $infoMessage =
            'Order assigned to ' .
            $rider['full_name'] .
            ' (' .
            $distance .
            ' km from restaurant).';

        return true;

    } catch (Throwable $e) {

        $conn->rollback();

        $errorMessage =
            $e->getMessage();

        return false;
    }
}

/* =========================================================
   UPDATE ORDER STATUS
========================================================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_order_status'])
) {

    $orderId =
        isset($_POST['order_id'])
            ? (int)$_POST['order_id']
            : 0;

    $newStatus =
        isset($_POST['order_status'])
            ? trim(
                (string)$_POST['order_status']
            )
            : '';

    $allowedStatuses =
        array(
            'pending',
            'confirmed',
            'preparing',
            'rider_assigned',
            'ready_for_pickup',
            'picked_up',
            'out_for_delivery',
            'on_the_way',
            'delivered',
            'cancelled'
        );

    if ($orderId <= 0) {

        $errorMessage =
            'Invalid order selected.';

    } elseif (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        $errorMessage =
            'Invalid order status.';

    } elseif ($restaurantId <= 0) {

        $errorMessage =
            'Restaurant record not found.';

    } else {

        /*
         * Our final workflow:
         *
         * Confirmed = restaurant accepted.
         * We immediately move it to preparing and start rider search.
         *
         * Preparing selected manually = also start rider search.
         */
        $shouldFindRider =
            in_array(
                $newStatus,
                array(
                    'confirmed',
                    'preparing'
                ),
                true
            );

        /*
         * "confirmed" is treated as "Accepted + Preparing".
         */
        $statusToSave =
            $newStatus === 'confirmed'
                ? 'preparing'
                : $newStatus;

        $stmt = $conn->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
              AND restaurant_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $errorMessage =
                'Database error: ' .
                $conn->error;

        } else {

            $stmt->bind_param(
                "sii",
                $statusToSave,
                $orderId,
                $restaurantId
            );

            if ($stmt->execute()) {

                $stmt->close();

                /*
                 * Start automatic rider matching immediately.
                 */
                if ($shouldFindRider) {

                    $assignmentInfo =
                        '';

                    $assignmentError =
                        '';

                    $assigned =
                        autoAssignNearestRider(
                            $conn,
                            $orderId,
                            $restaurantId,
                            $assignmentInfo,
                            $assignmentError
                        );

                    if ($assigned) {

                        $successMessage =
                            'Order accepted and rider assigned. ' .
                            $assignmentInfo;

                    } elseif (
                        $assignmentError !== ''
                    ) {

                        $successMessage =
                            'Order accepted and is preparing.';

                        $errorMessage =
                            'Rider assignment failed: ' .
                            $assignmentError;

                    } else {

                        $successMessage =
                            'Order accepted and is preparing. ' .
                            $assignmentInfo;
                    }

                } else {

                    $successMessage =
                        'Order status updated successfully.';
                }

            } else {

                $errorMessage =
                    'Could not update order status: ' .
                    $stmt->error;

                $stmt->close();
            }
        }
    }
}

/* =========================================================
   GET RESTAURANT ORDERS
========================================================= */
$orders = array();

if ($restaurantId > 0) {

    $stmt = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.restaurant_id,
            o.address_id,
            o.payment_method,
            o.subtotal,
            o.delivery_fee,
            o.discount,
            o.total,
            o.order_status,
            o.customer_note,
            o.created_at,

            u.full_name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone

        FROM orders o

        LEFT JOIN users u
            ON o.user_id = u.id

        WHERE o.restaurant_id = ?

        ORDER BY o.id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $restaurantId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $orders[] =
                $row;
        }

        $stmt->close();
    }
}

/* =========================================================
   GET ORDER ITEMS
========================================================= */
$orderItems = array();

foreach ($orders as $order) {

    $orderId =
        (int)$order['id'];

    $orderItems[$orderId] =
        array();

    $stmt = $conn->prepare("
        SELECT
            id,
            item_name,
            item_price,
            quantity,
            subtotal
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $orderId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $item =
            $result->fetch_assoc()
        ) {

            $orderItems[$orderId][] =
                $item;
        }

        $stmt->close();
    }
}

/* =========================================================
   GET CUSTOMER ADDRESSES
========================================================= */
$orderAddresses = array();

foreach ($orders as $order) {

    $orderId =
        (int)$order['id'];

    $addressId =
        (int)$order['address_id'];

    $orderAddresses[$orderId] =
        null;

    if ($addressId <= 0) {
        continue;
    }

    $stmt = $conn->prepare("
        SELECT
            address_title,
            address_line,
            city,
            area,
            phone
        FROM customer_addresses
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $addressId
        );

        $stmt->execute();

        $orderAddresses[$orderId] =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

/* =========================================================
   STATISTICS
========================================================= */
$totalOrders =
    count($orders);

$pendingOrders = 0;
$confirmedOrders = 0;
$preparingOrders = 0;
$deliveryOrders = 0;
$completedOrders = 0;

foreach ($orders as $order) {

    $status =
        strtolower(
            trim(
                (string)$order['order_status']
            )
        );

    if ($status === 'pending') {

        $pendingOrders++;

    } elseif (
        in_array(
            $status,
            array(
                'confirmed',
                'accepted'
            ),
            true
        )
    ) {

        $confirmedOrders++;

    } elseif (
        in_array(
            $status,
            array(
                'preparing',
                'rider_assigned',
                'ready_for_pickup'
            ),
            true
        )
    ) {

        $preparingOrders++;

    } elseif (
        in_array(
            $status,
            array(
                'out_for_delivery',
                'on_the_way',
                'picked_up'
            ),
            true
        )
    ) {

        $deliveryOrders++;

    } elseif (
        in_array(
            $status,
            array(
                'delivered',
                'completed'
            ),
            true
        )
    ) {

        $completedOrders++;
    }
}

/* =========================================================
   TOTAL SALES
========================================================= */
$totalSales = 0;

foreach ($orders as $order) {

    $status =
        strtolower(
            trim(
                (string)$order['order_status']
            )
        );

    if (
        $status !== 'cancelled' &&
        $status !== 'canceled'
    ) {

        $totalSales +=
            (float)$order['total'];
    }
}

/* =========================================================
   STATUS FUNCTIONS
========================================================= */
function orderStatusLabel($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            strtolower(
                trim(
                    (string)$status
                )
            )
        )
    );
}

function orderStatusClass($status)
{
    $status =
        strtolower(
            trim(
                (string)$status
            )
        );

    switch ($status) {

        case 'pending':
            return 'pending';

        case 'confirmed':
        case 'accepted':
            return 'confirmed';

        case 'preparing':
        case 'rider_assigned':
        case 'ready_for_pickup':
            return 'preparing';

        case 'out_for_delivery':
        case 'on_the_way':
        case 'picked_up':
            return 'delivery';

        case 'delivered':
        case 'completed':
            return 'delivered';

        case 'cancelled':
        case 'canceled':
            return 'cancelled';

        default:
            return 'default';
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
    Manage Orders - Humsafar
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

    background: #f5f6fa;

    color: #111827;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}

.topbar {

    position: fixed;

    left: 223px;

    right: 0;

    top: 0;

    height: 64px;

    background: #fff;

    border-bottom:
        1px solid #e5e7eb;

    z-index: 90;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    padding:
        0 25px;
}

.portal-label {

    font-size: 8px;

    color: #9ca3af;

    font-weight: 800;

    letter-spacing: 1.6px;

    text-transform:
        uppercase;
}

.page-top-title {

    margin-top: 4px;

    font-size: 14px;

    font-weight: 800;
}

.top-right {

    display: flex;

    align-items: center;

    gap: 14px;
}

.notification {

    width: 34px;

    height: 34px;

    border:
        1px solid #e5e7eb;

    border-radius: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #6b7280;
}

.top-avatar {

    width: 31px;

    height: 31px;

    background: #ffc400;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 800;
}

.top-user {

    font-size: 9px;

    font-weight: 800;
}

.top-role {

    font-size: 7px;

    color: #9ca3af;

    margin-top: 2px;
}

.main {

    margin-left: 223px;

    padding-top: 64px;

    min-height: 100vh;
}

.content {

    padding:
        31px
        27px
        60px;
}

.page-heading {

    margin-bottom: 22px;
}

.page-eyebrow {

    color: #ef003c;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: 1.5px;

    text-transform:
        uppercase;
}

.page-heading h1 {

    margin:
        7px
        0
        5px;

    font-size: 27px;

    line-height: 1.1;
}

.page-heading p {

    margin: 0;

    color: #8a94a6;

    font-size: 11px;
}

.restaurant-chip {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    background: #fff0f4;

    color: #ef003c;

    padding:
        7px 11px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 800;

    margin-top: 12px;
}

.message {

    padding:
        13px
        16px;

    border-radius: 9px;

    margin-bottom: 18px;

    font-size: 11px;

    font-weight: 700;
}

.message.success {

    background: #eaf8ef;

    color: #17733e;
}

.message.error {

    background: #fff0f1;

    color: #c82333;
}

.stats {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 13px;

    margin-bottom: 25px;
}

.stat-card {

    background: #fff;

    border:
        1px solid #e3e6eb;

    border-radius: 11px;

    padding: 17px;
}

.stat-icon {

    width: 34px;

    height: 34px;

    background: #fff0f4;

    color: #ef003c;

    border-radius: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    margin-bottom: 12px;
}

.stat-number {

    font-size: 22px;

    font-weight: 800;
}

.stat-label {

    margin-top: 5px;

    color: #9299a6;

    font-size: 9px;
}

.section-header {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    margin-bottom: 12px;
}

.section-title {

    font-size: 14px;

    font-weight: 800;
}

.section-subtitle {

    color: #9ca3af;

    font-size: 9px;

    margin-top: 4px;
}

.order-count {

    background: #fff0f4;

    color: #ef003c;

    padding:
        7px 11px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 800;
}

.order-card {

    background: #fff;

    border:
        1px solid #e2e5ea;

    border-radius: 12px;

    margin-bottom: 16px;

    overflow: hidden;
}

.order-header {

    min-height: 67px;

    padding:
        14px 17px;

    border-bottom:
        1px solid #edf0f3;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 15px;
}

.order-left {

    display: flex;

    align-items: center;

    gap: 12px;
}

.order-icon {

    width: 38px;

    height: 38px;

    border-radius: 9px;

    background: #fff0f4;

    color: #ef003c;

    display: flex;

    align-items: center;

    justify-content: center;
}

.order-number {

    font-size: 13px;

    font-weight: 800;
}

.order-date {

    color: #9ca3af;

    font-size: 8px;

    margin-top: 4px;
}

.status {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        7px 10px;

    border-radius: 18px;

    font-size: 8px;

    font-weight: 800;

    text-transform:
        uppercase;
}

.status.pending {

    background: #fff5dc;

    color: #9c7000;
}

.status.confirmed {

    background: #eaf4ff;

    color: #2671a8;
}

.status.preparing {

    background: #fff0df;

    color: #ac6200;
}

.status.delivery {

    background: #f1ebff;

    color: #6742a4;
}

.status.delivered {

    background: #e7f8ed;

    color: #18733e;
}

.status.cancelled {

    background: #fff0f1;

    color: #c82333;
}

.status.default {

    background: #f0f1f3;

    color: #6b7280;
}

.order-body {

    padding: 17px;
}

.order-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    margin-bottom: 14px;
}

.info-box {

    background: #fafbfc;

    border:
        1px solid #edf0f3;

    border-radius: 9px;

    padding: 13px;
}

.info-title {

    color: #9299a6;

    font-size: 8px;

    font-weight: 800;

    text-transform:
        uppercase;

    margin-bottom: 7px;
}

.info-main {

    font-size: 11px;

    font-weight: 800;

    line-height: 1.4;
}

.info-small {

    color: #6b7280;

    font-size: 9px;

    line-height: 1.5;

    margin-top: 4px;
}

.items-box {

    border:
        1px solid #edf0f3;

    border-radius: 9px;

    overflow: hidden;

    margin-bottom: 14px;
}

.items-heading {

    padding:
        10px 13px;

    background: #fafbfc;

    border-bottom:
        1px solid #edf0f3;

    font-size: 9px;

    font-weight: 800;
}

.item-row {

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 12px;

    padding:
        11px 13px;

    border-bottom:
        1px solid #edf0f3;
}

.item-row:last-child {

    border-bottom: 0;
}

.item-name {

    font-size: 10px;

    font-weight: 800;
}

.item-meta {

    color: #8a94a6;

    font-size: 8px;

    margin-top: 3px;
}

.item-price {

    color: #ef003c;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}

.bottom-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 13px;
}

.customer-note {

    background: #fffaf0;

    border:
        1px solid #f4e5bc;

    border-radius: 9px;

    padding: 13px;
}

.customer-note-title {

    font-size: 9px;

    font-weight: 800;

    color: #8d6900;

    margin-bottom: 6px;
}

.customer-note-text {

    font-size: 9px;

    line-height: 1.5;

    color: #6f5c2b;
}

.total-box {

    background: #fff5f8;

    border-radius: 9px;

    padding: 13px;
}

.total-row {

    display: flex;

    justify-content:
        space-between;

    padding: 5px 0;

    font-size: 9px;

    color: #697180;
}

.total-row strong {

    color: #111827;
}

.total-row.grand {

    border-top:
        1px solid #f0dbe2;

    margin-top: 6px;

    padding-top: 10px;

    color: #ef003c;

    font-size: 13px;

    font-weight: 800;
}

.total-row.grand strong {

    color: #ef003c;
}

.status-control {

    margin-top: 15px;

    padding-top: 15px;

    border-top:
        1px solid #edf0f3;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    gap: 12px;
}

.status-label {

    font-size: 9px;

    font-weight: 800;
}

.status-form {

    display: flex;

    gap: 8px;
}

.status-form select {

    height: 37px;

    min-width: 200px;

    border:
        1px solid #dfe3e8;

    border-radius: 7px;

    padding:
        0 10px;

    background: #fff;

    font-size: 9px;

    outline: none;
}

.update-btn {

    height: 37px;

    border: 0;

    border-radius: 7px;

    background: #ef003c;

    color: #fff;

    padding:
        0 15px;

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;
}

.update-btn:hover {

    background: #d90035;
}

.auto-rider-note {

    margin-top: 12px;

    padding:
        10px 12px;

    border-radius: 8px;

    background: #f2ecff;

    color: #6843a5;

    font-size: 9px;

    line-height: 1.5;

    font-weight: 700;
}

.empty {

    background: #fff;

    border:
        1px solid #e3e6eb;

    border-radius: 12px;

    padding: 70px 20px;

    text-align: center;
}

.empty-icon {

    width: 58px;

    height: 58px;

    margin:
        0 auto 15px;

    background: #fff0f4;

    color: #ef003c;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;
}

.empty h2 {

    margin:
        0 0 7px;

    font-size: 17px;
}

.empty p {

    margin: 0;

    color: #9ca3af;

    font-size: 10px;
}

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .order-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 850px) {

    .sidebar {

        width: 70px;
    }

    .brand-text,
    .brand-sub,
    .menu-title,
    .nav-item span,
    .sidebar-bottom .profile-info {

        display: none;
    }

    .brand {

        justify-content: center;

        padding: 10px;
    }

    .nav-item {

        justify-content: center;

        padding:
            14px 5px;
    }

    .sidebar-bottom {

        justify-content: center;
    }

    .topbar {

        left: 70px;
    }

    .main {

        margin-left: 70px;
    }

    .bottom-grid {

        grid-template-columns:
            1fr;
    }
}

@media (max-width: 650px) {

    .stats {

        grid-template-columns:
            1fr 1fr;
    }

    .content {

        padding:
            20px 14px 50px;
    }

    .order-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .order-grid {

        grid-template-columns:
            1fr;
    }

    .status-control {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .status-form {

        width: 100%;
    }

    .status-form select {

        flex: 1;

        min-width: 0;
    }

    .update-btn {

        padding:
            0 12px;
    }

    .top-user,
    .top-role {

        display: none;
    }
}

</style>

</head>

<body>

<?php
/*
|--------------------------------------------------------------------------
| EXISTING RESTAURANT SIDEBAR
|--------------------------------------------------------------------------
*/
include __DIR__ . '/restaurant-sidebar.php';
?>

<!-- ======================================================
     TOPBAR
====================================================== -->

<header class="topbar">

    <div>

        <div class="portal-label">
            RESTAURANT PARTNER PORTAL
        </div>

        <div class="page-top-title">
            Order Management
        </div>

    </div>

    <div class="top-right">

        <div class="notification">
            <i class="far fa-bell"></i>
        </div>

        <div class="top-avatar">

            <?= h(
                strtoupper(
                    substr(
                        $ownerName !== ''
                            ? $ownerName
                            : 'Z',
                        0,
                        1
                    )
                )
            ) ?>

        </div>

        <div>

            <div class="top-user">

                <?= h(
                    $ownerName !== ''
                        ? $ownerName
                        : 'Restaurant Owner'
                ) ?>

            </div>

            <div class="top-role">
                Restaurant Owner
            </div>

        </div>

    </div>

</header>

<!-- ======================================================
     MAIN
====================================================== -->

<main class="main">

<div class="content">

    <!-- PAGE HEADING -->

    <section class="page-heading">

        <div class="page-eyebrow">
            ORDER MANAGEMENT
        </div>

        <h1>
            Manage Orders
        </h1>

        <p>
            View customer orders, order details and update order status.
        </p>

        <div class="restaurant-chip">

            <i class="fas fa-store"></i>

            <?= h(
                $restaurantName !== ''
                    ? $restaurantName
                    : 'Your Restaurant'
            ) ?>

        </div>

    </section>


    <!-- MESSAGES -->

    <?php if ($successMessage !== ''): ?>

        <div class="message success">

            <i class="fas fa-circle-check"></i>

            &nbsp;

            <?= h($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="message error">

            <i class="fas fa-circle-exclamation"></i>

            &nbsp;

            <?= h($errorMessage) ?>

        </div>

    <?php endif; ?>


    <!-- RESTAURANT NOT FOUND -->

    <?php if (!$restaurant): ?>

        <div class="empty">

            <div class="empty-icon">

                <i class="fas fa-store-slash"></i>

            </div>

            <h2>
                Restaurant Record Not Found
            </h2>

            <p>
                Your owner account is approved, but no restaurant
                is linked with this account.
            </p>

        </div>

    <?php else: ?>


        <!-- STATISTICS -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>

                <div class="stat-number">
                    <?= $totalOrders ?>
                </div>

                <div class="stat-label">
                    Total Orders
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>

                <div class="stat-number">
                    <?= $pendingOrders ?>
                </div>

                <div class="stat-label">
                    Pending Orders
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-circle-check"></i>
                </div>

                <div class="stat-number">
                    <?= $confirmedOrders ?>
                </div>

                <div class="stat-label">
                    Confirmed Orders
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-fire-burner"></i>
                </div>

                <div class="stat-number">
                    <?= $preparingOrders ?>
                </div>

                <div class="stat-label">
                    Preparing / Assigned
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>

                <div class="stat-number">
                    Rs.
                    <?= number_format(
                        $totalSales,
                        0
                    ) ?>
                </div>

                <div class="stat-label">
                    Total Order Value
                </div>

            </div>

        </section>


        <!-- ORDERS TITLE -->

        <div class="section-header">

            <div>

                <div class="section-title">
                    Customer Orders
                </div>

                <div class="section-subtitle">
                    Orders placed for your restaurant.
                </div>

            </div>

            <div class="order-count">

                <?= $totalOrders ?>

                Orders

            </div>

        </div>


        <!-- ORDERS -->

        <?php if (!empty($orders)): ?>

            <?php foreach ($orders as $order): ?>

                <?php

                $orderId =
                    (int)$order['id'];

                $currentStatus =
                    strtolower(
                        trim(
                            (string)$order['order_status']
                        )
                    );

                $customerName =
                    trim(
                        (string)(
                            $order['customer_name']
                            ?? ''
                        )
                    );

                if ($customerName === '') {
                    $customerName =
                        'Customer';
                }

                $orderNumber =
                    trim(
                        (string)(
                            $order['order_number']
                            ?? $orderId
                        )
                    );

                $items =
                    isset(
                        $orderItems[$orderId]
                    )
                        ? $orderItems[$orderId]
                        : array();

                $address =
                    isset(
                        $orderAddresses[$orderId]
                    )
                        ? $orderAddresses[$orderId]
                        : null;

                ?>

                <article class="order-card">

                    <!-- ORDER HEADER -->

                    <div class="order-header">

                        <div class="order-left">

                            <div class="order-icon">

                                <i class="fas fa-receipt"></i>

                            </div>

                            <div>

                                <div class="order-number">

                                    Order #

                                    <?= h(
                                        $orderNumber
                                    ) ?>

                                </div>

                                <div class="order-date">

                                    <?php
                                    if (
                                        !empty(
                                            $order['created_at']
                                        )
                                    ) {

                                        echo h(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            )
                                        );

                                    } else {

                                        echo '-';

                                    }
                                    ?>

                                </div>

                            </div>

                        </div>


                        <span
                            class="status
                            <?= h(
                                orderStatusClass(
                                    $currentStatus
                                )
                            ) ?>"
                        >

                            <i class="fas fa-circle"></i>

                            <?= h(
                                orderStatusLabel(
                                    $currentStatus
                                )
                            ) ?>

                        </span>

                    </div>


                    <!-- ORDER BODY -->

                    <div class="order-body">


                        <!-- CUSTOMER / ADDRESS / PAYMENT -->

                        <div class="order-grid">


                            <!-- CUSTOMER -->

                            <div class="info-box">

                                <div class="info-title">

                                    <i class="fas fa-user"></i>

                                    Customer

                                </div>

                                <div class="info-main">

                                    <?= h(
                                        $customerName
                                    ) ?>

                                </div>

                                <?php if (
                                    !empty(
                                        $order['customer_phone']
                                    )
                                ): ?>

                                    <div class="info-small">

                                        <i class="fas fa-phone"></i>

                                        <?= h(
                                            $order['customer_phone']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $order['customer_email']
                                    )
                                ): ?>

                                    <div class="info-small">

                                        <i class="fas fa-envelope"></i>

                                        <?= h(
                                            $order['customer_email']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- ADDRESS -->

                            <div class="info-box">

                                <div class="info-title">

                                    <i class="fas fa-location-dot"></i>

                                    Delivery Address

                                </div>

                                <?php if ($address): ?>

                                    <div class="info-main">

                                        <?= h(
                                            $address['address_title']
                                            ?? 'Delivery Address'
                                        ) ?>

                                    </div>

                                    <div class="info-small">

                                        <?= h(
                                            $address['address_line']
                                            ?? ''
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $address['area']
                                            )
                                        ): ?>

                                            <br>

                                            <?= h(
                                                $address['area']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $address['city']
                                            )
                                        ): ?>

                                            <br>

                                            <?= h(
                                                $address['city']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $address['phone']
                                            )
                                        ): ?>

                                            <br>

                                            Phone:

                                            <?= h(
                                                $address['phone']
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <div class="info-small">
                                        Address information not available.
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- PAYMENT -->

                            <div class="info-box">

                                <div class="info-title">

                                    <i class="fas fa-credit-card"></i>

                                    Payment

                                </div>

                                <div class="info-main">

                                    <?= h(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $order['payment_method']
                                                ?? 'Not specified'
                                            )
                                        )
                                    ) ?>

                                </div>

                                <div class="info-small">

                                    Order Total

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['total'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                        </div>


                        <!-- ORDER ITEMS -->

                        <div class="items-box">

                            <div class="items-heading">

                                <i class="fas fa-utensils"></i>

                                &nbsp;

                                Order Items

                            </div>


                            <?php if (!empty($items)): ?>

                                <?php foreach (
                                    $items
                                    as $item
                                ): ?>

                                    <div class="item-row">

                                        <div>

                                            <div class="item-name">

                                                <?= h(
                                                    $item['item_name']
                                                ) ?>

                                            </div>

                                            <div class="item-meta">

                                                Qty:

                                                <?= h(
                                                    $item['quantity']
                                                ) ?>

                                                × Rs.

                                                <?= number_format(
                                                    (float)$item['item_price'],
                                                    2
                                                ) ?>

                                            </div>

                                        </div>


                                        <div class="item-price">

                                            Rs.

                                            <?= number_format(
                                                (float)$item['subtotal'],
                                                2
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="item-row">

                                    <div class="item-meta">
                                        No order items found.
                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- BOTTOM -->

                        <div class="bottom-grid">

                            <div>

                                <?php if (
                                    !empty(
                                        $order['customer_note']
                                    )
                                ): ?>

                                    <div class="customer-note">

                                        <div class="customer-note-title">

                                            <i class="fas fa-note-sticky"></i>

                                            Customer Note

                                        </div>

                                        <div class="customer-note-text">

                                            <?= nl2br(
                                                h(
                                                    $order['customer_note']
                                                )
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- TOTAL -->

                            <div class="total-box">

                                <div class="total-row">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['subtotal'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="total-row">

                                    <span>
                                        Delivery Fee
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['delivery_fee'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="total-row">

                                    <span>
                                        Discount
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['discount'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="total-row grand">

                                    <span>
                                        Total
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['total'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>

                            </div>

                        </div>


                        <!-- AUTO RIDER STATUS -->

                        <?php if (
                            $currentStatus === 'rider_assigned'
                        ): ?>

                            <div class="auto-rider-note">

                                <i class="fas fa-motorcycle"></i>

                                Rider automatically assigned.
                                The rider can now see the delivery
                                request from the rider panel.

                            </div>

                        <?php elseif (
                            $currentStatus === 'preparing'
                        ): ?>

                            <div class="auto-rider-note">

                                <i class="fas fa-magnifying-glass-location"></i>

                                Order is preparing.
                                The system searches for an available
                                rider immediately when the order is accepted.

                            </div>

                        <?php endif; ?>


                        <!-- STATUS -->

                        <div class="status-control">

                            <div class="status-label">

                                <i class="fas fa-arrows-rotate"></i>

                                Update Order Status

                            </div>


                            <form
                                method="POST"
                                class="status-form"
                            >

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?= $orderId ?>"
                                >


                                <select
                                    name="order_status"
                                >

                                    <option
                                        value="pending"
                                        <?= $currentStatus === 'pending'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Pending
                                    </option>


                                    <option
                                        value="confirmed"
                                        <?= (
                                            $currentStatus === 'confirmed' ||
                                            $currentStatus === 'accepted'
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Accept Order
                                    </option>


                                    <option
                                        value="preparing"
                                        <?= $currentStatus === 'preparing'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Preparing
                                    </option>


                                    <option
                                        value="rider_assigned"
                                        <?= $currentStatus === 'rider_assigned'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Rider Assigned
                                    </option>


                                    <option
                                        value="ready_for_pickup"
                                        <?= $currentStatus === 'ready_for_pickup'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Ready for Pickup
                                    </option>


                                    <option
                                        value="picked_up"
                                        <?= $currentStatus === 'picked_up'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Picked Up
                                    </option>


                                    <option
                                        value="out_for_delivery"
                                        <?= (
                                            $currentStatus === 'out_for_delivery' ||
                                            $currentStatus === 'on_the_way'
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Out for Delivery
                                    </option>


                                    <option
                                        value="delivered"
                                        <?= (
                                            $currentStatus === 'delivered' ||
                                            $currentStatus === 'completed'
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Delivered
                                    </option>


                                    <option
                                        value="cancelled"
                                        <?= (
                                            $currentStatus === 'cancelled' ||
                                            $currentStatus === 'canceled'
                                        )
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Cancelled
                                    </option>

                                </select>


                                <button
                                    type="submit"
                                    name="update_order_status"
                                    value="1"
                                    class="update-btn"
                                >

                                    <i class="fas fa-check"></i>

                                    Update

                                </button>

                            </form>

                        </div>


                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <!-- NO ORDERS -->

            <div class="empty">

                <div class="empty-icon">

                    <i class="fas fa-receipt"></i>

                </div>

                <h2>
                    No Orders Yet
                </h2>

                <p>
                    Customer orders for your restaurant
                    will appear here.
                </p>

            </div>

        <?php endif; ?>


    <?php endif; ?>

</div>

</main>

</body>

</html>
