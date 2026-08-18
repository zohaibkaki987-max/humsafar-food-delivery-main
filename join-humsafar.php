<?php
$page_title = "Join Humsafar";
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Join Humsafar</title><link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/css_header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>
*{box-sizing:border-box}
body{
    margin:0;
    background:#f7f7fb;
    color:#333;
    font-family:
    'Segoe UI',Tahoma,sans-serif}.
    join-page{
        max-width:1250px;
        margin:auto;
        padding:35px 20px 65px
        }
        .hero{
            min-height:300px;
            border-radius:24px;
            display:flex;
            align-items:center;
            padding:45px;
            background:linear-gradient
            (90deg,rgba(237,0,56,.96),
            rgba(244,63,120,.82)
            )
            ,url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1500&q=85') 
            center/cover;
            box-shadow:0 12px 35px rgba(237, 0, 55, 0.1);
            color:#fff;
            margin-bottom:42px
            }
            .hero h1{
                font-size:42px;
                margin:0 0 12px
                }
            .hero p{
                max-width:620px;
                line-height:1.7;
                margin:0}
            .title{
                text-align:center;
                margin-bottom:28px
                }
            .title h2{margin:0;font-size:28px}.title p{color:#777}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}.card{background:#fff;border:1px solid #f3dce5;border-radius:20px;overflow:hidden;box-shadow:0 8px 28px rgba(60,20,35,.07)}.image{height:165px;overflow:hidden}.image img{width:100%;height:100%;object-fit:cover}.body{padding:22px}.body h3{margin:0 0 8px;font-size:21px}.body p{color:#777;line-height:1.6;min-height:55px;font-size:13px}.features{margin:18px 0}.feature{font-size:12.5px;margin:8px 0;color:#555}.feature i{color:#ed0038;width:18px}.buttons{display:grid;grid-template-columns:1fr 1fr;gap:9px}.btn{padding:11px;border-radius:9px;text-align:center;text-decoration:none;font-size:13px;font-weight:800}.login{color:#ed0038;background:#fff;border:1px solid #f1b8c9}.register{color:#fff;background:linear-gradient(135deg,#ed0038,#f43f78)}.admin-access{text-align:center;margin:30px 0 0;padding-top:22px;border-top:1px solid #eadde3;color:#777;font-size:13px}.admin-access a{color:#ed0038;font-weight:800;text-decoration:none}.benefits{display:grid;grid-template-columns:repeat(4,1fr);gap:17px;margin-top:55px}.benefit{background:#fff;border:1px solid #f3dce5;border-radius:15px;padding:24px 17px;text-align:center}.benefit i{display:flex;align-items:center;justify-content:center;width:48px;height:48px;margin:auto auto 13px;border-radius:14px;color:#ed0038;background:#ffe8ef;font-size:21px}.benefit h4{margin:0 0 7px}.benefit p{margin:0;color:#777;font-size:12px;line-height:1.5}@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}.benefits{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.join-page{padding:20px 13px 45px}.hero{min-height:330px;padding:30px 22px;align-items:flex-end}.hero h1{font-size:31px}.grid,.benefits{grid-template-columns:1fr}}@media(max-width:430px){.buttons{grid-template-columns:1fr}}
</style></head><body><main class="join-page">
    <section class="hero">
        <div>
            <h1>Join Humsafar</h1>
            <p>Choose the account that fits you. Order food, grow your restaurant, or deliver with Humsafar.</p>
        </div></section><div class="title"><h2>Choose Your Account</h2><p>Join the Humsafar food delivery network</p></div><div class="grid"><article class="card"><div class="image"><img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80"></div><div class="body"><h3>Customer</h3><p>Order food from your favorite restaurants and track your delivery.</p><div class="features"><div class="feature"><i class="fa-solid fa-check"></i> Easy food ordering</div><div class="feature"><i class="fa-solid fa-check"></i> Live order tracking</div><div class="feature"><i class="fa-solid fa-check"></i> Secure account</div></div><div class="buttons"><a class="btn login" href="login.php">Login</a><a class="btn register" href="register.php">Sign Up</a></div></div></article><article class="card"><div class="image"><img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=900&q=80"></div><div class="body"><h3>Restaurant Owner</h3><p>Manage your restaurant, menus, orders and grow your business.</p><div class="features"><div class="feature"><i class="fa-solid fa-check"></i> Restaurant management</div><div class="feature"><i class="fa-solid fa-check"></i> Order management</div><div class="feature"><i class="fa-solid fa-check"></i> Business growth</div></div><div class="buttons"><a class="btn login" href="restaurant/restaurant-owner-login.php">Login</a><a class="btn register" href="restaurant/restaurant-owner-register.php">Sign Up</a></div></div></article><article class="card"><div class="image"><img src="https://images.unsplash.com/photo-1526367790999-0150786686a2?auto=format&fit=crop&w=900&q=80"></div><div class="body"><h3>Rider</h3><p>Deliver orders, manage your deliveries and earn with Humsafar.</p><div class="features"><div class="feature"><i class="fa-solid fa-check"></i> Flexible deliveries</div><div class="feature"><i class="fa-solid fa-check"></i> Live GPS support</div><div class="feature"><i class="fa-solid fa-check"></i> Earnings tracking</div></div><div class="buttons"><a class="btn login" href="rider/rider-login.php">Login</a><a class="btn register" href="rider/rider-register.php">Sign Up</a></div></div></article></div><div class="admin-access">For Admin: <a href="admin/admin-login.php">Login to Admin Panel</a></div><div class="benefits"><div class="benefit"><i class="fa-solid fa-bolt"></i><h4>Fast Service</h4><p>Simple ordering and delivery flow.</p></div><div class="benefit"><i class="fa-solid fa-location-dot"></i><h4>Live Tracking</h4><p>Track deliveries in real time.</p></div><div class="benefit"><i class="fa-solid fa-shield-halved"></i><h4>Secure</h4><p>Role-based access for every account.</p></div><div class="benefit"><i class="fa-solid fa-headset"></i><h4>Support</h4><p>Help is available when you need it.</p></div></div></main></body></html>