<?php
/**
 * Safe additive integration for customer Favorites, Reviews, Reorder and Notifications.
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
            // The existing order card already contains its order-details link. Insert the
            // feature buttons immediately before that link, so each button keeps the right ID.
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

        $css = '<style id="humsafar-customer-features-css">'
            . '.hcf-header-link{position:relative;min-height:41px;padding:0 11px;border:1px solid transparent;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#fff;color:#333;text-decoration:none;font-size:12px;font-weight:800;white-space:nowrap}.hcf-header-link:hover{background:#fff1f5;color:#ed0038;border-color:#f2bccd}.hcf-header-link i{font-size:15px}.hcf-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 4px;border-radius:20px;background:#ed0038;color:#fff;font-size:9px;font-weight:900;margin-left:2px}.hcf-strip{background:#fff;border-bottom:1px solid #eee}.hcf-strip-inner{max-width:1500px;margin:auto;padding:7px 4%;display:flex;gap:8px;flex-wrap:wrap}.hcf-strip-inner a{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:8px;background:#fff1f5;color:#ed0038;text-decoration:none;font-size:11px;font-weight:800}.hcf-favorite-float{position:fixed;right:22px;top:105px;z-index:1900;background:#fff;color:#ed0038;border:1px solid #ed0038;border-radius:22px;padding:10px 15px;text-decoration:none;font-weight:800;font-size:12px;box-shadow:0 7px 22px rgba(0,0,0,.12)}.hcf-order-actions{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px 22px}.hcf-order-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:9px;text-decoration:none;font-size:11px;font-weight:800}.hcf-order-btn.reorder{background:#ed0038;color:#fff}.hcf-order-btn.review{background:#fff7e6;color:#a86a00;border:1px solid #f0c36a}@media(max-width:750px){.hcf-header-link span{display:none}.hcf-header-link{width:40px;padding:0}.hcf-strip-inner{padding:7px 12px}.hcf-favorite-float{right:12px;top:100px}.hcf-order-actions{margin-left:17px}}'
            . '</style>';
        $html = preg_replace('/(<\/head>)/i', $css . '$1', $html, 1);

        return $html;
    }

    ob_start('humsafar_customer_feature_output');
}
