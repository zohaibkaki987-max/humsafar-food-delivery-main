<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) {
    header('Location: rider-login.php'); exit;
}
$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId < 1) { session_destroy(); header('Location: rider-login.php'); exit; }

function ro_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ro_label($v) { return ucwords(str_replace('_', ' ', strtolower((string)$v))); }
function ro_history($db,$orderId,$status,$riderId,$note) {
    $s=$db->prepare("INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note) VALUES(?,?,?,'delivery',?)");
    if($s){$s->bind_param('iiss',$orderId,$status,$riderId,$note);$s->execute();$s->close();}
}
function ro_notify($db,$userId,$title,$message,$type,$orderId) {
    $s=$db->prepare("INSERT INTO notifications(user_id,role,title,message,type,reference_id) VALUES(?,'customer',?,?,?,?)");
    if($s){$s->bind_param('isssi',$userId,$title,$message,$type,$orderId);$s->execute();$s->close();}
}
function ro_delivery($db,$riderId,$orderId,$status,$column) {
    $s=$db->prepare("SELECT id FROM rider_deliveries WHERE rider_id=? AND order_id=? ORDER BY id DESC LIMIT 1");
    if(!$s)return false;
    $s->bind_param('ii',$riderId,$orderId);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();
    if($row){
        $sql="UPDATE rider_deliveries SET status=?, {$column}=NOW() WHERE id=?";
        $s=$db->prepare($sql); if(!$s)return false; $s->bind_param('si',$status,$row['id']);$ok=$s->execute();$s->close();return $ok;
    }
    $s=$db->prepare("INSERT INTO rider_deliveries(rider_id,order_id,status,assigned_at,accepted_at) VALUES(?,?,?,NOW(),NOW())");
    if(!$s)return false;$s->bind_param('iis',$riderId,$orderId,$status);$ok=$s->execute();$s->close();return $ok;
}

$s=$conn->prepare("SELECT full_name,phone,status,availability_status FROM riders WHERE id=? LIMIT 1");
$s->bind_param('i',$riderId);$s->execute();$rider=$s->get_result()->fetch_assoc();$s->close();
if(!$rider)die('Rider account not found.');
$approved=strtolower((string)$rider['status'])==='active';
$message='';$type='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delivery_action'])){
    $action=(string)$_POST['delivery_action'];$orderId=(int)($_POST['order_id']??0);
    $conn->begin_transaction();
    try{
        if(!$approved)throw new Exception('Your rider account is not approved yet.');
        if($orderId<1||!in_array($action,['accept','picked_up','on_the_way','delivered'],true))throw new Exception('Invalid delivery request.');
        $s=$conn->prepare("SELECT id,user_id,rider_id,order_status FROM orders WHERE id=? LIMIT 1 FOR UPDATE");
        $s->bind_param('i',$orderId);$s->execute();$order=$s->get_result()->fetch_assoc();$s->close();
        if(!$order)throw new Exception('Order not found.');
        $cur=strtolower((string)$order['order_status']);$assigned=(int)$order['rider_id'];

        if($action==='accept'){
            if(!in_array($cur,['preparing','ready_for_pickup'],true))throw new Exception('Only Preparing or Ready for Pickup orders can be accepted.');
            if($assigned&&$assigned!==$riderId)throw new Exception('This order is already assigned to another rider.');
            $s=$conn->prepare("UPDATE orders SET rider_id=?,order_status='rider_assigned' WHERE id=? AND (rider_id IS NULL OR rider_id=0) AND order_status IN('preparing','ready_for_pickup')");
            $s->bind_param('ii',$riderId,$orderId);$s->execute();if($s->affected_rows!==1){$s->close();throw new Exception('This order was already taken.');}$s->close();
            ro_delivery($conn,$riderId,$orderId,'accepted','accepted_at');
            ro_history($conn,$orderId,'rider_assigned',$riderId,'Rider accepted delivery.');
            ro_notify($conn,(int)$order['user_id'],'Rider assigned','A rider has accepted order #'.$orderId.'. Rider information and live location are now available.','rider_assigned',$orderId);
            $s=$conn->prepare("UPDATE riders SET availability_status='busy' WHERE id=?");$s->bind_param('i',$riderId);$s->execute();$s->close();
            $message='Delivery accepted.';
        } else {
            if($assigned!==$riderId)throw new Exception('This order is not assigned to you.');
            $map=[
                'picked_up'=>['from'=>['rider_assigned'],'notify'=>'Order picked up','type'=>'order_picked_up','text'=>'Your order #'.$orderId.' has been picked up by the rider.','column'=>'picked_up_at'],
                'on_the_way'=>['from'=>['picked_up'],'notify'=>'Order is on the way','type'=>'out_for_delivery','text'=>'Your order #'.$orderId.' is now out for delivery.','column'=>'started_at'],
                'delivered'=>['from'=>['on_the_way','picked_up'],'notify'=>'Order delivered','type'=>'delivered','text'=>'Your order #'.$orderId.' has been delivered successfully.','column'=>'delivered_at']
            ];
            $cfg=$map[$action]??null;if(!$cfg||!in_array($cur,$cfg['from'],true))throw new Exception('Invalid delivery stage. Current status: '.ro_label($cur));
            $s=$conn->prepare("UPDATE orders SET order_status=? WHERE id=? AND rider_id=?");$s->bind_param('sii',$action,$orderId,$riderId);$s->execute();if($s->affected_rows!==1){$s->close();throw new Exception('Order status could not be updated.');}$s->close();
            ro_delivery($conn,$riderId,$orderId,$action,$cfg['column']);
            ro_history($conn,$orderId,$action,$riderId,'Rider updated delivery status.');
            ro_notify($conn,(int)$order['user_id'],$cfg['notify'],$cfg['text'],$cfg['type'],$orderId);
            if($action==='delivered'){$s=$conn->prepare("UPDATE riders SET availability_status='available' WHERE id=?");$s->bind_param('i',$riderId);$s->execute();$s->close();}
            $message='Order updated to '.ro_label($action).'.';
        }
        $conn->commit();$type='success';
    }catch(Throwable $e){$conn->rollback();$message=$e->getMessage();$type='error';}
}

if(($_GET['ajax']??'')==='location'&&$_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=utf-8');
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);$acc=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
    if($lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){echo json_encode(['ok'=>false]);exit;}
    $s=$conn->prepare("INSERT INTO rider_locations(rider_id,latitude,longitude,accuracy) VALUES(?,?,?,?)");$s->bind_param('iddd',$riderId,$lat,$lng,$acc);$ok=$s->execute();$s->close();echo json_encode(['ok'=>$ok]);exit;
}

$available=[];$s=$conn->prepare("SELECT o.*,u.full_name customer_name,u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.order_status IN('preparing','ready_for_pickup') AND (o.rider_id IS NULL OR o.rider_id=0) ORDER BY o.id DESC");$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc())$available[]=$x;$s->close();
$active=[];$s=$conn->prepare("SELECT o.*,u.full_name customer_name,u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.rider_id=? AND o.order_status IN('rider_assigned','picked_up','on_the_way') ORDER BY o.id DESC");$s->bind_param('i',$riderId);$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc())$active[]=$x;$s->close();
$done=[];$s=$conn->prepare("SELECT o.*,u.full_name customer_name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.rider_id=? AND o.order_status='delivered' ORDER BY o.id DESC LIMIT 30");$s->bind_param('i',$riderId);$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc())$done[]=$x;$s->close();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rider Orders | Humsafar</title><style>body{margin:0;background:#f6f7f9;font-family:Arial;color:#222}.page{margin-left:220px;padding:30px;min-height:100vh}.card,.stat,.empty,.profile{background:#fff;border:1px solid #e5e5e5;border-radius:14px}.top{display:flex;justify-content:space-between;align-items:center}.profile{padding:12px 16px}.profile small{display:block;color:#777;margin-top:4px}.flash{padding:14px;border-radius:10px;margin:18px 0;font-weight:bold}.success{background:#e9f8ee;color:#176b35}.error{background:#fff0f3;color:#a5002d}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:25px 0}.stat{padding:18px}.stat small{color:#777}.stat strong{display:block;font-size:26px;margin-top:5px}.section{margin:28px 0}.card{margin:14px 0;overflow:hidden}.head{padding:17px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between}.body{padding:20px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.box{background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px}.box small{display:block;color:#888;font-size:11px}.box b{display:block;margin-top:4px}.badge{padding:7px 11px;background:#fff0f5;color:#d00037;border-radius:20px;font-size:12px;font-weight:bold}.btn{border:0;border-radius:9px;padding:11px 16px;font-weight:bold;cursor:pointer;margin-top:15px}.accept{background:#ef003c;color:#fff}.next{background:#eaf5ff;color:#086a9d}.deliver{background:#e9f8ee;color:#176b35}.gps{margin-top:15px;padding:12px;background:#f3f9ff;border:1px solid #d9eafa;border-radius:10px;color:#476675}@media(max-width:800px){.page{margin-left:0;padding:18px}.grid,.stats{grid-template-columns:1fr}.profile{display:none}}</style></head><body><?php if(file_exists(__DIR__.'/rider-sidebar.php'))include __DIR__.'/rider-sidebar.php'; ?><main class="page"><div class="top"><div><small style="color:#ef003c;font-weight:bold">DELIVERY PARTNER</small><h1>Rider Orders</h1><p style="color:#777">Manage assigned deliveries and live location.</p></div><div class="profile"><b><?=ro_h($rider['full_name'])?></b><small><?=ro_label($rider['status'])?> · <?=ro_label($rider['availability_status'])?></small></div></div><?php if($message):?><div class="flash <?=ro_h($type)?>"><?=ro_h($message)?></div><?php endif;?><div class="stats"><div class="stat"><small>New Deliveries</small><strong><?=count($available)?></strong></div><div class="stat"><small>Active</small><strong><?=count($active)?></strong></div><div class="stat"><small>Completed</small><strong><?=count($done)?></strong></div></div><section class="section"><h2>New Deliveries</h2><?php if(!$approved):?><div class="empty">Your rider account must be active before accepting deliveries.</div><?php elseif(!$available):?><div class="empty">No new deliveries right now.</div><?php else:foreach($available as $o):?><article class="card"><div class="head"><b>Order #<?=ro_h($o['order_number'])?></b><span class="badge"><?=ro_label($o['order_status'])?></span></div><div class="body"><div class="grid"><div class="box"><small>Customer</small><b><?=ro_h($o['customer_name']??'Customer')?></b></div><div class="box"><small>Phone</small><b><?=ro_h($o['customer_phone']??'')?></b></div><div class="box"><small>Total</small><b>Rs <?=number_format((float)$o['total'],0)?></b></div></div><form method="post"><input type="hidden" name="order_id" value="<?=ro_h($o['id'])?>"><input type="hidden" name="delivery_action" value="accept"><button class="btn accept">Accept Delivery</button></form></div></article><?php endforeach;endif;?></section><section class="section"><h2>Active Deliveries</h2><?php if(!$active):?><div class="empty">No active delivery.</div><?php else:foreach($active as $o):$a=$o['order_status']==='rider_assigned'?['picked_up','Picked Up']:($o['order_status']==='picked_up'?['on_the_way','On The Way']:($o['order_status']==='on_the_way'?['delivered','Delivered']:null));?><article class="card"><div class="head"><b>Order #<?=ro_h($o['order_number'])?></b><span class="badge"><?=ro_label($o['order_status'])?></span></div><div class="body"><div class="grid"><div class="box"><small>Customer</small><b><?=ro_h($o['customer_name']??'Customer')?></b></div><div class="box"><small>Phone</small><b><?=ro_h($o['customer_phone']??'')?></b></div><div class="box"><small>Delivery Fee</small><b>Rs <?=number_format((float)$o['delivery_fee'],0)?></b></div></div><?php if($a):?><form method="post"><input type="hidden" name="order_id" value="<?=ro_h($o['id'])?>"><input type="hidden" name="delivery_action" value="<?=ro_h($a[0])?>"><button class="btn <?=($a[0]==='delivered'?'deliver':'next')?>"><?=ro_h($a[1])?></button></form><?php endif;?><div class="gps">📍 <b>Live GPS:</b> <span class="gps-text">Waiting for location permission…</span></div></div></article><?php endforeach;endif;?></section><section class="section"><h2>Completed Deliveries</h2><?php if(!$done):?><div class="empty">No completed deliveries yet.</div><?php else:foreach($done as $o):?><article class="card"><div class="body"><b>Order #<?=ro_h($o['order_number'])?></b> · <?=ro_h($o['customer_name']??'Customer')?> · Rs <?=number_format((float)$o['delivery_fee'],0)?></div></article><?php endforeach;endif;?></section></main><script>(function(){const active=<?=json_encode(count($active)>0)?>;if(!active)return;const els=document.querySelectorAll('.gps-text');if(!navigator.geolocation){els.forEach(e=>e.textContent='GPS not supported.');return}function send(p){const f=new FormData();f.append('latitude',p.coords.latitude);f.append('longitude',p.coords.longitude);f.append('accuracy',p.coords.accuracy||'');fetch('rider-orders.php?ajax=location',{method:'POST',body:f,cache:'no-store'}).then(r=>r.json()).then(x=>els.forEach(e=>e.textContent=x.ok?'Live location updated':'Location save failed')).catch(()=>els.forEach(e=>e.textContent='Location update failed'));}navigator.geolocation.watchPosition(send,()=>els.forEach(e=>e.textContent='Location permission denied.'),{enableHighAccuracy:true,maximumAge:5000,timeout:15000});})();</script></body></html>