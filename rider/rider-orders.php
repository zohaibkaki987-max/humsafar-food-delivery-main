<?php
session_start();
require_once '../includes/config.php';
if (empty($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) { header('Location: rider-login.php'); exit; }
$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId < 1) { session_destroy(); header('Location: rider-login.php'); exit; }
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function notifyCustomer($db,$uid,$title,$message,$type,$oid){
    if(!$uid) return;
    $s=$db->prepare("INSERT INTO notifications(user_id,role,title,message,type,reference_id) VALUES(?,'customer',?,?,?,?)");
    if($s){$s->bind_param('isssi',$uid,$title,$message,$type,$oid);$s->execute();$s->close();}
}
function history($db,$oid,$status,$riderId,$note=''){
    $s=$db->prepare("INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note) VALUES(?,?,'?','delivery',?)");
    if($s){$s->bind_param('isis',$oid,$status,$riderId,$note);$s->execute();$s->close();}
}
$r=['full_name'=>'Rider','phone'=>'','vehicle_type'=>'bike','bike_number'=>'','status'=>'pending','availability_status'=>'offline'];
$s=$conn->prepare('SELECT full_name,phone,vehicle_type,bike_number,status,availability_status FROM riders WHERE id=? LIMIT 1');
if($s){$s->bind_param('i',$riderId);$s->execute();$x=$s->get_result()->fetch_assoc();$s->close();if($x)$r=array_merge($r,$x);}
$approved=strtolower(trim($r['status']))==='active';
$msg='';$type='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delivery_action'])){
    $action=$_POST['delivery_action']; $oid=(int)($_POST['order_id']??0);
    $allowed=['accept','picked_up','on_the_way','delivered'];
    if(!$approved){$msg='Your rider account is not approved yet.';$type='error';}
    elseif(!$oid || !in_array($action,$allowed,true)){$msg='Invalid delivery request.';$type='error';}
    else{
        $conn->begin_transaction();
        try{
            $s=$conn->prepare('SELECT * FROM orders WHERE id=? LIMIT 1 FOR UPDATE');$s->bind_param('i',$oid);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();
            if(!$o) throw new Exception('Order not found.');
            $cur=strtolower(trim($o['order_status'])); $assigned=(int)($o['rider_id']??0);
            if($action==='accept'){
                if($cur!=='preparing') throw new Exception('Only Preparing orders can be accepted.');
                if($assigned && $assigned!==$riderId) throw new Exception('This order was already accepted by another rider.');
                $s=$conn->prepare("UPDATE orders SET rider_id=?,order_status='rider_assigned' WHERE id=? AND (rider_id IS NULL OR rider_id=0) AND order_status='preparing' LIMIT 1");$s->bind_param('ii',$riderId,$oid);$s->execute();
                if($s->affected_rows!==1) throw new Exception('This order was already taken.');$s->close();
                $s=$conn->prepare("INSERT INTO rider_deliveries(rider_id,order_id,status,assigned_at,accepted_at) VALUES(?,?, 'accepted',NOW(),NOW()) ON DUPLICATE KEY UPDATE status='accepted',accepted_at=NOW()");$s->bind_param('ii',$riderId,$oid);$s->execute();$s->close();
                history($conn,$oid,'rider_assigned',$riderId,'Rider accepted delivery.');
                notifyCustomer($conn,(int)$o['user_id'],'Rider assigned','A rider has accepted order #'.$oid.'. Rider information and live location are now available.','rider_assigned',$oid);
                $s=$conn->prepare("UPDATE riders SET availability_status='busy' WHERE id=?");$s->bind_param('i',$riderId);$s->execute();$s->close();
                $msg='Delivery accepted. You are assigned to order #'.$oid.'.';
            } elseif($assigned!==$riderId) throw new Exception('This order is not assigned to you.');
            elseif($action==='picked_up'){
                if($cur!=='rider_assigned') throw new Exception('Order must be Rider Assigned first.');
                $s=$conn->prepare("UPDATE orders SET order_status='picked_up' WHERE id=? AND rider_id=? LIMIT 1");$s->bind_param('ii',$oid,$riderId);$s->execute();$s->close();
                $s=$conn->prepare("UPDATE rider_deliveries SET status='picked_up',picked_up_at=NOW() WHERE rider_id=? AND order_id=?");$s->bind_param('ii',$riderId,$oid);$s->execute();$s->close();
                history($conn,$oid,'picked_up',$riderId,'Rider picked up the order.');notifyCustomer($conn,(int)$o['user_id'],'Order picked up','Your order #'.$oid.' has been picked up by the rider.','order_picked_up',$oid);$msg='Order marked as Picked Up.';
            } elseif($action==='on_the_way'){
                if($cur!=='picked_up') throw new Exception('Pick up the order before starting delivery.');
                $s=$conn->prepare("UPDATE orders SET order_status='on_the_way' WHERE id=? AND rider_id=? LIMIT 1");$s->bind_param('ii',$oid,$riderId);$s->execute();$s->close();
                $s=$conn->prepare("UPDATE rider_deliveries SET status='on_the_way',started_at=NOW() WHERE rider_id=? AND order_id=?");$s->bind_param('ii',$riderId,$oid);$s->execute();$s->close();
                history($conn,$oid,'on_the_way',$riderId,'Rider started delivery.');notifyCustomer($conn,(int)$o['user_id'],'Order is on the way','Your order #'.$oid.' is now out for delivery.','out_for_delivery',$oid);$msg='Order is now On The Way.';
            } else {
                if(!in_array($cur,['on_the_way','picked_up'],true)) throw new Exception('Order is not ready to be delivered.');
                $s=$conn->prepare("UPDATE orders SET order_status='delivered' WHERE id=? AND rider_id=? LIMIT 1");$s->bind_param('ii',$oid,$riderId);$s->execute();$s->close();
                $s=$conn->prepare("UPDATE rider_deliveries SET status='delivered',delivered_at=NOW() WHERE rider_id=? AND order_id=?");$s->bind_param('ii',$riderId,$oid);$s->execute();$s->close();
                history($conn,$oid,'delivered',$riderId,'Rider completed delivery.');notifyCustomer($conn,(int)$o['user_id'],'Order delivered','Your order #'.$oid.' has been delivered successfully.','delivered',$oid);
                $s=$conn->prepare("UPDATE riders SET availability_status='available' WHERE id=?");$s->bind_param('i',$riderId);$s->execute();$s->close();$msg='Order marked as Delivered.';
            }
            $conn->commit();$type='success';
        }catch(Throwable $e){$conn->rollback();$msg=$e->getMessage();$type='error';}
    }
}
if(($_GET['ajax']??'')==='location' && $_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=utf-8');
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);$acc=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
    if($lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){echo json_encode(['ok'=>false]);exit;}
    $s=$conn->prepare('INSERT INTO rider_locations(rider_id,latitude,longitude,accuracy) VALUES(?,?,?,?)');$s->bind_param('iddd',$riderId,$lat,$lng,$acc);$ok=$s->execute();$s->close();echo json_encode(['ok'=>$ok]);exit;
}
$available=[];$s=$conn->prepare("SELECT o.*,u.full_name customer_name,u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.order_status='preparing' AND (o.rider_id IS NULL OR o.rider_id=0) ORDER BY o.id DESC");if($s){$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc())$available[]=$x;$s->close();}
$active=[];$s=$conn->prepare("SELECT o.*,u.full_name customer_name,u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.rider_id=? AND o.order_status IN('rider_assigned','picked_up','on_the_way') ORDER BY o.id DESC");if($s){$s->bind_param('i',$riderId);$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc())$active[]=$x;$s->close();}
$historyRows=[];$earnings=0;$s=$conn->prepare("SELECT o.*,u.full_name customer_name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.rider_id=? AND o.order_status='delivered' ORDER BY o.id DESC LIMIT 30");if($s){$s->bind_param('i',$riderId);$s->execute();$rs=$s->get_result();while($x=$rs->fetch_assoc()){$historyRows[]=$x;$earnings+=(float)$x['delivery_fee'];}$s->close();}
function statusLabel($s){return ucwords(str_replace('_',' ',strtolower((string)$s)));}
function actionFor($status){if($status==='rider_assigned')return ['picked_up','Picked Up','pickup'];if($status==='picked_up')return ['on_the_way','On The Way','way'];if($status==='on_the_way')return ['delivered','Delivered','deliver'];return null;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rider Orders | Humsafar</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>*{box-sizing:border-box}body{margin:0;background:#f7f8fa;color:#222;font-family:Segoe UI,Arial,sans-serif}.page{margin-left:220px;padding:30px;min-height:100vh}.head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px}.eyebrow{font-size:12px;font-weight:900;letter-spacing:1px;color:#ef003c;text-transform:uppercase}.title{font-size:34px;margin:5px 0}.sub{color:#777}.profile,.card,.stat,.empty{background:#fff;border:1px solid #e7e7e7;border-radius:16px}.profile{padding:12px 16px;text-align:right}.profile small{display:block;color:#777}.flash{padding:14px 17px;border-radius:12px;margin-bottom:18px;font-weight:700}.success{background:#eaf9ef;color:#18703a}.error{background:#fff0f3;color:#b00030}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}.stat{padding:18px}.stat small{color:#777;font-weight:700}.stat strong{display:block;font-size:27px;margin-top:5px}.section{margin:28px 0}.section h2{font-size:21px}.card{overflow:hidden;margin-bottom:16px}.cardhead{padding:18px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between}.order{font-size:19px;font-weight:900}.badge{padding:7px 11px;border-radius:99px;background:#fff0f5;color:#d00037;font-size:12px;font-weight:900;height:max-content}.body{padding:20px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}.box{background:#fafafa;border:1px solid #eee;border-radius:11px;padding:13px}.box small{display:block;color:#888;font-size:10px;text-transform:uppercase}.box strong{display:block;margin-top:5px}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.btn{border:0;border-radius:10px;padding:12px 16px;font-weight:900;cursor:pointer}.accept{background:#ef003c;color:#fff}.pickup{background:#fff1dc;color:#875300}.way{background:#eaf6ff;color:#076a9e}.deliver{background:#eaf9ef;color:#18703a}.tracking{margin-top:16px;background:#f5faff;border:1px solid #d8eaf6;border-radius:12px;padding:14px}.empty{padding:45px;text-align:center;color:#888}.history{width:100%;border-collapse:collapse;background:#fff}.history th,.history td{padding:12px;border-bottom:1px solid #eee;text-align:left}.history th{font-size:12px;color:#777}@media(max-width:900px){.page{margin-left:0}.body{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}}@media(max-width:600px){.page{padding:17px}.profile{display:none}.grid{grid-template-columns:1fr}.actions .btn{width:100%}.stats{grid-template-columns:1fr 1fr}}
</style></head><body><?php if(file_exists(__DIR__.'/rider-sidebar.php')) include __DIR__.'/rider-sidebar.php'; ?><main class="page"><div class="head"><div><div class="eyebrow">Delivery Partner</div><h1 class="title">Rider Orders</h1><p class="sub">Accept orders, update delivery stages and share live GPS location.</p></div><div class="profile"><strong><?=h($r['full_name'])?></strong><small><?=h(statusLabel($r['status']))?> · <?=h(statusLabel($r['availability_status']))?></small></div></div>
<?php if($msg): ?><div class="flash <?=h($type)\?>"><?=h($msg)?></div><?php endif; ?>
<div class="stats"><div class="stat"><small>New Deliveries</small><strong><?=count($available)?></strong></div><div class="stat"><small>Active Deliveries</small><strong><?=count($active)?></strong></div><div class="stat"><small>Completed</small><strong><?=count($historyRows)?></strong></div><div class="stat"><small>Delivery Earnings</small><strong>Rs <?=number_format($earnings,0)?></strong></div></div>
<section class="section"><h2>🔔 New Deliveries</h2><?php if(!$approved): ?><div class="empty"><h3>Account approval required</h3><p>Your rider account must be active before accepting deliveries.</p></div><?php elseif(!$available): ?><div class="empty">No new deliveries right now.</div><?php else: foreach($available as $o): ?><article class="card"><div class="cardhead"><div><div class="order">Order #<?=h($o['order_number'])?></div><small><?=h($o['created_at'])?></small></div><span class="badge">Preparing</span></div><div class="body"><div class="grid"><div class="box"><small>Customer</small><strong><?=h($o['customer_name']??'Customer')?></strong></div><div class="box"><small>Phone</small><strong><?=h($o['customer_phone']??'')?></strong></div><div class="box"><small>Total</small><strong>Rs <?=number_format((float)$o['total'],0)?></strong></div></div><form method="post" class="actions"><input type="hidden" name="order_id" value="<?=h($o['id'])?>"><input type="hidden" name="delivery_action" value="accept"><button class="btn accept" type="submit">Accept Delivery</button></form></div></article><?php endforeach; endif; ?></section>
<section class="section"><h2>🚴 Active Deliveries</h2><?php if(!$active): ?><div class="empty">No active delivery.</div><?php else: foreach($active as $o): $a=actionFor(strtolower($o['order_status'])); ?><article class="card"><div class="cardhead"><div><div class="order">Order #<?=h($o['order_number'])?></div><small><?=h($o['customer_name']??'Customer')?></small></div><span class="badge"><?=h(statusLabel($o['order_status']))?></span></div><div class="body"><div class="grid"><div class="box"><small>Customer Phone</small><strong><?=h($o['customer_phone']??'')?></strong></div><div class="box"><small>Total</small><strong>Rs <?=number_format((float)$o['total'],0)?></strong></div><div class="box"><small>Delivery Fee</small><strong>Rs <?=number_format((float)$o['delivery_fee'],0)?></strong></div></div><?php if($a): ?><form method="post" class="actions"><input type="hidden" name="order_id" value="<?=h($o['id'])?>"><input type="hidden" name="delivery_action" value="<?=h($a[0])?>"><button class="btn <?=h($a[2])?>" type="submit"><?=h($a[1])?></button></form><?php endif; ?><div class="tracking"><b>📍 Live GPS:</b> <span id="gpsStatus">Waiting for location permission…</span><div style="font-size:12px;color:#667;margin-top:5px">Keep this page open while delivering so the customer can see your latest location.</div></div></div></article><?php endforeach; endif; ?></section>
<section class="section"><h2>Completed Deliveries</h2><?php if(!$historyRows): ?><div class="empty">No completed deliveries yet.</div><?php else: ?><div style="overflow:auto"><table class="history"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Delivery Fee</th><th>Date</th></tr></thead><tbody><?php foreach($historyRows as $o): ?><tr><td>#<?=h($o['order_number'])?></td><td><?=h($o['customer_name']??'Customer')?></td><td>Rs <?=number_format((float)$o['total'],0)?></td><td>Rs <?=number_format((float)$o['delivery_fee'],0)?></td><td><?=h($o['updated_at'])?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></main>
<script>
(function(){const active=<?=json_encode(count($active)>0)?>;const el=document.getElementById('gpsStatus');if(!active){if(el)el.textContent='No active delivery.';return;}if(!navigator.geolocation){if(el)el.textContent='GPS is not supported on this device.';return;}function send(p){const fd=new FormData();fd.append('latitude',p.coords.latitude);fd.append('longitude',p.coords.longitude);fd.append('accuracy',p.coords.accuracy||'');fetch('rider-orders.php?ajax=location',{method:'POST',body:fd,cache:'no-store'}).then(r=>r.json()).then(d=>{if(el)el.textContent=d.ok?'Live location updated':'Could not save location';}).catch(()=>{if(el)el.textContent='Location update failed';});}function err(){if(el)el.textContent='Location permission required.';}navigator.geolocation.getCurrentPosition(send,err,{enableHighAccuracy:true,maximumAge:5000,timeout:10000});setInterval(()=>navigator.geolocation.getCurrentPosition(send,err,{enableHighAccuracy:true,maximumAge:5000,timeout:10000}),5000);})();
</script></body></html>