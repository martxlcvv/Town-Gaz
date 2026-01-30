-- Update PIN to 123456 for all admin users
-- Run this in your phpMyAdmin or MySQL client

-- First, let's generate the proper hash for PIN 123456
-- Using PHP's password_hash with BCRYPT algorithm

UPDATE `admin_pins` SET `pin_hash` = '$2y$10$Zx5dMk8.h5.9Q7Zx5dMk8uJ8nLqK9pM7nK4L3m2O1r0S9t8v7w6x' WHERE `user_id` = 1;
UPDATE `admin_pins` SET `pin_hash` = '$2y$10$Zx5dMk8.h5.9Q7Zx5dMk8uJ8nLqK9pM7nK4L3m2O1r0S9t8v7w6x' WHERE `user_id` = 2;
UPDATE `admin_pins` SET `pin_hash` = '$2y$10$Zx5dMk8.h5.9Q7Zx5dMk8uJ8nLqK9pM7nK4L3m2O1r0S9t8v7w6x' WHERE `user_id` = 5;
UPDATE `admin_pins` SET `pin_hash` = '$2y$10$Zx5dMk8.h5.9Q7Zx5dMk8uJ8nLqK9pM7nK4L3m2O1r0S9t8v7w6x' WHERE `user_id` = 6;

-- Note: The hash above is a placeholder. To get the correct hash, 
-- you need to set it through the web interface or generate it with PHP.
