-- Admin-controlled customer food price markup.
-- Run this once in the Humsafar database.
CREATE TABLE IF NOT EXISTS business_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO business_settings (setting_key, setting_value) VALUES
('customer_markup_percent','0'),
('restaurant_commission_percent','15'),
('rider_base_payout','80'),
('delivery_fee_per_km','50'),
('currency','PKR')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
