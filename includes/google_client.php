<?php
// includes/google_client.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

function getGoogleClient() {
    $client = new Google\Client();
    $client->setApplicationName(GOOGLE_APP_NAME);
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    
    // Scopes for Login, Calendar, and Email
    $client->addScope("email");
    $client->addScope("profile");
    $client->addScope("https://www.googleapis.com/auth/calendar");
    $client->addScope("https://www.googleapis.com/auth/gmail.send");
    
    // Important for keeping connection alive without re-login
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    return $client;
}

function getGoogleService($userId, $serviceType = 'calendar') {
    require_once __DIR__ . '/../config/db.php';
    $pdo = Database::getConnection();
    
    // Fetch stored token
    $stmt = $pdo->prepare("SELECT google_refresh_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['google_refresh_token'])) {
        return null; // User not connected to Google
    }

    $client = getGoogleClient();
    // Refresh the access token using the stored refresh token
    $client->fetchAccessTokenWithRefreshToken($user['google_refresh_token']);

    if ($serviceType === 'calendar') {
        return new Google\Service\Calendar($client);
    } elseif ($serviceType === 'gmail') {
        return new Google\Service\Gmail($client);
    }
    return null;
}
?>