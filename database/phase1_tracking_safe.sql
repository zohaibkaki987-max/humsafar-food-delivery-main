-- Humsafar Food Delivery - Phase 1 Tracking Completion
-- Safe additive patch: no DROP TABLE, no data deletion.
-- MariaDB 10.4+
-- Purpose: keep rider delivery status, order status, rider availability,
-- and order_status_history synchronized for Picked Up -> On The Way -> Delivered.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- Make sure the statuses used by the current rider PHP page are accepted.
ALTER TABLE orders
  MODIFY order_status ENUM(
    'pending','confirmed','preparing','ready_for_pickup','rider_assigned',
    'picked_up','on_the_way','out_for_delivery','delivered','cancelled','rejected'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE rider_deliveries
  MODIFY status ENUM(
    'assigned','accepted','rejected','picked_up','on_the_way',
    'out_for_delivery','delivered','cancelled'
  ) NOT NULL DEFAULT 'assigned';

-- Record delivery-stage changes in the existing order history table.
DROP TRIGGER IF EXISTS trg_rider_delivery_status_history;
CREATE TRIGGER trg_rider_delivery_status_history
AFTER UPDATE ON rider_deliveries
FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        IF NEW.status = 'picked_up' THEN
            UPDATE orders
            SET order_status = 'picked_up'
            WHERE id = NEW.order_id AND rider_id = NEW.rider_id
              AND order_status IN ('rider_assigned','picked_up');

            INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note)
            SELECT NEW.order_id,'picked_up',NEW.rider_id,'delivery','Rider picked up the order.'
            WHERE NOT EXISTS (
                SELECT 1 FROM order_status_history
                WHERE order_id=NEW.order_id AND status='picked_up'
            );

        ELSEIF NEW.status IN ('on_the_way','out_for_delivery') THEN
            UPDATE orders
            SET order_status = 'out_for_delivery'
            WHERE id = NEW.order_id AND rider_id = NEW.rider_id
              AND order_status IN ('picked_up','on_the_way','out_for_delivery');

            INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note)
            SELECT NEW.order_id,'out_for_delivery',NEW.rider_id,'delivery','Rider started the delivery.'
            WHERE NOT EXISTS (
                SELECT 1 FROM order_status_history
                WHERE order_id=NEW.order_id AND status='out_for_delivery'
            );

        ELSEIF NEW.status = 'delivered' THEN
            UPDATE orders
            SET order_status = 'delivered'
            WHERE id = NEW.order_id AND rider_id = NEW.rider_id
              AND order_status IN ('picked_up','on_the_way','out_for_delivery','delivered');

            INSERT INTO order_status_history(order_id,status,changed_by,changed_by_role,note)
            SELECT NEW.order_id,'delivered',NEW.rider_id,'delivery','Rider delivered the order.'
            WHERE NOT EXISTS (
                SELECT 1 FROM order_status_history
                WHERE order_id=NEW.order_id AND status='delivered'
            );

            UPDATE riders
            SET availability_status='available'
            WHERE id=NEW.rider_id;
        END IF;
    END IF;
END;

-- When a rider accepts a delivery, keep rider availability busy.
DROP TRIGGER IF EXISTS trg_rider_delivery_insert_status;
CREATE TRIGGER trg_rider_delivery_insert_status
AFTER INSERT ON rider_deliveries
FOR EACH ROW
BEGIN
    IF NEW.status IN ('assigned','accepted','picked_up','on_the_way','out_for_delivery') THEN
        UPDATE riders SET availability_status='busy' WHERE id=NEW.rider_id;
    END IF;
END;

COMMIT;

-- Optional read-only verification:
-- SELECT id,order_id,rider_id,status,accepted_at,picked_up_at,delivered_at
-- FROM rider_deliveries ORDER BY id DESC LIMIT 10;
-- SELECT id,order_number,rider_id,order_status FROM orders ORDER BY id DESC LIMIT 10;
-- SELECT rider_id,availability_status FROM riders;
-- SELECT order_id,status,changed_by,changed_by_role,created_at
-- FROM order_status_history ORDER BY id DESC LIMIT 20;
