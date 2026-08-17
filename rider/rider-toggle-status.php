<?php
/* Rider Online / Offline control. Online is derived from a currently booked session. */
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['rider_id'])) {
    header('Location: rider-login.php');
    exit;
}

$riderId = (int) $_SESSION['rider_id'];

/* Only an approved/active rider may use the status control. */
$stmt = $conn->prepare("SELECT status FROM riders WHERE id = ? LIMIT 1");
$riderStatus = '';
if ($stmt) {
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $riderStatus = strtolower(trim((string)($row['status'] ?? '')));
}

if (!in_array($riderStatus, ['approved', 'active'], true)) {
    header('Location: rider-dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['go_offline'])) {
    header('Location: rider-dashboard.php');
    exit;
}

/* Find an order currently assigned to this rider. */
$orderColumns = [];
$columnsResult = $conn->query('SHOW COLUMNS FROM orders');
if ($columnsResult) {
    while ($column = $columnsResult->fetch_assoc()) {
        $orderColumns[] = $column['Field'];
    }
}

$riderOrderColumn = null;
foreach (['rider_id', 'delivery_rider_id', 'assigned_rider_id'] as $column) {
    if (in_array($column, $orderColumns, true)) {
        $riderOrderColumn = $column;
        break;
    }
}

$statusColumn = null;
foreach (['order_status', 'status'] as $column) {
    if (in_array($column, $orderColumns, true)) {
        $statusColumn = $column;
        break;
    }
}

$hasActiveOrder = false;
if ($riderOrderColumn !== null && $statusColumn !== null) {
    $sql = "SELECT 1 FROM orders
            WHERE `$riderOrderColumn` = ?
            AND LOWER(TRIM(`$statusColumn`)) NOT IN ('delivered','cancelled','completed')
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $riderId);
        $stmt->execute();
        $hasActiveOrder = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }
}

/* A rider carrying an order cannot close the session/go offline. */
if ($hasActiveOrder) {
    header('Location: rider-dashboard.php');
    exit;
}

/* Close only the currently running booked session. Future booked sessions remain scheduled. */
$delete = $conn->prepare("DELETE FROM rider_availability
    WHERE rider_id = ?
      AND available_date = CURDATE()
      AND start_time <= CURTIME()
      AND (end_time > CURTIME() OR end_time = '00:00:00')
    LIMIT 1");

if ($delete) {
    $delete->bind_param('i', $riderId);
    $delete->execute();
    $delete->close();
}

header('Location: rider-dashboard.php');
exit;
?>
