-- ============================================================
-- MIGRATION v5 - Separate uploaded product size guide images
-- Run once against your existing yvolution_db.
-- ============================================================
USE yvolution_db;

CREATE TABLE IF NOT EXISTS size_guides (
    guide_id INT AUTO_INCREMENT PRIMARY KEY,
    guide_key VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    image_public_id VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO size_guides (guide_key, name) VALUES
    ('jersey', 'Jersey'),
    ('t-shirt', 'T-Shirt'),
    ('polo-shirt', 'Polo Shirt'),
    ('long-sleeve', 'Long Sleeve'),
    ('shorts', 'Shorts')
ON DUPLICATE KEY UPDATE name = VALUES(name);
