-- Fix inventory table - add missing columns if they don't exist
-- This script will add current_stock and closing_stock columns to the inventory table

ALTER TABLE inventory ADD COLUMN IF NOT EXISTS current_stock INT(11) DEFAULT 0 AFTER opening_stock;
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS closing_stock INT(11) DEFAULT 0 AFTER current_stock;

-- Update any existing inventory records
UPDATE inventory SET current_stock = opening_stock WHERE current_stock = 0;
UPDATE inventory SET closing_stock = current_stock WHERE closing_stock = 0;
