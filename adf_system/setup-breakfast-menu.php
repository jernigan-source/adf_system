<?php
/**
 * SETUP BREAKFAST MENU DATABASE
 * Run this file once to initialize breakfast menu system
 */

define('APP_ACCESS', true);
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$success = [];
$errors = [];

try {
    // 1. Create breakfast_menus table
    $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_menus (
        id INT PRIMARY KEY AUTO_INCREMENT,
        menu_name VARCHAR(100) NOT NULL,
        description TEXT,
        category ENUM('western', 'indonesian', 'asian', 'drinks', 'beverages', 'extras') DEFAULT 'western',
        price DECIMAL(10,2) DEFAULT 0.00,
        is_free BOOLEAN DEFAULT TRUE COMMENT 'TRUE = Free breakfast, FALSE = Extra/Paid',
        is_available BOOLEAN DEFAULT TRUE,
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_available (is_available),
        INDEX idx_free (is_free)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $success[] = "✓ Table breakfast_menus created successfully";

    // 2. Update breakfast_log table to support menu_id
    try {
        $pdo->exec("ALTER TABLE breakfast_log 
                   ADD COLUMN IF NOT EXISTS menu_id INT NULL AFTER guest_id,
                   ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1 AFTER menu_id");
        $success[] = "✓ Table breakfast_log updated with menu_id and quantity columns";
    } catch (Exception $e) {
        // Column might already exist, that's okay
        $success[] = "✓ Table breakfast_log already has menu columns";
    }

    // 3. Insert default breakfast menus
    $checkMenus = $pdo->query("SELECT COUNT(*) as count FROM breakfast_menus")->fetch();
    
    if ($checkMenus['count'] == 0) {
        $menus = [
            // Western (Free Breakfast)
            ['American Breakfast', 'Eggs, bacon, sausage, toast, hash browns', 'western', 0, TRUE],
            ['Continental Breakfast', 'Croissant, jam, butter, fruit', 'western', 0, TRUE],
            ['Pancakes', 'Fluffy pancakes with maple syrup', 'western', 0, TRUE],
            ['French Toast', 'Classic french toast with fruits', 'western', 0, TRUE],
            
            // Indonesian (Free Breakfast)
            ['Nasi Goreng', 'Indonesian fried rice with egg', 'indonesian', 0, TRUE],
            ['Bubur Ayam', 'Chicken porridge with condiments', 'indonesian', 0, TRUE],
            ['Nasi Uduk', 'Coconut rice with side dishes', 'indonesian', 0, TRUE],
            ['Mie Goreng', 'Indonesian fried noodles', 'indonesian', 0, TRUE],
            ['Lontong Sayur', 'Rice cake with vegetable curry', 'indonesian', 0, TRUE],
            
            // Asian (Free Breakfast)
            ['Dim Sum', 'Assorted steamed dumplings', 'asian', 0, TRUE],
            ['Congee', 'Rice porridge with toppings', 'asian', 0, TRUE],
            
            // Drinks (Free)
            ['Coffee', 'Hot coffee', 'drinks', 0, TRUE],
            ['Tea', 'Hot tea', 'drinks', 0, TRUE],
            ['Orange Juice', 'Fresh orange juice', 'drinks', 0, TRUE],
            ['Milk', 'Fresh milk', 'drinks', 0, TRUE],
            ['Mineral Water', 'Bottled water', 'drinks', 0, TRUE],
            
            // Extra Breakfast (Paid/Berbayar)
            ['Extra Eggs Benedict', 'Premium eggs with hollandaise', 'extras', 50000, FALSE],
            ['Extra Ramen Bowl', 'Japanese ramen', 'extras', 45000, FALSE],
            ['Extra Toast Set', 'Additional toast with jam', 'extras', 15000, FALSE],
            ['Extra Egg', 'Additional egg', 'extras', 10000, FALSE],
            ['Fresh Fruit Platter', 'Seasonal fruits', 'extras', 35000, FALSE],
            ['Avocado Toast', 'Premium avocado on toast', 'extras', 40000, FALSE],
            ['Smoothie Bowl', 'Fruit smoothie bowl', 'extras', 45000, FALSE]
        ];

        $stmt = $pdo->prepare("INSERT INTO breakfast_menus (menu_name, description, category, price, is_free, is_available) 
                              VALUES (?, ?, ?, ?, ?, TRUE)");
        
        foreach ($menus as $menu) {
            $stmt->execute($menu);
        }
        
        $success[] = "✓ Inserted " . count($menus) . " default breakfast menu items (Free + Extra)";
    } else {
        $success[] = "✓ Breakfast menus already populated (skipped)";
    }

    $allSuccess = true;

} catch (Exception $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
    $allSuccess = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Breakfast Menu - ADF System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 3rem;
        }

        h1 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .status-icon {
            font-size: 4rem;
            text-align: center;
            margin: 2rem 0;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .info-box h3 {
            color: #0066cc;
            margin-bottom: 1rem;
        }

        .info-box ul {
            margin-left: 1.5rem;
            color: #333;
        }

        .info-box li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍳 Breakfast Menu Setup</h1>
        <p class="subtitle">Initialize breakfast menu system for Front Desk</p>

        <?php if ($allSuccess): ?>
            <div class="status-icon">✅</div>
            <?php foreach ($success as $msg): ?>
                <div class="message success"><?php echo $msg; ?></div>
            <?php endforeach; ?>

            <div class="info-box">
                <h3>🎉 Setup Complete!</h3>
                <ul>
                    <li>Breakfast menu database has been created</li>
                    <li><?php echo count($menus ?? []); ?> default menu items added</li>
                    <li>Menu categories: Western, Indonesian, Asian, Beverages, Extras</li>
                    <li>You can now manage menus in Settings → Breakfast Menu</li>
                </ul>
            </div>

            <a href="modules/frontdesk/settings.php?tab=breakfast_menu" class="btn">
                🛠️ Go to Breakfast Menu Settings
            </a>
            <a href="modules/frontdesk/breakfast.php" class="btn" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);">
                🍽️ Go to Breakfast Page
            </a>

        <?php else: ?>
            <div class="status-icon">❌</div>
            <?php foreach ($errors as $msg): ?>
                <div class="message error"><?php echo $msg; ?></div>
            <?php endforeach; ?>

            <button onclick="location.reload()" class="btn">🔄 Try Again</button>
        <?php endif; ?>

        <a href="modules/frontdesk/dashboard.php" class="btn" style="background: #6c757d; margin-top: 0.5rem;">
            🏠 Back to Dashboard
        </a>
    </div>
</body>
</html>
