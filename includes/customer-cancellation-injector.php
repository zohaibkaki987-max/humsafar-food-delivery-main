<?php
/**
 * Customer cancellation UI guard.
 * Backend enforcement lives in /cancel_order.php; this also injects the
 * Cancel Order button for orders that are actually cancellable.
 */
if (PHP_SAPI === 'cli' || empty($_SESSION['user_id'])) { return; }

if (!function_exists('humsafar_customer_cancellation_output')) {
    function humsafar_customer_cancellation_output($html) {
        $path = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $requestPath = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($path === 'login.php' || $path === 'register.php' || preg_match('#/(admin|restaurant|rider|delivery)/#i', $requestPath)) return $html;

        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) return $html;
        if (strpos($html, '</html>') === false) return $html;

        $userId = (int)$_SESSION['user_id'];
        $cancelable = [];
        $sql = "SELECT o.id
                FROM orders o
                LEFT JOIN payments p ON p.order_id=o.id
                WHERE o.user_id=?
                  AND o.order_status='pending'
                  AND COALESCE(p.status,'pending') NOT IN ('paid','completed','success','succeeded','verified')";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $cancelable[] = (int)$row['id'];
            }
            $stmt->close();
        }

        if ($path === 'my_orders.php' && !empty($cancelable)) {
            $allowed = array_fill_keys($cancelable, true);

            $button = function ($orderId) {
                return '<a href="cancel_order.php?order_id=' . (int)$orderId . '" class="hcf-order-btn cancel" onclick="return confirm(\'Are you sure you want to cancel this pending order?\');"><i class="fas fa-xmark"></i> Cancel Order</a>';
            };

            $html = preg_replace_callback(
                '/<a\\s+href="\\s*order-details\\.php\\?id=\\s*(\\d+)\\s*"[^>]*class="view-order-btn"[^>]*>/is',
                function ($m) use ($allowed, $button) {
                    $orderId = (int)$m[1];
                    if (!isset($allowed[$orderId])) return $m[0];
                    return $button($orderId) . $m[0];
                },
                $html
            );
        }

        $script = '<script id="humsafar-cancel-guard">(function(){const allowed=new Set(' . json_encode($cancelable, JSON_UNESCAPED_SLASHES) . ');document.querySelectorAll(\'a[href*="cancel_order.php"]\').forEach(function(a){const m=a.href.match(/[?&]order_id=(\\d+)/);if(!m||!allowed.has(Number(m[1]))) a.remove();});})();</script>';
        $html = preg_replace('/(<\\/body>)/i', $script . '$1', $html, 1);

        return $html;
    }
    ob_start('humsafar_customer_cancellation_output');
}
?>