<?php
// includes/notifications.php

// Show level up notification
function showLevelUpNotification($levelsGained) {
    if ($levelsGained > 0) {
        $_SESSION['level_up'] = $levelsGained;
    }
}

// Display notifications
function displayNotifications() {
    $html = '';
    
    // Level up notification
    if (isset($_SESSION['level_up']) && $_SESSION['level_up'] > 0) {
        $levels = $_SESSION['level_up'];
        $html .= '
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
            <div class="toast show" role="alert">
                <div class="toast-header bg-warning text-dark">
                    <i class="bi bi-trophy-fill me-2"></i>
                    <strong class="me-auto">Level Up! 🎉</strong>
                    <button type="button" class="btn-close" onclick="this.parentElement.parentElement.parentElement.remove()"></button>
                </div>
                <div class="toast-body">
                    <h5>Congratulations!</h5>
                    <p>You gained ' . $levels . ' level' . ($levels > 1 ? 's' : '') . '!</p>
                    <div class="d-grid">
                        <button class="btn btn-sm btn-outline-warning" onclick="celebrateLevelUp()">
                            <i class="bi bi-fire me-1"></i>Celebrate!
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        function celebrateLevelUp() {
            // Confetti effect
            const duration = 3000;
            const end = Date.now() + duration;
            
            (function frame() {
                confetti({
                    particleCount: 5,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 }
                });
                confetti({
                    particleCount: 5,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 }
                });
                
                if (Date.now() < end) {
                    requestAnimationFrame(frame);
                }
            }());
            
            // Remove notification
            document.querySelector(\'.toast\').closest(\'.position-fixed\').remove();
        }
        </script>';
        
        unset($_SESSION['level_up']);
    }
    
    return $html;
}

function sendEmailNotification($userId, $subject, $body) {
    require_once __DIR__ . '/google_client.php';
    require_once __DIR__ . '/../config/db.php';
    
    // 1. Get User Email
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) return false;

    // 2. Get Gmail Service
    $gmailService = getGoogleService($userId, 'gmail');
    
    if ($gmailService) {
        try {
            // Construct Email
            $strSubject = '=?utf-8?B?'.base64_encode($subject).'?=';
            $strRawMessage = "From: StudySync <me>\r\n";
            $strRawMessage .= "To: <" . $user['email'] . ">\r\n";
            $strRawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
            $strRawMessage .= "MIME-Version: 1.0\r\n";
            $strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $strRawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $strRawMessage .= $body;

            // The message needs to be base64url encoded
            $mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');
            
            $msg = new Google_Service_Gmail_Message();
            $msg->setRaw($mime);
            
            $gmailService->users_messages->send("me", $msg);
            return true;
        } catch (Exception $e) {
            error_log("Gmail Send Error: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

// Add confetti library for celebrations
function addConfettiScript() {
    return '<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>';
}
?>
