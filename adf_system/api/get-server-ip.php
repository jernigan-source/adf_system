<?php
/**
 * Get Server IP Address
 * Returns the server's local IP address for mobile access
 */
header('Content-Type: application/json');

function getServerIP() {
    // Try different methods to get server IP
    
    // Method 1: $_SERVER variables
    if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
        return $_SERVER['SERVER_ADDR'];
    }
    
    if (!empty($_SERVER['LOCAL_ADDR']) && $_SERVER['LOCAL_ADDR'] !== '127.0.0.1') {
        return $_SERVER['LOCAL_ADDR'];
    }
    
    // Method 2: gethostbyname (Windows)
    $hostname = gethostname();
    $ip = gethostbyname($hostname);
    if ($ip !== $hostname && $ip !== '127.0.0.1') {
        return $ip;
    }
    
    // Method 3: Network interfaces (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = shell_exec('ipconfig');
        if (preg_match('/IPv4 Address[^\:]*\:\s*([0-9\.]+)/', $output, $matches)) {
            return $matches[1];
        }
    } else {
        // Linux/Mac
        $output = shell_exec('hostname -I');
        if ($output) {
            $ips = explode(' ', trim($output));
            if (!empty($ips[0])) {
                return $ips[0];
            }
        }
    }
    
    // Fallback
    return '192.168.1.2';
}

try {
    $ip = getServerIP();
    $port = !empty($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '8080';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    
    echo json_encode([
        'success' => true,
        'ip' => $ip,
        'port' => $port,
        'protocol' => $protocol,
        'owner_login_url' => "{$protocol}://{$ip}:{$port}/narayana/owner-login.php",
        'owner_dashboard_url' => "{$protocol}://{$ip}:{$port}/narayana/modules/owner/dashboard.php",
        'hostname' => gethostname()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'ip' => '192.168.1.2' // fallback
    ]);
}
