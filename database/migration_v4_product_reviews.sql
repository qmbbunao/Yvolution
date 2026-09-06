-- ============================================================
-- MIGRATION v4 — Product reviews on feedback table
-- Run once against your existing yvolution_db (phpMyAdmin or CLI).
-- Makes order_id nullable and adds product_id for product detail reviews.
-- ============================================================
USE yvolution_db;

ALTER TABLE feedback
    MODIFY order_id INT DEFAULT NULL;

ALTER TABLE feedback
    ADD COLUMN product_id INT DEFAULT NULL AFTER order_id;

ALTER TABLE feedback
    ADD UNIQUE KEY uq_product_customer_review (product_id, customer_id);

ALTER TABLE feedback
    ADD CONSTRAINT fk_feedback_product
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL;
