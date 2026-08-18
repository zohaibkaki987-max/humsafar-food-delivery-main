-- Humsafar Payment Verification Upgrade
-- Run this once in the `humsafar` database.
-- Adds customer payment proof storage and a separate rider COD settlement queue.

SET @db := DATABASE();

-- Customer online payment proof.
SET @has_payment_proof := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payments' AND COLUMN_NAME='proof_image'
);
SET @sql := IF(@has_payment_proof=0,
  'ALTER TABLE payments ADD COLUMN proof_image VARCHAR(500) NULL AFTER transaction_reference',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rider COD money sent to Admin for settlement/verification.
CREATE TABLE IF NOT EXISTS rider_cod_settlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rider_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(80) NOT NULL DEFAULT 'bank_transfer',
  transaction_reference VARCHAR(190) NULL,
  proof_image VARCHAR(500) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rider_cod_status (status),
  INDEX idx_rider_cod_rider (rider_id),
  INDEX idx_rider_cod_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
