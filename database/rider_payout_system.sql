-- Humsafar Rider Per-Order Payout System
-- Run this once AFTER database/humsafar_business_upgrade.sql
-- It creates an automatic payout when rider_deliveries becomes delivered.

CREATE TABLE IF NOT EXISTS rider_payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rider_payout_rider_status (rider_id,status),
  INDEX idx_rider_payout_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS trg_humsafar_rider_delivery_payout;
DELIMITER $$
CREATE TRIGGER trg_humsafar_rider_delivery_payout
AFTER UPDATE ON rider_deliveries
FOR EACH ROW
BEGIN
  DECLARE payout_amount DECIMAL(12,2) DEFAULT 0;

  IF LOWER(COALESCE(NEW.status,'')) = 'delivered'
     AND LOWER(COALESCE(OLD.status,'')) <> 'delivered' THEN

    SELECT CAST(COALESCE(setting_value,'0') AS DECIMAL(12,2))
      INTO payout_amount
      FROM business_settings
     WHERE setting_key='rider_base_payout'
     LIMIT 1;

    IF payout_amount IS NULL THEN
      SET payout_amount = 0;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM rider_payouts
       WHERE rider_id=NEW.rider_id AND order_id=NEW.order_id
    ) THEN
      INSERT INTO rider_payouts(rider_id,order_id,amount,status,created_at)
      VALUES(NEW.rider_id,NEW.order_id,payout_amount,'pending',NOW());
    END IF;
  END IF;
END$$
DELIMITER ;

-- Optional repair: create missing payout records for deliveries that were already
-- completed before this trigger was installed.
INSERT INTO rider_payouts(rider_id,order_id,amount,status,created_at)
SELECT rd.rider_id, rd.order_id,
       CAST(COALESCE((SELECT setting_value FROM business_settings WHERE setting_key='rider_base_payout' LIMIT 1),'0') AS DECIMAL(12,2)),
       'pending', COALESCE(rd.delivered_at,NOW())
FROM rider_deliveries rd
LEFT JOIN rider_payouts rp ON rp.rider_id=rd.rider_id AND rp.order_id=rd.order_id
WHERE LOWER(COALESCE(rd.status,''))='delivered'
  AND rp.id IS NULL;
