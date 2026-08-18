<?php
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$adminNav = [
    ['admin-panel.php','fa-gauge-high','Dashboard'],
    ['business-dashboard.php','fa-chart-line','Business Center'],
    ['manage-orders.php','fa-receipt','Orders'],
    ['payment-verification.php','fa-credit-card','Payment Verification'],
    ['manage-restaurants.php','fa-store','Restaurants'],
    ['manage-riders.php','fa-motorcycle','Riders'],
    ['manage-users.php','fa-users','Customers'],
    ['business-management.php','fa-briefcase','Business Tools'],
    ['business-management.php#customer-pricing','fa-tags','Customer Pricing'],
    ['rider-payouts.php','fa-money-bill-transfer','Rider Payouts'],
    ['settings.php','fa-gear','Settings'],
    ['profile.php','fa-user','Profile']
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<aside class="sidebar">
    <div class="brand">
        <a href="business-dashboard.php">
            <span class="brand-icon"><i class="fa-solid fa-utensils"></i></span>
            <span><span class="brand-title">Humsafar</span><span class="brand-sub">ADMINISTRATION</span></span>
        </a>
    </div>
    <div class="side-content">
        <div class="label">Management</div>
        <?php foreach($adminNav as $n):
            $targetPage = basename(strtok($n[0], '#'));
            $active = $currentPage === $targetPage;
        ?>
            <a class="nav<?= $active ? ' active' : '' ?>" href="<?=htmlspecialchars($n[0],ENT_QUOTES,'UTF-8')?>">
                <i class="fa-solid <?=htmlspecialchars($n[1],ENT_QUOTES,'UTF-8')?>" aria-hidden="true"></i>
                <span><?=htmlspecialchars($n[2],ENT_QUOTES,'UTF-8')?></span>
            </a>
        <?php endforeach;?>
        <a class="nav logout-nav" href="admin-logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Logout</span></a>
    </div>
    <div class="side-user">
        <div class="side-user-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
        <div><strong><?=htmlspecialchars($_SESSION['admin_name']??'Administrator',ENT_QUOTES,'UTF-8')?></strong><small>Administrator</small></div>
    </div>
</aside>
<style>
.sidebar{position:fixed!important;left:0!important;top:0!important;bottom:0!important;width:218px!important;background:#fff!important;border-right:1px solid #f1dfe7!important;z-index:9999!important;overflow-y:auto!important;box-shadow:2px 0 14px rgba(70,0,25,.04)!important}
.sidebar *{box-sizing:border-box}
.sidebar .brand{padding:22px 18px!important;border-bottom:1px solid #f2e2e8!important;background:#fff!important}
.sidebar .brand a{display:flex!important;align-items:center!important;gap:10px!important;color:#ed0038!important;text-decoration:none!important}
.sidebar .brand-icon{width:38px!important;height:38px!important;border-radius:11px!important;background:#ed0038!important;color:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important;flex:0 0 38px!important}
.sidebar .brand-icon i{display:inline-block!important;color:#fff!important;font-style:normal!important;line-height:1!important}
.sidebar .brand-title{font-weight:900!important;font-size:17px!important;display:block!important}.sidebar .brand-sub{display:block!important;color:#999!important;font-size:8px!important;letter-spacing:1px!important;font-weight:800!important;margin-top:4px!important}
.sidebar .side-content{padding:18px 10px!important;background:#fff!important}.sidebar .label{font-size:9px!important;color:#aaa!important;font-weight:900!important;letter-spacing:1px!important;text-transform:uppercase!important;padding:0 10px 9px!important;background:transparent!important}
.sidebar a.nav{display:flex!important;align-items:center!important;gap:11px!important;color:#555!important;text-decoration:none!important;font-size:12px!important;font-weight:700!important;padding:11px 12px!important;border-radius:9px!important;margin:3px 0!important;background:transparent!important;min-height:42px!important}
.sidebar a.nav i.fa-solid{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:18px!important;min-width:18px!important;height:18px!important;text-align:center!important;color:#ed0038!important;font-size:14px!important;font-style:normal!important;visibility:visible!important;opacity:1!important}
.sidebar a.nav span{display:inline-block!important;color:inherit!important;line-height:1.2!important}
.sidebar a.nav:hover,.sidebar a.nav.active{background:#fff0f4!important;color:#ed0038!important}.sidebar a.nav:hover i,.sidebar a.nav.active i{color:#ed0038!important}
.sidebar .logout-nav{margin-top:14px!important;border-top:1px solid #f2dfe6!important;padding-top:15px!important;color:#b0002d!important}.sidebar .side-user{position:sticky!important;bottom:12px!important;margin:12px 11px!important;padding:10px!important;border:1px solid #f2dce5!important;border-radius:11px!important;background:#fffafd!important;display:flex!important;gap:9px!important;align-items:center!important;font-size:11px!important}.sidebar .side-user-icon{width:30px!important;height:30px!important;min-width:30px!important;border-radius:50%!important;background:#ed0038!important;color:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important}.sidebar .side-user-icon i{color:#fff!important;display:inline-block!important}.sidebar .side-user strong{display:block!important}.sidebar .side-user small{display:block!important;color:#999!important;margin-top:2px!important}
@media(max-width:700px){.sidebar{position:relative!important;width:100%!important;height:auto!important;max-height:none!important;overflow:visible!important}.sidebar .side-content{display:flex!important;overflow-x:auto!important;padding:8px!important;gap:2px!important}.sidebar .label{display:none!important}.sidebar a.nav{white-space:nowrap!important;flex:0 0 auto!important;margin:0!important}.sidebar .side-user{display:none!important}}
</style>