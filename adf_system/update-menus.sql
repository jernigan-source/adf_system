-- Update Menu Items untuk Match dengan Real System
-- Narayana Hotel Menu Structure

USE adf_system;

-- Backup dan hapus relasi dulu
DELETE FROM business_menu_config;
DELETE FROM user_menu_permissions;
DELETE FROM menu_items;

-- Insert menu yang sesuai dengan sistem real
INSERT INTO menu_items (id, menu_code, menu_name, menu_icon, menu_url, menu_order, is_active) VALUES
(1, 'dashboard', 'Dashboard', 'speedometer2', 'index.php', 1, 1),
(2, 'cashbook', 'Buku Kas Besar', 'journal-text', 'modules/cashbook/', 2, 1),
(3, 'divisions', 'Kelola Divisi', 'building', 'modules/divisions/', 3, 1),
(4, 'frontdesk', 'Frontdesk', 'door-open', 'modules/frontdesk/', 4, 1),
(5, 'sales_invoice', 'Sales Invoice', 'file-text', 'modules/sales/', 5, 1),
(6, 'procurement', 'PO & SHOOP', 'shopping-cart', 'modules/procurement/', 6, 1),
(7, 'reports', 'Reports', 'graph-up', 'modules/reports/', 7, 1),
(8, 'investor', 'Investor', 'currency-dollar', 'modules/investor/', 8, 1),
(9, 'project', 'Project', 'briefcase', 'modules/project/', 9, 1),
(10, 'settings', 'Pengaturan', 'gear', 'modules/settings/', 10, 1);

-- Re-assign semua menu ke Narayana Hotel dan Ben's Cafe
INSERT INTO business_menu_config (business_id, menu_id, is_enabled) 
SELECT b.id, m.id, 1
FROM businesses b
CROSS JOIN menu_items m
WHERE b.id IN (1, 2);

-- Re-assign permissions untuk user busita (owner) ke semua menu di kedua bisnis
INSERT INTO user_menu_permissions (user_id, business_id, menu_id, can_view, can_create, can_update, can_delete)
SELECT 2, b.id, m.id, 1, 1, 1, 1
FROM businesses b
CROSS JOIN menu_items m
WHERE b.id IN (1, 2);

SELECT 'Menu items updated successfully!' as status;
SELECT * FROM menu_items ORDER BY menu_order;
