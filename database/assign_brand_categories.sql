-- Add brand categories if missing (idempotent)
INSERT IGNORE INTO categories (name, description) VALUES
('SK (Super Kalan)', 'Super Kalan brand products'),
('TG (Town Gaz)', 'Town Gaz brand products'),
('MTG', 'MTG brand products'),
('Shine', 'Shine brand products'),
('Gasul', 'Gasul brand products'),
('Gasulette', 'Gasulette brand products'),
('Elite', 'Elite brand products'),
('LTG', 'LTG brand products'),
('Solane', 'Solane brand products');

-- Map products to brand categories by product_name patterns
START TRANSACTION;

UPDATE products p
JOIN categories c ON c.name = 'TG (Town Gaz)'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Town Gaz%' OR p.product_name LIKE '%TG (%' OR p.product_name LIKE '%TG %';

UPDATE products p
JOIN categories c ON c.name = 'Shine'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Shine%';

UPDATE products p
JOIN categories c ON c.name = 'SK (Super Kalan)'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Super Kalan%' OR p.product_name LIKE '%SK %' OR p.product_name LIKE '%SK(%';

UPDATE products p
JOIN categories c ON c.name = 'MTG'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%MTG%';

UPDATE products p
JOIN categories c ON c.name = 'Gasul'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Gasul%' OR p.product_name LIKE '%Gasul %';

UPDATE products p
JOIN categories c ON c.name = 'Gasulette'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Gasulette%';

UPDATE products p
JOIN categories c ON c.name = 'Elite'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Elite%';

UPDATE products p
JOIN categories c ON c.name = 'LTG'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%LTG%';

UPDATE products p
JOIN categories c ON c.name = 'Solane'
SET p.category_id = c.category_id
WHERE p.product_name LIKE '%Solane%';

COMMIT;

-- Optional: show affected counts (for manual run)
SELECT c.name, COUNT(*) as products_assigned
FROM products p
LEFT JOIN categories c ON p.category_id = c.category_id
WHERE c.name IN ('SK (Super Kalan)','TG (Town Gaz)','MTG','Shine','Gasul','Gasulette','Elite','LTG','Solane')
GROUP BY c.name;
