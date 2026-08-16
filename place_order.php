<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/customer-pricing.php';
if(session_status()===PHP_SESSION_NONE)session_start();
if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: checkout.php');exit;}
$userId=(int)$_SESSION['user_id'];
$addressId=(int)($_POST['address_id']??0);
$customerNote=trim((string)($_POST['customer_note']??''));
$allowedPayments=['cash_on_delivery','card','online'];
$paymentMethod=trim((string)($_SESSION['selected_payment_method']??'cash_on_delivery'));
$paymentReference=trim((string)($_SESSION['payment_transaction_reference']??''));
if(!in_array($paymentMethod,$allowedPayments,true))$paymentMethod='cash_on_delivery';
function order_fail($message){$_SESSION['order_error']=$message;header('Location: checkout.php');exit;}
if($addressId<=0)order_fail('Please select a delivery address.');
if($paymentMethod!=='cash_on_delivery' && $paymentReference==='')order_fail('Please provide a payment transaction reference before placing an online/card order.');
$addressStmt=$conn->prepare('SELECT id,address_title,address_line,city,area,phone FROM customer_addresses WHERE id=? AND user_id=? LIMIT 1');
if(!$addressStmt)order_fail('Unable to verify delivery address.');
$addressStmt->bind_param('ii',$addressId,$userId);$addressStmt->execute();$address=$addressStmt->get_result()->fetch_assoc();$addressStmt->close();
if(!$address)order_fail('Please select a valid delivery address.');
$cartStmt=$conn->prepare('SELECT c.menu_item_id,c.quantity,m.name AS item_name,m.price AS item_price,m.restaurant_id,r.delivery_fee FROM cart c INNER JOIN menu_items m ON c.menu_item_id=m.id INNER JOIN restaurants r ON m.restaurant_id=r.id WHERE c.user_id=? ORDER BY c.id ASC');
if(!$cartStmt)order_fail('Unable to load your cart.');
$cartStmt->bind_param('i',$userId);$cartStmt->execute();$result=$cartStmt->get_result();$cartItems=[];$restaurantId=0;$subtotal=0.0;$deliveryFee=0.0;
while($row=$result->fetch_assoc()){
 $itemRestaurantId=(int)$row['restaurant_id'];$quantity=max(1,(int)$row['quantity']);
 if($restaurantId===0){$restaurantId=$itemRestaurantId;$deliveryFee=(float)$row['delivery_fee'];}elseif($restaurantId!==$itemRestaurantId){$cartStmt->close();order_fail('Your cart contains items from multiple restaurants. Please keep one restaurant in the cart.');}
 $itemPrice=humsafar_customer_price_from_db($conn,(float)$row['item_price']);$itemSubtotal=$itemPrice*$quantity;$subtotal+=$itemSubtotal;
 $cartItems[]=['menu_item_id'=>(int)$row['menu_item_id'],'item_name'=>(string)$row['item_name'],'item_price'=>$itemPrice,'quantity'=>$quantity,'subtotal'=>$itemSubtotal];
}
$cartStmt->close();
if(!$cartItems||$restaurantId<=0)order_fail('Your cart is empty.');
$discount=0.0;$total=max(0,$subtotal+$deliveryFee-$discount);$orderNumber='';
for($attempt=0;$attempt<5;$attempt++){$candidate='HUM-'.date('YmdHis').'-'.random_int(1000,9999);$check=$conn->prepare('SELECT id FROM orders WHERE order_number=? LIMIT 1');if(!$check)order_fail('Unable to prepare the order.');$check->bind_param('s',$candidate);$check->execute();$exists=$check->get_result()->num_rows>0;$check->close();if(!$exists){$orderNumber=$candidate;break;}}
if($orderNumber==='')order_fail('Unable to generate a unique order number. Please try again.');
$conn->begin_transaction();
try{
 $initialStatus='pending';
 $orderStmt=$conn->prepare('INSERT INTO orders (order_number,user_id,restaurant_id,address_id,payment_method,subtotal,delivery_fee,discount,total,order_status,customer_note) VALUES (?,?,?,?,?,?,?,?,?,?,?)');if(!$orderStmt)throw new Exception($conn->error);
 $orderStmt->bind_param('siiisddddss',$orderNumber,$userId,$restaurantId,$addressId,$paymentMethod,$subtotal,$deliveryFee,$discount,$total,$initialStatus,$customerNote);if(!$orderStmt->execute())throw new Exception($orderStmt->error);$orderId=(int)$conn->insert_id;$orderStmt->close();if($orderId<=0)throw new Exception('Invalid order ID.');
 $itemStmt=$conn->prepare('INSERT INTO order_items (order_id,menu_item_id,item_name,item_price,quantity,subtotal) VALUES (?,?,?,?,?,?)');if(!$itemStmt)throw new Exception($conn->error);
 foreach($cartItems as $item){$menuItemId=$item['menu_item_id'];$itemName=$item['item_name'];$itemPrice=$item['item_price'];$quantity=$item['quantity'];$itemSubtotal=$item['subtotal'];$itemStmt->bind_param('iisdid',$orderId,$menuItemId,$itemName,$itemPrice,$quantity,$itemSubtotal);if(!$itemStmt->execute())throw new Exception($itemStmt->error);} $itemStmt->close();
 $snapshotStmt=$conn->prepare('INSERT INTO order_addresses (order_id,full_name,phone,address,city,area,landmark) VALUES (?,?,?,?,?,?,?)');if(!$snapshotStmt)throw new Exception($conn->error);
 $fullName=(string)($_SESSION['full_name']??'Customer');$phone=(string)($address['phone']??'');$addressLine=(string)$address['address_line'];$city=(string)$address['city'];$area=(string)($address['area']??'');$landmark='';
 $userStmt=$conn->prepare('SELECT full_name,phone FROM users WHERE id=? LIMIT 1');if($userStmt){$userStmt->bind_param('i',$userId);$userStmt->execute();$u=$userStmt->get_result()->fetch_assoc();if($u){$fullName=(string)$u['full_name'];if($phone==='')$phone=(string)$u['phone'];}$userStmt->close();}
 $snapshotStmt->bind_param('issssss',$orderId,$fullName,$phone,$addressLine,$city,$area,$landmark);if(!$snapshotStmt->execute())throw new Exception($snapshotStmt->error);$snapshotStmt->close();
 $provider=$paymentMethod==='cash_on_delivery'?'Cash on Delivery':($paymentMethod==='card'?'Card':'Online');$paymentStatus='pending';$reference=$paymentMethod==='cash_on_delivery'?null:$paymentReference;
 $paymentStmt=$conn->prepare('INSERT INTO payments (order_id,user_id,payment_method,provider,transaction_reference,amount,status) VALUES (?,?,?,?,?,?,?)');if(!$paymentStmt)throw new Exception($conn->error);
 $paymentStmt->bind_param('iisssds',$orderId,$userId,$paymentMethod,$provider,$reference,$total,$paymentStatus);if(!$paymentStmt->execute())throw new Exception($paymentStmt->error);$paymentStmt->close();
 $historyRole='customer';$historyNote='Order created by customer.';$historyStmt=$conn->prepare('INSERT INTO order_status_history (order_id,status,changed_by,changed_by_role,note) VALUES (?,?,?,?,?)');if(!$historyStmt)throw new Exception($conn->error);$historyStmt->bind_param('isiss',$orderId,$initialStatus,$userId,$historyRole,$historyNote);if(!$historyStmt->execute())throw new Exception($historyStmt->error);$historyStmt->close();
 $noticeTitle='Order placed successfully';$noticeMessage='Your order #'.$orderId.' has been placed and is pending restaurant confirmation.';$noticeType='order_placed';$noticeStmt=$conn->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id,is_read) VALUES (?, 'customer', ?, ?, ?, ?, 0)");if($noticeStmt){$noticeStmt->bind_param('isssi',$userId,$noticeTitle,$noticeMessage,$noticeType,$orderId);$noticeStmt->execute();$noticeStmt->close();}
 $deleteCart=$conn->prepare('DELETE FROM cart WHERE user_id=?');if(!$deleteCart)throw new Exception($conn->error);$deleteCart->bind_param('i',$userId);if(!$deleteCart->execute())throw new Exception($deleteCart->error);$deleteCart->close();
 $conn->commit();unset($_SESSION['selected_payment_method'],$_SESSION['payment_transaction_reference'],$_SESSION['order_error']);header('Location: order_success.php?order_id='.$orderId);exit;
}catch(Throwable $e){$conn->rollback();error_log('Humsafar place_order.php: '.$e->getMessage());order_fail('Unable to place your order. Nothing was removed from your cart. Please try again.');}
