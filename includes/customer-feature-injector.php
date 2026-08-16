<?php
/**
 * Safe additive integration for customer Favorites, Reviews, Reorder,
 * Notifications and Live Tracking.
 * Loaded from config.php so existing large customer pages remain untouched.
 */

if (PHP_SAPI === 'cli' || defined('HUMSAFAR_CUSTOMER_FEATURES_DISABLED')) {
    return;
}

if (!function_exists('humsafar_customer_feature_output')) {
    function humsafar_customer_feature_output($html)
    {
        if (strpos((string)$html, '</html>') === false) {
            return $html;
        }

        $path = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $requestPath = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));

        if (
            in_array($path, ['login.php', 'register.php', 'logout.php'], true) ||
            preg_match('#/(admin|restaurant|rider|delivery)/#i', $requestPath) ||
            empty($_SESSION['user_id'])
        ) {
            return $html;
        }

        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return $html;
        }

        $userId = (int)$_SESSION['user_id'];
        $unread = 0;
        $isFavorite = false;

        try {
            $check = $conn->query("SHOW TABLES LIKE 'customer_notifications'");
            if ($check && $check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM customer_notifications WHERE user_id=? AND is_read=0");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $unread = (int)($row['total'] ?? 0);
                    $stmt->close();
                }
            }

            if ($path === 'restaurant.php') {
                $restaurantId = (int)($_GET['id'] ?? 0);
                $check = $conn->query("SHOW TABLES LIKE 'restaurant_favorites'");
                if ($restaurantId > 0 && $check && $check->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT id FROM restaurant_favorites WHERE user_id=? AND restaurant_id=? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('ii', $userId, $restaurantId);
                        $stmt->execute();
                        $isFavorite = (bool)$stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }
                }
            }
        } catch (Throwable $e) {
            // Feature migration is optional; never break an existing customer page.
        }

        $badge = $unread > 0
            ? '<span class="hcf-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
            : '';

        $headerLinks = "<a href=\"favorites.php\" class=\"hcf-header-link\" title=\"Favourite Restaurants\" aria-label=\"Favourite Restaurants\"><i class=\"fas fa-heart\"></i><span>Favourites</span></a>"
            . "<a href=\"notifications.php\" class=\"hcf-header-link hcf-notification-link\" title=\"Notifications\" aria-label=\"Notifications\"><i class=\"fas fa-bell\"></i><span>Notifications</span>{$badge}</a>";

        $html = preg_replace(
            '/(<div\s+class="customer-header-actions"\s*>)/i',
            '$1' . $headerLinks,
            $html,
            1
        );

        $profileLinks = "<a href=\"favorites.php\" class=\"customer-menu-link\"><i class=\"fas fa-heart\"></i> Favourite Restaurants</a>"
            . "<a href=\"notifications.php\" class=\"customer-menu-link\"><i class=\"fas fa-bell\"></i> Notifications {$badge}</a>";
        $html = preg_replace(
            '/(<div\s+class="customer-user-menu"[^>]*>)/i',
            '$1' . $profileLinks,
            $html,
            1
        );

        $featureStrip = "<div class=\"hcf-strip\"><div class=\"hcf-strip-inner\">"
            . "<a href=\"favorites.php\"><i class=\"fas fa-heart\"></i> Favourites</a>"
            . "<a href=\"notifications.php\"><i class=\"fas fa-bell\"></i> Notifications {$badge}</a>"
            . "<a href=\"my_orders.php\"><i class=\"fas fa-receipt\"></i> My Orders</a>"
            . "</div></div>";
        $html = preg_replace('/(<\/header>)/i', '$1' . $featureStrip, $html, 1);

        if ($path === 'restaurant.php') {
            $restaurantId = (int)($_GET['id'] ?? 0);
            if ($restaurantId > 0) {
                $action = $isFavorite ? 'remove' : 'add';
                $label = $isFavorite ? 'Saved' : 'Favourite';
                $icon = $isFavorite ? 'fas fa-heart' : 'far fa-heart';
                $favoriteButton = '<a class="hcf-favorite-float" href="favorite_restaurant.php?restaurant_id=' . $restaurantId . '&action=' . $action . '" title="' . $label . '"><i class="' . $icon . '"></i> ' . $label . '</a>';
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $favoriteButton, $html, 1);
            }
        }

        if ($path === 'my_orders.php') {
            $html = preg_replace_callback(
                '/(<div\s+class="order-card"[^>]*>.*?)(<a\s+href="\s*order-details\.php\?id=(\d+)[^>]*class="view-order-btn"[^>]*>)/is',
                function ($m) {
                    $beforeLink = $m[1];
                    $orderId = (int)$m[3];
                    $cancelled = (bool)preg_match('/Order Cancelled|status-cancelled/i', $beforeLink);
                    $delivered = (bool)preg_match('/>\s*Delivered\s*</i', $beforeLink);
                    $buttons = '<div class="hcf-order-actions">';
                    if (!$cancelled) {
                        $buttons .= '<a href="reorder.php?order_id=' . $orderId . '" class="hcf-order-btn reorder"><i class="fas fa-rotate-right"></i> Reorder</a>';
                    }
                    if ($delivered) {
                        $buttons .= '<a href="review.php?order_id=' . $orderId . '" class="hcf-order-btn review"><i class="fas fa-star"></i> Review</a>';
                    }
                    $buttons .= '</div>';
                    return $beforeLink . $buttons . $m[2];
                },
                $html
            );
        }

        /* Customer Live Tracking: destination capture + route + ETA. */
        if ($path === 'track-order.php') {
            $trackingUi = '<div id="hcf-live-tracking" class="hcf-live-tracking">'
                . '<div class="hcf-track-line"><strong>📍 Delivery location</strong><span id="hcf-location-state">Checking…</span></div>'
                . '<button type="button" id="hcf-set-location" class="hcf-location-btn">Use My Current Location</button>'
                . '<div class="hcf-track-stats"><span>📏 <b id="hcf-distance">—</b></span><span>⏱️ <b id="hcf-eta">—</b></span><span>🔄 Live</span></div>'
                . '<div id="hcf-route-note" class="hcf-route-note">Route and ETA will appear when both rider and delivery location are available.</div>'
                . '</div>';
            $html = preg_replace('/(<div\s+class="map"\s+id="map"[^>]*><\/div>)/i', '$1' . $trackingUi, $html, 1);

            $trackingJs = <<<'JS'
<script id="hcf-live-tracking-js">
(function(){
  const root=document.getElementById('hcf-live-tracking');
  const btn=document.getElementById('hcf-set-location');
  if(!root||!btn)return;
  const params=new URLSearchParams(location.search), orderId=params.get('order_id');
  if(!orderId)return;
  let destinationMarker=null, routeLayer=null, lastRouteKey='';
  const state=document.getElementById('hcf-location-state'), distance=document.getElementById('hcf-distance'), eta=document.getElementById('hcf-eta'), note=document.getElementById('hcf-route-note');
  function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  async function saveLocation(){
    if(!navigator.geolocation){state.textContent='GPS supported nahi hai';return;}
    btn.disabled=true;btn.textContent='Location le raha hai…';
    navigator.geolocation.getCurrentPosition(async p=>{
      try{
        const f=new FormData();f.append('order_id',orderId);f.append('latitude',p.coords.latitude);f.append('longitude',p.coords.longitude);f.append('accuracy',p.coords.accuracy||'');
        const r=await fetch('save-delivery-location.php',{method:'POST',body:f,cache:'no-store'});const d=await r.json();
        state.textContent=d.ok?'Location saved ✓':'Location save nahi hui';
      }catch(e){state.textContent='Location save nahi hui';}
      btn.disabled=false;btn.textContent='Update My Location';load();
    },()=>{state.textContent='Location permission denied';btn.disabled=false;btn.textContent='Use My Current Location';},{enableHighAccuracy:true,timeout:10000,maximumAge:30000});
  }
  async function load(){
    try{
      const r=await fetch('live-tracking-data.php?order_id='+encodeURIComponent(orderId),{cache:'no-store'});const d=await r.json();if(!d.ok)return;
      if(d.destination){state.textContent='Location saved ✓';btn.textContent='Update My Location';}
      else{state.textContent='Location set karna zaroori hai';btn.textContent='Use My Current Location';}
      if(!d.destination||!d.rider||d.rider.latitude===null||d.rider.longitude===null)return;
      const a=[parseFloat(d.rider.latitude),parseFloat(d.rider.longitude)], b=[parseFloat(d.destination.latitude),parseFloat(d.destination.longitude)];
      if(!map||!window.L||isNaN(b[0]))return;
      if(!destinationMarker)destinationMarker=L.marker(b).addTo(map).bindPopup('📍 Delivery Location');else destinationMarker.setLatLng(b);
      const key=a.join(',')+'|'+b.join(',');if(key===lastRouteKey)return;lastRouteKey=key;
      const rr=await fetch('https://router.project-osrm.org/route/v1/driving/'+a[1]+','+a[0]+';'+b[1]+','+b[0]+'?overview=full&geometries=geojson').then(x=>x.json());
      if(!rr.routes||!rr.routes[0])return;
      const route=rr.routes[0];
      if(routeLayer)map.removeLayer(routeLayer);
      routeLayer=L.geoJSON(route.geometry,{style:{weight:5}}).addTo(map);
      map.fitBounds(routeLayer.getBounds(),{padding:[30,30]});
      distance.textContent=(route.distance/1000).toFixed(1)+' km';
      const mins=Math.max(1,Math.round(route.duration/60));eta.textContent=mins<60?mins+' min':Math.floor(mins/60)+'h '+(mins%60)+'m';
      note.textContent='Live road route aur estimated arrival time.';
    }catch(e){note.textContent='Live route temporarily unavailable.';}
  }
  btn.addEventListener('click',saveLocation);load();setInterval(load,10000);
})();
</script>
JS;
            $html = preg_replace('/(<\/body>)/i', $trackingJs . '$1', $html, 1);
        }

        $css = '<style id="humsafar-customer-features-css">'
            . '.hcf-header-link{position:relative;min-height:41px;padding:0 11px;border:1px solid transparent;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#fff;color:#333;text-decoration:none;font-size:12px;font-weight:800;white-space:nowrap}.hcf-header-link:hover{background:#fff1f5;color:#ed0038;border-color:#f2bccd}.hcf-header-link i{font-size:15px}.hcf-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 4px;border-radius:20px;background:#ed0038;color:#fff;font-size:9px;font-weight:900;margin-left:2px}.hcf-strip{background:#fff;border-bottom:1px solid #eee}.hcf-strip-inner{max-width:1500px;margin:auto;padding:7px 4%;display:flex;gap:8px;flex-wrap:wrap}.hcf-strip-inner a{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:8px;background:#fff1f5;color:#ed0038;text-decoration:none;font-size:11px;font-weight:800}.hcf-favorite-float{position:fixed;right:22px;top:105px;z-index:1900;background:#fff;color:#ed0038;border:1px solid #ed0038;border-radius:22px;padding:10px 15px;text-decoration:none;font-weight:800;font-size:12px;box-shadow:0 7px 22px rgba(0,0,0,.12)}.hcf-order-actions{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px 22px}.hcf-order-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:9px;text-decoration:none;font-size:11px;font-weight:800}.hcf-order-btn.reorder{background:#ed0038;color:#fff}.hcf-order-btn.review{background:#fff7e6;color:#a86a00;border:1px solid #f0c36a}.hcf-live-tracking{margin:14px 0 0;background:#fff;border:1px solid #eee;border-radius:13px;padding:14px}.hcf-track-line{display:flex;justify-content:space-between;gap:10px;font-size:13px}.hcf-track-line span{color:#777}.hcf-location-btn{margin-top:10px;border:0;border-radius:9px;padding:10px 14px;background:#ed0038;color:#fff;font-weight:800;cursor:pointer}.hcf-location-btn:disabled{opacity:.6}.hcf-track-stats{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.hcf-track-stats span{padding:8px 10px;border-radius:9px;background:#f7f7f8;font-size:12px}.hcf-route-note{margin-top:9px;color:#777;font-size:11px}@media(max-width:750px){.hcf-header-link span{display:none}.hcf-header-link{width:40px;padding:0}.hcf-strip-inner{padding:7px 12px}.hcf-favorite-float{right:12px;top:100px}.hcf-order-actions{margin-left:17px}.hcf-track-line{flex-direction:column}}
'
            . '</style>';
        $html = preg_replace('/(<\/head>)/i', $css . '$1', $html, 1);

        return $html;
    }

    ob_start('humsafar_customer_feature_output');
}
