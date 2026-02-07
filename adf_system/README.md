# Narayana Hotel Management System

Sistem Management Hotel dengan fokus Accounting yang modern dan mobile-friendly.

## 🚀 Features

### ✅ Sudah Dibuat (Fase 1 Complete!)

1. **Database Structure**
   - Tabel lengkap: users, divisions, categories, cash_book, cash_balance
   - Sample data untuk testing
   - Views untuk reporting

2. **Authentication System**
   - Login/Logout dengan session management
   - Password hashing (bcrypt)
   - Role-based access (admin, manager, accountant, staff)
   - Demo credentials: username `admin`, password `password`

3. **Dashboard**
   - Real-time monitoring
   - Summary cards (hari ini, bulan ini, tahun ini)
   - Top 5 divisi
   - Transaksi terakhir
   - Quick actions

4. **Buku Kas Besar**
   - List transaksi dengan filtering
   - Tambah transaksi baru
   - Filter by date, type, division
   - Summary income, expense, balance

5. **Modern UI/UX**
   - Design elegant 2028 vibe
   - Responsive & mobile-friendly
   - Dark theme with gradient accents
   - Smooth animations & transitions

### 🔜 Akan Dibuat (Fase 2)

6. **Edit & Delete Transactions**
7. **Reporting System**
   - Laporan per divisi
   - Laporan harian, mingguan, bulanan, tahunan
   - Export PDF & Excel
8. **User Management** (Admin)
9. **Settings & Configuration**

## 📦 Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP 7.4+)
- Web Browser (Chrome, Firefox, Safari, Edge)

### Setup Steps

1. **Copy Project ke XAMPP**
   ```
   C:\xampp\htdocs\narayana
   ```

2. **Import Database**
   - Buka phpMyAdmin: http://localhost/phpmyadmin
   - Klik "New" untuk buat database baru
   - Import file: `database.sql`
   - Atau copy-paste SQL query dari file tersebut

3. **Konfigurasi Database** (Optional)
   Edit file `config/config.php` jika perlu:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'narayana_hotel');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Default kosong
   ```

4. **Start XAMPP**
   - Jalankan Apache
   - Jalankan MySQL

5. **Akses Aplikasi**
   ```
   http://localhost/narayana
   ```

## 🔐 Default Login

| Username | Password | Role    |
|----------|----------|---------|
| admin    | password | Admin   |
| manager  | password | Manager |

## 📱 Mobile Access

Untuk monitoring lewat HP:

1. **Pastikan HP dan PC di network yang sama (WiFi yang sama)**

2. **Cek IP Address PC**
   - Buka Command Prompt (CMD)
   - Ketik: `ipconfig`
   - Cari "IPv4 Address" (contoh: 192.168.1.5)

3. **Akses dari HP**
   ```
   http://192.168.1.5/narayana
   ```
   *(ganti dengan IP Address PC Anda)*

## 🏗️ Project Structure

```
narayana/
├── assets/
│   ├── css/
│   │   └── style.css          # Main CSS (Modern Design)
│   ├── js/
│   │   └── main.js            # JavaScript utilities
│   └── images/
├── config/
│   ├── config.php             # Configuration
│   └── database.php           # Database connection class
├── includes/
│   ├── auth.php               # Authentication class
│   ├── functions.php          # Helper functions
│   ├── header.php             # Layout header
│   └── footer.php             # Layout footer
├── modules/
│   ├── cashbook/
│   │   ├── index.php          # List transactions
│   │   ├── add.php            # Add transaction
│   │   ├── edit.php           # Edit transaction (coming soon)
│   │   ├── delete.php         # Delete transaction (coming soon)
│   │   └── get_categories.php # AJAX categories
│   ├── divisions/             # Per Division Analysis (coming soon)
│   └── reports/               # Reporting Module (coming soon)
├── database.sql               # Database structure & sample data
├── index.php                  # Dashboard
├── login.php                  # Login page
├── logout.php                 # Logout handler
└── README.md                  # This file
```

## 🎨 Design Features

- **Modern Color Palette**: Primary (#6366f1), Secondary (#8b5cf6), Accent (#ec4899)
- **Dark Theme**: Professional & elegant
- **Responsive Grid**: Auto-fit layout untuk berbagai ukuran layar
- **Smooth Animations**: Fade in, hover effects, transitions
- **Icon System**: Feather Icons (https://feathericons.com/)
- **Typography**: Inter font family

## 🔧 Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+ (OOP)
- **Database**: MySQL (with PDO)
- **Icons**: Feather Icons
- **Fonts**: Google Fonts (Inter)

## 📊 Database Schema

### Main Tables:
1. **users** - User accounts & authentication
2. **divisions** - Income/Expense divisions (Hotel, Resto, Motor, etc.)
3. **categories** - Transaction categories per division
4. **cash_book** - Main transaction ledger (Buku Kas Besar)
5. **cash_balance** - Daily balance tracking

## 🎯 Next Steps

Untuk melanjutkan development:

1. ✅ Setup database
2. ✅ Login ke system
3. ✅ Explore dashboard
4. ✅ Coba tambah transaksi
5. 🔜 Implement edit/delete
6. 🔜 Build reporting module
7. 🔜 Add export PDF/Excel

## 🤝 Support

Jika ada pertanyaan atau butuh bantuan:
1. Check dokumentasi di README ini
2. Review code comments di setiap file
3. Test dengan sample data yang sudah ada

## 📝 License

Private project - Narayana Hotel © 2026

---

**Built with ❤️ for modern hotel management**
