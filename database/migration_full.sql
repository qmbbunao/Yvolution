-- ============================================================
-- COMBINED MIGRATION (idempotent) — run this ONE file in phpMyAdmin's
-- SQL tab against your EXISTING yvolution_db database.
--
-- Safe to run multiple times. Every statement uses "IF NOT EXISTS"
-- and each column is added in its own statement, so if you already
-- ran an earlier version of this migration, re-running it will just
-- skip whatever's already there instead of erroring out on everything.
--
-- If phpMyAdmin still reports an error on one of the last few
-- statements (the UNIQUE KEY / FOREIGN KEY ones near the bottom),
-- that's expected on a re-run and safe to ignore — it just means
-- that specific constraint was already added last time.
-- ============================================================
USE yvolution_db;

-- 1. Home address pin for customers
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_address VARCHAR(255) DEFAULT NULL AFTER address;
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_lat DECIMAL(10,7) DEFAULT NULL AFTER home_address;
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_lng DECIMAL(10,7) DEFAULT NULL AFTER home_lat;

-- 2. Social login (Google / Facebook) — password becomes optional for social-only accounts
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider ENUM('local','google','facebook') NOT NULL DEFAULT 'local' AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL AFTER auth_provider;
ALTER TABLE users ADD COLUMN IF NOT EXISTS facebook_id VARCHAR(255) DEFAULT NULL AFTER google_id;

-- 3. Flag services that need the tarpaulin-style options (size, event type, design source)
ALTER TABLE services ADD COLUMN IF NOT EXISTS requires_tarp_options TINYINT(1) NOT NULL DEFAULT 0 AFTER price_unit;

-- 4. Event type + cancellation + manual payment fields on orders
ALTER TABLE orders ADD COLUMN IF NOT EXISTS event_type VARCHAR(60) DEFAULT NULL AFTER order_type;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL AFTER status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','pending_verification','paid','rejected','refunded') NOT NULL DEFAULT 'unpaid' AFTER total_amount;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) DEFAULT NULL AFTER payment_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL AFTER payment_method;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_proof_url VARCHAR(255) DEFAULT NULL AFTER payment_reference;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_submitted_at DATETIME DEFAULT NULL AFTER payment_proof_url;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_verified_by INT DEFAULT NULL AFTER payment_submitted_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_verified_at DATETIME DEFAULT NULL AFTER payment_verified_by;

-- 5. Pre-made design templates the shop offers
CREATE TABLE IF NOT EXISTS design_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    event_type VARCHAR(60) DEFAULT NULL,
    image_url VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Let a design_upload row reference a chosen template
ALTER TABLE design_uploads ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL AFTER file_public_id;

-- 7. Returns / refunds
CREATE TABLE IF NOT EXISTS returns (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    reason ENUM('damaged','lost_in_delivery','wrong_item','other') NOT NULL,
    description TEXT,
    proof_url VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
    refund_amount DECIMAL(10,2) DEFAULT NULL,
    admin_notes TEXT,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Seed the Tarpaulin Printing service (only if it doesn't already exist)
INSERT INTO services (name, description, price, price_unit, requires_tarp_options, status)
SELECT * FROM (SELECT
    'Tarpaulin Printing' AS name,
    'Full-color tarpaulin printing for birthdays, graduations, weddings, and corporate events. Choose a size, upload your own design, or pick one of our ready-made templates.' AS description,
    350.00 AS price,
    'per piece' AS price_unit,
    1 AS requires_tarp_options,
    'active' AS status
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Tarpaulin Printing');

-- ============================================================
-- The statements below add constraints (unique keys / foreign keys).
-- These do NOT support "IF NOT EXISTS" in MariaDB/MySQL, so if you're
-- re-running this migration and one of these errors with something
-- like "Duplicate key name", that's fine — it means this specific
-- constraint was already added on a previous run. Everything above
-- this point (all the actual columns/tables the app needs) will
-- still have been applied successfully regardless.
-- ============================================================

ALTER TABLE users ADD UNIQUE KEY uq_users_google_id (google_id);
ALTER TABLE users ADD UNIQUE KEY uq_users_facebook_id (facebook_id);
ALTER TABLE orders ADD CONSTRAINT fk_orders_payment_verified_by FOREIGN KEY (payment_verified_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE design_uploads ADD CONSTRAINT fk_design_uploads_template FOREIGN KEY (template_id) REFERENCES design_templates(template_id) ON DELETE SET NULL;
