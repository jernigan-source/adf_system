<?php
// FORCE LOGOUT - Clear all sessions
session_start();
$_SESSION = array(); // Clear all session data
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Session Cleared</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .box {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
        }
        h1 { color: #2d3748; margin: 0 0 1rem; font-size: 1.5rem; }
        p { color: #718096; margin: 0 0 2rem; }
        a { 
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        a:hover { background: #5568d3; transform: translateY(-2px); }
    </style>
    <meta http-equiv="refresh" content="2;url=login.php">
</head>
<body>
    <div class="box">
        <h1>✓ Session Cleared</h1>
        <p>Semua session telah dihapus. Halaman akan redirect ke login dalam 2 detik...</p>
        <a href="login.php">Login Sekarang</a>
    </div>
</body>
</html>
