from pathlib import Path

# Customer My Orders
p = Path('my_orders.php')
s = p.read_text(encoding='utf-8')

if 'HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER' not in s:
    tracking_code = r'''

/* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER */
$customerTracking = [];
foreach ($orders as $trackingOrder) {
    $trackingOrderId = (int)($trackingOrder['id'] ?? 0);
    if ($trackingOrderId < 1) continue;
    $trackingStmt = $conn->prepare("SELECT rd.status AS delivery_status,r.id AS rider_id,r.full_name AS rider_name,r.phone AS rider_phone,r.vehicle_type,r.bike_number,rl.latitude,rl.longitude,rl.updated_at AS location_updated_at
        FROM rider_deliveries rd
        INNER JOIN riders r ON r.id=rd.rider_id
        LEFT JOIN rider_locations rl ON rl.id=(SELECT x.id FROM rider_locations x WHERE x.rider_id=r.id ORDER BY x.id DESC LIMIT 1)
        WHERE rd.order_id=? ORDER BY rd.id DESC LIMIT 1");
    if ($trackingStmt) {
        $trackingStmt->bind_param('i', $trackingOrderId);
        $trackingStmt->execute();
        $customerTracking[$trackingOrderId] = $trackingStmt->get_result()->fetch_assoc();
        $trackingStmt->close();
    }
}
'''
    marker = '$stmt->close();'
    if marker not in s:
        raise SystemExit('Customer insertion marker not found')
    s = s.replace(marker, marker + tracking_code, 1)

    css_link = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">'
    if css_link not in s:
        style_marker = '    <link\n        rel="stylesheet"\n        href="css/style.css"\n    >'
        if style_marker not in s:
            raise SystemExit('Customer stylesheet marker not found')
        s = s.replace(style_marker, style_marker + '\n\n    ' + css_link, 1)

    css = r'''
        /* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER_CSS */
        .live-map-card{margin:5px 22px 20px;padding:14px;background:#f8fbff;border:1px solid #dcecf6;border-radius:13px}
        .live-map-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;color:#333;font-size:13px;font-weight:800}
        .live-map-status{color:#1672b8;font-size:11px;font-weight:800}
        .live-map{height:260px;border-radius:11px;overflow:hidden;border:1px solid #dbe8ef}
        .live-map-meta{margin-top:8px;color:#777;font-size:11px}
        @media (max-width:750px){.live-map-card{margin-left:17px;margin-right:17px}.live-map{height:230px}}
'''
    pos = s.rfind('</style>')
    if pos < 0:
        raise SystemExit('Customer style end not found')
    s = s[:pos] + css + s[pos:]

    cancel_marker = '<!-- =================================================\n                         CANCELLED'
    cpos = s.find(cancel_marker)
    if cpos < 0:
        raise SystemExit('Customer cancelled marker not found')
    epos = s.rfind('<?php else: ?>', 0, cpos)
    if epos < 0:
        raise SystemExit('Customer cancelled else marker not found')
    map_html = r'''

                <?php $liveTracking = $customerTracking[(int)$order['id']] ?? null; ?>
                <?php if ($liveTracking): ?>
                <div class="live-map-card" data-live-order="<?php echo (int)$order['id']; ?>">
                    <div class="live-map-header">
                        <span><i class="fas fa-location-dot"></i> Live Delivery Location</span>
                        <span class="live-map-status" data-live-status>Waiting for rider GPS...</span>
                    </div>
                    <div class="live-map" id="customer-live-map-<?php echo (int)$order['id']; ?>"></div>
                    <div class="live-map-meta" data-live-meta>Map will update automatically when the rider sends a location.</div>
                </div>
                <?php endif; ?>

'''
    s = s[:epos] + map_html + s[epos:]

    js = r'''

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER_JS */
(function(){
    const maps = {};
    const defaultCenter = [25.3960, 68.3578];
    function initCustomerMap(orderId, lat, lng){
        const el = document.getElementById('customer-live-map-'+orderId);
        if(!el) return;
        const valid = Number.isFinite(lat) && Number.isFinite(lng);
        if(!maps[orderId]){
            maps[orderId] = L.map(el).setView(valid ? [lat,lng] : defaultCenter, 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(maps[orderId]);
        }
        if(valid){
            if(!maps[orderId].marker) maps[orderId].marker = L.marker([lat,lng]).addTo(maps[orderId]);
            else maps[orderId].marker.setLatLng([lat,lng]);
            maps[orderId].setView([lat,lng],15);
        }
    }
    async function poll(orderId){
        try{
            const r = await fetch('live-tracking-data.php?order_id='+encodeURIComponent(orderId),{cache:'no-store'});
            const d = await r.json();
            const card = document.querySelector('[data-live-order="'+orderId+'"]');
            if(!card) return;
            const status = card.querySelector('[data-live-status]');
            const meta = card.querySelector('[data-live-meta]');
            if(d.rider){
                const lat = parseFloat(d.rider.latitude), lng = parseFloat(d.rider.longitude);
                initCustomerMap(orderId, Number.isFinite(lat)?lat:NaN, Number.isFinite(lng)?lng:NaN);
                if(Number.isFinite(lat) && Number.isFinite(lng)){
                    if(status) status.textContent = 'Rider location is live';
                    if(meta) meta.textContent = 'Rider: '+(d.rider.rider_name||'Rider')+' · Last update: '+(d.rider.location_updated_at||'just now');
                }else if(status){
                    status.textContent = 'Waiting for rider GPS...';
                }
            }
        }catch(e){}
    }
    document.querySelectorAll('[data-live-order]').forEach(function(el){
        const id = el.getAttribute('data-live-order');
        initCustomerMap(id,NaN,NaN);
        poll(id);
        setInterval(function(){poll(id)},5000);
    });
})();
</script>
'''
    bodypos = s.rfind('</body>')
    if bodypos < 0:
        raise SystemExit('Customer body end not found')
    s = s[:bodypos] + js + s[bodypos:]
    p.write_text(s, encoding='utf-8')

# Rider Orders
p = Path('rider/rider-orders.php')
s = p.read_text(encoding='utf-8')

if 'HUMSAFAR_SHARED_LIVE_MAP_RIDER' not in s:
    css_link = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">'
    if css_link not in s:
        title_marker = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rider Orders | Humsafar</title>'
        if title_marker not in s:
            raise SystemExit('Rider title marker not found')
        s = s.replace(title_marker, title_marker + css_link, 1)

    gps_css = '.gps{margin-top:15px;padding:12px;background:#f3f9ff;border:1px solid #d9eafa;border-radius:10px}'
    if gps_css in s:
        s = s.replace(gps_css, '.gps{margin-top:15px;padding:0;background:#f3f9ff;border:1px solid #d9eafa;border-radius:10px;overflow:hidden}.gps-head{padding:12px}.rider-live-map{height:260px;width:100%}', 1)
    else:
        raise SystemExit('Rider GPS CSS marker not found')

    old = '<div class="gps">📍 <b>Live GPS:</b> <span class="gps-text">Waiting for location permission…</span></div>'
    new = '<div class="gps" data-rider-map-order="<?=ro_h($o[\'id\'])?>"><div class="gps-head">📍 <b>Live GPS:</b> <span class="gps-text">Waiting for location permission…</span></div><div class="rider-live-map" id="rider-live-map-<?=ro_h($o[\'id\'])?>"></div></div>'
    if old not in s:
        raise SystemExit('Rider GPS markup not found')
    s = s.replace(old, new, 1)

    old_js_start = s.find('<script>(function(){const active=')
    if old_js_start < 0:
        raise SystemExit('Rider JS start marker not found')
    old_js_end = s.find('</script>', old_js_start)
    if old_js_end < 0:
        raise SystemExit('Rider JS end marker not found')
    old_js_end += len('</script>')
    new_js = r'''<script>
/* HUMSAFAR_SHARED_LIVE_MAP_RIDER */
(function(){
    const active = <?=json_encode(count($active)>0)?>;
    const maps = {};
    const defaultCenter = [25.3960,68.3578];
    function initRiderMap(orderId,lat,lng){
        const el=document.getElementById('rider-live-map-'+orderId);
        if(!el)return;
        const valid=Number.isFinite(lat)&&Number.isFinite(lng);
        if(!maps[orderId]){
            maps[orderId]=L.map(el).setView(valid?[lat,lng]:defaultCenter,14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(maps[orderId]);
        }
        if(valid){
            if(!maps[orderId].marker)maps[orderId].marker=L.marker([lat,lng]).addTo(maps[orderId]);
            else maps[orderId].marker.setLatLng([lat,lng]);
            maps[orderId].setView([lat,lng],15);
        }
    }
    document.querySelectorAll('[data-rider-map-order]').forEach(function(el){
        initRiderMap(el.getAttribute('data-rider-map-order'),NaN,NaN);
    });
    if(!active)return;
    const els=document.querySelectorAll('.gps-text');
    if(!navigator.geolocation){els.forEach(e=>e.textContent='GPS not supported.');return;}
    function send(p){
        const lat=p.coords.latitude,lng=p.coords.longitude;
        document.querySelectorAll('[data-rider-map-order]').forEach(function(el){
            initRiderMap(el.getAttribute('data-rider-map-order'),lat,lng);
        });
        const f=new FormData();f.append('latitude',lat);f.append('longitude',lng);f.append('accuracy',p.coords.accuracy||'');
        fetch('rider-orders.php?ajax=location',{method:'POST',body:f,cache:'no-store'}).then(r=>r.json()).then(x=>els.forEach(e=>e.textContent=x.ok?'Live location updated':'Location save failed')).catch(()=>els.forEach(e=>e.textContent='Location update failed'));
    }
    navigator.geolocation.watchPosition(send,()=>els.forEach(e=>e.textContent='Location permission denied.'),{enableHighAccuracy:true,maximumAge:5000,timeout:15000});
})();
</script>'''
    s = s[:old_js_start] + new_js + s[old_js_end:]
    p.write_text(s, encoding='utf-8')

if 'HUMSAFAR_SHARED_LIVE_MAP_CUSTOMER' not in Path('my_orders.php').read_text(encoding='utf-8'):
    raise SystemExit('Customer map update failed')
if 'HUMSAFAR_SHARED_LIVE_MAP_RIDER' not in Path('rider/rider-orders.php').read_text(encoding='utf-8'):
    raise SystemExit('Rider map update failed')
