<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}
$userId=(int)$_SESSION['user_id']; $orderId=(int)($_GET['order_id']??0);
if($orderId<=0){header('Location: my_orders.php');exit;}

$o=$conn->prepare('SELECT id, restaurant_id, order_status FROM orders WHERE id=? AND user_id=? LIMIT 1');$o->bind_param('ii',$orderId,$userId);$o->execute();$order=$o->get_result()->fetch_assoc();$o->close();
if(!$order){$_SESSION['cart_error']='Order not found.';header('Location: my_orders.php');exit;}

$items=$conn->prepare('SELECT menu_item_id, quantity FROM order_items WHERE order_id=? ORDER BY id ASC');$items->bind_param('i',$orderId);$items->execute();$rs=$items->get_result();$rows=[];while($r=$rs->fetch_assoc())$rows[]=$r;$items->close();
if(!$rows){$_SESSION['cart_error']='This order has no items to reorder.';header('Location: my_orders.php');exit;}

/* Existing cart supports one restaurant at a time. Refuse to mix restaurants. */
$c=$conn->prepare('SELECT DISTINCT m.restaurant_id FROM cart c INNER JOIN menu_items m ON m.id=c.menu_item_id WHERE c.user_id=?');$c->bind_param('i',$userId);$c->execute();$cr=$c->get_result();$cartRestaurants=[];while($r=$cr->fetch_assoc())$cartRestaurants[]=(int)$r['restaurant_id'];$c->close();
if($cartRestaurants && (count($cartRestaurants)!==1 || $cartRestaurants[0]!=(int)$order['restaurant_id'])){$_SESSION['cart_error']='Your cart contains another restaurant. Please clear the cart before reordering this order.';header('Location: cart.php');exit;}

foreach($rows as $row){$menuId=(int)$row['menu_item_id'];$qty=max(1,min(99,(int)$row['quantity']));
  $v=$conn->prepare('SELECT id FROM menu_items WHERE id=? AND restaurant_id=? AND status=1 LIMIT 1');$rid=(int)$order['restaurant_id'];$v->bind_param('ii',$menuId,$rid);$v->execute();$valid=$v->get_result()->num_rows>0;$v->close();if(!$valid)continue;
  $e=$conn->prepare('SELECT id, quantity FROM cart WHERE user_id=? AND menu_item_id=? LIMIT 1');$e->bind_param('ii',$userId,$menuId);$e->execute();$existing=$e->get_result()->fetch_assoc();$e->close();
  if($existing){$newQty=min(99,(int)$existing['quantity']+$qty);$u=$conn->prepare('UPDATE cart SET quantity=? WHERE id=? AND user_id=?');$u->bind_param('iii',$newQty,$existing['id'],$userId);$u->execute();$u->close();}
  else{$i=$conn->prepare('INSERT INTO cart(user_id,menu_item_id,quantity) VALUES(?,?,?)');$i->bind_param('iii',$userId,$menuId,$qty);$i->execute();$i->close();}
}
$_SESSION['cart_success']='Previous order items have been added to your cart. Review the cart before checkout.';header('Location: cart.php');exit;