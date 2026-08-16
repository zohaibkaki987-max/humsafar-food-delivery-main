<?php
/**
 * Humsafar customer feature integration.
 * Loaded from config.php so existing large customer pages do not need to be replaced.
 * It only changes HTML output for root-level customer pages and never changes admin,
 * restaurant-owner or rider pages.
 */

if (PHP_SAPI === 'cli' || defined('HUMSAFAR_CUSTOMER_FEATURES_DISABLED')) {
    return;
}

if (!function_exists('humsafar_customer_feature_output')) {
    function humsafar_customer_feature_output($html)
    {
        $path = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $blocked = [
            'login.php', 'register.php', 'logout.php',
        ];

        // Customer pages are the root-level pages. Never inject into role dashboards.
        $requestPath = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
        if (
            in_array($path, $blocked, true) ||
            preg_match('#/(admin|restaurant|rider|delivery)/#i', $requestPath) ||
            !isset($_SESSION['user_id']) ||
            (int)$_SESSION['user_id'] <= 0
        ) {
            return $html;
        }

        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return $html;
        }

        $userId = (int)$_SESSION['user_id'];
        $unread = 0;
        $favoriteIds = [];

        // Feature tables may not have been imported yet. Never break the site if they are absent.
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

            $check = $conn->query("SHOW TABLES LIKE 'restaurant_favorites'");
            if ($check && $check->num_rows > 0 && $path === 'restaurant.php') {
                $restaurantId = (int)($_GET['id'] ?? 0);
                if ($restaurantId > 0) {
                    $stmt = $conn->prepare("SELECT id FROM restaurant_favorites WHERE user_id=? AND restaurant_id=? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('ii', $userId, $restaurantId);
                        $stmt->execute();
                        $favoriteIds[$restaurantId] = (bool)$stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }
                }
            }
        } catch (Throwable $e) {
            // Feature UI is optional until the migration is imported.
        }

        $badge = $unread > 0
            ? '<span class="hcf-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
            : '';

        $headerLinks = '
            <a href="favorites.php" class="hcf-header-link" title="Favourite Restaurants" aria-label="Favourite Restaurants">'
            . '<i class="fas fa-heart"></i><span>Favourites</span></a>'
            . '<a href="notifications.php" class="hcf-header-link hcf-notification-link" title="Notifications" aria-label="Notifications">'
            . '<i class="fas fa-bell"></i><span>Notifications</span>' . $badge . '</a>';

        // Add the feature links inside the existing customer header action area.
        $html = preg_replace(
            '/(<div\s+class="customer-header-actions"\s*>)/i',
            '$1' . $headerLinks,
            $html,
            1
        );

        // Add Favorites/Notifications to the existing profile dropdown.
        $profileLinks = '
            <a href="favorites.php" class="customer-menu-link"><i class="fas fa-heart"></i> Favourite Restaurants</a>
            <a href="notifications.php" class="customer-menu-link"><i class="fas fa-bell"></i> Notifications' . $badge . '</a>';
        $html = preg_replace(
            '/(<div\s+class="customer-user-menu"[^>]*>)/i',
            '$1' . $profileLinks,
            $html,
            1
        );

        // Add a compact feature navigation strip immediately after the customer header.
        $featureStrip = '
            <div class="hcf-strip">
                <div class="hcf-strip-inner">
                    <a href="favorites.php"><i class="fas fa-heart"></i> Favourites</a>
                    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications' . $badge . '</a>
                    <a href="my_orders.php"><i class="fas fa-receipt"></i> My Orders</a>
                </div>
            </div>';
        $html = preg_replace('/(<\/header>)/i', '$1' . $featureStrip, $html, 1);

        // Restaurant page: add a real favourite toggle using the existing action endpoint.
        if ($path === 'restaurant.php') {
            $restaurantId = (int)($_GET['id'] ?? 0);
            if ($restaurantId > 0) {
                $isFavorite = !empty($favoriteIds[$restaurantId]);
                $action = $isFavorite ? 'remove' : 'add';
                $label = $isFavorite ? 'Saved' : 'Favourite';
                $icon = $isFavorite ? 'fas fa-heart' : 'far fa-heart';
                $favoriteButton = '<a class="hcf-favorite-float" href="favorite_restaurant.php?restaurant_id=' . $restaurantId . '&action=' . $action . '" title="' . $label . '"><i class="' . $icon . '"></i> ' . $label . '</a>';
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $favoriteButton, $html, 1);
            }
        }

        // My Orders: add Review for delivered orders and Reorder for previous non-cancelled orders.
        if ($path === 'my_orders.php') {
            $html = preg_replace_callback(
                '/(<div\s+class="order-card"[^>]*>.*?<div\s+class="order-bottom"[^>]*>)/is',
                function ($m) {
                    $card = $m[1];
                    if (!preg_match('/order-details\.php\?id=(\d+)/i', $card, $idMatch)) {
                        return $m[0];
                    }
                    $orderId = (int)$idMatch[1];
                    $isCancelled = (bool)preg_match('/Order Cancelled|status-cancelled/i', $card);
                    $isDelivered = (bool)preg_match('/>\s*Delivered\s*</i', $card);
                    $buttons = '<div class="hcf-order-actions">';
                    if (!$isCancelled) {
                        $buttons .= '<a href="reorder.php?order_id=' . $orderId . '" class="hcf-order-btn reorder"><i class="fas fa-rotate-right"></i> Reorder</a>';
                    }
                    if ($isDelivered) {
                        $buttons .= '<a href="review.php?order_id=' . $orderId . '" class="hcf-order-btn review"><i class="fas fa-star"></i> Review</a>';
                    }
                    $buttons .= '</div>';
                    return str_replace($m[1], $buttons . $m[1], $m[0]);
                },
                $html
            );
        }

        $css = '<style id="humsafar-customer-features-css">
            .hcf-header-link{position:relative;min-height:41px;padding:0 11px;border:1px solid transparent;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#fff;color:#333;text-decoration:none;font-size:12px;font-weight:800;white-space:nowrap}.hcf-header-link:hover{background:#fff1f5;color:#ed0038;border-color:#f2bccd}.hcf-header-link i{font-size:15px}.hcf-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 4px;border-radius:20px;background:#ed0038;color:#fff;font-size:9px;font-weight:900;margin-left:2px}.hcf-strip{background:#fff;border-bottom:1px solid #eee}.hcf-strip-inner{max-width:1500px;margin:auto;padding:7px 4%;display:flex;gap:8px;flex-wrap:wrap}.hcf-strip-inner a{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:8px;background:#fff1f5;color:#ed0038;text-decoration:none;font-size:11px;font-weight:800}.hcf-favorite-float{position:fixed;right:22px;top:105px;z-index:1900;background:#fff;color:#ed0038;border:1px solid #ed0038;border-radius:22px;padding:10px 15px;text-decoration:none;font-weight:800;font-size:12px;box-shadow:0 7px 22px rgba(0,0,0,.12)}.hcf-order-actions{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px 22px}.hcf-order-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:9px;text-decoration:none;font-size:11px;font-weight:800}.hcf-order-btn.reorder{background:#ed0038;color:#fff}.hcf-order-btn.review{background:#fff7e6;color:#a86a00;border:1px solid #f0c36a}@media(max-width:750px){.hcf-header-link span{display:none}.hcf-header-link{width:40px;padding:0}.hcf-strip-inner{padding:7px 12px}.hcf-favorite-float{right:12px;top:100px}.hcf-order-actions{margin-left:17px}}
        </style>';
        $html = preg_replace('/(<\/head>)/i', $css . '$1', $html, 1);

        return $html;
    }

    ob_start('humsafar_customer_feature_output');
}
