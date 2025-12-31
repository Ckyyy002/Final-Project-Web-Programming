<?php
// includes/functions.php

// Sanitize input to prevent XSS
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect to a page
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Format date for display
function formatDate($date, $format = 'M d, Y') {
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'No date';
}

// Calculate XP needed for next level
function xpForNextLevel($currentLevel) {
    // Example: Level 1 needs 1000 XP to reach Level 2
    // Level 2 needs 2000 XP to reach Level 3
    return $currentLevel * 1000; 
}

// Get user level based on XP (Level 1 starts at 0 XP)
function getUserLevel($xp) {
    return floor($xp / 1000) + 1;
}

// Calculate progress to next level (FIXED)
function getLevelProgress($xp) {
    $currentLevel = getUserLevel($xp);
    
    // XP required to REACH the start of current level
    $xpForCurrentLevel = ($currentLevel - 1) * 1000; 
    
    // XP required to REACH the NEXT level
    // We use $currentLevel here because if I am Level 1, the goal is 1 * 1000 = 1000
    $xpForNextLevel = xpForNextLevel($currentLevel); 
    
    $currentXpInLevel = $xp - $xpForCurrentLevel;
    $xpNeeded = $xpForNextLevel - $xpForCurrentLevel;
    
    // Prevent division by zero
    if ($xpNeeded <= 0) return 100;

    return ($currentXpInLevel / $xpNeeded) * 100;
}

// Add XP to user and check for level up
function addUserXP($userId, $xpToAdd) {
    try {
        require_once __DIR__ . '/../config/db.php';
        $pdo = Database::getConnection();
        
        // First, get current XP and level
        $stmt = $pdo->prepare("SELECT xp_points, level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $currentXP = $user['xp_points'];
        $currentLevel = $user['level'];
        $newXP = $currentXP + $xpToAdd;
        
        // Calculate what level should be with new XP
        $newLevel = floor($newXP / 1000) + 1;
        
        // Update XP
        $stmt = $pdo->prepare("UPDATE users SET xp_points = ? WHERE id = ?");
        $stmt->execute([$newXP, $userId]);
        
        // Update level if it increased
        if ($newLevel > $currentLevel) {
            $stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->execute([$newLevel, $userId]);
            
            // Return the number of levels gained
            return $newLevel - $currentLevel;
        }
        
        return 0; // No level up
        
    } catch (PDOException $e) {
        error_log("Error adding XP: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a user activity to the database
 */
function logActivity($userId, $type, $description, $xpGained = 0) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, type, description, xp_gained, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $type, $description, $xpGained]);
        return true;
    } catch (PDOException $e) {
        // Optionally log error to file, but don't stop execution
        return false;
    }
}
?>