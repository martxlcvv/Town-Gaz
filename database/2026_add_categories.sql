-- Migration: add categories table and category_id to products
-- Run this SQL in your database (phpMyAdmin or MySQL client)

CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id to products if it doesn't exist
ALTER TABLE `products`
  ADD COLUMN `category_id` INT NULL AFTER `product_code`;

-- Add FK index
ALTER TABLE `products`
  ADD INDEX `idx_products_category` (`category_id`);

-- Add foreign key constraint
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_categories`
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`)
  ON DELETE SET NULL ON UPDATE CASCADE;
