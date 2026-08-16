<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/session.php';
if(session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['ok'=>false]);exit;}
$userId=(int)$_SESSION['user_id'];$orderId=(int)($_GET['order_id']??0);
if($orderId<1){http_response_code(422);echo json_encode(['ok'=>false]);exit;}
$s=$conn->prepare('SELECT id,order_status FROM orders WHERE id=? AND user_id=? LIMIT 1');$s->bind_param('ii',$orderId,$userId);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();
if(!$o){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
$out=['ok'=>true,'order_status'=>$o['order_status'],'destination'=>null,'rider'=>null];
$check=$conn->query("SHOW TABLES LIKE 'order_delivery_locations'");
if($check&&$check->num_rows){$s=$conn->prepare('SELECT latitude,longitude,accuracy,updated_at FROM order_delivery_locations WHERE order_id=? AND user_id=? LIMIT 1');$s->bind_param('ii',$orderId,$userId);$s->execute();$out['destination']=$s->get_result()->fetch_assoc();$s->close();}
$sql="SELECT r.full_name rider_name,r.phone rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at location_updated_at FROM rider_deliveries rd INNER JOIN riders r ON r.id=rd.rider_id LEFT JOIN rider_locations rl ON rl.id=(SELECT x.id FROM rider_locations x WHERE x.rider_id=r.id ORDER BY x.id DESC LIMIT 1) WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1";
$s=$conn->prepare($sql);if($s){$s->bind_param('i',$orderId);$s->execute();$out['rider']=$s->get_result()->fetch_assoc();$s->close();}
echo json_encode($out);
