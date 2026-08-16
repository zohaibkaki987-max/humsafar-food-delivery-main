<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) {
    header('Location: rider-login.php'); exit;
}
$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId <= 0) { session_unset(); session_destroy(); header('Location: rider-login.php'); exit; }

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists($db,$table){
    $table=$db->real_escape_string($table);
    $r=$db->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows>0;
}
function colExists($db,$table,$col){
    if(!tableExists($db,$table)) return false;
    $table=$db->real_escape_string($table); $col=$db->real_escape_string($col);
    $r=$db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && $r->num_rows>0;
}
function label($s){ return ucwords(str_replace('_',' ',strtolower(trim((string)$s)))); }
function notifyCustomer($db,$userId,$title,$message,$type,$orderId){
    if(!$userId || !tableExists($db,'notifications')) return;
    $st=$db->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id) VALUES (?, 'customer', ?, ?, ?, ?)");
    if($st){ $st->bind_param('isssi',$userId,$title,$message,$type,$orderId); $st->execute(); $st->close(); }
}
function riderDeliveryColumn($db){
    if(!tableExists($db,'rider_deliveries')) return null;
    if(colExists($db,'rider_deliveries','status')) return 'status';
    if(colExists($db,'rider_deliveries','delivery_status')) return 'delivery_status';
    return null;
}

/* Rider profile */
$rider=['full_name'=>$_SESSION['rider_name']??'Rider','phone'=>'','vehicle_type'=>'Bike','status'=>'pending','bike_number'=>''];
$st=$conn->prepare("SELECT full_name,phone,vehicle_type,status,bike_number FROM riders WHERE id=? LIMIT 1");
if($st){$st->bind_param('i',$riderId);$st->execute();$x=$st->get_result()->fetch_assoc();$st->close();if($x)$rider=array_merge($rider,$x);}
$approved=in_array(strtolower(trim((string)$rider['status'])),['active','approved'],true);

/* Detect the existing order schema without forcing one exact column name. */
$orderCols=[];$q=$conn->query('SHOW COLUMNS FROM orders');
if($q) while($c=$q->fetch_assoc()) $orderCols[]=$c['Field'];
$riderCol=null; foreach(['rider_id','delivery_rider_id','assigned_rider_id'] as $c){if(in_array($c,$orderCols,true)){$riderCol=$c;break;}}
$statusCol=null; foreach(['order_status','status'] as $c){if(in_array($c,$orderCols,true)){$statusCol=$c;break;}}
if(!$riderCol || !$statusCol) die('Orders table is missing the rider/status columns required for delivery workflow.');

/* GPS storage */
$conn->query("CREATE TABLE IF NOT EXISTS rider_locations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 rider_id INT NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 accuracy DECIMAL(10,2) NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_rider_location(rider_id,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Keep delivery records available even if the project did not previously create them. */
$conn->query("CREATE TABLE IF NOT EXISTS rider_deliveries (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 rider_id INT NOT NULL,
 order_id INT NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'accepted',
 accepted_at DATETIME NULL,
 picked_up_at DATETIME NULL,
 delivered_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rider_order(rider_id,order_id),
 INDEX idx_order(order_id), INDEX idx_rider_status(rider_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* GPS endpoint. Browser sends location while an active delivery is open. */
if(($_GET['ajax']??'')==='location' && $_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=utf-8');
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);
    $lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);
    $acc=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
    if($lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){echo json_encode(['ok'=>false,'message'=>'Invalid coordinates']);exit;}
    $st=$conn->prepare("INSERT INTO rider_locations(rider_id,latitude,longitude,accuracy) VALUES(?,?,?,?)");
    if($st){$st->bind_param('iddd',$riderId,$lat,$lng,$acc);$ok=$st->execute();$st->close();echo json_encode(['ok'=>$ok]);}
    else echo json_encode(['ok'=>false,'message'=>'GPS storage unavailable']);
    exit;
}

/* Rider status actions */
$flash='';$flashType='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delivery_action'])){
    $action=trim((string)$_POST['delivery_action']);
    $oid=(int)($_POST['order_id']??0);
    $allowed=['accept','picked_up','out_for_delivery','delivered'];
    if(!$approved){$flash='Your rider account is not approved yet.';$flashType='error';}
    elseif($oid<=0 || !in_array($action,$allowed,true)){$flash='Invalid delivery request.';$flashType='error';}
    else{
        $conn->begin_transaction();
        try{
            $st=$conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1 FOR UPDATE");
            if(!$st) throw new Exception('Unable to read order.');
            $st->bind_param('i',$oid);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
            if(!$order) throw new Exception('Order not found.');
            $current=strtolower(trim((string)$order[$statusCol]));
            $assigned=(int)($order[$riderCol]??0);

            if($action==='accept'){
                if($current!=='preparing') throw new Exception('Only Preparing orders can be accepted.');
                if($assigned>0 && $assigned!==$riderId) throw new Exception('This delivery has already been accepted by another rider.');
                $new='rider_assigned';
                $st=$conn->prepare("UPDATE orders SET `$riderCol`=?, `$statusCol`=? WHERE id=? AND (`$riderCol` IS NULL OR `$riderCol`=0) AND LOWER(TRIM(`$statusCol`))='preparing' LIMIT 1");
                if(!$st) throw new Exception('Unable to assign delivery.');
                $st->bind_param('isi',$riderId,$new,$oid);$st->execute();$changed=$st->affected_rows;$st->close();
                if($changed!==1) throw new Exception('This delivery was already taken.');

                $dc=riderDeliveryColumn($conn);
                if(!tableExists($conn,'rider_deliveries')) throw new Exception('Rider delivery table could not be created.');
                $st=$conn->prepare("INSERT INTO rider_deliveries(rider_id,order_id,status,accepted_at) VALUES(?,?, 'accepted', NOW()) ON DUPLICATE KEY UPDATE status='accepted',accepted_at=NOW()");
                if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$st->close();}
                notifyCustomer($conn,(int)($order['user_id']??0),'Rider assigned','A rider has accepted order #'.$oid.'. Rider details and live location are now available.','rider_assigned',$oid);
                $flash='Delivery accepted. You are now assigned to order #'.$oid.'.';
            }
            elseif($assigned!==$riderId) throw new Exception('This order is not assigned to you.');
            elseif($action==='picked_up'){
                if($current!=='rider_assigned') throw new Exception('Order must be Rider Assigned before pickup.');
                $new='picked_up';$st=$conn->prepare("UPDATE orders SET `$statusCol`=? WHERE id=? AND `$riderCol`=? LIMIT 1");$st->bind_param('sii',$new,$oid,$riderId);$st->execute();$st->close();
                $st=$conn->prepare("UPDATE rider_deliveries SET status='picked_up',picked_up_at=NOW() WHERE rider_id=? AND order_id=? LIMIT 1");if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$st->close();}
                notifyCustomer($conn,(int)($order['user_id']??0),'Order picked up','Your order #'.$oid.' has been picked up by the rider.','order_picked_up',$oid);
                $flash='Order marked as Picked Up.';
            }
            elseif($action==='out_for_delivery'){
                if($current!=='picked_up') throw new Exception('Order must be Picked Up first.');
                $new='out_for_delivery';$st=$conn->prepare("UPDATE orders SET `$statusCol`=? WHERE id=? AND `$riderCol`=? LIMIT 1");$st->bind_param('sii',$new,$oid,$riderId);$st->execute();$st->close();
                $st=$conn->prepare("UPDATE rider_deliveries SET status='out_for_delivery' WHERE rider_id=? AND order_id=? LIMIT 1");if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$st->close();}
                notifyCustomer($conn,(int)($order['user_id']??0),'Order is on the way','Your order #'.$oid.' is out for delivery. You can follow the rider live.','out_for_delivery',$oid);
                $flash='Order is now Out for Delivery.';
            }
            else{
                if(!in_array($current,['out_for_delivery','picked_up'],true)) throw new Exception('Order is not ready to be delivered.');
                $new='delivered';$st=$conn->prepare("UPDATE orders SET `$statusCol`=? WHERE id=? AND `$riderCol`=? LIMIT 1");$st->bind_param('sii',$new,$oid,$riderId);$st->execute();$st->close();
                $st=$conn->prepare("UPDATE rider_deliveries SET status='delivered',delivered_at=NOW() WHERE rider_id=? AND order_id=? LIMIT 1");if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$st->close();}
                notifyCustomer($conn,(int)($order['user_id']??0),'Order delivered','Your order #'.$oid.' has been delivered. Thank you for using Humsafar.','delivered',$oid);
                $flash='Order marked as Delivered successfully.';
            }
            if(tableExists($conn,'order_status_history') && $flash){
                $role='rider';$note=$flash;$by=$riderId;$st=$conn->prepare("INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note) VALUES(?,?,?,?,?)");
                if($st){$st->bind_param('isiss',$oid,$new,$by,$role,$note);$st->execute();$st->close();}
            }
            $conn->commit();$flashType='success';
        }catch(Throwable $ex){$conn->rollback();$flash=$ex->getMessage();$flashType='error';}
    }
}

/* New delivery pool: restaurant accepted => Preparing + no rider. */
$available=[];
$st=$conn->prepare("SELECT o.*, u.full_name customer_name, u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE LOWER(TRIM(o.`$statusCol`))='preparing' AND (o.`$riderCol` IS NULL OR o.`$riderCol`=0) ORDER BY o.id DESC");
if($st){$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$available[]=$x;$st->close();}

/* Rider's active deliveries. */
$mine=[];
$st=$conn->prepare("SELECT o.*, u.full_name customer_name, u.phone customer_phone FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.`$riderCol`=? AND LOWER(TRIM(o.`$statusCol`)) NOT IN('delivered','completed','cancelled','rejected') ORDER BY o.id DESC");
if($st){$st->bind_param('i',$riderId);$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$mine[]=$x;$st->close();}

/* Completed history + simple rider earnings based on delivery fee. */
$completed=[];$earnings=0;$completedCount=0;
$st=$conn->prepare("SELECT o.*, u.full_name customer_name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.`$riderCol`=? AND LOWER(TRIM(o.`$statusCol`)) IN('delivered','completed') ORDER BY o.id DESC LIMIT 30");
if($st){$st->bind_param('i',$riderId);$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc()){$completed[]=$x;$completedCount++;$earnings+=(float)($x['delivery_fee']??0);}$st->close();}

$activeIds=array_map(fn($x)=>(int)$x['id'],$mine);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rider Orders | Humsafar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f8fa;color:#222;font-family:Segoe UI,Arial,sans-serif;font-size:15px}.page{margin-left:220px;padding:30px 34px;min-height:100vh}.head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px}.eyebrow{font-size:12px;font-weight:900;letter-spacing:1.2px;color:#ef003c;text-transform:uppercase}.title{font-size:34px;margin:5px 0;font-weight:900}.sub{color:#777;margin:0}.profile{background:#fff;border:1px solid #e8e8e8;border-radius:13px;padding:12px 17px;text-align:right}.profile strong{font-size:15px}.profile small{display:block;color:#777;margin-top:4px}.flash{padding:15px 18px;border-radius:12px;margin-bottom:20px;font-weight:700}.success{background:#eaf9ef;border:1px solid #c8ecd4;color:#18703a}.error{background:#fff0f3;border:1px solid #ffd1db;color:#b00030}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:28px}.stat{background:#fff;border:1px solid #e8e8e8;border-radius:16px;padding:19px;box-shadow:0 4px 16px #00000008}.stat i{float:right;color:#ef003c;font-size:19px}.stat small{display:block;color:#777;font-weight:700}.stat strong{display:block;font-size:27px;margin-top:6px}.section{margin:27px 0}.section h2{font-size:22px;margin:0 0 15px}.card{background:#fff;border:1px solid #e7e7e7;border-radius:17px;overflow:hidden;margin-bottom:17px;box-shadow:0 5px 18px #00000008}.cardhead{padding:19px 22px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:10px}.order{font-size:20px;font-weight:900}.date{font-size:13px;color:#888;margin-top:4px}.badge{background:#fff0f5;color:#d00037;border-radius:99px;padding:8px 13px;font-size:12px;font-weight:900;height:max-content}.badge.green{background:#ebfaf0;color:#19713c}.body{padding:22px;display:grid;grid-template-columns:1fr 330px;gap:22px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:14px}.box small{display:block;color:#888;text-transform:uppercase;font-size:10px;font-weight:800}.box strong{display:block;margin-top:5px}.notice{margin-top:16px;padding:15px;border-radius:12px;background:#f5faff;border:1px solid #d8eaf6;color:#486675;line-height:1.55}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.btn{border:0;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:900;cursor:pointer}.accept{background:#ef003c;color:#fff}.pickup{background:#fff1dc;color:#875300}.way{background:#eaf6ff;color:#076a9e}.deliver{background:#eaf9ef;color:#18703a}.tracking{background:#f5faff;border:1px solid #d8eaf6;border-radius:13px;padding:17px}.tracking strong{font-size:16px}.tracking p{color:#667;line-height:1.6}.live{color:#09894a;font-weight:900}.gps{margin-top:10px;color:#58717c;font-size:13px}.map{height:190px;margin-top:14px;border-radius:12px;background:#eef3f5;display:flex;align-items:center;justify-content:center;text-align:center;color:#73838a;padding:15px}.empty{padding:50px;text-align:center;background:#fff;border:1px dashed #ddd;border-radius:16px;color:#888}.history{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden}.history th,.history td{padding:13px 15px;border-bottom:1px solid #eee;text-align:left}.history th{background:#fafafa;font-size:12px;text-transform:uppercase;color:#777}.money{font-weight:900}.hint{font-size:12px;color:#777;margin-top:7px}@media(max-width:1100px){.page{margin-left:0}.body{grid-template-columns:1fr}}@media(max-width:750px){.page{padding:18px}.stats{grid-template-columns:1fr 1fr}.grid{grid-template-columns:1fr}.profile{display:none}.title{font-size:28px}.cardhead{align-items:flex-start}.actions .btn{width:100%}}@media(max-width:500px){.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php if(file_exists(__DIR__.'/rider-sidebar.php')) include __DIR__.'/rider-sidebar.php'; ?>
<main class="page">
<div class="head"><div><div class="eyebrow">Delivery Partner</div><h1 class="title">Rider Orders</h1><p class="sub">Accept deliveries, update delivery stages and keep your GPS location live.</p></div><div class="profile"><strong><?=e($rider['full_name'])?></strong><small><?=e(label($rider['status']))?> · <?=e($rider['vehicle_type'])?></small></div></div>
<?php if($flash): ?><div class="flash <?=e($flashType)"><i class="fa-solid fa-circle-<?=($flashType==='success'?'check':'exclamation')?>"></i> <?=e($flash)?></div><?php endif; ?>
<div class="stats">
<div class="stat"><i class="fa-solid fa-bell"></i><small>New Deliveries</small><strong><?=count($available)?></strong></div>
<div class="stat"><i class="fa-solid fa-motorcycle"></i><small>Active Deliveries</small><strong><?=count($mine)?></strong></div>
<div class="stat"><i class="fa-solid fa-circle-check"></i><small>Completed</small><strong><?=$completedCount?></strong></div>
<div class="stat"><i class="fa-solid fa-wallet"></i><small>Delivery Earnings</small><strong>Rs <?=number_format($earnings,0)?></strong></div>
</div>

<section class="section"><h2><i class="fa-solid fa-bell"></i> New Deliveries</h2>
<?php if(!$approved): ?><div class="empty"><i class="fa-solid fa-user-clock" style="font-size:38px;color:#ef003c"></i><h3>Account approval required</h3><p>Your rider account must be approved before you can accept deliveries.</p></div>
<?php elseif(!$available): ?><div class="empty"><i class="fa-solid fa-motorcycle" style="font-size:38px;color:#ef003c"></i><h3>No new deliveries</h3><p>When a restaurant accepts an order, it changes to <b>Preparing</b> and appears here automatically.</p></div>
<?php else: foreach($available as $o): ?><article class="card"><div class="cardhead"><div><div class="order">Order #<?=e($o['order_number']??$o['id'])?></div><div class="date">Restaurant accepted · <?=e(date('d M Y, h:i A',strtotime($o['created_at']??'now')))?></div></div><span class="badge">Preparing · Rider Needed</span></div><div class="body"><div><div class="grid"><div class="box"><small>Customer</small><strong><?=e($o['customer_name']??'Customer')?></strong></div><div class="box"><small>Phone</small><strong><?=e($o['customer_phone']??'Not available')?></strong></div><div class="box"><small>Total</small><strong>Rs <?=number_format((float)($o['total']??0),0)?></strong></div></div><div class="notice"><b><i class="fa-solid fa-circle-info"></i> New delivery available</b><br>The restaurant accepted this order. Accept it to become the assigned rider. Only one rider can win the assignment.</div><div class="actions"><form method="post"><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><button class="btn accept" name="delivery_action" value="accept" type="submit"><i class="fa-solid fa-check"></i> Accept Delivery</button></form></div></div><div class="tracking"><strong><i class="fa-solid fa-location-dot"></i> After acceptance</strong><p>Customer and restaurant will be able to receive your rider information and live GPS location. Your next stages will be <b>Picked Up → Out for Delivery → Delivered</b>.</p></div></div></article><?php endforeach; endif; ?></section>

<section class="section"><h2><i class="fa-solid fa-truck-fast"></i> My Active Deliveries</h2>
<?php if(!$mine): ?><div class="empty">No active delivery assigned to you.</div>
<?php else: foreach($mine as $o): $s=strtolower(trim((string)$o[$statusCol])); ?><article class="card active-card" data-order-id="<?=e($o['id'])?>"><div class="cardhead"><div><div class="order">Order #<?=e($o['order_number']??$o['id'])?></div><div class="date">Assigned to <?=e($rider['full_name'])?></div></div><span class="badge <?=in_array($s,['rider_assigned','picked_up','out_for_delivery'],true)?'':'green'?>"><?=e(label($s))?></span></div><div class="body"><div><div class="grid"><div class="box"><small>Customer</small><strong><?=e($o['customer_name']??'Customer')?></strong></div><div class="box"><small>Phone</small><strong><?=e($o['customer_phone']??'Not available')?></strong></div><div class="box"><small>Total</small><strong>Rs <?=number_format((float)($o['total']??0),0)?></strong></div></div><div class="notice"><b>Delivery sequence:</b> Assigned → Picked Up → Out for Delivery → Delivered</div><div class="actions">
<?php if($s==='rider_assigned'): ?><form method="post"><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><button class="btn pickup" name="delivery_action" value="picked_up"><i class="fa-solid fa-box"></i> Mark Picked Up</button></form>
<?php elseif($s==='picked_up'): ?><form method="post"><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><button class="btn way" name="delivery_action" value="out_for_delivery"><i class="fa-solid fa-route"></i> Start Delivery</button></form>
<?php elseif($s==='out_for_delivery'): ?><form method="post" onsubmit="return confirm('Confirm that this order has been delivered to the customer?');"><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><button class="btn deliver" name="delivery_action" value="delivered"><i class="fa-solid fa-circle-check"></i> Mark Delivered</button></form><?php endif; ?></div></div><div class="tracking"><strong><i class="fa-solid fa-satellite-dish"></i> Live Location</strong><p class="live"><i class="fa-solid fa-circle"></i> GPS tracking can run during this active delivery.</p><div class="gps" data-gps-text>Waiting for GPS permission...</div><div class="map" data-map>GPS map will use your current device location.</div></div></div></article><?php endforeach; endif; ?></section>

<section class="section"><h2><i class="fa-solid fa-clock-rotate-left"></i> Delivery History</h2>
<?php if(!$completed): ?><div class="empty">No completed deliveries yet.</div><?php else: ?><div style="overflow:auto"><table class="history"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Delivery Earnings</th></tr></thead><tbody><?php foreach($completed as $o): ?><tr><td>#<?=e($o['order_number']??$o['id'])?></td><td><?=e($o['customer_name']??'Customer')?></td><td><?=e(date('d M Y',strtotime($o['created_at']??'now')))?></td><td>Rs <?=number_format((float)($o['total']??0),0)?></td><td class="money">Rs <?=number_format((float)($o['delivery_fee']??0),0)?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main>
<script>
const activeCards=document.querySelectorAll('.active-card');
let gpsStarted=false;
function sendGPS(pos){const fd=new FormData();fd.append('latitude',pos.coords.latitude);fd.append('longitude',pos.coords.longitude);fd.append('accuracy',pos.coords.accuracy||'');fetch('?ajax=location',{method:'POST',body:fd,credentials:'same-origin'}).then(()=>{}).catch(()=>{});document.querySelectorAll('[data-gps-text]').forEach(el=>el.innerHTML='<span style="color:#09894a;font-weight:800">GPS Live</span> · '+pos.coords.latitude.toFixed(5)+', '+pos.coords.longitude.toFixed(5));}
function gpsError(err){document.querySelectorAll('[data-gps-text]').forEach(el=>el.textContent='GPS unavailable: '+(err.code===1?'location permission denied':'unable to read location'));}
if(activeCards.length && navigator.geolocation){navigator.geolocation.watchPosition(sendGPS,gpsError,{enableHighAccuracy:true,maximumAge:5000,timeout:15000});gpsStarted=true;document.getElementById('gps')?.replaceChildren(document.createTextNode('Live'));}else if(document.getElementById('gps'))document.getElementById('gps').textContent='Off';
setInterval(()=>{if(document.visibilityState==='visible')location.reload();},20000);
</script>
</body></html>
