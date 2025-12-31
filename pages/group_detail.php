<?php
// pages/group_detail.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Add these 3 lines for debugging:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAuth();
$user = getCurrentUser();
$groupId = $_GET['id'] ?? 0;

if (!$groupId) { header('Location: groups.php'); exit; }

require_once '../config/db.php';
$pdo = Database::getConnection();

// --- AJAX API HANDLER (For Real-time Chat) ---
if (isset($_GET['fetch_messages'])) {
    header('Content-Type: application/json');
    $lastId = $_GET['last_id'] ?? 0;
    
    // Check membership
    $check = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
    $check->execute([$groupId, $user['id']]);
    if (!$check->fetch()) { echo json_encode([]); exit; }

    $stmt = $pdo->prepare("
        SELECT cm.*, u.name 
        FROM group_chat_messages cm 
        JOIN users u ON cm.user_id = u.id 
        WHERE cm.group_id = ? AND cm.id > ? 
        ORDER BY cm.created_at ASC
    ");
    $stmt->execute([$groupId, $lastId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
// ---------------------------------------------

// Get Group Info
$stmt = $pdo->prepare("SELECT sg.*, gm.role FROM study_groups sg JOIN group_members gm ON sg.id = gm.group_id WHERE sg.id = ? AND gm.user_id = ?");
$stmt->execute([$groupId, $user['id']]);
$group = $stmt->fetch();

if (!$group) { header('Location: groups.php'); exit; }

// --- HANDLE POST (Send Message + File Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $msg = sanitize($_POST['message']);
    $attachment = null;
    $attachmentType = null;

    // 1. Handle File Upload
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] == 0) {
        $uploadDir = '../uploads/chat/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = time() . '_' . basename($_FILES['chat_file']['name']);
        if (move_uploaded_file($_FILES['chat_file']['tmp_name'], $uploadDir . $fileName)) {
            $attachment = $fileName;
            // Simple type check (image or file)
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $attachmentType = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : 'file';
        }
    }

    // 2. Insert Message (Only if text or file exists)
    if (!empty($msg) || $attachment) {
        $ins = $pdo->prepare("INSERT INTO group_chat_messages (group_id, user_id, message, attachment, attachment_type) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$groupId, $user['id'], $msg, $attachment, $attachmentType]);
        
        $pdo->prepare("UPDATE study_groups SET last_activity = NOW() WHERE id = ?")->execute([$groupId]);
    }
}

// Initial Load of Messages
$stmt = $pdo->prepare("SELECT cm.*, u.name FROM group_chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.group_id = ? ORDER BY cm.created_at DESC LIMIT 50");
$stmt->execute([$groupId]);
$chatMessages = array_reverse($stmt->fetchAll());

// Fetch members
try {
    // FIXED: Changed 'username' to 'name' and 'profile_pic' to 'profile_picture'
    $memStmt = $pdo->prepare("SELECT u.name, u.profile_picture FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ?");
    $memStmt->execute([$groupId]);
    $members = $memStmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error (Members): " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .chat-container { height: 400px; overflow-y: auto; background: #f8f9fa; border-radius: 10px; padding: 15px; border: 1px solid #dee2e6; }
        .chat-message { margin-bottom: 10px; padding: 8px 12px; border-radius: 15px; max-width: 75%; }
        .message-sent { background: #4361ee; color: white; margin-left: auto; border-bottom-right-radius: 2px; }
        .message-received { background: white; border: 1px solid #dee2e6; margin-right: auto; border-bottom-left-radius: 2px; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        /* Fix for Blocked Edit Buttons */
        .group-card { position: relative; overflow: visible !important; z-index: 1; }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><?php echo htmlspecialchars($group['name']); ?></h2>
            <a href="groups.php" class="btn btn-outline-secondary">Back</a>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm group-card">
                    <div class="card-header bg-primary text-white">Chat Room</div>
                    <div class="card-body">
                        <div class="chat-container mb-3" id="chatContainer">
                            <?php foreach($chatMessages as $msg): ?>
                                <?php $isMe = $msg['user_id'] == $user['id']; ?>
                                <div class="chat-message <?php echo $isMe ? 'message-sent' : 'message-received'; ?>">
                                    <?php if(!$isMe): ?><small class="fw-bold d-block"><?php echo htmlspecialchars($msg['name']); ?></small><?php endif; ?>
                                    
                                    <div><?php echo htmlspecialchars($msg['message']); ?></div>

                                    <?php if (!empty($msg['attachment'])): ?>
                                        <div class="mt-2 pt-2 border-top <?php echo $isMe ? 'border-white' : 'border-secondary'; ?>" style="border-opacity: 0.2;">
                                            <?php if($msg['attachment_type'] === 'image'): ?>
                                                <img src="../uploads/chat/<?php echo $msg['attachment']; ?>" class="img-fluid rounded" style="max-height: 150px;">
                                            <?php else: ?>
                                                <a href="../uploads/chat/<?php echo $msg['attachment']; ?>" target="_blank" class="<?php echo $isMe ? 'text-white' : 'text-primary'; ?> text-decoration-none">
                                                    <i class="bi bi-file-earmark-arrow-down"></i> Download File
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <form method="POST" id="chatForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="send_message">
                            
                            <div class="input-group">
                                <input type="file" name="chat_file" id="chatFile" style="display: none;" onchange="this.form.submit()">
                                
                                <button type="button" class="btn btn-light border" onclick="document.getElementById('chatFile').click()">
                                    <i class="bi bi-paperclip"></i>
                                </button>
                                
                                <input type="text" name="message" id="messageInput" class="form-control" placeholder="Type a message..." autocomplete="off">
                                <button class="btn btn-primary"><i class="bi bi-send"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-sm group-card">
                    <div class="card-header">Group Info</div>
                    <div class="card-body">
                        <p><?php echo htmlspecialchars($group['description']); ?></p>
                        <hr>
                        <strong>Subject:</strong> <?php echo htmlspecialchars($group['subject']); ?><br>
                        <strong>Schedule:</strong> <?php echo htmlspecialchars($group['meeting_schedule']); ?>
                        <hr>
                        <div class="group-members-bar p-2 bg-light rounded d-flex align-items-center gap-2 flex-wrap">
                            <small class="text-muted fw-bold">MEMBERS:</small>
                            <?php foreach($members as $m): ?>
                                <img src="../uploads/avatars/<?php echo $m['profile_picture'] ?? 'default.png'; ?>" 
                                    title="<?php echo htmlspecialchars($m['name']); ?>"
                                    class="rounded-circle border" width="30" height="30"
                                    style="object-fit: cover;">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chatContainer = document.getElementById('chatContainer');
        const groupId = <?php echo $groupId; ?>;
        const currentUserId = <?php echo $user['id']; ?>;
        let lastId = <?php echo empty($chatMessages) ? 0 : end($chatMessages)['id']; ?>;

        function scrollToBottom() {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        scrollToBottom();

        // Updated Poll logic to show attachments
        setInterval(() => {
            fetch(`group_detail.php?id=${groupId}&fetch_messages=1&last_id=${lastId}`)
                .then(r => r.json())
                .then(data => {
                    if(data.length > 0) {
                        data.forEach(msg => {
                            const isMe = msg.user_id == currentUserId;
                            const div = document.createElement('div');
                            div.className = `chat-message ${isMe ? 'message-sent' : 'message-received'}`;
                            
                            let attachmentHtml = '';
                            if(msg.attachment) {
                                attachmentHtml = msg.attachment_type === 'image' 
                                    ? `<br><img src="../uploads/chat/${msg.attachment}" class="img-fluid rounded mt-2" style="max-height: 150px;">`
                                    : `<br><a href="../uploads/chat/${msg.attachment}" target="_blank" class="${isMe?'text-white':'text-primary'}"><i class="bi bi-download"></i> File</a>`;
                            }

                            div.innerHTML = (isMe ? '' : `<small class="fw-bold d-block">${msg.name}</small>`) + msg.message + attachmentHtml;
                            chatContainer.appendChild(div);
                            lastId = msg.id;
                        });
                        scrollToBottom();
                    }
                });
        }, 2000);
    </script>
</body>
</html>