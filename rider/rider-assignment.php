<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) {
    header('Location: rider-login.php');
    exit;
}

$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId < 1) {
    session_destroy();
    header('Location: rider-login.php');
    exit;
}

function ra_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ra_notify(mysqli $db, int $userId, string $title, string $message, string $type, int $orderId): void {
    if ($userId < 1) return;
    $s = $db->prepare("INSERT INTO notifications (user_id, role, title, message, type, reference_id) VALUES (?, 'customer', ?, ?, ?, ?)");
    if ($s) { $s->bind_param('isssi', $userId, $title, $message, $type, $orderId); $s->execute(); $s->close(); }
}
function ra_history(mysqli $db, int $orderId, string $status, int $riderId, string $note): void {
    $s = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, note) VALUES (?, ?, ?, 'rider', ?)");
    if ($s) { $s->bind_param('isis', $orderId, $status, $riderId, $note); $s->execute(); $s->close(); }
}

$rider = null;
$s = $conn->prepare("SELECT id, full_name, phone, vehicle_type, bike_number, status, availability_status FROM riders WHERE id=? LIMIT 1");
if ($s) { $s->bind_param('i', $riderId); $s->execute(); $rider = $s->get_result()->fetch_assoc(); $s->close(); }
if (!$rider) die('Rider account not found.');

$approved = in_array(strtolower(trim((string)$rider['status'])), ['active','approved'], true);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $orderId = (int)($_POST['order_id'] ?? 0);
    $allowed = ['accept','picked_up','on_the_way','delivered'];

    if (!$approved) $error = 'Rider account is not approved.';
    elseif ($orderId < 1 || !in_array($action, $allowed, true)) $error = 'Invalid delivery request.';
    else {
        $conn->begin_transaction();
        try {
            $s = $conn->prepare("SELECT id, user_id, rider_id, order_status FROM orders WHERE id=? LIMIT 1 FOR UPDATE");
            if (!$s) throw new Exception('Unable to load order.');
            $s->bind_param('i', $orderId); $s->execute(); $order = $s->get_result()->fetch_assoc(); $s->close();
            if (!$order) throw new Exception('Order not found.');

            $current = strtolower(trim((string)$order['order_status']));
            $assigned = (int)($order['rider_id'] ?? 0);

            if ($action === 'accept') {
                if ($current !== 'preparing') throw new Exception('Only Preparing orders can be accepted.');
                if ($assigned && $assigned !== $riderId) throw new Exception('This order is already assigned to another rider.');

                $new = 'rider_assigned';
                $s = $conn->prepare("UPDATE orders SET rider_id=?, order_status=? WHERE id=? AND (rider_id IS NULL OR rider_id=0) AND order_status='preparing' LIMIT 1");
                $s->bind_param('isi', $riderId, $new, $orderId); $s->execute();
                if ($s->affected_rows !== 1) { $s->close(); throw new Exception('This order was already taken.'); }
                $s->close();

                $s = $conn->prepare("INSERT INTO rider_deliveries (rider_id, order_id, status, accepted_at) VALUES (?, ?, 'accepted', NOW()) ON DUPLICATE KEY UPDATE status='accepted', accepted_at=NOW()");
                $s->bind_param('ii', $riderId, $orderId); $s->execute(); $s->close();
                ra_history($conn, $orderId, $new, $riderId, 'Rider accepted the delivery.');
                ra_notify($conn, (int)$order['user_id'], 'Rider assigned', 'A rider has accepted order #'.$orderId.'.', 'rider_assigned', $orderId);
                $message = 'Delivery accepted successfully.';
            } else {
                if ($assigned !== $riderId) throw new Exception('This order is not assigned to you.');

                $map = [
                    'picked_up' => ['from'=>'rider_assigned','notify'=>'Order picked up','type'=>'order_picked_up','text'=>'Your order #'.$orderId.' has been picked up by the rider.'],
                    'on_the_way' => ['from'=>'picked_up','notify'=>'Order is on the way','type'=>'out_for_delivery','text'=>'Your order #'.$orderId.' is now out for delivery.'],
                    'delivered' => ['from'=>['on_the_way','out_for_delivery','picked_up'],'notify'=>'Order delivered','type'=>'delivered','text'=>'Your order #'.$orderId.' has been delivered successfully.']
                ];
                $cfg = $map[$action];
                $validFrom = is_array($cfg['from']) ? in_array($current, $cfg['from'], true) : $current === $cfg['from'];
                if (!$validFrom) throw new Exception('Invalid delivery stage. Current status: '.ucwords(str_replace('_',' ',$current)).'.');

                $new = $action;
                $s = $conn->prepare("UPDATE orders SET order_status=? WHERE id=? AND rider_id=? LIMIT 1");
                $s->bind_param('sii', $new, $orderId, $riderId); $s->execute();
                if ($s->affected_rows !== 1) { $s->close(); throw new Exception('Order status could not be updated.'); }
                $s->close();

                if ($action === 'picked_up') {
                    $s = $conn->prepare("UPDATE rider_deliveries SET status='picked_up', picked_up_at=NOW() WHERE rider_id=? AND order_id=?");
                } elseif ($action === 'on_the_way') {
                    $s = $conn->prepare("UPDATE rider_deliveries SET status='on_the_way', started_at=NOW() WHERE rider_id=? AND order_id=?");
                } else {
                    $s = $conn->prepare("UPDATE rider_deliveries SET status='delivered', delivered_at=NOW() WHERE rider_id=? AND order_id=?");
                }
                if ($s) { $s->bind_param('ii', $riderId, $orderId); $s->execute(); $s->close(); }

                ra_history($conn, $orderId, $new, $riderId, 'Rider updated delivery status.');
                ra_notify($conn, (int)$order['user_id'], $cfg['notify'], $cfg['text'], $cfg['type'], $orderId);
                $message = 'Order updated to '.ucwords(str_replace('_',' ', $new)).'.';
            }

            if ($action === 'accept') {
                $s = $conn->prepare("UPDATE riders SET availability_status='busy' WHERE id=?");
            } elseif ($action === 'delivered') {
                $s = $conn->prepare("UPDATE riders SET availability_status='available' WHERE id=?");
            } else { $s = null; }
            if ($s) { $s->bind_param('i', $riderId); $s->execute(); $s->close(); }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$available = [];
$s = $conn->prepare("SELECT o.id,o.order_number,o.user_id,o.total,o.delivery_fee,o.order_status,o.created_at,u.full_name AS customer_name,u.phone AS customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.order_status='preparing' AND (o.rider_id IS NULL OR o.rider_id=0) ORDER BY o.id DESC");
if ($s) { $s->execute(); $rs=$s->get_result(); while($row=$rs->fetch_assoc()) $available[]=$row; $s->close(); }

$active = [];
$s = $conn->prepare("SELECT o.id,o.order_number,o.user_id,o.total,o.delivery_fee,o.order_status,o.created_at,u.full_name AS customer_name,u.phone AS customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.rider_id=? AND o.order_status NOT IN ('delivered','cancelled','rejected') ORDER BY o.id DESC");
if ($s) { $s->bind_param('i',$riderId); $s->execute(); $rs=$s->get_result(); while($row=$rs->fetch_assoc()) $active[]=$row; $s->close(); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rider Assignment | Humsafar</title><style>body{font-family:Arial,sans-serif;background:#f7f8fa;margin:0;color:#222}.wrap{max-width:1000px;margin:30px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e5e5;border-radius:15px;padding:20px;margin:15px 0}.top{display:flex;justify-content:space-between;align-items:center}.badge{padding:7px 11px;border-radius:20px;background:#fff0f5;color:#d00037;font-weight:700}.success{background:#eaf9ef;color:#18703a;padding:14px;border-radius:10px}.error{background:#fff0f3;color:#b00030;padding:14px;border-radius:10px}.btn{border:0;border-radius:9px;padding:11px 15px;font-weight:700;margin:4px;cursor:pointer}.accept{background:#ef003c;color:#fff}.pickup{background:#fff1dc}.way{background:#eaf6ff}.deliver{background:#eaf9ef}.meta{color:#666;margin:8px 0}.empty{padding:35px;text-align:center;color:#777;border:1px dashed #ccc;border-radius:12px}.actions{margin-top:15px}</style></head><body><div class="wrap"><div class="top"><div><h1>Rider Assignment & Delivery</h1><p>Logged in as <b><?=ra_h($rider['full_name'])?></b></p></div><span class="badge"><?=ra_h(ucwords($rider['availability_status']))?></span></div><?php if($message):?><div class="success"><?=ra_h($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=ra_h($error)?></div><?php endif;?>
<h2>New Deliveries</h2><?php if(!$approved):?><div class="empty">Your rider account is not approved.</div><?php elseif(!$available):?><div class="empty">No Preparing orders are waiting for a rider.</div><?php else: foreach($available as $o):?><div class="card"><div class="top"><h3>Order #<?=ra_h($o['order_number'])?></h3><span class="badge">Preparing · Rider Needed</span></div><div class="meta">Customer: <b><?=ra_h($o['customer_name'])?></b> · <?=ra_h($o['customer_phone'])?></div><div class="meta">Total: <b>PKR <?=number_format((float)$o['total'],2)?></b> · Delivery fee: PKR <?=number_format((float)$o['delivery_fee'],2)?></div><form method="post" class="actions"><input type="hidden" name="order_id" value="<?=ra_h($o['id'])?>"><button class="btn accept" name="action" value="accept">Accept Delivery</button></form></div><?php endforeach; endif;?>
<h2>My Active Deliveries</h2><?php if(!$active):?><div class="empty">No active delivery.</div><?php else: foreach($active as $o):?><div class="card"><div class="top"><h3>Order #<?=ra_h($o['order_number'])?></h3><span class="badge"><?=ra_h(ucwords(str_replace('_',' ',$o['order_status'])))?></span></div><div class="meta">Customer: <b><?=ra_h($o['customer_name'])?></b> · <?=ra_h($o['customer_phone'])?></div><div class="actions"><form method="post" style="display:inline"><input type="hidden" name="order_id" value="<?=ra_h($o['id'])?>"><?php if($o['order_status']==='rider_assigned'):?><button class="btn pickup" name="action" value="picked_up">Picked Up</button><?php elseif($o['order_status']==='picked_up'):?><button class="btn way" name="action" value="on_the_way">Start Delivery</button><?php elseif(in_array($o['order_status'],['on_the_way','out_for_delivery'],true)):?><button class="btn deliver" name="action" value="delivered">Mark Delivered</button><?php endif;?></form></div></div><?php endforeach; endif;?></div></body></html>