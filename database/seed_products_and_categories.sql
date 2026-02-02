-- Seed categories and products based on provided price list
-- Run this after running the migration file `2026_add_categories.sql`

-- Categories
-- Use INSERT IGNORE to skip categories that already exist (prevents duplicate-key errors)
INSERT IGNORE INTO categories (name, description) VALUES
('Cylinders', 'Gas cylinders by size and brand'),
('Accessories', 'Gaskets, nipples, clamps, controllers'),
('Burners', 'Single/Double burners and heavy variants'),
('Hoses', 'LPG hoses small and big'),
('Regulators', 'LPG regulators and snap-on types'),
('Brass Caps', 'Brass caps small and big'),
('Brand New Cylinder', 'New cylinders by size');
-- Brand-specific categories for faster filtering in product catalog
INSERT IGNORE INTO categories (name, description) VALUES
('SK (Super Kalan)', 'Super Kalan brand products'),
('TG (Town Gaz)', 'Town Gaz brand products'),
('MTG', 'MTG brand products'),
('Shine', 'Shine brand products'),
('Gasul', 'Gasul brand products'),
('Elite', 'Elite brand products'),
('LTG', 'LTG brand products');

-- Products (cylinders / LPG brands)
INSERT INTO products (product_name, product_code, size, unit, capital_cost, current_price, status, image_path, category_id)
VALUES
('Super Kalan (SK) 2.7KG', NULL, '2.7', 'kg', 0, 210.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Super Kalan @ (SK) 2.7KG', NULL, '2.7', 'kg', 0, 255.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Gasulette 2.7KG', NULL, '2.7', 'kg', 0, 290.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

('Town Gaz (TG) 5KG', NULL, '5', 'kg', 0, 445.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Shine 5KG', NULL, '5', 'kg', 0, 360.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

('Town Gaz (TG) 11KG', NULL, '11', 'kg', 0, 865.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Shine 11KG', NULL, '11', 'kg', 0, 865.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Gasul 11KG', NULL, '11', 'kg', 0, 1065.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Solane 11KG', NULL, '11', 'kg', 0, 1110.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Town Gaz (TG) Snap-On 11KG', NULL, '11', 'kg', 0, 915.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Town Gaz (TG) Compact 11KG', NULL, '11', 'kg', 0, 915.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),
('Elite 11KG', NULL, '11', 'kg', 0, 1145.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

('MTG 22KG', NULL, '22', 'kg', 0, 1770.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

('LTG 50KG', NULL, '50', 'kg', 0, 3400.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

('Solane 1.4KG', NULL, '1.4', 'kg', 0, 175.00, 'active', '', (SELECT category_id FROM categories WHERE name='Cylinders')),

-- Brand New (cylinders with content)
('Brand New Cylinder 2.7KG', NULL, '2.7', 'kg', 0, 850.00, 'active', '', (SELECT category_id FROM categories WHERE name='Brand New Cylinder')),
('Brand New Cylinder 5KG', NULL, '5', 'kg', 0, 1600.00, 'active', '', (SELECT category_id FROM categories WHERE name='Brand New Cylinder')),
('Brand New Cylinder 11KG', NULL, '11', 'kg', 0, 2600.00, 'active', '', (SELECT category_id FROM categories WHERE name='Brand New Cylinder')),

-- Accessories
('Gasket', NULL, '', '', 0, 10.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),
('Butterfly', NULL, '', '', 0, 30.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),
('Pihitan', NULL, '', '', 0, 30.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),
('Nipple', NULL, '', '', 0, 50.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),
('SK Controller (Super Kalan)', NULL, '', '', 0, 250.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),
('LPG Hose Clamp', NULL, '', '', 0, 20.00, 'active', '', (SELECT category_id FROM categories WHERE name='Accessories')),

-- Burners
('Single Burner', NULL, '', '', 0, 450.00, 'active', '', (SELECT category_id FROM categories WHERE name='Burners')),
('Single Burner Outlet', NULL, '', '', 0, 400.00, 'active', '', (SELECT category_id FROM categories WHERE name='Burners')),
('Single Burner (Heavy)', NULL, '', '', 0, 650.00, 'active', '', (SELECT category_id FROM categories WHERE name='Burners')),
('Double Burner', NULL, '', '', 0, 950.00, 'active', '', (SELECT category_id FROM categories WHERE name='Burners')),

-- Hoses
('LPG Hose Big (1.8)', NULL, '', '', 0, 250.00, 'active', '', (SELECT category_id FROM categories WHERE name='Hoses')),
('LPG Hose Small (1.2)', NULL, '', '', 0, 220.00, 'active', '', (SELECT category_id FROM categories WHERE name='Hoses')),

-- Regulators
('LPG Regulator Small (POL valve)', NULL, '', '', 0, 380.00, 'active', '', (SELECT category_id FROM categories WHERE name='Regulators')),
('LPG Regulator Big (POL valve)', NULL, '', '', 0, 920.00, 'active', '', (SELECT category_id FROM categories WHERE name='Regulators')),
('Gasul (Snap-On)', NULL, '', '', 0, 680.00, 'active', '', (SELECT category_id FROM categories WHERE name='Regulators')),
('Solane (Snap-On)', NULL, '', '', 0, 680.00, 'active', '', (SELECT category_id FROM categories WHERE name='Regulators')),

-- Brass Caps
('Brass Cap Small', NULL, '', '', 0, 140.00, 'active', '', (SELECT category_id FROM categories WHERE name='Brass Caps')),
('Brass Cap Big', NULL, '', '', 0, 180.00, 'active', '', (SELECT category_id FROM categories WHERE name='Brass Caps'));
