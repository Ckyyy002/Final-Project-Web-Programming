<?php
// includes/sidebar.php - Sidebar component for all pages

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get user data
require_once __DIR__ . '/../config/db.php';
$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../auth/logout.php');
    exit;
}

// Calculate user level and progress
require_once __DIR__ . '/functions.php';

// 1. Get current Level
$user['level'] = getUserLevel($user['xp_points']);

// 2. Get Progress Percentage (0-100%)
$user['level_progress'] = getLevelProgress($user['xp_points']);

// 3. Calculate XP details for the text display (e.g., "520 / 1000 XP")
$levelStartXP = ($user['level'] - 1) * 1000;
$nextLevelGoal = $user['level'] * 1000;
$user['current_xp_in_level'] = $user['xp_points'] - $levelStartXP;
$user['xp_needed'] = $nextLevelGoal - $levelStartXP; 

// Get quick stats
try {
    // Task count
    $taskStmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status != 'completed'");
    $taskStmt->execute([$user['id']]);
    $taskCount = $taskStmt->fetch()['count'];
    
    // Group count
    $groupStmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_members WHERE user_id = ?");
    $groupStmt->execute([$user['id']]);
    $groupCount = $groupStmt->fetch()['count'];
    
    // Today's event count
    $today = date('Y-m-d');
    $eventStmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND DATE(deadline) = ?");
    $eventStmt->execute([$user['id'], $today]);
    $eventCount = $eventStmt->fetch()['count'];
    
} catch (PDOException $e) {
    $taskCount = 0; $groupCount = 0; $eventCount = 0;
}
?>

<div class="sidebar d-flex flex-column p-3">
    <div class="text-center mb-4">
        <h3 class="fw-bold">
            <span class="text-white">Study</span><span class="text-warning">Sync</span>
        </h3>
        <p class="text-light opacity-75 small">Student Productivity Hub</p>
    </div>
    
    <div class="text-center mb-4">
        <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
             style="width: 80px; height: 80px; background: linear-gradient(135deg, #f72585, #4361ee); padding: 2px;">
             <?php if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'default-avatar.png' && file_exists(__DIR__ . '/../' . $user['profile_picture'])): ?>
                <img src="<?php echo '../' . $user['profile_picture']; ?>" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover; border: 2px solid white;">
            <?php else: ?>
                <span class="fw-bold fs-3 text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
            <?php endif; ?>
        </div>
        <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user['name']); ?></h5>
        <div class="badge bg-white text-primary rounded-pill">Level <?php echo $user['level']; ?></div>
    </div>
    
    <!-- XP Progress -->
    <div class="mb-3 px-2">
        <div class="d-flex justify-content-between mb-1 text-white small">
            <span>XP Progress</span>
            <span><?php echo $user['current_xp_in_level']; ?>/<?php echo $user['xp_needed']; ?> XP</span>
        </div>
        <div class="xp-bar">
            <div class="xp-progress" style="width: <?php echo $user['level_progress']; ?>%"></div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="mb-3 px-2">
        <div class="d-flex justify-content-between small text-white mb-2 opacity-75">
            <span><i class="bi bi-list-check me-1"></i> Active Tasks</span>
            <span class="badge bg-light text-dark"><?php echo $taskCount; ?></span>
        </div>
        <div class="d-flex justify-content-between small text-white mb-2 opacity-75">
            <span><i class="bi bi-people me-1"></i> Groups</span>
            <span class="badge bg-light text-dark"><?php echo $groupCount; ?></span>
        </div>
        <div class="d-flex justify-content-between small text-white opacity-75">
            <span><i class="bi bi-calendar me-1"></i> Today's Events</span>
            <span class="badge bg-light text-dark"><?php echo $eventCount; ?></span>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="nav flex-column mb-auto">
        <a href="dashboard.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="tasks.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
            <i class="bi bi-list-task me-2"></i> Tasks
        </a>
        <a href="calendar.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event me-2"></i> Calendar
        </a>
        <a href="groups.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
            <i class="bi bi-people me-2"></i> Study Groups
        </a>
        <a href="notes.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-text me-2"></i> Notes
        </a>
        <a href="profile.php" class="nav-link text-white mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle me-2"></i> Profile
        </a>
        
        <hr class="text-white opacity-25 my-2">
        
        <a href="../auth/logout.php" class="nav-link text-white">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </nav>
    
    <div class="sidebar-footer mt-auto pt-3 border-top border-white border-opacity-25 text-center">
        <small class="text-white opacity-50 d-block" style="font-size: 0.7rem;">
            &copy; <?php echo date('Y'); ?> StudySync v1.2<br>
            All rights reserved
        </small>
    </div>
</div>

<style>
/* Sidebar Base Styles */
.sidebar {
    background: linear-gradient(180deg, #4361ee, #3a0ca3);
    color: white;
    min-height: 100vh;
    width: 250px;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    overflow-y: auto; 
}

/* XP Bar Styles */
.xp-bar {
    height: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.xp-progress {
    height: 100%;
    background: linear-gradient(90deg, #f72585, #ffd166);
    border-radius: 4px;
    transition: width 0.5s ease;
}

/* Navigation Link Styles */
.nav-link {
    border-radius: 8px;
    transition: all 0.2s ease;
    padding: 10px 15px;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.2);
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Quick Stats */
.sidebar .badge {
    font-size: 0.7rem;
    padding: 0.2em 0.6em;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        position: relative;
        min-height: auto;
    }
}
</style>