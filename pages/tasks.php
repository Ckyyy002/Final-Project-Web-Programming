<?php
// pages/tasks.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

requireAuth();
$user = getCurrentUser();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        require_once '../config/db.php';
        $pdo = Database::getConnection();
        
        switch ($action) {
            case 'add_task':
                $title = sanitize($_POST['title'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $deadline = $_POST['deadline'] ?? '';
                $priority = $_POST['priority'] ?? 'medium';
                $category = sanitize($_POST['category'] ?? '');
                
                if (!empty($title) && !empty($deadline)) {
                    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, deadline, priority, category) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $title, $description, $deadline, $priority, $category]);
                    $taskId = $pdo->lastInsertId();

                    // Google Sync
                    require_once '../includes/google_client.php';
                    $calendarService = getGoogleService($user['id'], 'calendar');
                    if ($calendarService) {
                        try {
                            $event = new Google_Service_Calendar_Event([
                                'summary' => $title,
                                'description' => $description,
                                'start' => ['dateTime' => date('c', strtotime($deadline)), 'timeZone' => 'UTC'],
                                'end' => ['dateTime' => date('c', strtotime($deadline . ' +1 hour')), 'timeZone' => 'UTC'],
                            ]);
                            $calendarId = 'primary';
                            $event = $calendarService->events->insert($calendarId, $event);
                            $pdo->prepare("UPDATE tasks SET google_event_id = ? WHERE id = ?")->execute([$event->id, $taskId]);
                        } catch (Exception $e) { error_log("Google Sync Error: " . $e->getMessage()); }
                    }
                    $message = "Task added successfully!";
                    $messageType = 'success';
                }
                break;
                
            case 'update_status':
                $taskId = $_POST['task_id'] ?? 0;
                $status = $_POST['status'] ?? '';
                
                if ($taskId && in_array($status, ['completed', 'in_progress', 'pending'])) {
                    $checkStmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ? AND user_id = ?");
                    $checkStmt->execute([$taskId, $user['id']]);
                    $currentTask = $checkStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$status, $taskId, $user['id']]);
                    
                    if ($status === 'completed' && $currentTask['status'] !== 'completed') {
                        $levelsGained = addUserXP($user['id'], 50);
                        $message = ($levelsGained > 0) ? "Task completed! +50 XP & LEVEL UP! 脂" : "Task completed! +50 XP";
                        if ($levelsGained > 0) showLevelUpNotification($levelsGained);
                        $messageType = 'success';
                    } else {
                        $message = "Task status updated";
                        $messageType = 'info';
                    }
                }
                break;

            case 'submit_task':
                $taskId = $_POST['task_id'];
                // Handle File Upload
                if (isset($_FILES['work_file']) && $_FILES['work_file']['error'] == 0) {
                    $uploadDir = '../uploads/tasks/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileName = time() . '_' . basename($_FILES['work_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['work_file']['tmp_name'], $targetPath)) {
                        // Update Database: Save file path AND mark as completed
                        $stmt = $pdo->prepare("UPDATE tasks SET submission_file = ?, status = 'completed' WHERE id = ?");
                        $stmt->execute([$fileName, $taskId]);
                        
                        $message = "Work uploaded & Task Completed!";
                        $messageType = "success";
                    } else {
                        $message = "File upload failed.";
                        $messageType = "danger";
                    }
                }
                break;
                
            case 'delete_task':
                $taskId = $_POST['task_id'] ?? 0;
                if ($taskId) {
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
                    $stmt->execute([$taskId, $user['id']]);
                    $message = "Task deleted";
                    $messageType = 'warning';
                }
                break;
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }

        /* Compact Task Card for 4x3 Layout */
        .task-card { 
            position: relative;
            overflow: visible !important; /* Fixes dropdown being blocked */
            z-index: 1;
            transition: all 0.2s ease; 
            border-left: 4px solid;
            height: 180px;  /* REDUCED HEIGHT (was 250px) */
            font-size: 0.9rem; /* Slightly smaller text */
        }
        .task-card:hover { 
            transform: translateY(-3px); 
            z-index: 100; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); 
        }
        .task-card .card-body { padding: 12px; } /* Less padding */
        .task-card .card-title { font-size: 1rem; font-weight: bold; margin-bottom: 5px; }
        .task-card .card-text { 
            -webkit-line-clamp: 2; /* Show only 2 lines of description */
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .task-footer { margin-top: auto; }
        
        .dropdown-menu {
            z-index: 10000; /* Ensure menu sits on top of everything */
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            border: none;
        }

        .priority-urgent { border-left-color: #dc3545; }
        .priority-high { border-left-color: #fd7e14; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-low { border-left-color: #6c757d; }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.75em; }
        .completed-task { opacity: 0.7; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php echo displayNotifications(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold"><i class="bi bi-check-square-fill text-success me-2"></i>Task Management</h1>
                <p class="text-muted mb-0">Organize your academic tasks and deadlines</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="bi bi-plus-circle me-2"></i>Add New Task
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php 
            // Data Fetching Logic
            $statusFilter = $_GET['status'] ?? 'all';
            $priorityFilter = $_GET['priority'] ?? 'all';
            $categoryFilter = $_GET['category'] ?? 'all';
            $search = $_GET['search'] ?? '';

            try {
                $query = "SELECT * FROM tasks WHERE user_id = ?";
                $params = [$user['id']];
                
                if ($statusFilter !== 'all') { $query .= " AND status = ?"; $params[] = $statusFilter; }
                if ($priorityFilter !== 'all') { $query .= " AND priority = ?"; $params[] = $priorityFilter; }
                if ($categoryFilter !== 'all' && $categoryFilter !== '') { $query .= " AND category = ?"; $params[] = $categoryFilter; }
                if (!empty($search)) { $query .= " AND (title LIKE ? OR description LIKE ?)"; $searchTerm = "%$search%"; $params[] = $searchTerm; $params[] = $searchTerm; }
                
                $query .= " ORDER BY CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END, deadline ASC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $tasks = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("SELECT DISTINCT category FROM tasks WHERE user_id = ? AND category IS NOT NULL AND category != ''");
                $stmt->execute([$user['id']]);
                $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) { $tasks = []; $categories = []; }
        ?>

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body bg-light rounded">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="status" class="form-select border-0 shadow-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="priority" class="form-select border-0 shadow-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $priorityFilter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                            <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select border-0 shadow-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group shadow-sm">
                            <input type="text" name="search" class="form-control border-0" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-white border-0 bg-white text-primary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <?php if (empty($tasks)): ?>
                <div class="col-12 text-center py-5 card shadow-sm border-0">
                    <i class="bi bi-clipboard-check fs-1 text-muted mb-3"></i>
                    <h4>No tasks found</h4>
                    <p class="text-muted">Create a task to get started!</p>
                </div>
            <?php else: ?>
                <?php foreach($tasks as $task): ?>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card task-card shadow-sm priority-<?php echo $task['priority']; ?> <?php echo $task['status'] === 'completed' ? 'completed-task' : ''; ?>">
                            <div class="card-body">
                                
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0" title="<?php echo htmlspecialchars($task['title']); ?>">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($task['status'] !== 'completed'): ?>
                                                
                                                <li>
                                                    <form method="POST" class="p-0">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-check-circle text-success me-2"></i>Mark Complete</button>
                                                    </form>
                                                </li>

                                                <li>
                                                    <button type="button" class="dropdown-item" onclick="openSubmitModal(<?php echo $task['id']; ?>)">
                                                        <i class="bi bi-upload text-primary me-2"></i>Submit Work
                                                    </button>
                                                </li>
                                                <?php endif; ?>

                                            <li><hr class="dropdown-divider"></li>

                                            <li>
                                                <form method="POST" class="p-0" onsubmit="return confirm('Delete this task?')">
                                                    <input type="hidden" name="action" value="delete_task">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="card-text text-muted small">
                                    <?php echo htmlspecialchars($task['description'] ?? ''); ?>
                                </p>
                                
                                <div class="task-footer">
                                    <div class="mb-2">
                                        <span class="badge bg-<?php echo match($task['priority']) { 'urgent'=>'danger', 'high'=>'warning', 'medium'=>'info', default=>'secondary' }; ?> status-badge"><?php echo ucfirst($task['priority']); ?></span>
                                        <span class="badge bg-<?php echo match($task['status']) { 'completed'=>'success', 'in_progress'=>'primary', 'overdue'=>'danger', default=>'secondary' }; ?> status-badge"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span>
                                    </div>
                                    <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?php echo date('M d, H:i', strtotime($task['deadline'])); ?></small>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="modal fade" id="addTaskModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header"><h5 class="modal-title">Add New Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_task">
                            <div class="mb-3"><label>Title</label><input type="text" class="form-control" name="title" required></div>
                            <div class="mb-3"><label>Description</label><textarea class="form-control" name="description"></textarea></div>
                            <div class="row mb-3">
                                <div class="col-6"><label>Deadline</label><input type="datetime-local" class="form-control" name="deadline" required value="<?php echo date('Y-m-d\TH:i'); ?>"></div>
                                <div class="col-6"><label>Priority</label><select class="form-select" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                            </div>
                            <div class="mb-3"><label>Category</label><input type="text" class="form-control" name="category"></div>
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Add Task</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="submitWorkModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" enctype="multipart/form-data"> <div class="modal-header">
                            <h5 class="modal-title">Submit & Complete Task</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="submit_task">
                            <input type="hidden" name="task_id" id="submitTaskId">
                            
                            <div class="alert alert-info small">
                                Uploading a file will mark this task as <b>Completed</b>.
                            </div>
                            
                            <div class="mb-3">
                                <label>Upload Document (PDF, Docx, Image)</label>
                                <input type="file" name="work_file" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Upload & Complete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openSubmitModal(id) {
            document.getElementById('submitTaskId').value = id;
            new bootstrap.Modal(document.getElementById('submitWorkModal')).show();
        }
    </script>
    
    <?php echo addConfettiScript(); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>