<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR - RESTAURANT OWNER ORDERS
|--------------------------------------------------------------------------
| Workflow:
| Pending -> Accept/Decline
| Accept  -> Preparing (customer sees Preparing)
|          -> order becomes available to riders
| Rider accepts -> Rider information + live location are shown here
| Decline -> Rejected
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection is not available.');

function ro_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ro_table($conn,$table){
    $table=$conn->real_escape_string($table);
    $r=$conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows>0;
}
function ro_status($s){ return ucwords(str_replace('_',' ',strtolower(trim((string)$s)))); }

/* OWNER */
$ownerId=(int)($_SESSION['restaurant_owner_id'] ?? $_SESSION['restaurant_user_id'] ?? $_SESSION['owner_id'] ?? 0);
$ownerEmail=trim((string)($_SESSION['restaurant_owner_email'] ?? $_SESSION['email'] ?? ''));
$owner=null;

if($ownerId>0){
    $st=$conn->prepare("SELECT id,restaurant_name,full_name,email,phone,status FROM restaurant_users WHERE id=? LIMIT 1");
    if($st){$st->bind_param('i',$ownerId);$st->execute();$owner=$st->get_result()->fetch_assoc();$st->close();}
}
if(!$owner && $ownerEmail!==''){
    $st=$conn->prepare("SELECT id,restaurant_name,full_name,email,phone,status FROM restaurant_users WHERE email=? LIMIT 1");
    if($st){$st->bind_param('s',$ownerEmail);$st->execute();$owner=$st->get_result()->fetch_assoc();$st->close();}
}
if(!$owner){ header('Location: restaurant-owner-login.php'); exit; }
$ownerId=(int)$owner['id'];
$ownerStatus=strtolower(trim((string)$owner['status']));
if(!in_array($ownerStatus,['approved','active'],true)){ header('Location: restaurant-owner-dashboard.php'); exit; }

/* RESTAURANT */
$restaurantName=trim((string)$owner['restaurant_name']);
$restaurant=null;$restaurantId=0;
$st=$conn->prepare("SELECT id,name,status,latitude,longitude FROM restaurants WHERE name=? LIMIT 1");
if($st){$st->bind_param('s',$restaurantName);$st->execute();$restaurant=$st->get_result()->fetch_assoc();$st->close();}
if($restaurant){$restaurantId=(int)$restaurant['id'];}
if($restaurantId<=0) die('Restaurant record not found.');

$successMessage='';$errorMessage='';

/* CUSTOMER NOTIFICATION */
function ro_customer_notification($conn,$userId,$title,$message,$type,$referenceId){
    if(!ro_table($conn,'notifications')) return;
    $st=$conn->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id) VALUES (?, 'customer', ?, ?, ?, ?)");
    if($st){$st->bind_param('isssi',$userId,$title,$message,$type,$referenceId);$st->execute();$st->close();}
}

/*
|--------------------------------------------------------------------------
| AJAX LIVE LOCATION
|--------------------------------------------------------------------------
*/
if(isset($_GET['ajax']) && $_GET['ajax']==='location'){
    header('Content-Type: application/json; charset=utf-8');
    $oid=(int)($_GET['order_id'] ?? 0);
    $out=['ok'=>false];
    if($oid>0){
        $sql="SELECT rd.status AS delivery_status, r.id AS rider_id,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at
              FROM rider_deliveries rd
              INNER JOIN riders r ON r.id=rd.rider_id
              LEFT JOIN rider_locations rl ON rl.id=(SELECT rl2.id FROM rider_locations rl2 WHERE rl2.rider_id=r.id ORDER BY rl2.id DESC LIMIT 1)
              INNER JOIN orders o ON o.id=rd.order_id
              WHERE rd.order_id=? AND o.restaurant_id=? AND rd.status IN ('accepted','picked_up','on_the_way','out_for_delivery','delivered')
              ORDER BY rd.id DESC LIMIT 1";
        $st=$conn->prepare($sql);
        if($st){$st->bind_param('ii',$oid,$restaurantId);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
            if($row){$out=['ok'=>true,'rider'=>$row];}
        }
    }
    echo json_encode($out);exit;
}

/*
|--------------------------------------------------------------------------
| ACCEPT / DECLINE
|--------------------------------------------------------------------------
*/
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=trim((string)($_POST['action'] ?? ''));
    $orderId=(int)($_POST['order_id'] ?? 0);

    if($orderId<=0 || !in_array($action,['accept','decline'],true)){
        $errorMessage='Invalid order request.';
    }else{
        $conn->begin_transaction();
        try{
            $st=$conn->prepare("SELECT id,user_id,order_status FROM orders WHERE id=? AND restaurant_id=? LIMIT 1 FOR UPDATE");
            if(!$st) throw new Exception('Could not prepare order query.');
            $st->bind_param('ii',$orderId,$restaurantId);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
            if(!$order) throw new Exception('Order not found.');

            $current=strtolower(trim((string)$order['order_status']));
            if($action==='accept'){
                if($current!=='pending') throw new Exception('This order is no longer pending.');
                $newStatus='preparing';
                $st=$conn->prepare("UPDATE orders SET order_status=? WHERE id=? AND restaurant_id=? AND order_status='pending' LIMIT 1");
                if(!$st) throw new Exception('Could not prepare accept request.');
                $st->bind_param('sii',$newStatus,$orderId,$restaurantId);
                if(!$st->execute() || $st->affected_rows!==1){$st->close();throw new Exception('Order was already updated.');}
                $st->close();

                if(ro_table($conn,'order_status_history')){
                    $role='restaurant';$note='Restaurant accepted the order. Food preparation started.';$changedBy=$ownerId;
                    $h=$conn->prepare("INSERT INTO order_status_history (order_id,status,changed_by,changed_by_role,note) VALUES (?,?,?,?,?)");
                    if($h){$h->bind_param('isiss',$orderId,$newStatus,$changedBy,$role,$note);$h->execute();$h->close();}
                }
                ro_customer_notification($conn,(int)$order['user_id'],'Order accepted','Your order #'.$orderId.' has been accepted and is now being prepared.','order_status',$orderId);

                /* Rider pool notification. Existing rider page can use the order status 'preparing' as the available pool. */
                if(ro_table($conn,'notifications')){
                    $r=$conn->query("SELECT id FROM riders WHERE LOWER(TRIM(status)) IN ('active','approved')");
                    if($r){
                        $n=$conn->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id) VALUES (?, 'rider', ?, ?, ?, ?)");
                        if($n){
                            $title='New delivery available';$msg='Order #'.$orderId.' is ready for rider acceptance.';$type='delivery_available';
                            while($rr=$r->fetch_assoc()){$rid=(int)$rr['id'];$n->bind_param('isssi',$rid,$title,$msg,$type,$orderId);$n->execute();}
                            $n->close();
                        }
                    }
                }
                $conn->commit();
                $successMessage='Order accepted successfully. Status is now Preparing and the order has been released to the rider pool.';
            }else{
                if($current!=='pending') throw new Exception('Only pending orders can be declined.');
                $newStatus='rejected';
                $st=$conn->prepare("UPDATE orders SET order_status=? WHERE id=? AND restaurant_id=? AND order_status='pending' LIMIT 1");
                if(!$st) throw new Exception('Could not prepare decline request.');
                $st->bind_param('sii',$newStatus,$orderId,$restaurantId);
                if(!$st->execute() || $st->affected_rows!==1){$st->close();throw new Exception('Order was already updated.');}
                $st->close();
                if(ro_table($conn,'order_status_history')){
                    $role='restaurant';$note='Restaurant declined the order.';$changedBy=$ownerId;
                    $h=$conn->prepare("INSERT INTO order_status_history (order_id,status,changed_by,changed_by_role,note) VALUES (?,?,?,?,?)");
                    if($h){$h->bind_param('isiss',$orderId,$newStatus,$changedBy,$role,$note);$h->execute();$h->close();}
                }
                ro_customer_notification($conn,(int)$order['user_id'],'Order declined','Unfortunately, restaurant declined your order #'.$orderId.'.','order_rejected',$orderId);
                $conn->commit();
                $successMessage='Order declined successfully.';
            }
        }catch(Throwable $e){$conn->rollback();$errorMessage=$e->getMessage();}
    }
}

/* ORDERS */
$orders=[];
$st=$conn->prepare("SELECT o.id,o.order_number,o.user_id,o.address_id,o.payment_method,o.subtotal,o.delivery_fee,o.discount,o.total,o.order_status,o.customer_note,o.created_at,u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone
                   FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.restaurant_id=? ORDER BY o.id DESC");
if($st){$st->bind_param('i',$restaurantId);$st->execute();$rs=$st->get_result();while($row=$rs->fetch_assoc())$orders[]=$row;$st->close();}

$orderItems=[];$orderAddresses=[];$riderInfo=[];
foreach($orders as $o){
    $oid=(int)$o['id'];$orderItems[$oid]=[];
    $st=$conn->prepare("SELECT id,item_name,item_price,quantity,subtotal FROM order_items WHERE order_id=? ORDER BY id ASC");
    if($st){$st->bind_param('i',$oid);$st->execute();$rs=$st->get_result();while($x=$rs->fetch_assoc())$orderItems[$oid][]=$x;$st->close();}

    $orderAddresses[$oid]=null;$aid=(int)$o['address_id'];
    if($aid>0){$st=$conn->prepare("SELECT address_title,address_line,city,area,phone FROM customer_addresses WHERE id=? LIMIT 1");if($st){$st->bind_param('i',$aid);$st->execute();$orderAddresses[$oid]=$st->get_result()->fetch_assoc();$st->close();}}

    $riderInfo[$oid]=null;
    $st=$conn->prepare("SELECT rd.status AS delivery_status,r.id AS rider_id,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at
                        FROM rider_deliveries rd INNER JOIN riders r ON r.id=rd.rider_id
                        LEFT JOIN rider_locations rl ON rl.id=(SELECT rl2.id FROM rider_locations rl2 WHERE rl2.rider_id=r.id ORDER BY rl2.id DESC LIMIT 1)
                        WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1");
    if($st){$st->bind_param('i',$oid);$st->execute();$riderInfo[$oid]=$st->get_result()->fetch_assoc();$st->close();}
}

$totalOrders=count($orders);$pending=0;$preparing=0;$delivery=0;$completed=0;$sales=0;
foreach($orders as $o){$s=strtolower(trim((string)$o['order_status']));if($s==='pending')$pending++;elseif($s==='preparing')$preparing++;elseif(in_array($s,['rider_assigned','picked_up','on_the_way','out_for_delivery'],true))$delivery++;elseif(in_array($s,['delivered','completed'],true)){$completed++;$sales+=(float)$o['total'];}}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Orders | <?=ro_h($restaurantName)?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#fff8fb;color:#252525;font-family:"Segoe UI",Tahoma,sans-serif;font-size:14px}.page{margin-left:218px;min-height:100vh;padding:30px 34px}.top{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:25px}.eyebrow{font-size:12px;font-weight:800;color:#ef003c;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:7px}.title{font-size:32px;font-weight:850;margin:0}.sub{margin:6px 0 0;color:#777;font-size:14px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}.stat{background:#fff;border:1px solid #f0dfe6;border-radius:15px;padding:19px 20px;box-shadow:0 5px 18px rgba(30,0,20,.04)}.stat small{display:block;color:#888;font-weight:700;font-size:12px}.stat strong{display:block;font-size:26px;margin-top:5px}.stat i{float:right;color:#ef003c;margin-top:4px}.flash{padding:14px 17px;border-radius:11px;margin-bottom:18px;font-size:13px;font-weight:700}.success{background:#ecfbf1;border:1px solid #ccefd8;color:#19723c}.error{background:#fff0f3;border:1px solid #ffd0da;color:#b40030}.empty{background:#fff;border:1px dashed #e5cfd8;border-radius:16px;padding:60px;text-align:center;color:#888}.orders{display:flex;flex-direction:column;gap:18px}.order{background:#fff;border:1px solid #eddfe4;border-radius:17px;overflow:hidden;box-shadow:0 7px 22px rgba(30,0,20,.045)}.order-head{padding:19px 21px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1e7ea}.order-no{font-size:19px;font-weight:850}.date{color:#888;font-size:12px;margin-top:4px}.badge{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:850}.pending-badge{background:#fff4d9;color:#9a6500}.preparing-badge{background:#fff0f5;color:#d50039}.rider-badge{background:#edf7ff;color:#09689d}.done-badge{background:#edf9f0;color:#21733c}.reject-badge{background:#fff0f0;color:#a5002c}.order-body{padding:21px;display:grid;grid-template-columns:1.4fr .8fr;gap:22px}.customer{display:flex;gap:13px;align-items:center;margin-bottom:17px}.avatar{width:46px;height:46px;border-radius:50%;background:#ffe5ed;color:#ef003c;display:flex;align-items:center;justify-content:center;font-weight:900}.customer strong{font-size:16px}.muted{color:#777;font-size:12px;margin-top:3px}.items{border-top:1px solid #f1e7ea;padding-top:13px}.item{display:flex;justify-content:space-between;padding:8px 0;font-size:14px}.item span:last-child{font-weight:800}.summary{background:#fffafd;border:1px solid #f0e1e7;border-radius:13px;padding:16px}.summary-row{display:flex;justify-content:space-between;padding:7px 0;color:#666;font-size:13px}.summary-row.total{border-top:1px solid #eadde2;margin-top:5px;padding-top:12px;color:#222;font-size:17px;font-weight:900}.address{margin-top:13px;padding-top:13px;border-top:1px solid #eadde2}.address strong{font-size:13px}.actions{display:flex;gap:10px;margin-top:18px}.btn{border:0;border-radius:10px;padding:12px 18px;font-weight:850;font-size:13px;cursor:pointer}.accept{background:#ef003c;color:#fff}.decline{background:#fff0f3;color:#bd0032;border:1px solid #ffd0da}.btn:hover{transform:translateY(-1px)}.rider-panel{margin:0 21px 21px;padding:17px;background:#f8fbff;border:1px solid #dcecf6;border-radius:14px}.rider-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:13px}.rider-title{font-size:16px;font-weight:900}.rider-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.rider-detail{background:#fff;border-radius:10px;padding:11px;border:1px solid #e6eff4}.rider-detail small{display:block;color:#888;font-size:10px;text-transform:uppercase;font-weight:800}.rider-detail strong{display:block;margin-top:4px;font-size:13px}.map{height:230px;border-radius:12px;margin-top:13px;overflow:hidden}.waiting{margin:0 21px 21px;padding:13px 15px;border-radius:11px;background:#fff8e8;border:1px solid #f4dfaa;color:#855900;font-size:13px;font-weight:700}.section-title{font-size:18px;font-weight:850;margin:0 0 14px}@media(max-width:1000px){.page{margin-left:0;padding:22px}.stats{grid-template-columns:repeat(2,1fr)}.order-body{grid-template-columns:1fr}}@media(max-width:600px){.page{padding:15px}.title{font-size:26px}.stats{grid-template-columns:1fr 1fr}.order-head{align-items:flex-start;gap:10px}.rider-grid{grid-template-columns:1fr}.actions{flex-direction:column}.btn{width:100%}}
</style>
</head>
<body>
<?php include __DIR__.'/restaurant-sidebar.php'; ?>
<main class="page">
<div class="top"><div><div class="eyebrow">Restaurant Owner</div><h1 class="title">Orders</h1><p class="sub">Manage incoming orders and send accepted orders to the rider pool.</p></div><div><strong><?=ro_h($restaurantName)?></strong></div></div>
<?php if($successMessage):?><div class="flash success"><i class="fa-solid fa-circle-check"></i> <?=ro_h($successMessage)?></div><?php endif;?>
<?php if($errorMessage):?><div class="flash error"><i class="fa-solid fa-circle-exclamation"></i> <?=ro_h($errorMessage)?></div><?php endif;?>
<div class="stats">
<div class="stat"><i class="fa-solid fa-receipt"></i><small>Total Orders</small><strong><?=$totalOrders?></strong></div>
<div class="stat"><i class="fa-solid fa-hourglass-half"></i><small>Pending</small><strong><?=$pending?></strong></div>
<div class="stat"><i class="fa-solid fa-fire-burner"></i><small>Preparing</small><strong><?=$preparing?></strong></div>
<div class="stat"><i class="fa-solid fa-motorcycle"></i><small>Completed Sales</small><strong>Rs <?=number_format($sales,0)?></strong></div>
</div>
<h2 class="section-title">Recent Orders</h2>
<?php if(!$orders):?><div class="empty"><i class="fa-solid fa-bag-shopping" style="font-size:35px;color:#ef003c"></i><h3>No orders yet</h3><p>New customer orders will appear here.</p></div><?php else:?><div class="orders">
<?php foreach($orders as $o): $oid=(int)$o['id'];$s=strtolower(trim((string)$o['order_status']));$r=$riderInfo[$oid]??null;$isPending=$s==='pending';$hasRider=$r && !empty($r['rider_id']);$mapId='map_'.$oid;?>
<article class="order" data-order-id="<?=$oid?>">
<div class="order-head"><div><div class="order-no">Order #<?=ro_h($o['order_number'] ?: $oid)?></div><div class="date"><?=ro_h(date('d M Y, h:i A',strtotime($o['created_at'])))?></div></div>
<?php if($isPending):?><span class="badge pending-badge">Pending</span><?php elseif($s==='preparing'):?><span class="badge preparing-badge">Preparing</span><?php elseif($hasRider):?><span class="badge rider-badge"><?=ro_h(ro_status($r['delivery_status']))?></span><?php elseif(in_array($s,['delivered','completed'],true)):?><span class="badge done-badge">Delivered</span><?php elseif(in_array($s,['rejected','cancelled'],true)):?><span class="badge reject-badge">Declined</span><?php else:?><span class="badge preparing-badge"><?=ro_h(ro_status($s))?></span><?php endif;?></div>
<div class="order-body"><div>
<div class="customer"><div class="avatar"><?=ro_h(strtoupper(substr(trim((string)$o['customer_name']),0,1) ?: 'C'))?></div><div><strong><?=ro_h($o['customer_name'] ?: 'Customer')?></strong><div class="muted"><i class="fa-solid fa-phone"></i> <?=ro_h($o['customer_phone'] ?: 'No phone')?></div></div></div>
<div class="items"><?php foreach(($orderItems[$oid]??[]) as $it):?><div class="item"><span><?=ro_h($it['item_name'])?> × <?=ro_h($it['quantity'])?></span><span>Rs <?=number_format((float)$it['subtotal'],0)?></span></div><?php endforeach;?></div>
<?php if($isPending):?><div class="actions"><form method="post"><input type="hidden" name="action" value="accept"><input type="hidden" name="order_id" value="<?=$oid?>"><button class="btn accept" type="submit"><i class="fa-solid fa-check"></i> Accept Order</button></form><form method="post" onsubmit="return confirm('Decline this order?');"><input type="hidden" name="action" value="decline"><input type="hidden" name="order_id" value="<?=$oid?>"><button class="btn decline" type="submit"><i class="fa-solid fa-xmark"></i> Decline</button></form></div><?php endif;?>
</div><div class="summary"><div class="summary-row"><span>Subtotal</span><strong>Rs <?=number_format((float)$o['subtotal'],0)?></strong></div><div class="summary-row"><span>Delivery</span><strong>Rs <?=number_format((float)$o['delivery_fee'],0)?></strong></div><div class="summary-row"><span>Discount</span><strong>- Rs <?=number_format((float)$o['discount'],0)?></strong></div><div class="summary-row"><span>Payment</span><strong><?=ro_h(strtoupper((string)$o['payment_method']))?></strong></div><div class="summary-row total"><span>Total</span><strong>Rs <?=number_format((float)$o['total'],0)?></strong></div>
<div class="address"><strong><i class="fa-solid fa-location-dot"></i> Delivery Address</strong><?php $a=$orderAddresses[$oid]??null;if($a):?><div class="muted"><?=ro_h($a['address_line'])?>, <?=ro_h($a['area'])?>, <?=ro_h($a['city'])?><br><?=ro_h($a['phone'])?></div><?php else:?><div class="muted">Address unavailable</div><?php endif;?></div></div></div>
<?php if($s==='preparing' && !$hasRider):?><div class="waiting"><i class="fa-solid fa-motorcycle"></i> Order is <strong>Preparing</strong>. It is now available to riders. Waiting for a rider to accept the delivery.</div><?php endif;?>
<?php if($hasRider):?><div class="rider-panel" data-rider-order="<?=$oid?>"><div class="rider-head"><div class="rider-title"><i class="fa-solid fa-motorcycle"></i> Rider Information</div><span class="badge rider-badge">Rider accepted</span></div><div class="rider-grid"><div class="rider-detail"><small>Name</small><strong data-rider-name><?=ro_h($r['rider_name'])?></strong></div><div class="rider-detail"><small>Phone</small><strong><?=ro_h($r['rider_phone'])?></strong></div><div class="rider-detail"><small>Vehicle</small><strong><?=ro_h($r['vehicle_type'] ?: 'Bike')?> <?=ro_h($r['bike_number'] ?: '')?></strong></div><div class="rider-detail"><small>Delivery Status</small><strong data-rider-status><?=ro_h(ro_status($r['delivery_status']))?></strong></div></div><div class="map" id="<?=$mapId?>"></div></div><?php endif;?>
</article>
<?php endforeach;?></div><?php endif;?>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const maps={};
function initMap(orderId,lat,lng){const el=document.getElementById('map_'+orderId);if(!el||lat===null||lng===null)return;if(!maps[orderId]){maps[orderId]=L.map(el).setView([lat,lng],15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(maps[orderId]);maps[orderId].marker=L.marker([lat,lng]).addTo(maps[orderId]);}else{maps[orderId].marker.setLatLng([lat,lng]);maps[orderId].setView([lat,lng]);}}
async function poll(orderId){try{const r=await fetch('?ajax=location&order_id='+encodeURIComponent(orderId),{cache:'no-store'});const d=await r.json();if(d.ok&&d.rider){const x=d.rider;const p=document.querySelector('[data-rider-order="'+orderId+'"]');if(p){const n=p.querySelector('[data-rider-name]'),s=p.querySelector('[data-rider-status]');if(n)n.textContent=x.rider_name||'Rider';if(s)s.textContent=(x.delivery_status||'accepted').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());}if(x.latitude!==null&&x.longitude!==null)initMap(orderId,parseFloat(x.latitude),parseFloat(x.longitude));}}catch(e){}}
document.querySelectorAll('[data-rider-order]').forEach(el=>{const id=el.getAttribute('data-rider-order');poll(id);setInterval(()=>poll(id),5000);});
</script>
</body></html>
