-- Humsafar Food Delivery - Phase 1 Foundation (SAFE MIGRATION)
-- MariaDB 10.4 / MySQL-compatible
-- IMPORTANT: This migration does NOT DROP tables and does NOT replace existing data.
-- Run this AFTER the existing `humsafar` database is already working.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- ============================================================
-- 1. STANDARDIZE CUSTOMER ADDRESSES
-- ============================================================
-- `customer_addresses` is the canonical customer address table.
-- Existing legacy `addresses` data is copied into it only when
-- the same user/address does not already exist.

INSERT INTO customer_addresses
    (user_id, address_title, address_line, city, area, phone, is_default)
SELECT
    a.user_id,
    COALESCE(NULLIF(a.label, ''), CONCAT(UCASE(LEFT(a.address_type,1)), SUBSTRING(a.address_type,2))),
    a.address,
    a.city,
    a.area,
    a.phone,
    a.is_default
FROM addresses a
WHERE NOT EXISTS (
    SELECT 1
    FROM customer_addresses ca
    WHERE ca.user_id = a.user_id
      AND ca.address_line = a.address
      AND COALESCE(ca.area, '') = COALESCE(a.area, '')
);

-- Copy any legacy user_addresses rows as well.
INSERT INTO customer_addresses
    (user_id, address_title, address_line, city, area, phone, is_default)
SELECT
    ua.user_id,
    COALESCE(NULLIF(ua.title, ''), 'Home'),
    ua.address,
    COALESCE(NULLIF(ua.city, ''), 'Hyderabad'),
    NULL,
    ua.phone,
    ua.is_default
FROM user_addresses ua
WHERE NOT EXISTS (
    SELECT 1
    FROM customer_addresses ca
    WHERE ca.user_id = ua.user_id
      AND ca.address_line = ua.address
      AND COALESCE(ca.city, '') = COALESCE(ua.city, '')
);

-- ============================================================
-- 2. ENSURE ORDERS -> RIDERS RELATIONSHIP
-- ============================================================
-- `orders.rider_id` already exists in the current schema, but
-- the foreign key was missing. Add the index/FK only if absent.

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_rider_id'
);

SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE orders ADD KEY idx_orders_rider_id (rider_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'orders'
      AND constraint_name = 'fk_orders_rider'
      AND constraint_type = 'FOREIGN KEY'
);

SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. RIDER DATA NORMALIZATION
-- ============================================================
-- Current riders table permits only `bike`; normalize old empty
-- values before relying on the field in the application.

UPDATE riders
SET vehicle_type = 'bike'
WHERE vehicle_type IS NULL OR vehicle_type = '';

-- ============================================================
-- 4. ORDER ADDRESS INTEGRITY
-- ============================================================
-- Every order should keep a snapshot in order_addresses. This is
-- intentionally NOT replaced by a live customer address lookup.
-- No destructive schema change is required here.

-- ============================================================
-- 5. RESTAURANT / RIDER ADDRESS RULE
-- ============================================================
-- Restaurant address remains `restaurants.address`.
-- Rider address remains `riders.address`.
-- Customer address remains `customer_addresses`.
-- Do NOT merge these into one address table.

COMMIT;

-- Verification queries (safe/read-only):
-- SELECT COUNT(*) AS customer_addresses_count FROM customer_addresses;
-- SELECT COUNT(*) AS orders_with_rider FROM orders WHERE rider_id IS NOT NULL;
-- SELECT rc.CONSTRAINT_NAME
-- FROM information_schema.referential_constraints rc
-- WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
--   AND rc.TABLE_NAME = 'orders'
--   AND rc.CONSTRAINT_NAME = 'fk_orders_rider';
