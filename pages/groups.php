<?php
// pages/groups.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php'; // Include notifications

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
            case 'create_group':
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $subject = sanitize($_POST['subject'] ?? '');
                $meetingSchedule = sanitize($_POST['meeting_schedule'] ?? '');
                $isPublic = isset($_POST['is_public']) ? 1 : 0;
                
                if (!empty($name)) {
                    $stmt = $pdo->prepare("INSERT INTO study_groups (name, description, creator_id, subject, meeting_schedule, is_public) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $user['id'], $subject, $meetingSchedule, $isPublic]);
                    $groupId = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'creator')");
                    $stmt->execute([$groupId, $user['id']]);
                    
                    $message = "Study group created!";
                    $messageType = 'success';
                }
                break;

            // --- NEW: EDIT GROUP LOGIC ---
            case 'edit_group':
                $groupId = $_POST['group_id'] ?? 0;
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $subject = sanitize($_POST['subject'] ?? '');
                $meetingSchedule = sanitize($_POST['meeting_schedule'] ?? '');
                $isPublic = isset($_POST['is_public']) ? 1 : 0;

                // Verify creator
                $check = $pdo->prepare("SELECT id FROM study_groups WHERE id = ? AND creator_id = ?");
                $check->execute([$groupId, $user['id']]);
                
                if ($check->rowCount() > 0) {
                    $stmt = $pdo->prepare("UPDATE study_groups SET name=?, description=?, subject=?, meeting_schedule=?, is_public=? WHERE id=?");
                    $stmt->execute([$name, $description, $subject, $meetingSchedule, $isPublic, $groupId]);
                    $message = "Group updated successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Unauthorized: You are not the creator.";
                    $messageType = 'danger';
                }
                break;

            // --- NEW: DELETE GROUP LOGIC ---
            case 'delete_group':
                $groupId = $_POST['group_id'] ?? 0;
                // Verify creator
                $check = $pdo->prepare("SELECT id FROM study_groups WHERE id = ? AND creator_id = ?");
                $check->execute([$groupId, $user['id']]);

                if ($check->rowCount() > 0) {
                    // Manual Cascade Delete (Safer for simple DBs)
                    $pdo->prepare("DELETE FROM group_chat_messages WHERE group_id = ?")->execute([$groupId]);
                    $pdo->prepare("DELETE FROM group_members WHERE group_id = ?")->execute([$groupId]);
                    $pdo->prepare("DELETE FROM study_groups WHERE id = ?")->execute([$groupId]);
                    
                    $message = "Group deleted permanently.";
                    $messageType = 'warning';
                }
                break;
                
            case 'join_group':
                $groupId = $_POST['group_id'] ?? 0;
                if ($groupId) {
                    $stmt = $pdo->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
                    $stmt->execute([$groupId, $user['id']]);
                    
                    if ($stmt->rowCount() === 0) {
                        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                        $stmt->execute([$groupId, $user['id']]);
                        
                        // XP Logic
                        $levelsGained = addUserXP($user['id'], 100);
                        if ($levelsGained > 0) {
                            showLevelUpNotification($levelsGained);
                            $message = "Joined! +100 XP & LEVEL UP! 🎉";
                        } else {
                            $message = "Joined! +100 XP";
                        }
                        $messageType = 'success';
                        
                        // Redirect to chat
                        header("Location: group_detail.php?id=" . $groupId);
                        exit;
                    }
                }
                break;
                
            case 'leave_group':
                $groupId = $_POST['group_id'] ?? 0;
                if ($groupId) {
                    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ? AND role != 'creator'");
                    $stmt->execute([$groupId, $user['id']]);
                    if ($stmt->rowCount() > 0) {
                        $message = "Left study group";
                        $messageType = 'warning';
                    } else {
                        $message = "Creator cannot leave. Delete the group instead.";
                        $messageType = 'danger';
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get user's groups and available groups
try {
    require_once '../config/db.php';
    $pdo = Database::getConnection();
    
    // Get user's groups
    $stmt = $pdo->prepare("
        SELECT sg.*, gm.role 
        FROM study_groups sg
        JOIN group_members gm ON sg.id = gm.group_id
        WHERE gm.user_id = ?
        ORDER BY sg.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $userGroups = $stmt->fetchAll();
    
    // Get available public groups (not joined)
    $stmt = $pdo->prepare("
        SELECT sg.*, 
               (SELECT COUNT(*) FROM group_members WHERE group_id = sg.id) as member_count
        FROM study_groups sg
        WHERE sg.is_public = 1 
        AND sg.id NOT IN (
            SELECT group_id FROM group_members WHERE user_id = ?
        )
        ORDER BY sg.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $availableGroups = $stmt->fetchAll();
    
    // Get group members for user's groups
    $groupMembers = [];
    foreach ($userGroups as $group) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.profile_picture, gm.role
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ?
        ");
        $stmt->execute([$group['id']]);
        $groupMembers[$group['id']] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Groups query error: " . $e->getMessage());
    $userGroups = [];
    $availableGroups = [];
    $groupMembers = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    
    <style>
        .group-card {
            position: relative;
            overflow: visible !important; /* Allows edit dropdowns to show */
            z-index: 1;
        }
        
        .group-card:hover { z-index: 50; }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-left: -10px;
        }
        
        .member-avatar:first-child {
            margin-left: 0;
        }
        
        .subject-badge {
            background: linear-gradient(45deg, #4361ee, #3a0ca3);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">👥 Study Groups</h1>
                <p class="text-muted mb-0">Collaborate and learn together</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="bi bi-plus-circle me-2"></i>Create Group
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Your Study Groups</h5>
            </div>
            <div class="card-body">
                <?php if (empty($userGroups)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people fs-1 text-muted mb-3"></i>
                        <h4>No groups yet</h4>
                        <p class="text-muted">Create or join a study group to get started!</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($userGroups as $group): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card group-card h-100 border border-primary">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <a href="group_detail.php?id=<?php echo $group['id']; ?>" class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </a>
                                                </h5>
                                                <?php if ($group['subject']): ?>
                                                    <span class="badge subject-badge mb-2"><?php echo htmlspecialchars($group['subject']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-<?php echo $group['role'] === 'creator' ? 'success' : 'info'; ?>">
                                                    <?php echo ucfirst($group['role']); ?>
                                                </span>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="group_detail.php?id=<?php echo $group['id']; ?>">
                                                                <i class="bi bi-chat me-2"></i>Open Chat
                                                            </a>
                                                        </li>
                                                        
                                                        <?php if ($group['role'] === 'creator'): ?>
                                                            <li>
                                                                <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode($group); ?>)'>
                                                                    <i class="bi bi-pencil me-2"></i>Edit Group
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="dropdown-item p-0" onsubmit="return confirm('WARNING: This will delete the group and all chat history. Are you sure?')">
                                                                    <input type="hidden" name="action" value="delete_group">
                                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete Group</button>
                                                                </form>
                                                            </li>
                                                        <?php else: ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="dropdown-item p-0" onsubmit="return confirm('Leave this group?')">
                                                                    <input type="hidden" name="action" value="leave_group">
                                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Leave Group</button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($group['description']): ?>
                                            <p class="card-text text-muted small mb-3">
                                                <?php echo htmlspecialchars(substr($group['description'], 0, 100)); ?>
                                                <?php if (strlen($group['description']) > 100): ?>...<?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($group['meeting_schedule']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php echo htmlspecialchars($group['meeting_schedule']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1">Members:</small>
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex">
                                                    <?php 
                                                    $members = $groupMembers[$group['id']] ?? [];
                                                    $displayCount = min(4, count($members));
                                                    for ($i = 0; $i < $displayCount; $i++): 
                                                    ?>
                                                        <div class="member-avatar bg-<?php echo ['primary', 'success', 'warning', 'danger'][$i % 4]; ?> d-flex align-items-center justify-content-center text-white fw-bold">
                                                            <?php echo strtoupper(substr($members[$i]['name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                                <?php if (count($members) > 4): ?>
                                                    <small class="text-muted ms-2">+<?php echo count($members) - 4; ?> more</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-people me-1"></i>
                                                <?php echo count($members); ?> members
                                            </small>
                                            
                                            <?php if ($group['role'] !== 'creator'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Leave this group?')">
                                                    <input type="hidden" name="action" value="leave_group">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Leave</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-compass me-2"></i>Discover Groups</h5>
            </div>
            <div class="card-body">
                <?php if (empty($availableGroups)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-search fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No public groups available to join</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($availableGroups as $group): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card group-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($group['name']); ?></h5>
                                        <?php if ($group['subject']): ?>
                                            <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($group['subject']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($group['description']): ?>
                                            <p class="card-text text-muted small mb-3">
                                                <?php echo htmlspecialchars(substr($group['description'], 0, 100)); ?>...
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <small class="text-muted"><i class="bi bi-people me-1"></i><?php echo $group['member_count']; ?> members</small>
                                            <?php if ($group['meeting_schedule']): ?>
                                                <small class="text-muted"><i class="bi bi-calendar me-1"></i><?php echo htmlspecialchars($group['meeting_schedule']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="action" value="join_group">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-person-plus me-2"></i>Join Group</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="modal fade" id="createGroupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title">Create Study Group</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="create_group">
                            <div class="mb-3"><label class="form-label">Group Name *</label><input type="text" class="form-control" name="name" required placeholder="e.g., Advanced Calculus Study Group"></div>
                            <div class="mb-3"><label class="form-label">Subject/Course</label><input type="text" class="form-control" name="subject" placeholder="e.g., Mathematics, Computer Science"></div>
                            <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="What will this group focus on?"></textarea></div>
                            <div class="mb-3"><label class="form-label">Meeting Schedule</label><input type="text" class="form-control" name="meeting_schedule" placeholder="e.g., Every Monday 2-4 PM, Virtual"></div>
                            <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="is_public" id="is_public" checked><label class="form-check-label" for="is_public">Public Group (others can discover and join)</label></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Group</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editGroupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header"><h5 class="modal-title">Edit Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_group">
                            <input type="hidden" name="group_id" id="edit_group_id">
                            
                            <div class="mb-3"><label class="form-label">Group Name *</label><input type="text" class="form-control" name="name" id="edit_name" required></div>
                            <div class="mb-3"><label class="form-label">Subject/Course</label><input type="text" class="form-control" name="subject" id="edit_subject"></div>
                            <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" id="edit_description" rows="3"></textarea></div>
                            <div class="mb-3"><label class="form-label">Meeting Schedule</label><input type="text" class="form-control" name="meeting_schedule" id="edit_schedule"></div>
                            <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="is_public" id="edit_public"><label class="form-check-label" for="edit_public">Public Group</label></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS to populate the edit modal
        function openEditModal(group) {
            document.getElementById('edit_group_id').value = group.id;
            document.getElementById('edit_name').value = group.name;
            document.getElementById('edit_subject').value = group.subject;
            document.getElementById('edit_description').value = group.description;
            document.getElementById('edit_schedule').value = group.meeting_schedule;
            document.getElementById('edit_public').checked = (group.is_public == 1);
            
            new bootstrap.Modal(document.getElementById('editGroupModal')).show();
        }
    </script>
</body>
</html>