-- Seed inventory rows for today with zero stock for all products
-- Run this after you run the categories/products seed

INSERT INTO inventory (product_id, `date`, opening_stock, current_stock, closing_stock, updated_by)
SELECT p.product_id, CURDATE(), 0, 0, 0, 1
FROM products p
ON DUPLICATE KEY UPDATE
  opening_stock = VALUES(opening_stock),
  current_stock = VALUES(current_stock),
  closing_stock = VALUES(closing_stock),
  updated_by = VALUES(updated_by);
