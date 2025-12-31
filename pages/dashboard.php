<?php
// pages/dashboard.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

requireAuth();
$user = getCurrentUser();
$message = '';
$messageType = '';

// --- 1. HANDLE ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // A. Sync Calendar
    if ($action === 'sync_calendar') {
        require_once '../includes/google_client.php';
        $calendarService = getGoogleService($user['id'], 'calendar');
        
        if ($calendarService) {
            try {
                require_once '../config/db.php';
                $pdo = Database::getConnection();
                
                $calendarId = 'primary';
                $optParams = ['maxResults' => 10, 'orderBy' => 'startTime', 'singleEvents' => true, 'timeMin' => date('c')];
                $results = $calendarService->events->listEvents($calendarId, $optParams);
                $events = $results->getItems();
                $count = 0;

                foreach ($events as $event) {
                    $googleId = $event->id;
                    $summary = $event->getSummary();
                    $start = $event->start->dateTime ?? $event->start->date;
                    
                    $check = $pdo->prepare("SELECT id FROM tasks WHERE google_event_id = ? AND user_id = ?");
                    $check->execute([$googleId, $user['id']]);
                    
                    if ($check->rowCount() == 0) {
                        $ins = $pdo->prepare("INSERT INTO tasks (user_id, title, deadline, priority, google_event_id) VALUES (?, ?, ?, 'medium', ?)");
                        $mysqlDate = date('Y-m-d H:i:s', strtotime($start));
                        $ins->execute([$user['id'], $summary, $mysqlDate, $googleId]);
                        $count++;
                    }
                }
                
                // Log Activity if sync was successful
                if ($count > 0 && function_exists('logActivity')) {
                    logActivity($user['id'], 'sync', "Synced $count tasks from Google Calendar");
                }

                $message = "Synced $count tasks from Google Calendar!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Sync failed: " . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = "Please login with Google first.";
            $messageType = 'warning';
        }
    }
    
    // B. Complete Task
    if ($action === 'update_status') {
        $taskId = $_POST['task_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        if ($taskId && $status === 'completed') {
            try {
                require_once '../config/db.php';
                $pdo = Database::getConnection();
                
                // Get task title for the log before updating
                $stmtTitle = $pdo->prepare("SELECT title FROM tasks WHERE id = ? AND user_id = ?");
                $stmtTitle->execute([$taskId, $user['id']]);
                $task = $stmtTitle->fetch();
                $taskTitle = $task ? $task['title'] : 'Task';

                // Update Status
                $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ? AND user_id = ?");
                $stmt->execute([$taskId, $user['id']]);
                
                if ($stmt->rowCount() > 0) {
                    $levelsGained = addUserXP($user['id'], 50);
                    
                    // Log Activity: Task Completion
                    if (function_exists('logActivity')) {
                        logActivity($user['id'], 'task_completed', "Completed: " . substr($taskTitle, 0, 20) . "...", 50);
                    }

                    if ($levelsGained > 0) {
                        showLevelUpNotification($levelsGained);
                        // Log Activity: Level Up
                        if (function_exists('logActivity')) {
                            logActivity($user['id'], 'level_up', "Reached Level " . ($user['level'] + $levelsGained));
                        }
                    }

                    $message = "Task completed! +50 XP";
                    $messageType = 'success';
                    $user = getCurrentUser(); // Refresh user data
                }
            } catch (PDOException $e) {
                // Silent fail
            }
        }
    }
}

// --- 2. GET DATA (Tasks, Stats, Activities) ---
try {
    require_once '../config/db.php';
    $pdo = Database::getConnection();
    
    // A. Today's Tasks
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE user_id = ? 
        AND DATE(deadline) = ?
        AND status != 'completed'
        ORDER BY 
            CASE priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            deadline ASC
        LIMIT 5
    ");
    $stmt->execute([$user['id'], $today]);
    $todaysTasks = $stmt->fetchAll();
    
    // B. Upcoming Tasks
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    
    $stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE user_id = ? 
        AND DATE(deadline) >= ? 
        AND DATE(deadline) <= ?
        AND status != 'completed'
        ORDER BY deadline ASC
        LIMIT 10
    ");
    $stmt->execute([$user['id'], $tomorrow, $nextWeek]);
    $upcomingTasks = $stmt->fetchAll();
    
    // C. Task Statistics & Productivity
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status");
    $stmt->execute([$user['id']]);
    $taskStats = $stmt->fetchAll();
    
    $total_count = 0;
    $completed_count = 0;
    
    foreach ($taskStats as $stat) {
        $total_count += $stat['count'];
        if ($stat['status'] === 'completed') {
            $completed_count += $stat['count'];
        }
    }
    
    // Calculate Productivity Percentage (Avoid division by zero)
    $productivity_percentage = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;

    // D. Weekly Progress (New Feature)
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek   = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM tasks 
        WHERE user_id = ? 
        AND deadline BETWEEN ? AND ?
    ");
    $stmt->execute([$user['id'], $startOfWeek . ' 00:00:00', $endOfWeek . ' 23:59:59']);
    $weeklyStats = $stmt->fetch();

    $weeklyTotal = $weeklyStats['total'] ?? 0;
    $weeklyCompleted = $weeklyStats['completed'] ?? 0;
    $weeklyPercent = $weeklyTotal > 0 ? round(($weeklyCompleted / $weeklyTotal) * 100) : 0;

    // E. Fetch Recent Activities
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activity_log 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 4
        ");
        $stmt->execute([$user['id']]);
        $recent_activities = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recent_activities = []; 
    }
    
    // F. Count Overdue
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deadline < NOW() AND status != 'completed'");
    $stmt->execute([$user['id']]);
    $overdue = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $todaysTasks = []; $upcomingTasks = []; $taskStats = []; $overdue = 0; $recent_activities = [];
    $weeklyTotal = 0; $weeklyCompleted = 0; $weeklyPercent = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .stat-card { 
            transition: transform 0.3s ease; 
            border-radius: 15px; 
            height: 100%;
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
        }
        .task-card { 
            border-left: 4px solid; 
            transition: all 0.3s ease; 
            border-radius: 10px;
        }
        .task-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .priority-urgent { border-left-color: #dc3545; }
        .priority-high { border-left-color: #fd7e14; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-low { border-left-color: #6c757d; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .progress-sm { height: 5px; }
        .quick-action-btn { transition: all 0.2s ease; }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php echo displayNotifications(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>! 👋</h1>
                <p class="text-muted mb-0">Here's your productivity overview</p>
            </div>
            <div class="d-flex gap-2">
                <a href="tasks.php" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> New Task
                </a>
                <a href="groups.php" class="btn btn-primary">
                    <i class="bi bi-people"></i> New Group
                </a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="sync_calendar">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-google me-2"></i>Sync Calendar
                    </button>
                </form>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card bg-primary text-white p-3 shadow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Total Tasks</h6>
                            <h3 class="mb-0"><?php echo array_sum(array_column($taskStats, 'count')); ?></h3>
                        </div>
                        <i class="bi bi-list-task fs-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card bg-success text-white p-3 shadow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Completed</h6>
                            <h3 class="mb-0">
                                <?php 
                                    $completed = 0;
                                    foreach($taskStats as $s) if($s['status']=='completed') $completed=$s['count'];
                                    echo $completed; 
                                ?>
                            </h3>
                        </div>
                        <i class="bi bi-check-circle fs-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card bg-warning text-white p-3 shadow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Pending</h6>
                            <h3 class="mb-0">
                                <?php 
                                    $pending = 0;
                                    foreach($taskStats as $s) if(in_array($s['status'], ['pending','in_progress'])) $pending+=$s['count'];
                                    echo $pending; 
                                ?>
                            </h3>
                        </div>
                        <i class="bi bi-clock fs-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card bg-info text-white p-3 shadow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">XP Points</h6>
                            <h3 class="mb-0"><?php echo $user['xp_points']; ?></h3>
                        </div>
                        <i class="bi bi-trophy fs-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card border-danger p-3 shadow h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-danger mb-1">Overdue</h6>
                            <h3 class="mb-0"><?php echo $overdue; ?></h3>
                            <small class="text-muted">Need attention</small>
                        </div>
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                    <div class="mt-2">
                        <?php if($overdue > 0): ?>
                            <a href="tasks.php?status=overdue" class="small text-danger text-decoration-none">View →</a>
                        <?php else: ?>
                            <span class="small text-success">All caught up!</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <div class="stat-card border-info p-3 shadow h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-info mb-1">Weekly Goal</h6>
                            <h3 class="mb-0">
                                <?php echo $weeklyCompleted; ?>/<span class="fs-6 text-muted"><?php echo $weeklyTotal; ?></span>
                            </h3>
                            <small class="text-muted">Tasks done this week</small>
                        </div>
                        <?php if($weeklyPercent >= 100): ?>
                            <i class="bi bi-star-fill text-warning fs-4"></i>
                        <?php else: ?>
                            <i class="bi bi-calendar-check text-info fs-4"></i>
                        <?php endif; ?>
                    </div>
                    <div class="progress progress-sm mt-2">
                        <div class="progress-bar bg-info" style="width: <?php echo $weeklyPercent; ?>%"></div>
                    </div>
                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo $weeklyPercent; ?>% Completed</small>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <div class="card border-warning p-3 shadow h-100">
                    <h6 class="text-warning mb-3">Productivity</h6>
                    <div class="text-center">
                        <div class="position-relative d-inline-block">
                            <canvas id="productivityChart" width="80" height="80"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <span class="fw-bold"><?php echo $productivity_percentage; ?>%</span>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Completion Rate</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <div class="card border-success p-3 shadow h-100">
                    <h6 class="text-success mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="tasks.php?add=quick" class="btn btn-sm btn-outline-success quick-action-btn">
                            <i class="bi bi-plus-circle me-1"></i> Quick Task
                        </a>
                        <a href="calendar.php" class="btn btn-sm btn-outline-primary quick-action-btn">
                            <i class="bi bi-calendar-plus me-1"></i> Schedule
                        </a>
                        <a href="notes.php" class="btn btn-sm btn-outline-warning quick-action-btn">
                            <i class="bi bi-file-earmark-text me-1"></i> Add Note
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar-day me-2"></i> Today's Tasks</h5>
                        <span class="badge bg-primary"><?php echo count($todaysTasks); ?> tasks</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaysTasks)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check2-circle display-4 text-muted mb-3"></i>
                                <p class="text-muted">No pending tasks for today!</p>
                                <a href="tasks.php" class="btn btn-sm btn-outline-primary">Create a Task</a>
                            </div>
                        <?php else: ?>
                            <?php foreach($todaysTasks as $task): ?>
                                <div class="task-card p-3 mb-3 border priority-<?php echo $task['priority']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <div class="d-flex gap-2 align-items-center">
                                                <span class="badge bg-secondary"><?php echo ucfirst($task['priority']); ?></span>
                                                <span class="text-muted small">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($task['deadline'])); ?>
                                                </span>
                                                <?php if($task['category']): ?>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($task['category']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg"></i> Complete
                                                </button>
                                            </form>
                                            <a href="tasks.php?edit=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php if(!empty($task['description'])): ?>
                                        <p class="text-muted small mt-2 mb-0"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-week me-2"></i> Next 7 Days</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcomingTasks)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-check display-4 text-muted mb-3"></i>
                                <p class="text-muted">No upcoming tasks.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($upcomingTasks as $task): ?>
                                    <div class="list-group-item border-0 px-3 py-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo date('D, M j', strtotime($task['deadline'])); ?>
                                                    at <?php echo date('H:i', strtotime($task['deadline'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $task['priority'] == 'urgent' ? 'danger' : ($task['priority'] == 'high' ? 'warning' : 'secondary'); ?>">
                                                <?php echo substr(ucfirst($task['priority']), 0, 1); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-activity me-2"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (empty($recent_activities)): ?>
                        <div class="col-12 text-center text-muted py-4">
                            No recent activity recorded.
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_activities as $activity): ?>
                            <div class="col-md-6">
                                <div class="activity-item d-flex mb-3">
                                    <div class="activity-icon me-3">
                                        <?php 
                                            $icon = 'bi-circle';
                                            $color = 'text-secondary';
                                            switch($activity['type']) {
                                                case 'task_completed': $icon='bi-check-circle-fill'; $color='text-success'; break;
                                                case 'level_up': $icon='bi-trophy-fill'; $color='text-warning'; break;
                                                case 'group_join': $icon='bi-people-fill'; $color='text-primary'; break;
                                                case 'sync': $icon='bi-calendar-check-fill'; $color='text-info'; break;
                                            }
                                        ?>
                                        <i class="bi <?php echo $icon . ' ' . $color; ?> fs-4"></i>
                                    </div>
                                    <div class="activity-content">
                                        <small class="text-muted">
                                            <?php echo date('M j, g:i a', strtotime($activity['created_at'])); ?>
                                        </small>
                                        <p class="mb-1 fw-medium">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </p>
                                        <?php if(!empty($activity['xp_gained'])): ?>
                                            <span class="badge bg-<?php echo str_replace('text-', '', $color); ?>">
                                                +<?php echo $activity['xp_gained']; ?> XP
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Productivity Chart Logic
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('productivityChart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                
                // Pass PHP variable to JS safely
                const percentage = <?php echo $productivity_percentage; ?> / 100;
                
                // Draw Background Circle (Light Grey)
                ctx.beginPath();
                ctx.arc(40, 40, 35, 0, 2 * Math.PI);
                ctx.strokeStyle = '#f3f4f6';
                ctx.lineWidth = 8;
                ctx.stroke();
                
                // Draw Progress Circle (Orange)
                ctx.beginPath();
                // Arc goes from -0.5*PI (top) to calculated end point
                ctx.arc(40, 40, 35, -0.5 * Math.PI, (percentage * 2 - 0.5) * Math.PI);
                ctx.strokeStyle = '#f59e0b';
                ctx.lineWidth = 8;
                ctx.stroke();
            }
        });
    </script>
    
    <?php echo addConfettiScript(); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>