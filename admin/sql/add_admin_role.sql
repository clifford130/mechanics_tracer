-- Run this once to add the admin role to users.role
-- Execute in phpMyAdmin or: mysql -u root mechanic_tracer < add_admin_role.sql

ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('driver','mechanic','pending','admin') DEFAULT 'pending';
