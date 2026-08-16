-- Customer-facing food price markup controlled by Admin.
-- Restaurant menu_items.price remains the base restaurant price.
CREATE TABLE IF NOT EXISTS business_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO business_settings (setting_key, setting_value)
VALUES ('customer_markup_percent','0')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
