-- ============================================================
-- SUPERSEDED — use database/migration_full.sql instead.
-- This file is kept only for reference; don't run it directly,
-- it doesn't have the idempotent "IF NOT EXISTS" safety that
-- migration_full.sql has.
-- ============================================================
-- MIGRATION v3 — run this once in phpMyAdmin (SQL tab) against
-- your EXISTING yvolution_db database, AFTER migration_v2.sql.
-- ============================================================
USE yvolution_db;

-- 1. Order cancellation tracking (customers can cancel within 2 hours of placing)
ALTER TABLE orders
    ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER status;

-- 2. Manual payment (GCash / bank transfer + proof-of-payment upload)
ALTER TABLE orders
    ADD COLUMN payment_status ENUM('unpaid','pending_verification','paid','rejected','refunded') NOT NULL DEFAULT 'unpaid' AFTER total_amount,
    ADD COLUMN payment_method VARCHAR(30) DEFAULT NULL AFTER payment_status,
    ADD COLUMN payment_reference VARCHAR(100) DEFAULT NULL AFTER payment_method,
    ADD COLUMN payment_proof_url VARCHAR(255) DEFAULT NULL AFTER payment_reference,
    ADD COLUMN payment_submitted_at DATETIME DEFAULT NULL AFTER payment_proof_url,
    ADD COLUMN payment_verified_by INT DEFAULT NULL AFTER payment_submitted_at,
    ADD COLUMN payment_verified_at DATETIME DEFAULT NULL AFTER payment_verified_by,
    ADD CONSTRAINT fk_orders_payment_verified_by FOREIGN KEY (payment_verified_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- 3. Returns / refunds (damaged or lost-in-delivery items)
CREATE TABLE IF NOT EXISTS returns (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    reason ENUM('damaged','lost_in_delivery','wrong_item','other') NOT NULL,
    description TEXT,
    proof_url VARCHAR(255) DEFAULT NULL,       -- Cloudinary photo proof
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

-- 4. Social login (Google / Facebook) — password becomes optional for social-only accounts
ALTER TABLE users
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    ADD COLUMN auth_provider ENUM('local','google','facebook') NOT NULL DEFAULT 'local' AFTER password_hash,
    ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER auth_provider,
    ADD COLUMN facebook_id VARCHAR(255) DEFAULT NULL AFTER google_id,
    ADD UNIQUE KEY uq_users_google_id (google_id),
    ADD UNIQUE KEY uq_users_facebook_id (facebook_id);
