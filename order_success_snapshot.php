<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: login.php'); exit;
}

$userId = (int)$_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) { header('Location: index.php'); exit; }

$stmt = $conn->prepare('SELECT o.id,o.order_number,o.payment_method,o.subtotal,o.delivery_fee,o.discount,o.total,o.order_status,o.customer_note,o.created_at,r.name AS restaurant_name FROM orders o LEFT JOIN restaurants r ON r.id=o.restaurant_id WHERE o.id=? AND o.user_id=? LIMIT 1');
$stmt->bind_param('ii',$orderId,$userId); $stmt->execute(); $order=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$order) { header('Location: cart.php'); exit; }

$itemStmt=$conn->prepare('SELECT item_name,item_price,quantity,subtotal FROM order_items WHERE order_id=? ORDER BY id ASC');
$itemStmt->bind_param('i',$orderId); $itemStmt->execute(); $items=[]; $rs=$itemStmt->get_result(); while($r=$rs->fetch_assoc()) $items[]=$r; $itemStmt->close();

$addrStmt=$conn->prepare('SELECT full_name,phone,address,city,area,landmark FROM order_addresses WHERE order_id=? LIMIT 1');
$addrStmt->bind_param('i',$orderId); $addrStmt->execute(); $address=$addrStmt->get_result()->fetch_assoc(); $addrStmt->close();

function os_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$payment=$order['payment_method']==='card'?'Debit / Credit Card':($order['payment_method']==='online'?'Online Payment':'Cash on Delivery');
$status=ucwords(str_replace('_',' ',$order['order_status']));
require_once __DIR__ . '/includes/customer-header.php';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order Confirmed - Humsafar</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/css_header.css"><style>
body{margin:0;background:#f6f6f7;font-family:Arial,sans-serif;color:#222}.wrap{max-width:900px;margin:40px auto;padding:20px}.card{background:#fff;border-radius:18px;padding:32px;box-shadow:0 8px 30px rgba(0,0,0,.08)}.ok{text-align:center}.icon{margin:auto;width:70px;height:70px;border-radius:50%;display:grid;place-items:center;background:#e9f8ef;color:#198754;font-size:36px}.ok h1{margin:16px 0 8px}.muted{color:#707070}.number{display:inline-block;margin-top:12px;padding:9px 16px;border-radius:20px;background:#fff1f2;color:#d9273a;font-weight:700}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:25px}.box{border:1px solid #eee;border-radius:14px;padding:18px}.box h3{margin:0 0 12px}.item{display:flex;justify-content:space-between;gap:15px;padding:10px 0;border-bottom:1px solid #eee}.item:last-child{border-bottom:0}.total{display:flex;justify-content:space-between;border-top:2px solid #eee;margin-top:12px;padding-top:14px;font-size:20px;font-weight:700}.actions{display:flex;gap:12px;justify-content:center;margin-top:25px}.btn{padding:12px 18px;border-radius:10px;text-decoration:none;background:#222;color:#fff}.btn.alt{background:#eee;color:#222}@media(max-width:650px){.grid{grid-template-columns:1fr}.card{padding:20px}.actions{flex-direction:column}}
</style></head><body><div class="wrap"><div class="card"><div class="ok"><div class="icon">✓</div><h1>Order Placed Successfully!</h1><p class="muted">Your order has been received and sent for restaurant confirmation.</p><div class="number"><?php echo os_h($order['order_number']); ?></div></div>
<div class="grid"><div class="box"><h3>Restaurant</h3><strong><?php echo os_h($order['restaurant_name']); ?></strong><p class="muted">Status: <?php echo os_h($status); ?></p><p class="muted">Payment: <?php echo os_h($payment); ?></p></div><div class="box"><h3>Delivery Address</h3><?php if($address): ?><strong><?php echo os_h($address['full_name']); ?></strong><br><?php echo os_h($address['phone']); ?><br><?php echo os_h($address['address']); ?><br><?php echo os_h($address['area']); ?><?php echo $address['area']?', ':''; ?><?php echo os_h($address['city']); ?><?php if(!empty($address['landmark'])):?><br>Landmark: <?php echo os_h($address['landmark']); ?><?php endif;?><?php else:?><span class="muted">Address snapshot not found.</span><?php endif;?></div></div>
<div class="box" style="margin-top:16px"><h3>Order Items</h3><?php foreach($items as $item):?><div class="item"><span><?php echo os_h($item['item_name']); ?> × <?php echo (int)$item['quantity']; ?></span><strong>PKR <?php echo number_format((float)$item['subtotal'],2); ?></strong></div><?php endforeach;?><div class="total"><span>Total</span><span>PKR <?php echo number_format((float)$order['total'],2); ?></span></div></div>
<div class="actions"><a class="btn" href="index.php">Continue Shopping</a><a class="btn alt" href="cart.php">View Cart</a></div></div></div></body></html>
