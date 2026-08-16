<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id']) && empty($_SESSION['admin_logged_in'])) { header('Location: admin-login.php'); exit; }
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId=(int)($_POST['payment_id']??0);
    $action=$_POST['payment_action']??'';
    if ($paymentId>0 && in_array($action,['verify','reject'],true)) {
        $conn->begin_transaction();
        try {
            $s=$conn->prepare("SELECT p.id,p.order_id,p.status,p.payment_method,p.amount,o.user_id,o.order_status FROM payments p INNER JOIN orders o ON o.id=p.order_id WHERE p.id=? LIMIT 1 FOR UPDATE");
            if(!$s) throw new Exception($conn->error);
            $s->bind_param('i',$paymentId); $s->execute(); $p=$s->get_result()->fetch_assoc(); $s->close();
            if(!$p) throw new Exception('Payment record not found.');
            if($p['status']!=='pending') throw new Exception('This payment has already been processed.');
            if($p['payment_method']==='cash_on_delivery') throw new Exception('Cash on Delivery is completed at delivery; it cannot be online-verified.');
            $newStatus=$action==='verify'?'paid':'failed';
            $u=$conn->prepare("UPDATE payments SET status=?, paid_at=CASE WHEN ?='paid' THEN COALESCE(paid_at,CURRENT_TIMESTAMP) ELSE NULL END WHERE id=? AND status='pending'");
            if(!$u) throw new Exception($conn->error);
            $u->bind_param('ssi',$newStatus,$newStatus,$paymentId); if(!$u->execute() || $u->affected_rows!==1) throw new Exception('Payment could not be updated.'); $u->close();
            $title=$newStatus==='paid'?'Payment verified':'Payment rejected';
            $message=$newStatus==='paid'?'Your online payment for order #'.$p['order_id'].' has been verified.':'Your online payment for order #'.$p['order_id'].' was rejected.';
            $n=$conn->prepare("INSERT INTO notifications (user_id,role,title,message,type,reference_id,is_read) VALUES (?, 'customer', ?, ?, 'payment_update', ?, 0)");
            if($n){$uid=(int)$p['user_id'];$oid=(int)$p['order_id'];$n->bind_param('issi',$uid,$title,$message,$oid);$n->execute();$n->close();}
            $conn->commit(); $msg=$title.'.';
        } catch(Throwable $e) { $conn->rollback(); $err=$e->getMessage(); }
    }
}

$rows=[]; $summary=['pending'=>0,'paid'=>0,'failed'=>0];
$q=$conn->query("SELECT p.id,p.order_id,p.payment_method,p.provider,p.status,p.amount,p.transaction_reference,p.paid_at,p.created_at,o.order_number,o.order_status,u.full_name AS customer_name,u.phone AS customer_phone,r.name AS restaurant_name FROM payments p LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN users u ON u.id=o.user_id LEFT JOIN restaurants r ON r.id=o.restaurant_id ORDER BY p.created_at DESC");
if($q){while($r=$q->fetch_assoc()){$rows[]=$r;if(isset($summary[$r['status']]))$summary[$r['status']]++;}}
else{$err='Unable to load payment records: '.$conn->error;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Verification - Humsafar</title><style>body{margin:0;background:#f7f7f9;font-family:Segoe UI,Arial;color:#222}.page{margin-left:218px;padding:30px}.wrap{max-width:1250px;margin:auto}h1{margin:0;font-size:29px;font-weight:900}.sub{color:#777}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:20px 0}.card,.tablebox{background:#fff;border:1px solid #eee;border-radius:13px}.card{padding:18px}.num{font-size:25px;font-weight:900}.label{font-size:11px;color:#777;font-weight:700}.tablebox{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1050px}th,td{padding:13px;border-bottom:1px solid #eee;text-align:left;font-size:12px}th{background:#faf5f7;font-size:10px;text-transform:uppercase;color:#777}.badge{padding:5px 8px;border-radius:20px;font-size:10px;font-weight:800}.pending{background:#fff4d8;color:#916500}.paid{background:#e7f8ed;color:#18733b}.failed{background:#ffe9ed;color:#a4002b}.btn{border:0;border-radius:7px;padding:7px 10px;font-size:10px;font-weight:800;cursor:pointer}.verify{background:#e7f8ed;color:#18733b}.reject{background:#ffe9ed;color:#a4002b}.msg{padding:11px;border-radius:8px;background:#eaf8ef;color:#176c36;margin:15px 0}.err{background:#fff0f3;color:#a0002d}.empty{padding:30px;text-align:center;color:#888}@media(max-width:900px){.page{margin-left:0}.cards{grid-template-columns:1fr}}</style></head><body><?php include __DIR__.'/admin-sidebar.php';?><main class="page"><div class="wrap"><h1>Payment Verification</h1><p class="sub">Verify or reject online/card payments. Cash on Delivery is settled at delivery.</p><?php if($msg):?><div class="msg"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="msg err"><?=h($err)?></div><?php endif;?><div class="cards"><div class="card"><div class="num"><?=$summary['pending']?></div><div class="label">Pending Verification</div></div><div class="card"><div class="num"><?=$summary['paid']?></div><div class="label">Verified / Paid</div></div><div class="card"><div class="num"><?=$summary['failed']?></div><div class="label">Rejected</div></div></div><div class="tablebox"><table><thead><tr><th>Order</th><th>Customer</th><th>Restaurant</th><th>Method</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="9" class="empty">No payment records found.</td></tr><?php else:foreach($rows as $r):?><tr><td><b>#<?=h($r['order_number']?:$r['order_id'])?></b></td><td><?=h($r['customer_name'])?><br><small><?=h($r['customer_phone'])?></small></td><td><?=h($r['restaurant_name'])?></td><td><?=h(strtoupper($r['payment_method']))?></td><td><b>PKR <?=number_format((float)$r['amount'],2)?></b></td><td><?=h($r['transaction_reference']?:'—')?></td><td><span class="badge <?=h($r['status'])?>"><?=h(ucfirst($r['status']))?></span></td><td><?=h($r['created_at'])?></td><td><?php if($r['status']==='pending' && $r['payment_method']!=='cash_on_delivery'):?><form method="post"><input type="hidden" name="payment_id" value="<?=h($r['id'])?>"><button class="btn verify" name="payment_action" value="verify">Verify</button> <button class="btn reject" name="payment_action" value="reject">Reject</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div></main></body></html>