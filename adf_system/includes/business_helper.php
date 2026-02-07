<?php
/**
 * Business Helper Functions
 * Manage active business per user session
 */

/**
 * Get list of all available businesses
 * @return array Array of business configurations
 */
function getAvailableBusinesses() {
    $businessesPath = __DIR__ . '/../config/businesses/';
    $businesses = [];
    
    if (is_dir($businessesPath)) {
        $files = scandir($businessesPath);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $businessId = pathinfo($file, PATHINFO_FILENAME);
                $config = require $businessesPath . $file;
                // Add ID to config
                $config['id'] = $businessId;
                $businesses[$businessId] = $config;
            }
        }
    }
    
    // Sort by name
    uasort($businesses, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $businesses;
}

/**
 * Get active business ID from session
 * @return string Business ID
 */
function getActiveBusinessId() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        session_start();
    }
    
    // Check if business is set in session
    if (isset($_SESSION['active_business_id'])) {
        return $_SESSION['active_business_id'];
    }
    
    // Default to first available business (for backward compatibility)
    $businesses = getAvailableBusinesses();
    if (!empty($businesses)) {
        $firstBusinessId = array_key_first($businesses);
        setActiveBusinessId($firstBusinessId);
        return $firstBusinessId;
    }
    
    // Fallback
    return 'narayana-hotel';
}

/**
 * Set active business ID in session
 * @param string $businessId Business ID to set as active
 * @return bool Success
 */
function setActiveBusinessId($businessId) {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        session_start();
    }
    
    // Validate business exists
    $businessFile = __DIR__ . '/../config/businesses/' . $businessId . '.php';
    if (file_exists($businessFile)) {
        $_SESSION['active_business_id'] = $businessId;
        return true;
    }
    
    return false;
}

/**
 * Get active business configuration
 * @return array Business configuration array
 */
function getActiveBusinessConfig() {
    $businessId = getActiveBusinessId();
    $businessFile = __DIR__ . '/../config/businesses/' . $businessId . '.php';
    
    if (file_exists($businessFile)) {
        return require $businessFile;
    }
    
    // Return default/empty config if file not found
    return [
        'business_id' => $businessId,
        'name' => 'Unknown Business',
        'business_type' => 'general',
        'theme' => [
            'color_primary' => '#4338ca',
            'color_secondary' => '#818cf8',
            'icon' => '🏢'
        ]
    ];
}

/**
 * Check if user has access to specific business
 * @param string $businessId Business ID to check
 * @param object $user User object (optional, uses current user if not provided)
 * @return bool True if user has access
 */
function userHasBusinessAccess($businessId, $user = null) {
    // For now, all authenticated users can access all businesses
    // Later you can add business-specific permissions in users table
    return true;
}

/**
 * Get business name with icon
 * @param string $businessId Business ID (optional, uses active business if not provided)
 * @return string Formatted business name with icon
 */
function getBusinessDisplayName($businessId = null) {
    if ($businessId === null) {
        $config = getActiveBusinessConfig();
    } else {
        $businessFile = __DIR__ . '/../config/businesses/' . $businessId . '.php';
        if (file_exists($businessFile)) {
            $config = require $businessFile;
        } else {
            return 'Unknown Business';
        }
    }
    
    return $config['theme']['icon'] . ' ' . $config['name'];
}

/**
 * Get business logo path
 * @return string|null Logo URL or null
 */
function getBusinessLogo() {
    $config = getActiveBusinessConfig();
    $businessId = $config['business_id'];
    
    try {
        $db = Database::getInstance();
        
        // Priority 1: Business-specific logo from settings table (new format: company_logo_businessid)
        $businessLogo = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = :key",
            ['key' => 'company_logo_' . $businessId]
        );
        
        if ($businessLogo && $businessLogo['setting_value']) {
            $logoPath = __DIR__ . '/../uploads/logos/' . $businessLogo['setting_value'];
            if (file_exists($logoPath)) {
                $timestamp = filemtime($logoPath);
                return BASE_URL . '/uploads/logos/' . $businessLogo['setting_value'] . '?v=' . $timestamp;
            }
        }
        
        // Priority 2: Custom logo from config
        if (!empty($config['logo'])) {
            $logoPath = __DIR__ . '/../uploads/logos/' . $config['logo'];
            if (file_exists($logoPath)) {
                $timestamp = filemtime($logoPath);
                return BASE_URL . '/uploads/logos/' . $config['logo'] . '?v=' . $timestamp;
            }
        }
        
        // Priority 3: Business-specific logo file (business-id_logo.png or .jpg)
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            $defaultLogo = __DIR__ . '/../uploads/logos/' . $businessId . '_logo.' . $ext;
            if (file_exists($defaultLogo)) {
                $timestamp = filemtime($defaultLogo);
                return BASE_URL . '/uploads/logos/' . $businessId . '_logo.' . $ext . '?v=' . $timestamp;
            }
        }
        
        // Priority 4: Global company logo from settings (fallback)
        $globalLogo = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'company_logo' LIMIT 1"
        );
        if ($globalLogo && $globalLogo['setting_value']) {
            $settingsLogo = __DIR__ . '/../uploads/logos/' . $globalLogo['setting_value'];
            if (file_exists($settingsLogo)) {
                $timestamp = filemtime($settingsLogo);
                return BASE_URL . '/uploads/logos/' . $globalLogo['setting_value'] . '?v=' . $timestamp;
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    return null;
}

/**
 * Get business theme CSS variables
 * @return string CSS variables
 */
function getBusinessThemeCSS() {
    $config = getActiveBusinessConfig();
    $theme = $config['theme'] ?? [];
    
    $primary = $theme['color_primary'] ?? '#4338ca';
    $secondary = $theme['color_secondary'] ?? '#3730a3';
    
    return ":root {
        --business-primary: {$primary};
        --business-secondary: {$secondary};
        --accent-primary: {$primary};
        --accent-secondary: {$secondary};
    }";
}

/**
 * Check if module is enabled for current business
 * @param string $module Module name
 * @return bool
 */
function isModuleEnabled($module) {
    $config = getActiveBusinessConfig();
    $enabledModules = $config['enabled_modules'] ?? [];
    return in_array($module, $enabledModules);
}

/**
 * Get business-specific view file path
 * Falls back to default view if business-specific view doesn't exist
 * 
 * @param string $module Module name (e.g., 'cashbook', 'dashboard')
 * @param string $view View name (e.g., 'index', 'add', 'edit')
 * @return string|null View file path or null if not found
 */
function getBusinessView($module, $view) {
    $config = getActiveBusinessConfig();
    $businessType = $config['business_type'];
    $businessId = $config['business_id'];
    
    // Priority 1: Business-specific view (by ID)
    $businessSpecificView = __DIR__ . "/../modules/{$module}/views/{$businessId}/{$view}.php";
    if (file_exists($businessSpecificView)) {
        return $businessSpecificView;
    }
    
    // Priority 2: Business type view (shared by type)
    $typeView = __DIR__ . "/../modules/{$module}/views/{$businessType}/{$view}.php";
    if (file_exists($typeView)) {
        return $typeView;
    }
    
    // Priority 3: Default view
    $defaultView = __DIR__ . "/../modules/{$module}/{$view}.php";
    if (file_exists($defaultView)) {
        return $defaultView;
    }
    
    return null;
}

/**
 * Get cashbook columns for current business
 * @return array Business-specific cashbook columns
 */
function getBusinessCashbookColumns() {
    $config = getActiveBusinessConfig();
    return $config['cashbook_columns'] ?? [];
}

/**
 * Get business terminology
 * @param string $key Terminology key
 * @return string Translated term
 */
function getBusinessTerm($key) {
    $config = getActiveBusinessConfig();
    $terminology = $config['terminology'] ?? [];
    
    return $terminology[$key] ?? ucfirst($key);
}
