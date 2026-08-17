<?php

$host = "localhost";
$dbname = "humsafar";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/customer-feature-injector.php';
require_once __DIR__ . '/customer-cancellation-injector.php';
require_once __DIR__ . '/rider-payout-sync.php';

// Global fixed delivery fee for every restaurant/customer.
$globalFeeTable = $conn->query("SHOW TABLES LIKE 'business_settings'");
if ($globalFeeTable && $globalFeeTable->num_rows > 0) {
    $globalFeeResult = $conn->query("SELECT setting_value FROM business_settings WHERE setting_key = 'delivery_fee_per_km' LIMIT 1");
    if ($globalFeeResult && ($globalFeeRow = $globalFeeResult->fetch_assoc())) {
        $globalDeliveryFee = (float)$globalFeeRow['setting_value'];
        $syncStmt = $conn->prepare("UPDATE restaurants SET delivery_fee = ? WHERE delivery_fee <> ? OR delivery_fee IS NULL");
        if ($syncStmt) {
            $syncStmt->bind_param('dd', $globalDeliveryFee, $globalDeliveryFee);
            $syncStmt->execute();
            $syncStmt->close();
        }
    }
}

if (basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'business-management.php') {
    ob_start(function ($html) {
        $html = str_replace('Delivery fee per KM and rider payout are controlled here by Admin and are applied system-wide to all restaurants and deliveries.', 'One fixed delivery fee and rider payout are controlled here by Admin and applied system-wide to all restaurants and deliveries.', $html);
        $html = str_replace('Admin sets one delivery rate per started KM. This is the <b>global rate for every restaurant</b>.', 'Admin sets one fixed delivery fee. The same amount is automatically applied to every restaurant and shown to every customer.', $html);
        $html = str_replace('Delivery Fee per KM (PKR)', 'Delivery Fee per Order (PKR)', $html);
        $html = str_replace('Current:</b> <?=$rate?> PKR per started KM<br><small>Example: 3.2 KM = 4 KM × <?=$rate?> PKR.</small>', 'Current:</b> <?=$rate?> PKR fixed delivery fee per order.<br><small>No KM calculation. This amount is synchronized to all restaurants.</small>', $html);
        $html = str_replace('<b>Delivery fee</b> = CEIL(distance in KM) × Admin Global Delivery Fee/KM', '<b>Delivery fee</b> = Admin Global Fixed Delivery Fee (same for every restaurant)', $html);
        $html = str_replace('<b>Customer total</b> = Marked-up items + Delivery fee − Coupon discount', '<b>Customer total</b> = Marked-up items + Fixed Delivery Fee − Coupon discount', $html);
        return $html;
    });
}

// Rider Available Orders: rider must be approved AND online in a booked session.
if (basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'rider-orders.php' && !empty($_SESSION['rider_id'])) {
    $riderOrdersId = (int)$_SESSION['rider_id'];
    $riderOrdersOnline = false;
    $riderOrdersApproved = false;

    $roStatus = $conn->prepare("SELECT status FROM riders WHERE id=? LIMIT 1");
    if ($roStatus) {
        $roStatus->bind_param('i', $riderOrdersId);
        $roStatus->execute();
        $roStatusRow = $roStatus->get_result()->fetch_assoc();
        $roStatus->close();
        $riderOrdersApproved = in_array(strtolower(trim((string)($roStatusRow['status'] ?? ''))), ['active','approved'], true);
    }

    if ($riderOrdersApproved) {
        $roSession = $conn->prepare("SELECT id FROM rider_availability WHERE rider_id=? AND available_date=CURDATE() AND start_time<=CURTIME() AND (end_time>CURTIME() OR end_time='00:00:00') LIMIT 1");
        if ($roSession) {
            $roSession->bind_param('i', $riderOrdersId);
            $roSession->execute();
            $riderOrdersOnline = $roSession->get_result()->num_rows > 0;
            $roSession->close();
        }
    }

    // COD settlement lock: any COD amount not yet approved by Admin blocks new orders.
    $riderCodLocked = false;
    $codWalletExists = $conn->query("SHOW TABLES LIKE 'rider_cod_wallet'");
    $codSettlementExists = $conn->query("SHOW TABLES LIKE 'rider_cod_settlements'");
    if ($codWalletExists && $codWalletExists->num_rows > 0 && $codSettlementExists && $codSettlementExists->num_rows > 0) {
        $lockStmt = $conn->prepare("SELECT GREATEST(0, COALESCE((SELECT SUM(net_payable) FROM rider_cod_wallet WHERE rider_id=?),0) - COALESCE((SELECT SUM(amount) FROM rider_cod_settlements WHERE rider_id=? AND status='approved'),0)) outstanding");
        if ($lockStmt) {
            $lockStmt->bind_param('ii', $riderOrdersId, $riderOrdersId);
            $lockStmt->execute();
            $riderCodLocked = (float)($lockStmt->get_result()->fetch_assoc()['outstanding'] ?? 0) > 0.009;
            $lockStmt->close();
        }
    }

    // Server-side protection: even a manually crafted POST cannot accept another order.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_action']) && (string)$_POST['delivery_action'] === 'accept' && (!$riderOrdersOnline || $riderCodLocked)) {
        header('Location: rider-orders.php');
        exit;
    }

    if (!$riderOrdersOnline || $riderCodLocked) {
        ob_start(function ($html) use ($riderCodLocked) {
            $notice = $riderCodLocked
                ? '<div class="empty">COD payment is pending Admin approval. You cannot receive another order until your outstanding COD is settled and approved.</div>'
                : '<div class="empty">You are offline. Go online during an active booked session to see available orders.</div>';
            $html = preg_replace('/<section class="section"><h2>New Deliveries<\/h2>.*?<\/section><section class="section"><h2>Active Deliveries<\/h2>/s', '<section class="section"><h2>New Deliveries</h2>'.$notice.'</section><section class="section"><h2>Active Deliveries</h2>', $html, 1);
            $html = preg_replace('/(<div class="stat"><small>New Deliveries<\/small><strong>)\d+(<\/strong>)/', '$10$2', $html, 1);
            return $html;
        });
    }
}

// Show Admin-controlled rider payout beside completed deliveries.
if (basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'rider-orders.php') {
    ob_start(function ($html) {
        $script = <<<'HTML'
<script>
(function(){
    function addRiderEarnings(){
        var completedSection = Array.from(document.querySelectorAll('.section')).find(function(s){var h=s.querySelector('h2');return h&&h.textContent.trim().toLowerCase()==='completed deliveries';});
        if(!completedSection)return;
        var cards=completedSection.querySelectorAll('article.card');if(!cards.length)return;
        fetch('rider-payout-api.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){
            if(!data.ok||!data.payouts)return;
            cards.forEach(function(card){
                var text=card.textContent||'',match=text.match(/Order #([^\s·]+)/);if(!match)return;
                var payout=data.payouts[match[1]];if(!payout||card.querySelector('.rider-earned-box'))return;
                var box=document.createElement('div');box.className='rider-earned-box';box.style.cssText='margin-top:12px;padding:10px 12px;border-radius:9px;background:#eaf9ef;border:1px solid #cfeeda;color:#176c36;font-weight:800;display:flex;justify-content:space-between;gap:10px;';box.innerHTML='<span>💰 Your Earning</span><span>Rs '+Number(payout.amount).toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:2})+'</span>';card.querySelector('.body').appendChild(box);
            });
        }).catch(function(){});
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',addRiderEarnings);else addRiderEarnings();
})();
</script>
HTML;
        return str_ireplace('</body>',$script.'</body>',$html);
    });
}

?>