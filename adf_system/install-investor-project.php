<?php
/**
 * Investor & Project Module Installation Script
 * Run this script once to initialize the database
 */

// Define APP_ACCESS before anything else
define('APP_ACCESS', true);

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Check login only - no permission check for install
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// No permission check - any logged in user can install

// Display form if not POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Install Investor & Project Module</title>
        <style>
            body { font-family: sans-serif; padding: 2rem; max-width: 600px; margin: 0 auto; }
            h1 { color: #667eea; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
            .btn { display: inline-block; padding: 0.75rem 2rem; background: #667eea; color: white; text-decoration: none; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; }
            .btn:hover { background: #5568d3; }
        </style>
    </head>
    <body>
        <h1>🔧 Install Investor & Project Module</h1>
        <div class="warning">
            <strong>⚠️ Perhatian:</strong>
            <p>Script ini akan membuat tables berikut:</p>
            <ul>
                <li><code>investors</code> - Data investor</li>
                <li><code>investor_balances</code> - Balance per investor</li>
                <li><code>investor_transactions</code> - History transaksi</li>
                <li><code>projects</code> - Data project</li>
                <li><code>project_expenses</code> - Pengeluaran project</li>
                <li><code>project_expense_categories</code> - Kategori pengeluaran</li>
            </ul>
            <p>Jika table sudah ada, akan di-skip.</p>
        </div>
        <form method="POST">
            <button type="submit" class="btn">🚀 Install Sekarang</button>
        </form>
        <p><a href="<?php echo BASE_URL; ?>">← Kembali ke Dashboard</a></p>
    </body>
    </html>
    <?php
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Installing Investor & Project Module</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto; }
        h1 { color: #667eea; }
        .success { color: green; }
        .error { color: red; }
        .info { color: #666; }
    </style>
</head>
<body>
<?php

try {
    $db = Database::getInstance()->getConnection();
    
    echo "<h1>🔧 Installing Investor & Project Modules...</h1>";
    echo "<p>This may take a few moments...</p>";
    echo "<hr>";

    // Read migration file
    $migration_file = __DIR__ . '/database/migration-investor-project.sql';
    echo "<p class='info'>Looking for migration file at: $migration_file</p>";
    
    if (!file_exists($migration_file)) {
        throw new Exception('Migration file not found: ' . $migration_file);
    }
    
    echo "<p class='success'>✓ Migration file found!</p>";

    $sql = file_get_contents($migration_file);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($s) { return !empty($s) && !preg_match('/^--/', $s); }
    );

    $executed = 0;
    foreach ($statements as $statement) {
        if (strpos($statement, 'INSERT') !== false || strpos($statement, 'CREATE') !== false) {
            echo "<p>✓ Executing: " . substr($statement, 0, 60) . "...</p>";
            $db->exec($statement);
            $executed++;
        }
    }

    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✅ Installation completed successfully!</p>";
    echo "<p>Executed " . $executed . " database statements</p>";
    echo "<p><a href='" . BASE_URL . "/modules/investor/index.php'>Go to Investor Module</a></p>";
    echo "<p><a href='" . BASE_URL . "/modules/project/index.php'>Go to Project Module</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Installation Failed!</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
}
?>
</body>
</html>
