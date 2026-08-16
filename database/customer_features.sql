-- Humsafar Customer Features
-- Ratings & Reviews + Favourite Restaurants + Customer Notifications
-- Reorder uses the existing orders/order_items/cart tables and needs no new table.
-- Safe migration: creates only missing feature tables.

CREATE TABLE IF NOT EXISTS restaurant_favorites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_restaurant_favorite (user_id, restaurant_id),
    KEY idx_favorites_user (user_id),
    KEY idx_favorites_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS restaurant_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    order_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_order_customer (user_id, order_id),
    KEY idx_reviews_restaurant (restaurant_id),
    KEY idx_reviews_user (user_id),
    KEY idx_reviews_order (order_id),
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read (user_id, is_read),
    KEY idx_notifications_order (order_id),
    KEY idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automatically create a customer notification whenever the existing
-- order status history records a status change.
DROP TRIGGER IF EXISTS trg_customer_order_notification;

DELIMITER $$
CREATE TRIGGER trg_customer_order_notification
AFTER INSERT ON order_status_history
FOR EACH ROW
BEGIN
    DECLARE v_user_id INT DEFAULT 0;
    DECLARE v_title VARCHAR(150);
    DECLARE v_message VARCHAR(500);

    SELECT user_id INTO v_user_id
    FROM orders
    WHERE id = NEW.order_id
    LIMIT 1;

    SET v_title = CASE NEW.status
        WHEN 'pending' THEN 'Order received'
        WHEN 'confirmed' THEN 'Order confirmed'
        WHEN 'preparing' THEN 'Your food is being prepared'
        WHEN 'ready_for_pickup' THEN 'Order ready for pickup'
        WHEN 'rider_assigned' THEN 'Rider assigned'
        WHEN 'picked_up' THEN 'Rider picked up your order'
        WHEN 'on_the_way' THEN 'Your rider is on the way'
        WHEN 'out_for_delivery' THEN 'Your rider is on the way'
        WHEN 'delivered' THEN 'Order delivered'
        WHEN 'cancelled' THEN 'Order cancelled'
        WHEN 'rejected' THEN 'Order rejected'
        ELSE 'Order status updated'
    END;

    SET v_message = CASE NEW.status
        WHEN 'pending' THEN 'We received your order and sent it to the restaurant.'
        WHEN 'confirmed' THEN 'The restaurant confirmed your order.'
        WHEN 'preparing' THEN 'The restaurant is preparing your food.'
        WHEN 'ready_for_pickup' THEN 'Your food is ready and waiting for pickup.'
        WHEN 'rider_assigned' THEN 'A rider has been assigned to your order.'
        WHEN 'picked_up' THEN 'Your rider has picked up the order.'
        WHEN 'on_the_way' THEN 'Your order is on the way.'
        WHEN 'out_for_delivery' THEN 'Your order is on the way.'
        WHEN 'delivered' THEN 'Your order has been delivered. Enjoy your meal!'
        WHEN 'cancelled' THEN 'Your order has been cancelled.'
        WHEN 'rejected' THEN 'The restaurant could not accept your order.'
        ELSE COALESCE(NULLIF(NEW.note, ''), 'Your order status has been updated.')
    END;

    IF v_user_id > 0 THEN
        INSERT INTO customer_notifications
            (user_id, order_id, type, title, message)
        VALUES
            (v_user_id, NEW.order_id, 'order_status', v_title, v_message);
    END IF;
END$$
DELIMITER ;

-- Optional one-time backfill is intentionally not automatic.
-- Run it manually if you want old order history converted into notifications.
