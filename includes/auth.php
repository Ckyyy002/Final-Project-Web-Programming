<?php
// includes/auth.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Check authentication and redirect if not logged in
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL . 'auth/login.php');
    }
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Calculate derived fields
            $user['level'] = getUserLevel($user['xp_points']);
            $user['level_progress'] = getLevelProgress($user['xp_points']);
            $user['xp_for_next_level'] = xpForNextLevel($user['level'] - 1);
            $user['xp_needed'] = $user['xp_for_next_level'] - (($user['level'] - 1) * 1000);
            $user['current_xp_in_level'] = $user['xp_points'] - (($user['level'] - 1) * 1000);
        }
        
        return $user;
        
    } catch (PDOException $e) {
        error_log("Error getting user: " . $e->getMessage());
        return null;
    }
}

// Login user
function loginUser($userId, $remember = false) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Generate CSRF token
    generateCSRFToken();
    
    // If remember me is checked, set cookie
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + SESSION_LIFETIME;
        
        try {
            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::getConnection();
            
            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $token, date('Y-m-d H:i:s', $expires)]);
            
            setcookie('remember_token', $token, $expires, '/', '', true, true);
            
        } catch (PDOException $e) {
            error_log("Error setting remember token: " . $e->getMessage());
        }
    }
}

// Logout user
function logoutUser() {
    // Clear remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        try {
            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::getConnection();
            
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
            
        } catch (PDOException $e) {
            error_log("Error clearing remember token: " . $e->getMessage());
        }
        
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Clear session
    $_SESSION = [];
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}
// REMOVED: getPendingFriendRequestCount function
?>
