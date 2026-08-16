<?php
/* Humsafar - Customer Live Order Tracking */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$userId = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

function th($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists($conn,$name){ $n=$conn->real_escape_string($name); $r=$conn->query("SHOW TABLES LIKE '$n'"); return $r && $r->num_rows>0; }
function label($s){ return ucwords(str_replace('_',' ',strtolower(trim((string)$s)))); }

if ($orderId <= 0) { http_response_code(400); die('Invalid order.'); }

/* JSON endpoint used by the map. Customer can only read their own order. */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'location') {
    header('Content-Type: application/json; charset=utf-8');
    $out=['ok'=>false,'order_status'=>'','rider'=>null];
    $st=$conn->prepare("SELECT id,order_status FROM orders WHERE id=? AND user_id=? LIMIT 1");
    if($st){ $st->bind_param('ii',$orderId,$userId); $st->execute(); $o=$st->get_result()->fetch_assoc(); $st->close(); }
    if(!$o){ echo json_encode($out); exit; }
    $out['ok']=true; $out['order_status']=$o['order_status'];
    if(tableExists($conn,'rider_deliveries') && tableExists($conn,'riders')){
        $sql="SELECT rd.status AS delivery_status,r.id AS rider_id,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at
              FROM rider_deliveries rd INNER JOIN riders r ON r.id=rd.rider_id
              LEFT JOIN rider_locations rl ON rl.id=(SELECT rl2.id FROM rider_locations rl2 WHERE rl2.rider_id=r.id ORDER BY rl2.id DESC LIMIT 1)
              WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1";
        $st=$conn->prepare($sql);
        if($st){$st->bind_param('i',$orderId);$st->execute();$r=$st->get_result()->fetch_assoc();$st->close();if($r){$out['rider']=$r;}}
    }
    echo json_encode($out); exit;
}

$st=$conn->prepare("SELECT o.id,o.order_number,o.order_status,o.created_at,o.total,r.name AS restaurant_name FROM orders o LEFT JOIN restaurants r ON r.id=o.restaurant_id WHERE o.id=? AND o.user_id=? LIMIT 1");
if(!$st){die('Database error: '.th($conn->error));}
$st->bind_param('ii',$orderId,$userId);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
if(!$order){http_response_code(404);die('Order not found.');}

$status=strtolower(trim((string)$order['order_status']));
$active=in_array($status,['preparing','ready','ready_for_pickup','rider_assigned','picked_up','on_the_way','out_for_delivery'],true);
$initialRider=null;
if(tableExists($conn,'rider_deliveries') && tableExists($conn,'riders')){
 $sql="SELECT rd.status AS delivery_status,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at FROM rider_deliveries rd INNER JOIN riders r ON r.id=rd.rider_id LEFT JOIN rider_locations rl ON rl.id=(SELECT rl2.id FROM rider_locations rl2 WHERE rl2.rider_id=r.id ORDER BY rl2.id DESC LIMIT 1) WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1";
 $st=$conn->prepare($sql);if($st){$st->bind_param('i',$orderId);$st->execute();$initialRider=$st->get_result()->fetch_assoc();$st->close();}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Track Order #<?=th($order['order_number'] ?: $order['id'])?> - Humsafar</title><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><style>
*{box-sizing:border-box}body{margin:0;background:#f7f7f8;color:#242424;font-family:Segoe UI,Tahoma,sans-serif}.wrap{max-width:980px;margin:35px auto;padding:0 18px 50px}.back{display:inline-block;color:#e9003b;text-decoration:none;font-weight:800;margin-bottom:18px}.card{background:#fff;border:1px solid #eee;border-radius:18px;box-shadow:0 8px 25px rgba(0,0,0,.06);overflow:hidden}.head{padding:24px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:15px;align-items:center}.head h1{margin:0;font-size:26px}.muted{color:#888;font-size:13px;margin-top:5px}.badge{padding:9px 13px;border-radius:999px;background:#fff0f5;color:#d90038;font-weight:800;font-size:12px}.body{padding:24px}.timeline{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:25px}.step{text-align:center;color:#999;font-size:12px;font-weight:700}.dot{width:34px;height:34px;margin:0 auto 8px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eee;color:#888}.step.done{color:#e9003b}.step.done .dot{background:#e9003b;color:#fff}.rider{display:grid;grid-template-columns:1fr 1.5fr;gap:18px}.info{background:#fafafa;border:1px solid #eee;border-radius:14px;padding:18px}.info h2{font-size:18px;margin:0 0 14px}.row{padding:9px 0;border-bottom:1px solid #eee;font-size:13px}.row:last-child{border:0}.row strong{display:block;font-size:15px;margin-top:3px}.map{height:430px;border-radius:14px;overflow:hidden;border:1px solid #ddd}.waiting{padding:16px;border-radius:12px;background:#fff7df;color:#825a00;font-weight:700;margin-bottom:18px}.done{padding:16px;border-radius:12px;background:#ebf9ef;color:#16713a;font-weight:700;margin-bottom:18px}@media(max-width:760px){.rider{grid-template-columns:1fr}.timeline{grid-template-columns:1fr 1fr 1fr 1fr 1fr;overflow:auto}.map{height:330px}.head{align-items:flex-start;flex-direction:column}}
</style></head><body><main class="wrap"><a class="back" href="my_orders.php">← My Orders</a><section class="card"><div class="head"><div><h1>Track Order #<?=th($order['order_number'] ?: $order['id'])?></h1><div class="muted"><?=th($order['restaurant_name'] ?: 'Restaurant')?> · Rs <?=number_format((float)$order['total'],0)?></div></div><span class="badge" id="statusBadge"><?=th(label($status))?></span></div><div class="body"><div class="timeline" id="timeline"><div class="step" data-step="1"><div class="dot">1</div>Order Placed</div><div class="step" data-step="2"><div class="dot">2</div>Accepted</div><div class="step" data-step="3"><div class="dot">3</div>Preparing</div><div class="step" data-step="4"><div class="dot">4</div>Rider Assigned</div><div class="step" data-step="5"><div class="dot">5</div>On the Way</div></div><div id="message"></div><div class="rider"><div class="info"><h2>Rider Information</h2><div id="riderInfo"><div class="muted">Waiting for a rider to accept your delivery.</div></div></div><div><div class="map" id="map"></div><div class="muted" style="margin-top:7px">Rider location refreshes automatically every 5 seconds.</div></div></div></div></section></main><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>
let map=null,marker=null;const orderId=<?=json_encode($orderId)?>;const initialStatus=<?=json_encode($status)?>;
function stepFor(s){s=(s||'').toLowerCase();if(s==='pending')return 1;if(['confirmed','accepted'].includes(s))return 2;if(s==='preparing')return 3;if(['rider_assigned'].includes(s))return 4;if(['picked_up','on_the_way','out_for_delivery','ready','ready_for_pickup'].includes(s))return s==='ready'||s==='ready_for_pickup'?4:5;if(['delivered','completed'].includes(s))return 5;return 1}
function updateTimeline(s){let n=stepFor(s);document.querySelectorAll('.step').forEach(x=>x.classList.toggle('done',parseInt(x.dataset.step)<=n));}
function initMap(lat,lng){if(!map){map=L.map('map').setView([lat,lng],15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);marker=L.marker([lat,lng]).addTo(map);}else{marker.setLatLng([lat,lng]);map.setView([lat,lng]);}}
function render(d){const s=(d.order_status||'').toLowerCase();document.getElementById('statusBadge').textContent=(s||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());updateTimeline(s);const msg=document.getElementById('message');if(['delivered','completed'].includes(s))msg.innerHTML='<div class="done">✓ Your order has been delivered.</div>';else if(d.rider)msg.innerHTML='';else msg.innerHTML='<div class="waiting">Your food is being prepared. As soon as a rider accepts the delivery, their information and live location will appear here.</div>';const box=document.getElementById('riderInfo');if(d.rider){const r=d.rider;box.innerHTML='<div class="row">Rider<strong>'+esc(r.rider_name||'Rider')+'</strong></div><div class="row">Phone<strong>'+esc(r.rider_phone||'Not available')+'</strong></div><div class="row">Vehicle<strong>'+esc((r.vehicle_type||'Bike')+' '+(r.bike_number||''))+'</strong></div><div class="row">Delivery Status<strong>'+esc((r.delivery_status||'Accepted').replaceAll('_',' '))+'</strong></div>';if(r.latitude!==null&&r.longitude!==null&&!isNaN(parseFloat(r.latitude))){initMap(parseFloat(r.latitude),parseFloat(r.longitude));}}}
function esc(v){return String(v).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
async function poll(){try{const res=await fetch('track-order.php?ajax=location&order_id='+encodeURIComponent(orderId),{cache:'no-store'});const d=await res.json();if(d.ok)render(d);}catch(e){}}updateTimeline(initialStatus);poll();setInterval(poll,5000);
</script></body></html>
