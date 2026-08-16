<?php
/**
 * Humsafar customer pricing helper.
 *
 * Restaurant menu prices are stored as the restaurant's base price.
 * The customer-facing price must include the admin's restaurant commission
 * so the commission is recovered from the customer-facing menu price.
 *
 * Optional customer_markup_percent is applied on top as an additional
 * customer markup when configured by Admin.
 */

function humsafar_setting_percent($conn, string $settingKey, float $default = 0.0): float
{
    $percent = $default;

    $stmt = $conn->prepare(
        "SELECT setting_value
         FROM business_settings
         WHERE setting_key = ?
         LIMIT 1"
    );

    if ($stmt) {
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $percent = (float) $row['setting_value'];
        }

        $stmt->close();
    }

    return max(0.0, min(100.0, $percent));
}

function humsafar_customer_markup_percent($conn): float
{
    return humsafar_setting_percent($conn, 'customer_markup_percent', 0.0);
}

function humsafar_restaurant_commission_percent($conn): float
{
    return humsafar_setting_percent($conn, 'restaurant_commission_percent', 15.0);
}

/**
 * Calculate the customer-facing price.
 *
 * Example:
 * Base restaurant price = Rs. 100
 * Admin commission      = 15%
 * Customer markup       = 0%
 * Customer price        = Rs. 115
 *
 * If an additional customer markup is configured, it is applied after
 * the commission-inclusive price.
 */
function humsafar_customer_price($basePrice, $commissionPercent = 15.0, $markupPercent = 0.0): float
{
    $base = max(0.0, (float) $basePrice);
    $commission = max(0.0, min(100.0, (float) $commissionPercent));
    $markup = max(0.0, min(100.0, (float) $markupPercent));

    $commissionInclusive = $base * (1 + $commission / 100);
    $customerPrice = $commissionInclusive * (1 + $markup / 100);

    return round($customerPrice, 2);
}

function humsafar_customer_price_from_db($conn, $basePrice): float
{
    $commission = humsafar_restaurant_commission_percent($conn);
    $markup = humsafar_customer_markup_percent($conn);

    return humsafar_customer_price($basePrice, $commission, $markup);
}
