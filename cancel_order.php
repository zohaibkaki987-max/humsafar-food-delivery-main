<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { header('Location: my_orders.php'); exit; }

/*
 * Customer can cancel ONLY when:
 * - order is still pending (restaurant has not accepted it), and
 * - payment is not completed.
 * The final UPDATE repeats both conditions atomically so a race with payment
 * or restaurant acceptance cannot bypass the rule.
 */
$stmt = $conn->prepare(
    "SELECT o.id, o.order_status, COALESCE(p.status, 'pending') AS payment_status
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.id
     WHERE o.id = ? AND o.user_id = ?
     ORDER BY p.id DESC LIMIT 1"
);
if (!$stmt) die('Database error: ' . $conn->error);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) { header('Location: my_orders.php'); exit; }

$status = strtolower(trim((string)$order['order_status']));
$payment_status = strtolower(trim((string)$order['payment_status']));
$paymentCompleted = in_array($payment_status, ['paid','completed','success','succeeded','verified'], true);

if ($status !== 'pending' || $paymentCompleted) {
    header('Location: order_success.php?order_id=' . $order_id . '&cancel=not_allowed');
    exit;
}

/* Atomic final guard: payment must still be incomplete and order still pending. */
$update = $conn->prepare(
    "UPDATE orders o
     LEFT JOIN payments p ON p.order_id = o.id
     SET o.order_status = 'cancelled'
     WHERE o.id = ?
       AND o.user_id = ?
       AND o.order_status = 'pending'
       AND COALESCE(p.status, 'pending') NOT IN ('paid','completed','success','succeeded','verified')"
);
if (!$update) die('Database error: ' . $conn->error);
$update->bind_param('ii', $order_id, $user_id);
$update->execute();
$changed = $update->affected_rows;
$update->close();

if ($changed !== 1) {
    header('Location: order_success.php?order_id=' . $order_id . '&cancel=not_allowed');
    exit;
}

$history = $conn->prepare(
    "INSERT INTO order_status_history
     (order_id, status, changed_by, changed_by_role, note)
     VALUES (?, 'cancelled', ?, 'customer', 'Order cancelled by customer before payment and restaurant acceptance.')"
);
if ($history) {
    $history->bind_param('ii', $order_id, $user_id);
    $history->execute();
    $history->close();
}

$notice = $conn->prepare(
    "INSERT INTO notifications
     (user_id, role, title, message, type, reference_id, is_read)
     VALUES (?, 'customer', 'Order cancelled', ?, 'order_cancelled', ?, 0)"
);
if ($notice) {
    $message = 'Your order #' . $order_id . ' was cancelled successfully.';
    $notice->bind_param('isi', $user_id, $message, $order_id);
    $notice->execute();
    $notice->close();
}

header('Location: order_success.php?order_id=' . $order_id . '&cancel=success');
exit;
?>