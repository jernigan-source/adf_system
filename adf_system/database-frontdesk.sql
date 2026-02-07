-- ============================================
-- FRONTDESK MODULE DATABASE SCHEMA
-- Konversi dari Python frontdesk.py ke MySQL
-- ============================================

-- 1. MASTER DATA KAMAR
CREATE TABLE IF NOT EXISTS fd_master_kamar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_posisi VARCHAR(20) UNIQUE NOT NULL COMMENT 'Format: GEDUNG_INDEX (Contoh: A_0)',
    nama_kamar VARCHAR(50) NOT NULL COMMENT 'Nama tampil di denah (Contoh: 101)',
    gedung VARCHAR(10) NOT NULL COMMENT 'Nama gedung (A, B, C, dll)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gedung (gedung),
    INDEX idx_nama (nama_kamar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. LAYOUT GEDUNG
CREATE TABLE IF NOT EXISTS fd_layout_gedung (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_index INT NOT NULL COMMENT 'Index urutan gedung (0, 1, 2, ...)',
    nama_gedung VARCHAR(10) NOT NULL UNIQUE COMMENT 'Nama gedung (A, B, C, dll)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default gedung
INSERT INTO fd_layout_gedung (block_index, nama_gedung) VALUES
(0, 'A'), (1, 'B'), (2, 'C')
ON DUPLICATE KEY UPDATE block_index=VALUES(block_index);

-- 3. RESERVASI
CREATE TABLE IF NOT EXISTS fd_reservasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_tamu VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20),
    kamar VARCHAR(50),
    checkin_date DATE NOT NULL,
    checkout_date DATE,
    status ENUM('pending', 'confirmed', 'checked_in', 'cancelled') DEFAULT 'pending',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_checkin (checkin_date),
    INDEX idx_kamar (kamar),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. IN-HOUSE (TAMU MENGINAP)
CREATE TABLE IF NOT EXISTS fd_inhouse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reg_id VARCHAR(50) UNIQUE NOT NULL COMMENT 'Format: REG-HHMMSS',
    nama_tamu VARCHAR(100) NOT NULL,
    kamar VARCHAR(50) NOT NULL,
    no_hp VARCHAR(20),
    harga_per_malam DECIMAL(12,2) DEFAULT 0,
    
    tgl_masuk DATETIME NOT NULL,
    tgl_keluar DATETIME NULL COMMENT 'Actual checkout time',
    rencana_keluar DATE NOT NULL COMMENT 'Planned checkout date',
    
    status ENUM('Active', 'CheckedOut') DEFAULT 'Active',
    total_tagihan DECIMAL(12,2) DEFAULT 0,
    
    -- Breakfast Order Fields
    bf_makanan TEXT COMMENT 'Menu makanan yang dipesan',
    bf_minuman TEXT COMMENT 'Menu minuman yang dipesan',
    bf_pax_makan INT DEFAULT 0,
    bf_pax_minum INT DEFAULT 0,
    bf_jam VARCHAR(10) COMMENT 'Jam sarapan (HH:MM)',
    bf_note TEXT COMMENT 'Catatan khusus',
    bf_lokasi VARCHAR(50) COMMENT 'Resto / Room Service',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_kamar (kamar),
    INDEX idx_status (status),
    INDEX idx_tgl_keluar (rencana_keluar),
    INDEX idx_nama (nama_tamu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. HOUSEKEEPING STATUS
CREATE TABLE IF NOT EXISTS fd_hk_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kamar VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('CLN', 'DTY') DEFAULT 'CLN' COMMENT 'CLN=Clean, DTY=Dirty',
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(50),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MASTER MENU BREAKFAST
CREATE TABLE IF NOT EXISTS fd_menu_breakfast (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    jenis ENUM('Makanan', 'Minuman', 'Lainnya') DEFAULT 'Makanan',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jenis (jenis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample menu
INSERT INTO fd_menu_breakfast (nama_menu, jenis) VALUES
('Nasi Goreng', 'Makanan'),
('Mie Goreng', 'Makanan'),
('American Breakfast', 'Makanan'),
('Roti Bakar + Telur', 'Makanan'),
('Pancake', 'Makanan'),
('Kopi', 'Minuman'),
('Teh', 'Minuman'),
('Jus Jeruk', 'Minuman'),
('Jus Mangga', 'Minuman'),
('Air Mineral', 'Minuman')
ON DUPLICATE KEY UPDATE nama_menu=VALUES(nama_menu);

-- 7. WARNA TEMA DENAH (CUSTOMIZABLE)
CREATE TABLE IF NOT EXISTS fd_color_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    color_key VARCHAR(50) UNIQUE NOT NULL,
    hex_value VARCHAR(7) NOT NULL DEFAULT '#000000',
    label VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default colors (sesuai Python script)
INSERT INTO fd_color_config (color_key, hex_value, label, description) VALUES
('HK_Clean_Fill', '#212121', 'Kamar Kosong (Fill)', 'Background kamar kosong bersih'),
('HK_Clean_Line', '#566573', 'Kamar Kosong (Garis)', 'Border kamar kosong'),
('HK_Dirty_Fill', '#6E2C00', 'Kamar Kotor (Fill)', 'Background kamar kotor'),
('HK_Dirty_Line', '#E67E22', 'Kamar Kotor (Garis)', 'Border kamar kotor'),
('OCC_Fill', '#0B5345', 'Tamu In-House (Fill)', 'Background kamar berisi tamu'),
('OCC_Line', '#2ECC71', 'Tamu In-House (Garis)', 'Border kamar occupied'),
('DUE_Fill', '#641E16', 'Due Out / Cek-Out (Fill)', 'Background kamar checkout hari ini'),
('DUE_Line', '#E74C3C', 'Due Out / Cek-Out (Garis)', 'Border due out'),
('ARR_Today_Fill', '#154360', 'Arrival Hari Ini (Fill)', 'Background arrival today'),
('ARR_Today_Line', '#3498DB', 'Arrival Hari Ini (Garis)', 'Border arrival today'),
('ARR_Tmr_Fill', '#1F618D', 'Arrival Besok (Fill)', 'Background arrival tomorrow'),
('ARR_Tmr_Line', '#85C1E9', 'Arrival Besok (Garis)', 'Border arrival tomorrow'),
('B2B_Line', '#D946EF', 'Border Estafet/B2B (Magenta)', 'Border untuk back-to-back booking'),
('B2B_Text', '#F0ABFC', 'Teks/Ikon Estafet (Pink)', 'Warna teks B2B indicator')
ON DUPLICATE KEY UPDATE hex_value=VALUES(hex_value);

-- 8. SAMPLE DATA KAMAR (4 KAMAR PER GEDUNG)
INSERT INTO fd_master_kamar (id_posisi, nama_kamar, gedung) VALUES
-- Gedung A
('A_0', '101', 'A'),
('A_1', '102', 'A'),
('A_2', '103', 'A'),
('A_3', '104', 'A'),
-- Gedung B
('B_0', '201', 'B'),
('B_1', '202', 'B'),
('B_2', '203', 'B'),
('B_3', '204', 'B'),
-- Gedung C
('C_0', '301', 'C'),
('C_1', '302', 'C'),
('C_2', '303', 'C'),
('C_3', '304', 'C')
ON DUPLICATE KEY UPDATE nama_kamar=VALUES(nama_kamar);
