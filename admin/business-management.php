<?php
require_once __DIR__.'/../includes/config.php';
if(session_status()===PHP_SESSION_NONE)session_start();
if(empty($_SESSION['admin_id']) && empty($_SESSION['admin_logged_in'])){header('Location: admin-login.php');exit;}
function bh($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$msg='';$err='';

// Self-heal the business settings storage so Admin does not need to manually import SQL.
$businessTableSql="CREATE TABLE IF NOT EXISTS business_settings (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(100) NOT NULL UNIQUE,
 setting_value TEXT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if(!$conn->query($businessTableSql)){
    $err='Unable to initialize business settings: '.($conn->error ?: 'database error');
}else{
    $defaults=[
        'customer_markup_percent'=>'0',
        'restaurant_commission_percent'=>'15',
        'rider_base_payout'=>'80',
        'delivery_fee_per_km'=>'50',
        'currency'=>'PKR'
    ];
    $seed=$conn->prepare('INSERT IGNORE INTO business_settings(setting_key,setting_value) VALUES(?,?)');
    if($seed){foreach($defaults as $k=>$v){$seed->bind_param('ss',$k,$v);$seed->execute();}$seed->close();}
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='setting'){
 $key=trim($_POST['key']??'');$value=trim($_POST['value']??'');
 $allowed=['customer_markup_percent','restaurant_commission_percent','rider_base_payout','delivery_fee_per_km','currency'];
 if(!in_array($key,$allowed,true))$err='Invalid business setting.';
 elseif($value==='')$err='Setting value is required.';
 elseif(in_array($key,['customer_markup_percent','restaurant_commission_percent'],true)&&((float)$value<0||(float)$value>100))$err='Percentage must be between 0 and 100.';
 elseif(in_array($key,['rider_base_payout','delivery_fee_per_km'],true)&&(float)$value<0)$err='Amount cannot be negative.';
 else{
   $s=$conn->prepare('INSERT INTO business_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
   if($s){$s->bind_param('ss',$key,$value);if($s->execute())$msg='Business setting saved successfully.';else$err='Unable to save setting: '.($s->error?:'database error');$s->close();}
   else $err='Unable to prepare business setting update: '.($conn->error?:'database error');
 }
}
$settings=[];$q=$conn->query('SELECT setting_key,setting_value FROM business_settings ORDER BY setting_key');if($q)while($r=$q->fetch_assoc())$settings[$r['setting_key']]=$r['setting_value'];
$markup=(float)($settings['customer_markup_percent']??0);$commission=(float)($settings['restaurant_commission_percent']??15);$rate=(float)($settings['delivery_fee_per_km']??50);$riderPay=(float)($settings['rider_base_payout']??80);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Business Management</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>body{margin:0;background:#f7f7f9;font-family:Segoe UI,Arial;color:#222}.page{margin-left:218px;padding:30px}.wrap{max-width:1150px}.title{font-size:30px;font-weight:900;margin:0}.sub{color:#777}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:22px}.card{background:#fff;border:1px solid #f0dfe6;border-radius:15px;padding:20px;box-shadow:0 5px 18px #00000008}.field{margin:10px 0}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:6px}.hint{font-size:12px;color:#777;line-height:1.5}.input,select{width:100%;padding:11px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;font:inherit}.btn{border:0;background:#ed0038;color:#fff;padding:11px 16px;border-radius:8px;font-weight:800;cursor:pointer}.btn:hover{background:#c90030}.msg{padding:11px;border-radius:8px;margin:15px 0;background:#eaf8ef;color:#176c36}.err{background:#fff0f3;color:#a0002d}.formula{background:#f8f8fa;border-radius:10px;padding:13px;margin-top:14px;font-size:12px;line-height:1.7}.badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#fff0f4;color:#ed0038;font-weight:800;font-size:12px}.customer-card{scroll-margin-top:25px;border:2px solid #ed0038}.customer-card h3{font-size:18px}.customer-card .input{font-size:17px;font-weight:800}.customer-card .btn{font-size:14px;padding:12px 18px}@media(max-width:900px){.page{margin-left:0}.grid{grid-template-columns:1fr}} </style></head><body><?php include __DIR__.'/admin-sidebar.php';?><main class="page"><div class="wrap"><h1 class="title">Business Management</h1><p class="sub">Admin controls for customer pricing, restaurant commission, rider payout and delivery pricing.</p><?php if($msg):?><div class="msg"><?=bh($msg)?></div><?php endif;?><?php if($err):?><div class="msg err"><?=bh($err)?></div><?php endif;?><div class="grid">
<section id="customer-pricing" class="card customer-card"><h3><i class="fa-solid fa-tags"></i> Customer Pricing Control</h3><p class="hint">This is the percentage Admin adds to every restaurant owner's base menu price before the price is displayed to customers.</p><form method="post"><input type="hidden" name="action" value="setting"><input type="hidden" name="key" value="customer_markup_percent"><div class="field"><label>Customer Price Markup (%)</label><input class="input" name="value" type="number" min="0" max="100" step="0.01" value="<?=bh($markup)?>" required></div><button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Customer Markup</button></form><div class="formula"><b>Example:</b> Restaurant base price PKR 100 + <?=$markup?>% = <b>PKR <?=number_format(100*(1+$markup/100),2)?></b> customer price.</div></section>
<section class="card"><h3><i class="fa-solid fa-store"></i> Restaurant Commission</h3><p class="hint">Separate from customer markup. Used for restaurant settlement.</p><form method="post"><input type="hidden" name="action" value="setting"><input type="hidden" name="key" value="restaurant_commission_percent"><div class="field"><label>Restaurant Commission (%)</label><input class="input" name="value" type="number" min="0" max="100" step="0.01" value="<?=bh($commission)?>" required></div><button class="btn">Save Commission</button></form><div class="formula">Current: <span class="badge"><?=$commission?>%</span></div></section>
<section class="card"><h3><i class="fa-solid fa-motorcycle"></i> Rider & Delivery</h3><form method="post"><input type="hidden" name="action" value="setting"><div class="field"><label>Setting</label><select name="key"><option value="delivery_fee_per_km">Delivery Fee per KM</option><option value="rider_base_payout">Rider Base Payout</option></select></div><div class="field"><label>Amount (PKR)</label><input class="input" name="value" type="number" min="0" step="0.01" value="<?=bh($rate)?>" required></div><button class="btn">Save Setting</button></form><div class="formula">Delivery: <?=$rate?> PKR per started KM<br>Rider payout: <?=$riderPay?> PKR/order</div></section>
<section class="card" style="grid-column:1/-1"><h3>Pricing Model</h3><div class="formula"><b>Customer item price</b> = Restaurant base price × (1 + Customer Markup % / 100)<br><b>Delivery fee</b> = CEIL(distance in KM) × Admin Delivery Fee/KM<br><b>Customer total</b> = Marked-up items + Delivery fee − Coupon discount<br><b>Restaurant settlement</b> = applicable restaurant base amount − Restaurant Commission</div></section>
</div></div></main></body></html>