-- Humsafar Food Delivery - Phase 1 Customer Order Alignment
-- Safe migration for the CURRENT checkout.php implementation.
-- IMPORTANT: This does NOT DROP TABLES and does NOT delete existing records.
-- MariaDB 10.4+
--
-- Current checkout.php uses the `addresses` table for customer delivery
-- addresses, while the previous orders FK/snapshot logic used
-- `customer_addresses`. This patch makes the database match the live
-- checkout implementation.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- ============================================================
-- 1. ORDER STATUS COMPATIBILITY
-- ============================================================
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
-- 2. ORDER ADDRESS SOURCE = `addresses`
-- ============================================================
-- checkout.php validates and sends addresses.id as orders.address_id.
-- Remove the old FK to customer_addresses so those IDs are not rejected.

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND CONSTRAINT_NAME = 'fk_orders_address'
);

SET @drop_fk_sql := IF(
    @fk_exists > 0,
    'ALTER TABLE orders DROP FOREIGN KEY fk_orders_address',
    'SELECT 1'
);

PREPARE stmt_drop_fk FROM @drop_fk_sql;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

-- Keep the existing index on orders.address_id. The application verifies
-- that the selected address belongs to the logged-in customer before order
-- creation, so the relationship remains enforced at the PHP layer.

-- ============================================================
-- 3. ORDER ADDRESS SNAPSHOT
-- ============================================================
-- Snapshot the exact customer address at order creation time.
-- Later edits to the customer address will not change old orders.

DROP TRIGGER IF EXISTS trg_orders_snapshot_address;

CREATE TRIGGER trg_orders_snapshot_address
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO order_addresses
        (order_id, full_name, phone, address, city, area, landmark)
    SELECT
        NEW.id,
        a.full_name,
        a.phone,
        a.address,
        a.city,
        a.area,
        a.landmark
    FROM addresses a
    WHERE a.id = NEW.address_id
      AND a.user_id = NEW.user_id;
END;

-- ============================================================
-- 4. INITIAL STATUS HISTORY
-- ============================================================
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

-- ============================================================
-- 5. PAYMENT RECORD FOR EVERY ORDER
-- ============================================================
-- checkout.php already saves the selected payment method on orders.
-- This trigger creates the corresponding payment ledger record in `payments`.
-- COD remains pending until the delivery/payment process marks it paid.

DROP TRIGGER IF EXISTS trg_orders_create_payment;

CREATE TRIGGER trg_orders_create_payment
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT INTO payments
        (order_id, user_id, payment_method, provider, transaction_reference, amount, status, paid_at)
    SELECT
        NEW.id,
        NEW.user_id,
        NEW.payment_method,
        CASE
            WHEN NEW.payment_method = 'cash_on_delivery' THEN 'Cash on Delivery'
            WHEN NEW.payment_method = 'card' THEN 'Card'
            WHEN NEW.payment_method = 'online' THEN 'Online'
            ELSE NEW.payment_method
        END,
        NULL,
        NEW.total,
        'pending',
        NULL
    WHERE NOT EXISTS (
        SELECT 1
        FROM payments p
        WHERE p.order_id = NEW.id
    );
END;

COMMIT;

-- ============================================================
-- READ-ONLY VERIFICATION
-- ============================================================
-- Run these after the patch if you want to verify the result:
-- SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='address_id';
-- SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
-- WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='orders';
-- SELECT COUNT(*) FROM order_addresses;
-- SELECT COUNT(*) FROM order_status_history;
-- SELECT COUNT(*) FROM payments;
