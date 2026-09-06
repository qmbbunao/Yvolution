-- ============================================================
-- YVOLUTION CUSTOM APPAREL - DATABASE SCHEMA
-- Plain PHP / PDO / MySQL (phpMyAdmin managed)
-- ============================================================

CREATE DATABASE IF NOT EXISTS yvolution_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yvolution_db;

-- ------------------------------------------------------------
-- 1. ROLES & USERS
-- ------------------------------------------------------------
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE   -- customer, admin, superadmin
) ENGINE=InnoDB;

INSERT INTO roles (role_name) VALUES ('customer'), ('admin'), ('superadmin');

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL DEFAULT 1,
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL, -- NULL for social-login-only accounts (Google/Facebook)
    auth_provider ENUM('local','google','facebook') NOT NULL DEFAULT 'local',
    google_id VARCHAR(255) DEFAULT NULL UNIQUE,
    facebook_id VARCHAR(255) DEFAULT NULL UNIQUE,
    phone VARCHAR(20),
    address VARCHAR(255),
    home_address VARCHAR(255) DEFAULT NULL,
    home_lat DECIMAL(10,7) DEFAULT NULL,
    home_lng DECIMAL(10,7) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL, -- cloudinary url
    status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. CATALOG: SERVICES, PACKAGES, PRODUCTS, PROMOTIONS
-- ------------------------------------------------------------
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    type ENUM('product','service','package') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    available_sizes VARCHAR(150) DEFAULT 'S,M,L,XL,XXL',
    available_colors VARCHAR(255) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,      -- cloudinary
    stock_qty INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE product_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_unit VARCHAR(40) DEFAULT 'per piece', -- per piece, per order, per hour
    requires_tarp_options TINYINT(1) NOT NULL DEFAULT 0, -- shows size/event-type/design-source options at checkout
    image_url VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    includes TEXT,                 -- what's bundled
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_quantity INT DEFAULT 1,
    image_url VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE promotions (
    promo_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    promo_code VARCHAR(30) DEFAULT NULL UNIQUE,
    image_url VARCHAR(255) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE testimonials (
    testimonial_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    image_url VARCHAR(255) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. ORDERS, QUOTATIONS, DESIGN UPLOADS
-- ------------------------------------------------------------
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,  -- e.g. YVO-2026-00001
    customer_id INT NOT NULL,
    order_type ENUM('product','service','package','custom') NOT NULL DEFAULT 'custom',
    event_type VARCHAR(60) DEFAULT NULL, -- birthday, graduation, wedding, corporate, other
    status ENUM(
        'pending_review','quotation_sent','quotation_accepted','quotation_rejected',
        'in_production','ready_for_pickup','completed','cancelled'
    ) NOT NULL DEFAULT 'pending_review',
    cancelled_at DATETIME DEFAULT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('unpaid','pending_verification','paid','rejected','refunded') NOT NULL DEFAULT 'unpaid',
    payment_method VARCHAR(30) DEFAULT NULL,       -- gcash, bank_transfer
    payment_reference VARCHAR(100) DEFAULT NULL,   -- customer's reference number/note
    payment_proof_url VARCHAR(255) DEFAULT NULL,   -- Cloudinary screenshot/receipt
    payment_submitted_at DATETIME DEFAULT NULL,
    payment_verified_by INT DEFAULT NULL,
    payment_verified_at DATETIME DEFAULT NULL,
    notes TEXT,
    qr_code_url VARCHAR(255) DEFAULT NULL,     -- QR Server API generated tracking code
    production_event_id VARCHAR(120) DEFAULT NULL, -- Google Calendar event id
    delivery_lat DECIMAL(10,7) DEFAULT NULL,   -- from Nominatim/Leaflet
    delivery_lng DECIMAL(10,7) DEFAULT NULL,
    delivery_address VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_type ENUM('product','service','package','custom') NOT NULL,
    item_ref_id INT DEFAULT NULL,     -- FK to products/services/packages depending on item_type
    item_name VARCHAR(150) NOT NULL,
    size VARCHAR(20) DEFAULT NULL,
    color VARCHAR(40) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE design_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    event_type VARCHAR(60) DEFAULT NULL, -- birthday, graduation, wedding, corporate, other
    image_url VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE design_uploads (
    upload_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    file_url VARCHAR(255) NOT NULL,        -- Cloudinary secure_url
    file_public_id VARCHAR(255) DEFAULT NULL, -- Cloudinary public_id (for deletion)
    template_id INT DEFAULT NULL, -- if the customer chose a shop-provided design instead of uploading
    file_type VARCHAR(30) DEFAULT NULL,
    label VARCHAR(100) DEFAULT NULL,       -- e.g. "Front design", "Reference image"
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT,
    reviewed_by INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES design_templates(template_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE quotations (
    quotation_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    breakdown TEXT,                 -- JSON or text breakdown of costs
    valid_until DATE DEFAULT NULL,
    status ENUM('pending','sent','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(40) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT DEFAULT NULL,
    product_id INT DEFAULT NULL,
    customer_id INT NOT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    comments TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_customer_review (product_id, customer_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE returns (
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

-- ------------------------------------------------------------
-- 4. INVENTORY
-- ------------------------------------------------------------
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) DEFAULT NULL UNIQUE,
    category VARCHAR(80) DEFAULT NULL,   -- e.g. blanks, ink, thread
    unit VARCHAR(20) DEFAULT 'pcs',
    quantity_on_hand INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','discontinued') NOT NULL DEFAULT 'active',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    change_qty INT NOT NULL,           -- positive = stock in, negative = stock out
    reason VARCHAR(150) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. HOMEPAGE / CMS CONTENT
-- ------------------------------------------------------------
CREATE TABLE homepage_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(60) NOT NULL,   -- hero, about, why_choose_us, etc.
    title VARCHAR(200) DEFAULT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    content_text TEXT,
    image_url VARCHAR(255) DEFAULT NULL,
    video_url VARCHAR(255) DEFAULT NULL,  -- manually supplied hero video path/url
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    updated_by INT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE banners (
    banner_id INT AUTO_INCREMENT PRIMARY KEY,
    image_url VARCHAR(255) NOT NULL,
    title VARCHAR(150) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE contact_inquiries (
    inquiry_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    subject VARCHAR(150) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new','read','responded') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. SYSTEM: API SETTINGS, AUDIT LOGS, BACKUPS
-- ------------------------------------------------------------
CREATE TABLE api_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    api_name VARCHAR(60) NOT NULL UNIQUE,  -- smtp, cloudinary, qr_server, maps, calendar
    config_json TEXT NOT NULL,             -- encrypted/serialized config (keys, etc.)
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    updated_by INT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_affected VARCHAR(60) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE backup_logs (
    backup_id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(150) NOT NULL,
    file_size_kb INT DEFAULT NULL,
    type ENUM('manual','scheduled') NOT NULL DEFAULT 'manual',
    performed_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. SEED DATA
-- ------------------------------------------------------------

-- NOTE: The default Super Admin account is NOT seeded here with a hardcoded
-- password hash (never trust a hash you didn't generate yourself).
-- Run database/seed_superadmin.php once in your browser after importing
-- this schema — it creates the first Super Admin with a properly generated
-- password_hash() value, then you should delete that file.

INSERT INTO categories (name, type) VALUES
('T-Shirts', 'product'),
('Jerseys', 'product'),
('Hoodies', 'product'),
('Uniforms', 'product'),
('Screen Printing', 'service'),
('Embroidery', 'service'),
('Sublimation', 'service');

INSERT INTO homepage_content (section_key, title, subtitle, content_text, sort_order) VALUES
('hero', 'Wear Your Game.', 'Custom apparel built for teams that play to win.', 'Yvolution Custom Apparel designs and prints jerseys, uniforms, and merch for teams, schools, and brands across the city.', 1),
('about', 'Who We Are', 'Print shop with a competitive streak.', 'From concept sketch to finished jersey, we handle design approval, production, and delivery in one streamlined pipeline.', 2);

INSERT INTO services (name, description, price, price_unit, requires_tarp_options, status) VALUES
('Tarpaulin Printing', 'Full-color tarpaulin printing for birthdays, graduations, weddings, and corporate events. Choose a size, upload your own design, or pick one of our ready-made templates.', 350.00, 'per piece', 1, 'active');

-- ------------------------------------------------------------
-- END OF SCHEMA
-- ------------------------------------------------------------
