<?php
/*
 * Global rider payout sync.
 * Every delivered order gets exactly one payout using Admin's rider_base_payout setting.
 * The payout is created idempotently, so refreshing pages never duplicates earnings.
 */
if (!isset($conn) || !$conn) {
    return;
}

$conn->query("CREATE TABLE IF NOT EXISTS rider_payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(rider_id,status),
    INDEX(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$basePayout = 80.00;
$settingResult = $conn->query("SELECT setting_value FROM business_settings WHERE setting_key='rider_base_payout' LIMIT 1");
if ($settingResult && ($setting = $settingResult->fetch_assoc())) {
    $basePayout = max(0, (float)$setting['setting_value']);
}

/*
 * Use rider_deliveries as the source of truth for completed deliveries.
 * A unique logical check on rider + order prevents duplicate payout rows.
 */
$sql = "INSERT INTO rider_payouts (rider_id, order_id, amount, status, created_at)
        SELECT rd.rider_id, rd.order_id, ?, 'pending', COALESCE(rd.delivered_at, NOW())
        FROM rider_deliveries rd
        LEFT JOIN rider_payouts rp
          ON rp.rider_id = rd.rider_id AND rp.order_id = rd.order_id
        WHERE LOWER(COALESCE(rd.status,'')) = 'delivered'
          AND rd.rider_id > 0
          AND rd.order_id IS NOT NULL
          AND rp.id IS NULL";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('d', $basePayout);
    $stmt->execute();
    $stmt->close();
}
?>