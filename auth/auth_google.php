<?php
// auth/auth_google.php - Google OAuth2 Callback Skeleton
require_once '../includes/google_client.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// If there are error parameters (e.g. user denied access), show error
if (isset($_GET['error'])) {
    die("Google Login Error: " . htmlspecialchars($_GET['error']));
}

$client = getGoogleClient();
header('Location: ' . $client->createAuthUrl());
exit();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<div class='container mt-5'>";
echo "<div class='card'>";
echo "<div class='card-header'>Google Authentication</div>";
echo "<div class='card-body'>";

// Check if we have an authorization code
if (isset($_GET['code'])) {
    echo "<h4>Google OAuth2 Callback Received</h4>";
    echo "<p>Authorization code: " . htmlspecialchars(substr($_GET['code'], 0, 50)) . "...</p>";
    
    echo "<div class='alert alert-info'>";
    echo "<h5>Next Steps:</h5>";
    echo "<ol>";
    echo "<li>Go to <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a></li>";
    echo "<li>Create a new project or select existing one</li>";
    echo "<li>Enable 'Google Calendar API' and 'Gmail API'</li>";
    echo "<li>Configure OAuth 2.0 consent screen</li>";
    echo "<li>Create credentials (OAuth 2.0 Client ID)</li>";
    echo "<li>Add authorized redirect URI: " . GOOGLE_REDIRECT_URI . "</li>";
    echo "<li>Update GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in config/constants.php</li>";
    echo "</ol>";
    echo "</div>";
    
    // Simulate successful login for demo
    echo "<div class='alert alert-warning'>";
    echo "<p><strong>Demo Mode:</strong> Google API not configured yet.</p>";
    echo "<p>For testing, you can <a href='login.php'>login normally</a> or configure Google OAuth.</p>";
    echo "</div>";
    
    // Optional: Auto-login as demo user for testing
    if (isset($_GET['demo'])) {
        try {
            require_once '../config/db.php';
            $pdo = Database::getConnection();
            
            // Get any user for demo
            $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
            $user = $stmt->fetch();
            
            if ($user) {
                loginUser($user['id']);
                echo "<div class='alert alert-success'>";
                echo "Demo login successful! Redirecting to dashboard...";
                echo "</div>";
                echo "<script>setTimeout(function() { window.location.href = '../pages/dashboard.php'; }, 2000);</script>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Demo login failed: " . $e->getMessage() . "</div>";
        }
    }
    
} elseif (isset($_GET['error'])) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>Google Authentication Error</h5>";
    echo "<p>" . htmlspecialchars($_GET['error']) . "</p>";
    if (isset($_GET['error_description'])) {
        echo "<p>" . htmlspecialchars($_GET['error_description']) . "</p>";
    }
    echo "</div>";
    
    echo "<a href='login.php' class='btn btn-primary'>Back to Login</a>";
    
} else {
    echo "<h4>Google OAuth2 Setup Required</h4>";
    echo "<p>This page handles Google OAuth2 callbacks. To set up:</p>";
    
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>Quick Setup Commands</div>";
    echo "<div class='card-body'>";
    echo "<pre><code>";
    echo "# Install Google API PHP Client\n";
    echo "composer require google/apiclient:^2.0\n\n";
    echo "# Or download manually:\n";
    echo "cd /var/www/html/student_hub\n";
    echo "mkdir -p vendor\n";
    echo "cd vendor\n";
    echo "git clone https://github.com/googleapis/google-api-php-client.git\n";
    echo "</code></pre>";
    echo "</div>";
    echo "</div>";
    
    echo "<a href='login.php' class='btn btn-secondary'>Back to Login</a>";
    echo " <a href='?demo=1' class='btn btn-warning'>Try Demo Login</a>";
}

echo "</div>";
echo "<div class='card-footer'>";
echo "<small class='text-muted'>Configure Google API credentials to enable Google Login</small>";
echo "</div>";
echo "</div>";
echo "</div>";

// Add Bootstrap for styling
echo '
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
';
?>
