<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$pageTitle = 'FrontDesk Setup Instructions';
include 'includes/header.php';
?>

<style>
.setup-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.step-box {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.step-number {
    display: inline-block;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-right: 1rem;
}

.step-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.step-content {
    color: var(--text-secondary);
    line-height: 1.6;
}

.code-block {
    background: rgba(0, 0, 0, 0.3);
    padding: 1rem;
    border-radius: 8px;
    font-family: monospace;
    font-size: 0.9rem;
    overflow-x: auto;
    margin: 1rem 0;
    color: #10b981;
}

.btn-large {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 1rem 2rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
}

.success-box {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid #10b981;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.success-box h3 {
    color: #10b981;
    margin: 0 0 0.5rem 0;
}

.success-box p {
    color: var(--text-secondary);
    margin: 0;
}
</style>

<div class="setup-container">
    <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem;">
        🔧 FrontDesk Menu Setup
    </h1>
    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
        Panduan lengkap untuk setup dan menggunakan FrontDesk Settings menu
    </p>

    <!-- Step 1 -->
    <div class="step-box">
        <div style="display: flex; align-items: flex-start;">
            <div class="step-number">1</div>
            <div style="flex: 1;">
                <div class="step-title">Akses FrontDesk Menu</div>
                <div class="step-content">
                    <p>Dari sidebar menu utama:</p>
                    <ol style="margin: 0.5rem 0;">
                        <li>Login ke sistem dengan akun admin/manager</li>
                        <li>Cari menu <strong>"Front Desk"</strong> di sidebar sebelah kiri</li>
                        <li>Hover atau klik pada menu tersebut</li>
                        <li>Dropdown submenu akan muncul dengan pilihan:
                            <ul style="margin: 0.5rem 0;">
                                <li>📊 Dashboard</li>
                                <li>📅 Reservasi</li>
                                <li>📆 Calendar View</li>
                                <li>🍽️ Breakfast Order</li>
                                <li><strong>⚙️ Pengaturan</strong> ← Klik ini</li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="step-box">
        <div style="display: flex; align-items: flex-start;">
            <div class="step-number">2</div>
            <div style="flex: 1;">
                <div class="step-title">Klik Menu Pengaturan</div>
                <div class="step-content">
                    <p>Setelah dropdown muncul, klik menu <strong>"⚙️ Pengaturan"</strong></p>
                    <p>Sistem akan membuka halaman settings FrontDesk</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3 - Setup Database -->
    <div class="step-box" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1)); border-color: rgba(245, 158, 11, 0.3);">
        <div style="display: flex; align-items: flex-start;">
            <div class="step-number" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">3</div>
            <div style="flex: 1;">
                <div class="step-title">Setup Database (Jika Diperlukan)</div>
                <div class="step-content">
                    <p>Jika Anda melihat pesan:</p>
                    <div class="code-block">
                        ⚠️ Database Setup Required<br>
                        FrontDesk tables belum diinisialisasi
                    </div>
                    <p><strong>Langkah yang harus dilakukan:</strong></p>
                    <ol style="margin: 0.5rem 0;">
                        <li>Klik tombol biru <strong>"🔧 Setup Database Now"</strong></li>
                        <li>Tunggu proses setup selesai</li>
                        <li>Akan menampilkan:
                            <div class="code-block">
                                ✓ Table: room_types<br>
                                ✓ Table: rooms<br>
                                ✓ Table: guests<br>
                                ✓ Table: bookings<br>
                                ✓ Table: booking_payments<br>
                                <br>
                                ✅ All tables created successfully!
                            </div>
                        </li>
                        <li>Klik link <strong>"Go to FrontDesk Settings"</strong></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 4 -->
    <div class="step-box">
        <div style="display: flex; align-items: flex-start;">
            <div class="step-number">4</div>
            <div style="flex: 1;">
                <div class="step-title">Gunakan Settings Page</div>
                <div class="step-content">
                    <p>Sekarang Anda dapat menggunakan FrontDesk Settings untuk:</p>
                    <ul style="margin: 0.5rem 0;">
                        <li><strong>🚪 Manage Rooms</strong> - Tambah/edit/hapus kamar</li>
                        <li><strong>🏢 Room Types</strong> - Kelola tipe-tipe kamar</li>
                        <li><strong>💰 OTA Fees</strong> - Atur komisi OTA (Agoda, Booking.com, dll)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div class="success-box">
        <h3 style="font-size: 1.25rem;">✅ Setup Selesai!</h3>
        <p>Menu FrontDesk Settings sekarang siap digunakan</p>
    </div>

    <!-- Quick Links -->
    <div style="background: rgba(99, 102, 241, 0.05); border: 2px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 1.5rem;">
        <h3 style="color: var(--text-primary); margin: 0 0 1rem 0;">⚡ Quick Links</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/index.php" class="btn-large" style="text-align: center;">
                🏁 FrontDesk Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/settings.php" class="btn-large" style="text-align: center;">
                ⚙️ Settings Page
            </a>
            <a href="<?php echo BASE_URL; ?>/setup-frontdesk-tables.php" class="btn-large" style="text-align: center;">
                🔧 Setup Database
            </a>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(239, 68, 68, 0.05); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2);">
        <h3 style="color: #ef4444; margin-top: 0;">🆘 Troubleshooting</h3>
        
        <div style="margin-bottom: 1rem;">
            <strong style="color: var(--text-primary);">❓ Settings masih redirect ke dashboard?</strong>
            <ul style="margin: 0.5rem 0; font-size: 0.9rem; color: var(--text-secondary);">
                <li>Pastikan sudah login dengan akun <strong>admin</strong> atau <strong>manager</strong></li>
                <li>Clear browser cache (Ctrl+Shift+Delete)</li>
                <li>Coba akses langsung: <code style="background: rgba(0,0,0,0.1); padding: 0.25rem 0.5rem; border-radius: 4px;">/modules/frontdesk/settings.php</code></li>
            </ul>
        </div>

        <div style="margin-bottom: 1rem;">
            <strong style="color: var(--text-primary);">❓ Setup database gagal?</strong>
            <ul style="margin: 0.5rem 0; font-size: 0.9rem; color: var(--text-secondary);">
                <li>Pastikan <strong>MySQL running</strong></li>
                <li>Check database connection di config</li>
                <li>Coba setup ulang: <a href="<?php echo BASE_URL; ?>/setup-frontdesk-tables.php" style="color: #6366f1; text-decoration: underline;">setup-frontdesk-tables.php</a></li>
            </ul>
        </div>

        <div>
            <strong style="color: var(--text-primary);">❓ Tabel tidak muncul setelah setup?</strong>
            <ul style="margin: 0.5rem 0; font-size: 0.9rem; color: var(--text-secondary);">
                <li>Refresh halaman (F5)</li>
                <li>Check MySQL console untuk error log</li>
                <li>Verifikasi database permissions</li>
            </ul>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
