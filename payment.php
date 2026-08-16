<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/session.php';
if(session_status()===PHP_SESSION_NONE)session_start();
if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $method=$_POST['payment_method']??'cash_on_delivery';
    $ref=trim((string)($_POST['transaction_reference']??''));
    if(!in_array($method,['cash_on_delivery','card','online'],true))$method='cash_on_delivery';
    if($method!=='cash_on_delivery' && $ref==='')$error='Online/Card payment ke liye transaction reference zaroori hai.';
    if($error===''){
        $_SESSION['selected_payment_method']=$method;
        $_SESSION['payment_transaction_reference']=$method==='cash_on_delivery'?'':$ref;
        header('Location: checkout.php');exit;
    }
}
$current=$_SESSION['selected_payment_method']??'cash_on_delivery';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment - Humsafar</title><style>body{font-family:Arial;background:#f7f7f9;margin:0}.box{max-width:620px;margin:60px auto;background:#fff;padding:28px;border-radius:16px;box-shadow:0 5px 25px #00000010}h1{margin-top:0}label{display:block;margin:14px 0 6px;font-weight:700}select,input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd;border-radius:9px}button{margin-top:18px;padding:12px 18px;border:0;border-radius:9px;font-weight:800;cursor:pointer}.error{background:#fff0f2;color:#a0002d;padding:10px;border-radius:8px}.note{font-size:13px;color:#777;line-height:1.5}</style></head><body><div class="box"><h1>Payment Method</h1><p class="note">Cash on Delivery par payment delivery ke waqt hogi. Online/Card payment ke liye apna transaction reference enter karein; admin payment verify karega.</p><?php if($error):?><div class="error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post"><label>Payment Method</label><select name="payment_method" id="payment_method" onchange="toggleRef()"><option value="cash_on_delivery" <?=$current==='cash_on_delivery'?'selected':''?>>Cash on Delivery</option><option value="card" <?=$current==='card'?'selected':''?>>Card / Online</option><option value="online" <?=$current==='online'?'selected':''?>>Online Transfer</option></select><div id="refBox"><label>Transaction Reference</label><input name="transaction_reference" placeholder="Transaction ID / Reference"></div><button type="submit">Continue to Checkout</button></form></div><script>function toggleRef(){document.getElementById('refBox').style.display=document.getElementById('payment_method').value==='cash_on_delivery'?'none':'block'}toggleRef();</script></body></html>