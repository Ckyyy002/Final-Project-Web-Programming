<?php
// config/constants.php

// Site Configuration
define('SITE_NAME', 'StudySync');
define('SITE_URL', 'https://lucky-himawan.rf.gd/');
define('SITE_ROOT', dirname(dirname(__FILE__)));

// Database Configuration
define('DB_HOST', 'sql104.infinityfree.com');
define('DB_NAME', 'if0_40665242_studysync');
define('DB_USER', 'if0_40665242');
define('DB_PASS', 'boyb3Swpgy');

// Session Configuration
define('SESSION_NAME', 'student_hub_session');
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Gamification Constants
define('XP_TASK_COMPLETE', 50);
define('XP_GROUP_JOIN', 100);
define('XP_GROUP_CREATE', 150);
define('XP_DAILY_LOGIN', 25);
define('XP_TASK_CREATE', 10);
// REMOVED: XP_ADD_FRIEND

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');

// Google API Configuration
define('GOOGLE_CLIENT_ID', '~.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '~');
define('GOOGLE_REDIRECT_URI', SITE_URL . 'auth/google_callback.php');
define('GOOGLE_APP_NAME', 'StudySync');
?>
