-- Rename database dari narayana_db ke adf_system
-- Execute this script in MySQL

-- Create new database with correct name
CREATE DATABASE IF NOT EXISTS adf_system;

-- Copy all tables from narayana_db to adf_system
USE narayana_db;

-- Get all tables and copy them
SET FOREIGN_KEY_CHECKS=0;

-- If you want to use MySQL command line, use this alternative approach:
-- mysqldump -u root narayana_db | mysql -u root adf_system
-- Then drop old database:
-- DROP DATABASE narayana_db;
