<?php
// auth/logout.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

logoutUser();

// Redirect to home page
redirect(SITE_URL);
?>
