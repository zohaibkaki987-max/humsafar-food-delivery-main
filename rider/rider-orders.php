<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) { header('Location: rider-login.php'); exit; }
$riderId=(int)($_SESSION['rider_id']??0);
if($riderId<=0){session_unset();session_destroy();header('Location: rider-login.php');exit;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function existsTable($db,$name){$name=$db->real_escape_string($name);$r=$db->query("SHOW TABLES LIKE '$name'");return $r&&$r->num_rows>0;}
function existsCol($db,$table,$col){if(!existsTable($db,$table))return false;$table=$db->real_escape_string($table);$col=$db->real_escape_string($col);$r=$db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");return $r&&$r->num_rows>0;}
function label($s){return ucwords(str_replace('_',' ',strtolower(trim((string)$s))));}

$rider=['full_name'=>$_SESSION['rider_name']??'Rider','phone'=>'','vehicle_type'=>'Bike','status'=>'pending','bike_number'=>''];
$st=$conn->prepare("SELECT full_name,phone,vehicle_type,status,bike_number FROM riders WHERE id=? LIMIT 1");
if($st){$st->bind_param('i',$riderId);$st->execute();$x=$st->get_result()->fetch_assoc();$st->close();if($x)$rider=array_merge($rider,$x);}
$approved=in_array(strtolower(trim($rider['status'])),['active','approved'],true);

/* Live GPS storage used by customer + restaurant tracking pages. */
$conn->query("CREATE TABLE IF NOT EXISTS rider_locations (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,rider_id INT NOT NULL,latitude DECIMAL(10,7) NOT NULL,longitude DECIMAL(10,7) NOT NULL,accuracy DECIMAL(10,2) NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_rider_location(rider_id,updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$cols=[];$q=$conn->query('SHOW COLUMNS FROM orders');if($q)while($c=$q->fetch_assoc())$cols[]=$c['Field'];
$riderCol=null;foreach(['rider_id','delivery_rider_id','assigned_rider_id'] as $c){if(in_array($c,$cols,true)){$riderCol=$c;break;}}
$statusCol=null;foreach(['order_status','status'] as $c){if(in_array($c,$cols,true)){$statusCol=$c;break;}}
if(!$riderCol||!$statusCol)die('Orders table is missing rider/status columns.');

/* GPS endpoint */
if(($_GET['ajax']??'')==='location'&&$_SERVER['REQUEST_METHOD']==='POST'){
 header('Content-Type: application/json');
 $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);$acc=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
 if($lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){echo json_encode(['ok'=>false]);exit;}
 $st=$conn->prepare("INSERT INTO rider_locations(rider_id,latitude,longitude,accuracy) VALUES(?,?,?,?)");
 if($st){$st->bind_param('iddd',$riderId,$lat,$lng,$acc);$ok=$st->execute();$st->close();echo json_encode(['ok'=>$ok]);}else echo json_encode(['ok'=>false]);exit;
}

$msg='';$type='';
/* Restaurant Accept => Preparing. Rider Accept => Rider Assigned. */
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['accept_delivery'])){
 $oid=(int)($_POST['order_id']??0);
 if(!$approved){$msg='Your rider account is not approved yet.';$type='error';}
 elseif($oid<=0){$msg='Invalid order.';$type='error';}
 else{
  $conn->begin_transaction();
  try{
   $st=$conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1 FOR UPDATE");if(!$st)throw new Exception('Unable to read order.');
   $st->bind_param('i',$oid);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
   if(!$order)throw new Exception('Order not found.');
   if((int)($order[$riderCol]??0)>0)throw new Exception('This order has already been accepted by another rider.');
   if(strtolower(trim((string)$order[$statusCol]))!=='preparing')throw new Exception('Only Preparing orders released by the restaurant can be accepted.');
   $new='rider_assigned';$st=$conn->prepare("UPDATE orders SET `$riderCol`=?,`$statusCol`=? WHERE id=? AND (`$riderCol` IS NULL OR `$riderCol`=0) AND LOWER(TRIM(`$statusCol`))='preparing' LIMIT 1");if(!$st)throw new Exception('Unable to assign delivery.');
   $st->bind_param('isi',$riderId,$new,$oid);$st->execute();$changed=$st->affected_rows;$st->close();if($changed!==1)throw new Exception('This delivery was already taken.');

   if(existsTable($conn,'rider_deliveries')){
    $dcol=existsCol($conn,'rider_deliveries','status')?'status':(existsCol($conn,'rider_deliveries','delivery_status')?'delivery_status':null);
    $st=$conn->prepare("SELECT id FROM rider_deliveries WHERE rider_id=? AND order_id=? LIMIT 1");$old=null;if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$old=$st->get_result()->fetch_assoc();$st->close();}
    if(!$old){if($dcol){$ds='accepted';$st=$conn->prepare("INSERT INTO rider_deliveries(rider_id,order_id,`$dcol`) VALUES(?,?,?)");if($st){$st->bind_param('iis',$riderId,$oid,$ds);$st->execute();$st->close();}}else{$st=$conn->prepare("INSERT INTO rider_deliveries(rider_id,order_id) VALUES(?,?)");if($st){$st->bind_param('ii',$riderId,$oid);$st->execute();$st->close();}}}
    elseif($dcol){$did=(int)$old['id'];$st=$conn->prepare("UPDATE rider_deliveries SET `$dcol`='accepted' WHERE id=? LIMIT 1");if($st){$st->bind_param('i',$did);$st->execute();$st->close();}}
   }
   if(existsTable($conn,'notifications')&&isset($order['user_id'])){
    $uid=(int)$order['user_id'];$title='Rider assigned';$text='A rider has accepted your order #'.$oid.'. You can now see rider information and live location.';$nt='rider_assigned';
    $st=$conn->prepare("INSERT INTO notifications(user_id,role,title,message,type,reference_id) VALUES(?,'customer',?,?,?,?)");if($st){$st->bind_param('isssi',$uid,$title,$text,$nt,$oid);$st->execute();$st->close();}
   }
   $conn->commit();$msg='Delivery accepted successfully. Your rider is now assigned to this order.';$type='success';
  }catch(Throwable $ex){$conn->rollback();$msg=$ex->getMessage();$type='error';}
 }
}

/* Orders accepted by this rider */
$mine=[];$st=$conn->prepare("SELECT * FROM orders WHERE `$riderCol`=? AND LOWER(TRIM(`$statusCol`)) NOT IN('delivered','completed','cancelled','rejected') ORDER BY id DESC");if($st){$st->bind_param('i',$riderId);$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$mine[]=$x;$st->close();}
/* New orders: restaurant accepted them => Preparing + no rider. */
$available=[];$st=$conn->prepare("SELECT * FROM orders WHERE LOWER(TRIM(`$statusCol`))='preparing' AND (`$riderCol` IS NULL OR `$riderCol`=0) ORDER BY id DESC");if($st){$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$available[]=$x;$st->close();}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rider Orders | Humsafar</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>
*{box-sizing:border-box}body{margin:0;background:#f7f8fa;color:#222;font-family:Segoe UI,Arial,sans-serif;font-size:15px}.page{margin-left:220px;padding:32px;min-height:100vh}.head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:25px}.eyebrow{font-size:12px;font-weight:900;letter-spacing:1.2px;color:#ef003c;text-transform:uppercase}.title{font-size:34px;margin:5px 0;font-weight:900}.sub{color:#777;margin:0}.profile{background:#fff;border:1px solid #eee;border-radius:13px;padding:12px 17px;text-align:right}.profile strong{font-size:15px}.profile small{display:block;color:#777;margin-top:4px}.flash{padding:15px 18px;border-radius:12px;margin-bottom:20px;font-weight:700}.success{background:#eaf9ef;border:1px solid #c8ecd4;color:#18703a}.error{background:#fff0f3;border:1px solid #ffd1db;color:#b00030}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:30px}.stat{background:#fff;border:1px solid #e8e8e8;border-radius:16px;padding:20px;box-shadow:0 4px 16px #00000008}.stat i{float:right;color:#ef003c;font-size:20px}.stat small{display:block;color:#777;font-weight:700}.stat strong{display:block;font-size:28px;margin-top:6px}.section{margin:28px 0}.section h2{font-size:22px;margin-bottom:15px}.card{background:#fff;border:1px solid #e8e8e8;border-radius:17px;overflow:hidden;margin-bottom:17px;box-shadow:0 5px 18px #00000008}.cardhead{padding:20px 22px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:10px}.order{font-size:20px;font-weight:900}.date{font-size:13px;color:#888;margin-top:4px}.badge{background:#fff0f5;color:#d00037;border-radius:99px;padding:8px 13px;font-size:12px;font-weight:900;height:max-content}.body{padding:22px;display:grid;grid-template-columns:1fr 320px;gap:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:15px}.box small{display:block;color:#888;text-transform:uppercase;font-size:10px;font-weight:800}.box strong{display:block;margin-top:5px}.notice{margin-top:17px;padding:15px;border-radius:12px;background:#f5faff;border:1px solid #d8eaf6;color:#486675}.accept{margin-top:17px;background:#ef003c;color:#fff;border:0;border-radius:11px;padding:14px 20px;font-size:15px;font-weight:900;cursor:pointer}.empty{padding:55px;text-align:center;background:#fff;border:1px dashed #ddd;border-radius:16px;color:#888}.tracking{padding:18px;background:#f5faff;border:1px solid #d8eaf6;border-radius:13px}.tracking strong{font-size:16px}.live{color:#09894a;font-weight:900}.gps{margin-top:8px;color:#58717c;font-size:13px}.map{height:270px;margin-top:15px;border-radius:12px;background:#eee;display:flex;align-items:center;justify-content:center;color:#888;text-align:center;padding:15px}.btn{margin-top:10px;border:1px solid #d5e5ec;background:#fff;border-radius:9px;padding:9px 13px;font-weight:800;cursor:pointer}@media(max-width:1050px){.page{margin-left:0}.body{grid-template-columns:1fr}}@media(max-width:650px){.page{padding:18px}.stats{grid-template-columns:1fr}.grid{grid-template-columns:1fr}.profile{display:none}.title{font-size:28px}}
</style></head><body><?php if(file_exists(__DIR__.'/rider-sidebar.php'))include __DIR__.'/rider-sidebar.php';?><main class="page">
<div class="head"><div><div class="eyebrow">Delivery Partner</div><h1 class="title">Rider Orders</h1><p class="sub">Restaurant-approved orders appear here automatically.</p></div><div class="profile"><strong><?=e($rider['full_name'])?></strong><small><?=e(label($rider['status']))?> · <?=e($rider['vehicle_type'])?></small></div></div>
<?php if($msg):?><div class="flash <?=e($type)"><i class="fa-solid fa-circle-<?=($type==='success'?'check':'exclamation')?>"></i> <?=e($msg)?></div><?php endif;?>
<div class="stats"><div class="stat"><i class="fa-solid fa-bell"></i><small>New Deliveries</small><strong><?=count($available)?></strong></div><div class="stat"><i class="fa-solid fa-motorcycle"></i><small>My Active Deliveries</small><strong><?=count($mine)?></strong></div><div class="stat"><i class="fa-solid fa-location-dot"></i><small>GPS</small><strong id="gps">Off</strong></div></div>
<section class="section"><h2><i class="fa-solid fa-bell"></i> New Deliveries</h2><?php if(!$approved):?><div class="empty">Your rider account must be approved before accepting deliveries.</div><?php elseif(!$available):?><div class="empty"><i class="fa-solid fa-motorcycle" style="font-size:38px;color:#ef003c"></i><h3>No new deliveries</h3><p>When a restaurant accepts an order, it will appear here as <b>Preparing</b>.</p></div><?php else:foreach($available as $o):?><article class="card"><div class="cardhead"><div><div class="order">Order #<?=e($o['order_number']??$o['id'])?></div><div class="date">Restaurant accepted · <?=e(date('d M Y, h:i A',strtotime($o['created_at']??'now')))?></div></div><span class="badge">Preparing · Rider Needed</span></div><div class="body"><div><div class="grid"><div class="box"><small>Total</small><strong>Rs <?=number_format((float)($o['total']??0),0)?></strong></div><div class="box"><small>Payment</small><strong><?=e(strtoupper($o['payment_method']??'COD'))?></strong></div></div><div class="notice"><b><i class="fa-solid fa-circle-info"></i> New delivery</b><br>The restaurant accepted this order and changed it to <b>Preparing</b>. Accept it to become the assigned rider.</div><form method="post"><input type="hidden" name="order_id" value="<?=e($o['id'])?>"><button class="accept" name="accept_delivery" type="submit"><i class="fa-solid fa-check"></i> Accept Delivery</button></form></div><div class="tracking"><strong><i class="fa-solid fa-route"></i> After acceptance</strong><p style="color:#667;line-height:1.6">Your rider information will be connected to the order. Customer and restaurant can then receive your rider details and live GPS location.</p></div></div></article><?php endforeach;endif;?></section>
<section class="section"><h2><i class="fa-solid fa-truck-fast"></i> My Active Deliveries</h2><?php if(!$mine):?><div class="empty">No active delivery assigned to you.</div><?php else:foreach($mine as $o):?><article class="card"><div class="cardhead"><div><div class="order">Order #<?=e($o['order_number']??$o['id'])?></div><div class="date">Assigned to you</div></div><span class="badge"><?=e(label($o[$statusCol]))?></span></div><div class="body"><div class="grid"><div class="box"><small>Total</small><strong>Rs <?=number_format((float)($o['total']??0),0)?></strong></div><div class="box"><small>Payment</small><strong><?=e(strtoupper($o['payment_method']??'COD'))?></strong></div></div><div class="tracking"><strong><span class="live">● LIVE</span> Location sharing</strong><div class="gps" id="locText">Enable GPS to share your live location.</div><button class="btn" onclick="startGPS()" type="button"><i class="fa-solid fa-location-crosshairs"></i> Enable Live Location</button><div class="map" id="map">Live map will appear after GPS permission.</div></div></div></article><?php endforeach;endif;?></section>
</main><script>
let watching=false;function startGPS(){if(watching)return;if(!navigator.geolocation){document.getElementById('locText').textContent='This browser does not support GPS.';return;}watching=true;document.getElementById('gps').textContent='LIVE';navigator.geolocation.watchPosition(async p=>{let lat=p.coords.latitude,lng=p.coords.longitude,acc=p.coords.accuracy;document.getElementById('locText').textContent='Live GPS: '+lat.toFixed(6)+', '+lng.toFixed(6)+' · accuracy '+Math.round(acc)+'m';let fd=new FormData();fd.append('latitude',lat);fd.append('longitude',lng);fd.append('accuracy',acc);try{await fetch('?ajax=location',{method:'POST',body:fd});}catch(e){}document.getElementById('map').innerHTML='<b>📍 Live location active</b><br>'+lat.toFixed(6)+', '+lng.toFixed(6);},e=>{document.getElementById('locText').textContent='GPS error: '+e.message;},{enableHighAccuracy:true,maximumAge:3000,timeout:10000});}
<?php if($mine):?>startGPS();<?php endif;?>
</script></body></html>
