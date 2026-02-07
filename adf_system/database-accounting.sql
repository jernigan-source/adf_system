-- ============================================
-- NARAYANA HOTEL MANAGEMENT SYSTEM
-- Database Schema & Sample Data
-- ============================================

-- 1. DIVISIONS TABLE
CREATE TABLE IF NOT EXISTS divisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    division_name VARCHAR(100) NOT NULL,
    division_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (division_code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Divisions
INSERT INTO divisions (division_name, division_code, description) VALUES
('Restaurant', 'RES', 'Restaurant & Dining Services'),
('Room Service', 'RS', 'In-Room Dining Service'),
('Front Office', 'FO', 'Reception & Guest Services'),
('Housekeeping', 'HK', 'Room Cleaning & Maintenance'),
('Laundry', 'LDY', 'Laundry Services'),
('Spa & Wellness', 'SPA', 'Spa & Massage Services'),
('Bar & Lounge', 'BAR', 'Bar & Beverage'),
('Banquet', 'BNQ', 'Event & Meeting Room'),
('Minibar', 'MB', 'In-Room Minibar Sales'),
('Transportation', 'TRANS', 'Airport Transfer & Car Rental')
ON DUPLICATE KEY UPDATE division_name=VALUES(division_name);

-- 2. CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    transaction_type ENUM('income', 'expense') NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (transaction_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Categories
INSERT INTO categories (category_name, transaction_type, description) VALUES
('Food Sales', 'income', 'Revenue from food sales'),
('Beverage Sales', 'income', 'Revenue from beverage sales'),
('Room Service Charges', 'income', 'Room service charges'),
('Service Charges', 'income', 'Additional service fees'),
('Laundry Income', 'income', 'Laundry service revenue'),
('Spa Treatment', 'income', 'Spa & massage revenue'),
('Transportation Fee', 'income', 'Airport transfer & car rental'),
('Minibar Sales', 'income', 'Minibar product sales'),
('Food Supplies', 'expense', 'Purchase of food ingredients'),
('Beverage Supplies', 'expense', 'Purchase of beverages & drinks'),
('Staff Salary', 'expense', 'Employee salaries & wages'),
('Utilities', 'expense', 'Electricity, water, internet'),
('Maintenance', 'expense', 'Equipment & facility maintenance'),
('Cleaning Supplies', 'expense', 'Cleaning materials & chemicals'),
('Laundry Supplies', 'expense', 'Detergent & laundry materials'),
('Transportation Cost', 'expense', 'Fuel & vehicle maintenance')
ON DUPLICATE KEY UPDATE category_name=VALUES(category_name);

-- 3. CASH BOOK TABLE
CREATE TABLE IF NOT EXISTS cash_book (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('income', 'expense') NOT NULL,
    division_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_time TIME NOT NULL,
    description TEXT,
    receipt_number VARCHAR(50),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (transaction_type),
    INDEX idx_date (transaction_date),
    INDEX idx_division (division_id),
    INDEX idx_category (category_id),
    FOREIGN KEY (division_id) REFERENCES divisions(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Transactions (akan diisi via PHP script)
