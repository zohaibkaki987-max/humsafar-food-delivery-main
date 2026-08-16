<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) {
    header('Location: rider-login.php');
    exit;
}

$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId <= 0) {
    session_unset();
    session_destroy();
    header('Location: rider-login.php');
    exit;
}

function ro($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists($conn,$table){
    $table=$conn->real_escape_string($table);
    $r=$conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows>0;
}
function colExists($conn,$table,$column){
    if(!tableExists($conn,$table)) return false;
    $table=$conn->real_escape_string($table);$column=$conn->real_escape_string($column);
    $r=$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows>0;
}
function statusLabel($s){return ucwords(str_replace('_',' ',strtolower(trim((string)$s))));}

/* Rider */
$rider=['id'=>$riderId,'full_name'=>$_SESSION['rider_name']??'Rider','email'=>'','phone'=>'','vehicle_type'=>'Bike','status'=>'pending','bike_number'=>''];
$st=$conn->prepare("SELECT id,full_name,email,phone,vehicle_type,status,bike_number FROM riders WHERE id=? LIMIT 1");
if($st){$st->bind_param('i',$riderId);$st->execute();$db=$st->get_result()->fetch_assoc();$st->close();if($db)$rider=$db;}
$isApproved=in_array(strtolower(trim((string)$rider['status'])),['active','approved'],true);

/* Location table is required by the customer/restaurant live-map screens. */
$conn->query("CREATE TABLE IF NOT EXISTS rider_locations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 rider_id INT NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 accuracy DECIMAL(10,2) NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_rider_location (rider_id,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Detect existing order columns. */
$orderColumns=[];$cr=$conn->query('SHOW COLUMNS FROM orders');
if($cr)while($c=$cr->fetch_assoc())$orderColumns[]=$c['Field'];
$has=function($c)use($orderColumns){return in_array($c,$orderColumns,true);};
$riderColumn=null;foreach(['rider_id','delivery_rider_id','assigned_rider_id'] as $c){if($has($c)){$riderColumn=$c;break;}}
$statusColumn=null;foreach(['order_status','status'] as $c){if($has($c)){$statusColumn=$c;break;}}
if(!$riderColumn || !$statusColumn) die('The orders table needs a rider assignment column and an order status column.');

$message='';$messageType='';

/* AJAX: rider sends GPS position. */
if(isset($_GET['ajax']) && $_GET['ajax']==='update_location'){
    header('Content-Type: application/json; charset=utf-8');
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);
    $lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);
    $acc=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
    if($lat===false||$lng===false||$lat < -90||$lat > 90||$lng < -180||$lng > 180){echo json_encode(['ok'=>false,'message'=>'Invalid GPS coordinates.']);exit;}
    $st=$conn->prepare("INSERT INTO rider_locations (rider_id,latitude,longitude,accuracy) VALUES (?,?,?,?)");
    if(!$st){echo json_encode(['ok'=>false,'message'=>'Location storage is unavailable.']);exit;}
    $st->bind_param('iddd',$riderId,$lat,$lng,$acc);$ok=$st->execute();$st->close();
    echo json_encode(['ok'=>$ok]);exit;
}

/* AJAX: latest rider location for this rider. */
if(isset($_GET['ajax']) && $_GET['ajax']==='my_location'){
    header('Content-Type: application/json; charset=utf-8');
    $st=$conn->prepare("SELECT latitude,longitude,accuracy,updated_at FROM rider_locations WHERE rider_id=? ORDER BY id DESC LIMIT 1");
    $row=null;if($st){$st->bind_param('i',$riderId);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();}
    echo json_encode(['ok'=>(bool)$row,'location'=>$row]);exit;
}

/* Accept Delivery: only PREPARING orders released by restaurant are available. */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accept_delivery'])){
    $orderId=(int)($_POST['order_id']??0);
    if(!$isApproved){$message='Your rider account is not approved yet.';$messageType='error';}
    elseif($orderId<=0){$message='Invalid order selected.';$messageType='error';}
    else{
        $conn->begin_transaction();
        try{
            $st=$conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1 FOR UPDATE");
            if(!$st)throw new Exception('Unable to prepare order query.');
            $st->bind_param('i',$orderId);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
            if(!$order)throw new Exception('Order not found.');
            $current=strtolower(trim((string)($order[$statusColumn]??'')));
            $existing=(int)($order[$riderColumn]??0);
            if($existing>0)throw new Exception('This order has already been accepted by another rider.');
            if($current!=='preparing')throw new Exception('This order is no longer available. Only Preparing orders can be accepted.');

            $newStatus='rider_assigned';
            $sql="UPDATE orders SET `$riderColumn`=?, `$statusColumn`=? WHERE id=? AND (`$riderColumn` IS NULL OR `$riderColumn`=0) AND LOWER(TRIM(`$statusColumn`))='preparing' LIMIT 1";
            $st=$conn->prepare($sql);if(!$st)throw new Exception('Unable to assign this delivery.');
            $st->bind_param('isi',$riderId,$newStatus,$orderId);$st->execute();$affected=$st->affected_rows;$st->close();
            if($affected!==1)throw new Exception('This delivery was already taken by another rider.');

            if(tableExists($conn,'rider_deliveries')){
                $deliveryStatusCol=colExists($conn,'rider_deliveries','status')?'status':(colExists($conn,'rider_deliveries','delivery_status')?'delivery_status':null);
                $check=$conn->prepare("SELECT id FROM rider_deliveries WHERE rider_id=? AND order_id=? LIMIT 1");
                $exists=null;if($check){$check->bind_param('ii',$riderId,$orderId);$check->execute();$exists=$check->get_result()->fetch_assoc();$check->close();}
                if(!$exists){
                    if($deliveryStatusCol){$q="INSERT INTO rider_deliveries (rider_id,order_id,`$deliveryStatusCol`) VALUES (?,?,?)";$ins=$conn->prepare($q);$ds='accepted';if($ins){$ins->bind_param('iis',$riderId,$orderId,$ds);$ins->execute();$ins->close();}}
                    else{$ins=$conn->prepare("INSERT INTO rider_deliveries (rider_id,order_id) VALUES (?,?)");if($ins){$ins->bind_param('ii',$riderId,$orderId);$ins->execute();$ins->close();}}
                }else if($deliveryStatusCol){$up=$conn->prepare("UPDATE rider_deliveries SET `$deliveryStatusCol`='accepted' WHERE id=? LIMIT 1");if($up){$did=(int)$exists['id'];$up->bind_param('i',$did);$up->execute();$up->close();}}
            }

            /* Customer notification when rider accepts. */
            if(tableExists($conn,'notifications') && isset($order['user_id'])){
                $title='Rider assigned';$msg='A rider has accepted your order #'.$orderId.' and is on the way to pick it up.';$type='rider_assigned';$uid=(int)$order['user_id'];
                $n=$conn->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id) VALUES (?, 'customer', ?, ?, ?, ?)");
                if($n){$n->bind_param('isssi',$uid,$title,$msg,$type,$orderId);$n->execute();$n->close();}
            }
            $conn->commit();
            $message='Delivery accepted. Your rider information is now connected to the customer and restaurant order tracking.';$messageType='success';
        }catch(Throwable $e){$conn->rollback();$message=$e->getMessage();$messageType='error';}
    }
}

/* Active orders accepted by this rider. */
$myOrders=[];
$sql="SELECT o.* FROM orders o WHERE o.`$riderColumn`=? AND LOWER(TRIM(o.`$statusColumn`)) NOT IN ('delivered','completed','cancelled','rejected') ORDER BY o.id DESC";
$st=$conn->prepare($sql);if($st){$st->bind_param('i',$riderId);$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$myOrders[]=$x;$st->close();}

/* Available = restaurant accepted and preparing, with no rider yet. */
$available=[];
$sql="SELECT o.* FROM orders o WHERE LOWER(TRIM(o.`$statusColumn`))='preparing' AND (o.`$riderColumn` IS NULL OR o.`$riderColumn`=0) ORDER BY o.id DESC";
$st=$conn->prepare($sql);if($st){$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$available[]=$x;$st->close();}

/* Current location for UI. */
$location=null;$st=$conn->prepare("SELECT latitude,longitude,accuracy,updated_at FROM rider_locations WHERE rider_id=? ORDER BY id DESC LIMIT 1");if($st){$st->bind_param('i',$riderId);$st->execute();$location=$st->get_result()->fetch_assoc();$st->close();}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Rider Orders | Humsafar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f8f9fb;color:#202124;font-family:"Segoe UI",Arial,sans-serif;font-size:15px}.page{margin-left:220px;padding:30px 34px;min-height:100vh}.head{display:flex;justify-content:space-between;align-items:end;margin-bottom:24px}.eyebrow{color:#ef003c;font-weight:800;font-size:12px;letter-spacing:1.2px;text-transform:uppercase}.title{font-size:34px;margin:5px 0 0;font-weight:900}.sub{color:#777;margin:7px 0 0;font-size:15px}.profile{background:#fff;border:1px solid #eee;border-radius:13px;padding:12px 16px;text-align:right}.profile strong{font-size:15px}.profile small{display:block;color:#777;margin-top:3px}.flash{padding:15px 18px;border-radius:12px;margin-bottom:20px;font-weight:700}.success{background:#eaf9ef;border:1px solid #c8ecd4;color:#19703b}.error{background:#fff0f3;border:1px solid #ffd1db;color:#b10031}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}.stat{background:#fff;border:1px solid #e9e9e9;border-radius:16px;padding:20px;box-shadow:0 5px 18px rgba(0,0,0,.04)}.stat i{float:right;color:#ef003c;font-size:20px}.stat small{display:block;color:#777;font-weight:700}.stat strong{display:block;font-size:29px;margin-top:6px}.section{margin-top:28px}.section h2{font-size:22px;margin:0 0 14px}.card{background:#fff;border:1px solid #e8e8e8;border-radius:17px;margin-bottom:17px;overflow:hidden;box-shadow:0 5px 18px rgba(0,0,0,.04)}.card-head{display:flex;justify-content:space-between;gap:12px;padding:20px 22px;border-bottom:1px solid #eee}.order-no{font-size:20px;font-weight:900}.date{color:#888;font-size:13px;margin-top:4px}.badge{display:inline-flex;align-items:center;border-radius:99px;padding:8px 13px;font-size:12px;font-weight:900;background:#fff0f5;color:#d00037;height:max-content}.card-body{padding:21px;display:grid;grid-template-columns:1fr 330px;gap:24px}.info{display:grid;grid-template-columns:1fr 1fr;gap:13px}.box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:14px}.box small{display:block;color:#888;text-transform:uppercase;font-size:10px;font-weight:800}.box strong{display:block;margin-top:5px;font-size:14px}.items{margin-top:16px;border-top:1px solid #eee;padding-top:12px}.item{display:flex;justify-content:space-between;padding:8px 0}.accept{margin-top:17px;background:#ef003c;color:#fff;border:0;border-radius:11px;padding:14px 20px;font-weight:900;font-size:15px;cursor:pointer}.accept:hover{opacity:.92}.waiting{padding:15px 18px;background:#fff8e6;border:1px solid #f1dfac;color:#805900;border-radius:12px;font-weight:700}.empty{padding:55px;background:#fff;border:1px dashed #ddd;border-radius:16px;text-align:center;color:#888}.map{height:300px;border-radius:13px;overflow:hidden;border:1px solid #ddd}.tracking{margin-top:16px;padding:17px;background:#f5faff;border:1px solid #d9ebf8;border-radius:13px}.tracking strong{font-size:16px}.gps{margin-top:8px;color:#4d6c7b;font-size:13px}.live{color:#0c8c4a;font-weight:900}.loc-btn{margin-top:10px;border:1px solid #d7e7ee;background:#fff;border-radius:9px;padding:9px 13px;font-weight:800;cursor:pointer}@media(max-width:1050px){.page{margin-left:0}.card-body{grid-template-columns:1fr}.map{height:280px}}@media(max-width:650px){.page{padding:18px}.head{align-items:flex-start;gap:12px}.profile{display:none}.stats{grid-template-columns:1fr}.info{grid-template-columns:1fr}.title{font-size:28px}}
</style></head><body>
<?php if(file_exists(__DIR__.'/rider-sidebar.php')) include __DIR__.'/rider-sidebar.php'; ?>
<main class="page"><div class="head"><div><div class="eyebrow">Delivery Partner</div><h1 class="title">Rider Orders</h1><p class="sub">Accept restaurant-prepared orders and keep your live location connected.</p></div><div class="profile"><strong><?=ro($rider['full_name'])?></strong><small><?=ro(statusLabel($rider['status']))?> · <?=ro($rider['vehicle_type'])?></small></div></div>
<?php if($message):?><div class="flash <?=ro($messageType)"><i class="fa-solid fa-circle-<?= $messageType==='success'?'check':'exclamation'?>"></i> <?=ro($message)?></div><?php endif;?>
<div class="stats"><div class="stat"><i class="fa-solid fa-bell"></i><small>Available Deliveries</small><strong><?=count($available)?></strong></div><div class="stat"><i class="fa-solid fa-motorcycle"></i><small>My Active Deliveries</small><strong><?=count($myOrders)?></strong></div><div class="stat"><i class="fa-solid fa-location-dot"></i><small>Live Location</small><strong id="gpsState">Off</strong></div></div>
<section class="section"><h2><i class="fa-solid fa-bell"></i> New Deliveries</h2>
<?php if(!$isApproved):?><div class="waiting">Your rider account must be approved before deliveries can be accepted.</div>
<?php elseif(!$available):?><div class="empty"><i class="fa-solid fa-motorcycle" style="font-size:38px;color:#ef003c"></i><h3>No new deliveries</h3><p>When a restaurant accepts an order, it will appear here automatically.</p></div>
<?php else:foreach($available as $o):?><article class="card"><div class="card-head"><div><div class="order-no">Order #<?=ro($o['order_number']?:$o['id'])?></div><div class="date">Restaurant accepted · <?=ro(date('d M Y, h:i A',strtotime($o['created_at'])))?></div></div><span class="badge">Preparing · Rider Needed</span></div><div class="card-body"><div><div class="info"><div class="box"><small>Order Total</small><strong>Rs <?=number_format((float)$o['total'],0)?></strong></div><div class="box"><small>Payment</small><strong><?=ro(strtoupper($o['payment_method']??'COD'))?></strong></div></div><div class="items"><strong>Delivery job</strong><p style="color:#666;margin:7px 0">The restaurant has accepted this order and started preparing it. Accept it to become the assigned rider.</p></div><form method="post"><input type="hidden" name="order_id" value="<?=ro($o['id'])?>"><button class="accept" type="submit" name="accept_delivery"><i class="fa-solid fa-check"></i> Accept Delivery</button></form></div><div class="tracking"><strong><i class="fa-solid fa-route"></i> What happens after you accept?</strong><p style="margin:8px 0;color:#667">You become the assigned rider. Your name and contact details become available to the customer/restaurant, and your live GPS location can be shared on their tracking map.</p></div></div></article><?php endforeach;endif;?></section>
<section class="section"><h2><i class="fa-solid fa-truck-fast"></i> My Active Deliveries</h2>
<?php if(!$myOrders):?><div class="empty">No active deliveries right now.</div><?php else:foreach($myOrders as $o):?><article class="card"><div class="card-head"><div><div class="order-no">Order #<?=ro($o['order_number']?:$o['id'])?></div><div class="date">Assigned to you</div></div><span class="badge"><?=ro(statusLabel($o[$statusColumn]))?></span></div><div class="card-body"><div><div class="info"><div class="box"><small>Order Total</small><strong>Rs <?=number_format((float)$o['total'],0)?></strong></div><div class="box"><small>Payment</small><strong><?=ro(strtoupper($o['payment_method']??'COD'))?></strong></div></div><div class="tracking"><strong><span class="live">● LIVE</span> Location sharing</strong><div class="gps" id="locationText">Starting GPS tracking when location permission is granted...</div><button class="loc-btn" type="button" onclick="startGPS()"><i class="fa-solid fa-location-crosshairs"></i> Enable Live Location</button></div></div><div id="riderMap" class="map"></div></div></article><?php endforeach;endif;?></section>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>
let watchId=null,map=null,marker=null;
function startGPS(){if(!navigator.geolocation){document.getElementById('locationText').textContent='Geolocation is not supported on this device.';return;}if(watchId!==null)return;watchId=navigator.geolocation.watchPosition(sendLocation,function(e){document.getElementById('locationText').textContent='GPS permission/location error: '+e.message;},{enableHighAccuracy:true,maximumAge:3000,timeout:10000});document.getElementById('gpsState').textContent='LIVE';}
async function sendLocation(pos){const lat=pos.coords.latitude,lng=pos.coords.longitude,acc=pos.coords.accuracy;document.getElementById('locationText').textContent='Live GPS: '+lat.toFixed(6)+', '+lng.toFixed(6)+' · accuracy '+Math.round(acc)+'m';try{const fd=new FormData();fd.append('latitude',lat);fd.append('longitude',lng);fd.append('accuracy',acc);const r=await fetch('?ajax=update_location',{method:'POST',body:fd});const d=await r.json();if(d.ok)drawMap(lat,lng);}catch(e){}}
function drawMap(lat,lng){const el=document.getElementById('riderMap');if(!el)return;if(!map){map=L.map(el).setView([lat,lng],16);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);marker=L.marker([lat,lng]).addTo(map);}else{marker.setLatLng([lat,lng]);map.setView([lat,lng]);}}
<?php if($location):?>drawMap(<?=json_encode((float)$location['latitude'])?>,<?=json_encode((float)$location['longitude'])?>);<?php endif;?>
<?php if($myOrders):?>startGPS();<?php endif;?>
</script></body></html>
