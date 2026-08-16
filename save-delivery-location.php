<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/session.php';
if(session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Login required']);exit;}
$userId=(int)$_SESSION['user_id'];
$orderId=(int)($_POST['order_id']??0);
$lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);
$lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);
$accuracy=filter_var($_POST['accuracy']??null,FILTER_VALIDATE_FLOAT);
if($orderId<1||$lat===false||$lng===false||$lat<-90||$lat>90||$lng<-180||$lng>180){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Invalid location']);exit;}
$s=$conn->prepare("SELECT id,order_status FROM orders WHERE id=? AND user_id=? LIMIT 1");
$s->bind_param('ii',$orderId,$userId);$s->execute();$order=$s->get_result()->fetch_assoc();$s->close();
if(!$order){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Order not found']);exit;}
$status=strtolower((string)$order['order_status']);
if(in_array($status,['delivered','cancelled','completed'],true)){echo json_encode(['ok'=>false,'error'=>'Tracking is no longer active']);exit;}
$create="CREATE TABLE IF NOT EXISTS order_delivery_locations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,order_id INT NOT NULL UNIQUE,user_id INT NOT NULL,latitude DECIMAL(10,7) NOT NULL,longitude DECIMAL(10,7) NOT NULL,accuracy DECIMAL(10,2) NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_odl_user(user_id),INDEX idx_odl_order(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if(!$conn->query($create)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Unable to initialize tracking database']);exit;}
$s=$conn->prepare("INSERT INTO order_delivery_locations(order_id,user_id,latitude,longitude,accuracy) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),latitude=VALUES(latitude),longitude=VALUES(longitude),accuracy=VALUES(accuracy),updated_at=CURRENT_TIMESTAMP");
$s->bind_param('iiddd',$orderId,$userId,$lat,$lng,$accuracy);$ok=$s->execute();$s->close();
echo json_encode(['ok'=>$ok]);
