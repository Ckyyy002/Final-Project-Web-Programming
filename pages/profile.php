<?php
// pages/profile.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAuth();
$user = getCurrentUser();
$message = '';
$messageType = '';

// Check constants
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024);
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        require_once '../config/db.php';
        $pdo = Database::getConnection();

        // --- Handle Avatar Update ---
        if ($action === 'update_avatar' && isset($_FILES['profile_picture'])) {
            $file = $_FILES['profile_picture'];
            
            // Debugging: Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = "Upload failed with error code: " . $file['error']; 
                $messageType = 'danger';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ALLOWED_IMAGE_TYPES) && $file['size'] <= MAX_FILE_SIZE) {
                    // Path Logic: We are in 'pages/', we need to go up to root, then into uploads
                    $uploadDir = '../uploads/profile_pics/'; 
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = $user['id'] . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // DB Logic: Store 'uploads/profile_pics/filename.ext'
                        $dbPath = 'uploads/profile_pics/' . $fileName;
                        
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$dbPath, $user['id']]);
                        
                        $message = "Profile picture updated!"; 
                        $messageType = 'success';
                        // Refresh user data immediately
                        $user['profile_picture'] = $dbPath; 
                    } else {
                        $message = "Failed to move uploaded file. Check folder permissions."; 
                        $messageType = 'danger';
                    }
                } else {
                    $message = "Invalid file type or too large."; 
                    $messageType = 'danger';
                }
            }
        }

        // --- Handle Info Update ---
        if ($action === 'update_profile') {
            $name = sanitize($_POST['name']);
            $newPass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            
            $updates = [];
            $params = [];
            
            if ($name !== $user['name']) {
                $updates[] = "name = ?";
                $params[] = $name;
            }
            
            if (!empty($newPass)) {
                if ($newPass === $confirm) {
                    $updates[] = "password_hash = ?";
                    $params[] = password_hash($newPass, PASSWORD_DEFAULT);
                } else {
                    $message = "Passwords do not match.";
                    $messageType = 'danger';
                }
            }
            
            if (!empty($updates) && empty($message)) {
                $params[] = $user['id'];
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "Profile updated!"; $messageType = 'success';
                $user['name'] = $name; // Update display
            }
        }
        
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .profile-avatar { width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(45deg, #4361ee, #f72585); color: white; display: flex; align-items: center; justify-content: center; font-size: 4rem; margin: 0 auto; object-fit: cover; }
        .xp-progress-bar { height: 10px; border-radius: 5px; background: rgba(0,0,0,0.1); overflow: hidden; }
        .xp-progress-fill { height: 100%; background: linear-gradient(90deg, #f72585, #4cc9f0); }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .avatar-container { position: relative; width: 150px; margin: 0 auto 1rem; }
        .avatar-edit-icon { position: absolute; bottom: 5px; right: 5px; background: #fff; border-radius: 50%; padding: 8px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h1 class="h3 fw-bold mb-4">👤 Your Profile</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm text-center p-3">
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="action" value="update_avatar">
                        <div class="avatar-container">
                            <?php 
                            // Path Logic: If not default and file exists
                            $imagePath = $user['profile_picture'];
                            if ($imagePath && $imagePath !== 'default-avatar.png' && file_exists('../' . $imagePath)) {
                                $displaySrc = '../' . $imagePath;
                            } else {
                                $displaySrc = null;
                            }
                            ?>
                            
                            <?php if ($displaySrc): ?>
                                <img src="<?php echo htmlspecialchars($displaySrc); ?>" class="profile-avatar">
                            <?php else: ?>
                                <div class="profile-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                            <?php endif; ?>
                            
                            <label for="profile_upload" class="avatar-edit-icon text-primary">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="profile_upload" name="profile_picture" class="d-none" onchange="document.getElementById('avatarForm').submit()">
                        </div>
                    </form>
                    
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="mb-3 px-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Level <?php echo $user['level']; ?></span>
                            <span><?php echo $user['current_xp_in_level']; ?> / <?php echo $user['xp_needed']; ?> XP</span>
                        </div>
                        <div class="xp-progress-bar">
                            <div class="xp-progress-fill" style="width: <?php echo $user['level_progress']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header"><h6 class="mb-0">Edit Details</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3"><label>Full Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
                            <div class="mb-3"><label>Email</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled></div>
                            <hr>
                            <h6>Change Password <small class="text-muted">(Optional)</small></h6>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label>New Password</label><input type="password" name="new_password" class="form-control"></div>
                                <div class="col-md-6 mb-3"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control"></div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>