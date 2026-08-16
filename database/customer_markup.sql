-- Admin-controlled customer food pricing.
-- Restaurant menu prices remain base prices.
-- Customer-facing prices include the admin restaurant commission and any
-- optional customer markup.

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

-- The homepage currently reads its legacy app_settings key. Keep that
-- customer-facing setting aligned with the same restaurant commission so
-- homepage prices and restaurant/cart prices never disagree.
CREATE TABLE IF NOT EXISTS app_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'platform_markup_percent', setting_value
FROM business_settings
WHERE setting_key = 'restaurant_commission_percent'
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
