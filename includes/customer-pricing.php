<?php
/** Humsafar customer pricing helper. Restaurant prices remain base prices. */
function humsafar_customer_markup_percent($conn): float {
    $percent = 0.0;
    $stmt = $conn->prepare("SELECT setting_value FROM business_settings WHERE setting_key='customer_markup_percent' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) $percent = (float)$row['setting_value'];
        $stmt->close();
    }
    return max(0.0, min(100.0, $percent));
}
function humsafar_customer_price($basePrice, $markupPercent): float {
    $base = max(0.0, (float)$basePrice);
    $markup = max(0.0, min(100.0, (float)$markupPercent));
    return round($base * (1 + $markup / 100), 2);
}
function humsafar_customer_price_from_db($conn, $basePrice): float {
    return humsafar_customer_price($basePrice, humsafar_customer_markup_percent($conn));
}
