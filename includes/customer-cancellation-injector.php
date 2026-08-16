<?php
/**
 * Customer cancellation UI guard.
 * Backend enforcement lives in /cancel_order.php; this only keeps the UI honest.
 */
if (PHP_SAPI === 'cli' || empty($_SESSION['user_id'])) { return; }

if (!function_exists('humsafar_customer_cancellation_output')) {
    function humsafar_customer_cancellation_output($html) {
        $path = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $requestPath = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($path === 'login.php' || $path === 'register.php' || preg_match('#/(admin|restaurant|rider|delivery)/#i', $requestPath)) return $html;

        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) return $html;

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
            while ($row = $result->fetch_assoc()) $cancelable[] = (int)$row['id'];
            $stmt->close();
        }

        if (strpos($html, '</html>') === false) return $html;
        $json = json_encode($cancelable, JSON_UNESCAPED_SLASHES);
        $script = '<script id="humsafar-cancel-guard">(function(){const allowed=new Set(' . $json . ');document.querySelectorAll(\'a[href*="cancel_order.php"]\').forEach(function(a){const m=a.href.match(/[?&]order_id=(\\d+)/);if(!m||!allowed.has(Number(m[1]))) {a.remove();}});})();</script>';
        return preg_replace('/(<\\/body>)/i', $script . '$1', $html, 1);
    }
    ob_start('humsafar_customer_cancellation_output');
}
?>