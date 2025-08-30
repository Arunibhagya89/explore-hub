<?php
// modules/auth/logout.php - User Logout

session_start();
require_once '../../config/config.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    
    $log_sql = "INSERT INTO activity_logs (user_id, action, module, ip_address) 
                VALUES (?, 'logout', 'auth', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_stmt->bind_param("is", $_SESSION['user_id'], $ip_address);
    $log_stmt->execute();
    
    $conn->close();
}

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit();
?>