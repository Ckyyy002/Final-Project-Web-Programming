<?php
// pages/calendar.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

requireAuth();
$user = getCurrentUser();
$message = '';
$messageType = '';

// --- 1. Handle Sync Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_calendar') {
    require_once '../includes/google_client.php';
    $calendarService = getGoogleService($user['id'], 'calendar');
    
    if ($calendarService) {
        try {
            $calendarId = 'primary';
            $optParams = [
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'timeMin' => date('c', strtotime('-1 month')),
                'timeMax' => date('c', strtotime('+3 months')),
            ];
            $results = $calendarService->events->listEvents($calendarId, $optParams);
            $events = $results->getItems();
            $count = 0;
            $pdo = Database::getConnection();
            foreach ($events as $event) {
                $googleId = $event->id;
                $summary = $event->getSummary();
                $start = $event->start->dateTime ?? $event->start->date;
                $check = $pdo->prepare("SELECT id FROM tasks WHERE google_event_id = ? AND user_id = ?");
                $check->execute([$googleId, $user['id']]);
                if ($check->rowCount() == 0) {
                    $ins = $pdo->prepare("INSERT INTO tasks (user_id, title, deadline, priority, google_event_id, status) VALUES (?, ?, ?, 'medium', ?, 'pending')");
                    $mysqlDate = date('Y-m-d H:i:s', strtotime($start));
                    $ins->execute([$user['id'], $summary, $mysqlDate, $googleId]);
                    $count++;
                }
            }
            $message = "Synced $count new events from Google Calendar!";
            $messageType = 'success';
        } catch (Exception $e) { $message = "Sync error: " . $e->getMessage(); $messageType = 'danger'; }
    } else { $message = "Google account not connected."; $messageType = 'warning'; }
}

// --- 2. Handle Add Task ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    try {
        require_once '../config/db.php';
        $pdo = Database::getConnection();
        $title = sanitize($_POST['title']);
        $deadline = $_POST['deadline']; 
        if ($title && $deadline) {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, deadline, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$user['id'], $title, $deadline]);
            $taskId = $pdo->lastInsertId();
            require_once '../includes/google_client.php';
            $calendarService = getGoogleService($user['id'], 'calendar');
            if ($calendarService) {
                $ev = new Google_Service_Calendar_Event([
                    'summary' => $title,
                    'start' => ['dateTime' => date('c', strtotime($deadline)), 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => date('c', strtotime($deadline . ' +1 hour')), 'timeZone' => 'UTC'],
                ]);
                $createdEvent = $calendarService->events->insert('primary', $ev);
                $pdo->prepare("UPDATE tasks SET google_event_id = ? WHERE id = ?")->execute([$createdEvent->id, $taskId]);
            }
            $message = "Task added to Calendar!";
            $messageType = 'success';
        }
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); $messageType = 'danger'; }
}

// --- 3. Fetch Tasks ---
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT id, title, deadline, status, priority FROM tasks WHERE user_id = ?");
$stmt->execute([$user['id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calendarEvents = [];
foreach ($tasks as $t) {
    // Custom colors matching the new theme
    $color = '#4f46e5'; // Indigo (Default)
    if ($t['priority'] === 'urgent') $color = '#ef4444'; // Red
    if ($t['priority'] === 'high') $color = '#f97316'; // Orange
    if ($t['status'] === 'completed') $color = '#10b981'; // Emerald

    $calendarEvents[] = [
        'title' => $t['title'],
        'start' => $t['deadline'],
        'color' => $color,
        'url'   => 'tasks.php?search=' . urlencode($t['title'])
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="../css/custom.css">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-bg: #f3f4f6;
            --text-dark: #1f2937;
        }

        body { background-color: #f9fafb; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }

        /* Modern Calendar Container */
        #calendar { 
            max-width: 100%; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 24px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            font-family: system-ui, -apple-system, sans-serif;
        }

        /* --- FullCalendar Overrides for Modern Look --- */
        
        /* Toolbar */
        .fc-header-toolbar { margin-bottom: 2rem !important; }
        .fc-toolbar-title { font-weight: 800; font-size: 1.75rem !important; color: var(--text-dark); }
        
        /* Buttons */
        .fc-button {
            border-radius: 8px !important;
            font-weight: 600 !important;
            text-transform: capitalize;
            box-shadow: none !important;
            padding: 8px 16px !important;
        }
        .fc-button-primary { 
            background-color: var(--primary-color) !important; 
            border-color: var(--primary-color) !important; 
        }
        .fc-button-active { background-color: #4338ca !important; }
        
        /* Grid */
        .fc-theme-standard th { 
            padding: 15px 0; 
            background-color: #f9fafb; 
            color: #6b7280; 
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            border: none !important;
        }
        .fc-theme-standard td { border-color: #f3f4f6 !important; }
        
        /* Current Day */
        .fc-day-today { background-color: #f0fdf4 !important; /* Light Green Tint */ }

        /* Events */
        .fc-event { 
            border-radius: 6px; 
            padding: 2px 4px; 
            font-size: 0.85rem; 
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.1s;
        }
        .fc-event:hover { transform: scale(1.02); }
        .fc-event-time { font-weight: 700; margin-right: 4px; }
        
        /* Header Stats / Actions */
        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php echo displayNotifications(); ?>
        
        <div class="page-header">
            <div>
                <h1 class="h3 fw-bold mb-1">📅 Schedule</h1>
                <p class="text-muted mb-0">Manage your timeline and deadlines</p>
            </div>
            <div class="d-flex gap-2">
                <form method="POST">
                    <input type="hidden" name="action" value="sync_calendar">
                    <button class="btn btn-light text-primary border"><i class="bi bi-google me-2"></i>Sync</button>
                </form>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-plus-lg me-2"></i>Add Task
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div id='calendar'></div>
    </div>

    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Add New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_task">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TASK TITLE</label>
                            <input type="text" name="title" class="form-control form-control-lg" required placeholder="e.g. Physics Final Exam">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">DEADLINE</label>
                            <input type="datetime-local" name="deadline" id="eventDateInput" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'standard',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                // FIXED: Better time formatting (e.g., 2:30 PM)
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                },
                dayMaxEvents: true, // Allow "more" link when too many events
                events: <?php echo json_encode($calendarEvents); ?>,
                dateClick: function(info) {
                    var dateStr = info.dateStr;
                    var timeStr = new Date().toTimeString().substring(0,5);
                    document.getElementById('eventDateInput').value = dateStr + 'T' + timeStr;
                    var myModal = new bootstrap.Modal(document.getElementById('addEventModal'));
                    myModal.show();
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>
