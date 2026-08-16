-- Humsafar distance-based delivery fee
-- Run once in the `humsafar` database AFTER the existing business upgrade SQL.
-- Fee = CEILING(distance in KM) * admin rate per KM.
-- If coordinates are missing, the existing restaurant delivery_fee is retained as a safe fallback.

INSERT IGNORE INTO business_settings (setting_key, setting_value)
VALUES ('delivery_fee_per_km','50');

ALTER TABLE restaurants
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;

ALTER TABLE customer_addresses
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS delivery_distance_km DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS delivery_fee_per_km DECIMAL(10,2) NULL;

DROP TRIGGER IF EXISTS trg_orders_distance_delivery_fee;

DELIMITER $$
CREATE TRIGGER trg_orders_distance_delivery_fee
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE v_rate DECIMAL(10,2) DEFAULT 0;
    DECLARE v_r_lat DECIMAL(10,7) DEFAULT NULL;
    DECLARE v_r_lng DECIMAL(10,7) DEFAULT NULL;
    DECLARE v_c_lat DECIMAL(10,7) DEFAULT NULL;
    DECLARE v_c_lng DECIMAL(10,7) DEFAULT NULL;
    DECLARE v_cos DECIMAL(20,15) DEFAULT 1;
    DECLARE v_distance DECIMAL(10,2) DEFAULT NULL;

    SELECT CAST(setting_value AS DECIMAL(10,2))
      INTO v_rate
      FROM business_settings
     WHERE setting_key = 'delivery_fee_per_km'
     LIMIT 1;

    SELECT latitude, longitude
      INTO v_r_lat, v_r_lng
      FROM restaurants
     WHERE id = NEW.restaurant_id
     LIMIT 1;

    SELECT latitude, longitude
      INTO v_c_lat, v_c_lng
      FROM customer_addresses
     WHERE id = NEW.address_id
       AND user_id = NEW.user_id
     LIMIT 1;

    IF v_rate > 0
       AND v_r_lat IS NOT NULL AND v_r_lng IS NOT NULL
       AND v_c_lat IS NOT NULL AND v_c_lng IS NOT NULL THEN

        SET v_cos =
            SIN(RADIANS(v_r_lat)) * SIN(RADIANS(v_c_lat)) +
            COS(RADIANS(v_r_lat)) * COS(RADIANS(v_c_lat)) *
            COS(RADIANS(v_c_lng - v_r_lng));

        SET v_cos = LEAST(1, GREATEST(-1, v_cos));
        SET v_distance = 6371 * ACOS(v_cos);

        SET NEW.delivery_distance_km = ROUND(v_distance, 2);
        SET NEW.delivery_fee_per_km = v_rate;
        SET NEW.delivery_fee = CEIL(GREATEST(v_distance, 0.01)) * v_rate;
        SET NEW.total = NEW.subtotal + NEW.delivery_fee - NEW.discount;
    END IF;
END$$
DELIMITER ;

-- Example: set the admin rate to PKR 50 per started kilometre.
-- UPDATE business_settings SET setting_value='50' WHERE setting_key='delivery_fee_per_km';
