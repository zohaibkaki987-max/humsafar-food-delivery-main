-- Humsafar Food Delivery - Phase 1 Core Flow Safe Patch
-- Purpose: make the existing Customer -> Restaurant -> Rider -> Delivered flow
-- consistent without dropping tables or deleting existing records.
-- MariaDB 10.4+

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- ============================================================
-- 1. STATUS COMPATIBILITY
-- ============================================================
-- Current PHP rider/restaurant code uses `out_for_delivery`, while
-- the original orders enum used `on_the_way`. Keep both values so
-- the currently deployed pages cannot fail when updating a status.

ALTER TABLE orders
  MODIFY order_status ENUM(
    'pending',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'rider_assigned',
    'picked_up',
    'on_the_way',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'rejected'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE rider_deliveries
  MODIFY status ENUM(
    'assigned',
    'accepted',
    'rejected',
    'picked_up',
    'on_the_way',
    'out_for_delivery',
    'delivered',
    'cancelled'
  ) NOT NULL DEFAULT 'assigned';

-- ============================================================
-- 2. ORDER ADDRESS SNAPSHOT
-- ============================================================
-- Checkout currently stores the customer's address_id on orders.
-- This trigger also creates an immutable-ish snapshot in
-- order_addresses, so later customer-address edits do not change
-- the address belonging to an old order.

DROP TRIGGER IF EXISTS trg_orders_snapshot_address;

CREATE TRIGGER trg_orders_snapshot_address
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO order_addresses
        (order_id, full_name, phone, address, city, area, landmark)
    SELECT
        NEW.id,
        u.full_name,
        COALESCE(ca.phone, u.phone),
        ca.address_line,
        ca.city,
        ca.area,
        NULL
    FROM customer_addresses ca
    INNER JOIN users u ON u.id = NEW.user_id
    WHERE ca.id = NEW.address_id
      AND ca.user_id = NEW.user_id;
END;

-- ============================================================
-- 3. INITIAL ORDER STATUS HISTORY
-- ============================================================
-- Guarantees every newly-created order starts with a history row.

DROP TRIGGER IF EXISTS trg_orders_initial_history;

CREATE TRIGGER trg_orders_initial_history
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT INTO order_status_history
        (order_id, status, changed_by, changed_by_role, note)
    VALUES
        (NEW.id, NEW.order_status, NEW.user_id, 'customer', 'Order created by customer.');
END;

COMMIT;

-- ============================================================
-- READ-ONLY VERIFICATION
-- ============================================================
-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='order_status';
-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rider_deliveries' AND COLUMN_NAME='status';
-- SELECT COUNT(*) FROM order_addresses;
-- SELECT COUNT(*) FROM order_status_history;
