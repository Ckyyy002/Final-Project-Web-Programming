<?php
// auth/google_callback.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/google_client.php';
require_once '../config/db.php';

session_start();
$client = getGoogleClient();

// var_dump($_GET['code']);
// die();

if (isset($_GET['code'])) {
    // Exchange code for token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    // Get User Profile
    $google_oauth = new Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $google_id = $google_account_info->id;
    $picture = $google_account_info->picture;
    
    // Get Refresh Token (only sent on first consent, so save it!)
    $refreshToken = isset($token['refresh_token']) ? $token['refresh_token'] : null;

    $pdo = Database::getConnection();

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists - Update tokens
        $sql = "UPDATE users SET google_id = ?, google_picture = ?";
        $params = [$google_id, $picture];
        
        if ($refreshToken) {
            $sql .= ", google_refresh_token = ?";
            $params[] = $refreshToken;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $user['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        loginUser($user['id']);
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, google_picture, google_refresh_token, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
        // Set a random password since they use Google
        $randomPass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
        $stmt->execute([$name, $email, $google_id, $picture, $refreshToken, $randomPass]);
        
        $userId = $pdo->lastInsertId();
        loginUser($userId);
    }
    
    redirect(SITE_URL . 'pages/dashboard.php');
} else {
    redirect(SITE_URL . 'auth/login.php');
}
?>