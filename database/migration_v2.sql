-- ============================================================
-- SUPERSEDED — use database/migration_full.sql instead.
-- This file is kept only for reference; don't run it directly,
-- it doesn't have the idempotent "IF NOT EXISTS" safety that
-- migration_full.sql has.
-- ============================================================
-- MIGRATION v2 — run this once in phpMyAdmin (SQL tab) against
-- your EXISTING yvolution_db database. Safe to run even if some
-- of these already exist (uses IF NOT EXISTS / checks where possible).
-- ============================================================
USE yvolution_db;

-- 1. Home address pin for customers (separate from per-order delivery address)
ALTER TABLE users
    ADD COLUMN home_address VARCHAR(255) DEFAULT NULL AFTER address,
    ADD COLUMN home_lat DECIMAL(10,7) DEFAULT NULL AFTER home_address,
    ADD COLUMN home_lng DECIMAL(10,7) DEFAULT NULL AFTER home_lat;

-- 2. Flag services that need the tarpaulin-style options (size, event type, design source)
ALTER TABLE services
    ADD COLUMN requires_tarp_options TINYINT(1) NOT NULL DEFAULT 0 AFTER price_unit;

-- 3. Event type for orders that need it (tarpaulin/party/event printing)
ALTER TABLE orders
    ADD COLUMN event_type VARCHAR(60) DEFAULT NULL AFTER order_type;

-- 4. Pre-made design templates the shop offers (customer can pick instead of uploading their own)
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

-- 5. Let a design_upload row reference a chosen template instead of (or alongside) an uploaded file
ALTER TABLE design_uploads
    ADD COLUMN template_id INT DEFAULT NULL AFTER file_public_id,
    ADD CONSTRAINT fk_design_uploads_template FOREIGN KEY (template_id) REFERENCES design_templates(template_id) ON DELETE SET NULL;

-- 6. Seed the Tarpaulin Printing service
INSERT INTO services (name, description, price, price_unit, requires_tarp_options, status)
VALUES (
    'Tarpaulin Printing',
    'Full-color tarpaulin printing for birthdays, graduations, weddings, and corporate events. Choose a size, upload your own design, or pick one of our ready-made templates.',
    350.00,
    'per piece',
    1,
    'active'
);
