-- ============================================
-- SETTINGS TABLE (Company Settings & Configuration)
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'textarea', 'file', 'boolean') DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default company settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'Narayana Hotel', 'text', 'Nama Perusahaan'),
('company_tagline', 'Hotel Management System', 'text', 'Tagline Perusahaan'),
('company_address', 'Jl. Raya Ubud No. 123, Gianyar, Bali 80571', 'textarea', 'Alamat Perusahaan'),
('company_phone', '+62 361 123456', 'text', 'Telepon Perusahaan'),
('company_email', 'info@narayanahotel.com', 'text', 'Email Perusahaan'),
('company_website', 'www.narayanahotel.com', 'text', 'Website Perusahaan'),
('company_logo', '', 'file', 'Logo Perusahaan'),
('report_show_logo', '1', 'boolean', 'Tampilkan Logo di Laporan'),
('report_show_address', '1', 'boolean', 'Tampilkan Alamat di Laporan'),
('report_show_phone', '1', 'boolean', 'Tampilkan Telepon di Laporan'),
('currency_symbol', 'Rp', 'text', 'Symbol Mata Uang'),
('currency_position', 'left', 'text', 'Posisi Symbol (left/right)'),
('date_format', 'd/m/Y', 'text', 'Format Tanggal'),
('timezone', 'Asia/Makassar', 'text', 'Timezone')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
