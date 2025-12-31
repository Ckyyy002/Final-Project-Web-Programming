<?php
// pages/notes.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAuth();
$user = getCurrentUser();
$message = '';
$messageType = '';

// Database connection for notes
try {
    require_once '../config/db.php';
    $pdo = Database::getConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle note creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_note') {
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $tags = sanitize($_POST['tags'] ?? '');
    
    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, tags) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $content, $tags]);
            
            $message = "Note created successfully!";
            $messageType = 'success';
            
            // Add XP for creating notes
            require_once '../includes/notifications.php';
            $levelsGained = addUserXP($user['id'], 25);
            if ($levelsGained > 0) {
                showLevelUpNotification($levelsGained);
                $message .= " +25 XP & LEVEL UP! 🎉";
            } else {
                $message .= " +25 XP";
            }
            
        } catch (PDOException $e) {
            $message = "Error creating note: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = "Please fill in both title and content";
        $messageType = 'warning';
    }
}

// Handle note deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_note') {
    $noteId = $_POST['note_id'] ?? 0;
    
    if ($noteId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
            $stmt->execute([$noteId, $user['id']]);
            
            $message = "Note deleted!";
            $messageType = 'warning';
        } catch (PDOException $e) {
            $message = "Error deleting note: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch user's notes
try {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    $notes = [];
}

// Create notes table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        tags VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/custom.css">
    <style>
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .note-card { 
            transition: all 0.3s ease; 
            border-left: 4px solid #4361ee;
            height: 100%;
        }
        .note-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .note-content {
            max-height: 150px;
            overflow: hidden;
            position: relative;
        }
        .note-content.expanded {
            max-height: none;
        }
        .read-more {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, white);
            padding-top: 40px;
            text-align: center;
        }
        .tag-badge {
            font-size: 0.7rem;
            padding: 0.2em 0.6em;
            margin-right: 0.3em;
        }
        .note-preview {
            -webkit-line-clamp: 3;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php 
        require_once '../includes/notifications.php';
        echo displayNotifications(); 
        ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">📝 Notes & Quick Thoughts</h1>
                <p class="text-muted mb-0">Jot down ideas, formulas, or reminders</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNoteModal">
                <i class="bi bi-plus-circle me-2"></i>New Note
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Notes List -->
            <div class="col-lg-8">
                <?php if (empty($notes)): ?>
                    <div class="card shadow-sm text-center p-5">
                        <i class="bi bi-journal-text display-1 text-muted mb-3"></i>
                        <h4 class="text-muted">No notes yet</h4>
                        <p class="text-muted">Create your first note to get started!</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createNoteModal">
                            Create Note
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($notes as $note): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card note-card shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title fw-bold mb-0" style="font-size: 0.9rem;">
                                                <?php echo htmlspecialchars(substr($note['title'], 0, 30)); ?>
                                                <?php if(strlen($note['title']) > 30): ?>...<?php endif; ?>
                                            </h6>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" onclick="editNote(<?php echo $note['id']; ?>)">
                                                            <i class="bi bi-pencil me-2"></i>Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="p-0" onsubmit="return confirm('Delete this note?')">
                                                            <input type="hidden" name="action" value="delete_note">
                                                            <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="note-content mb-3">
                                            <p class="card-text text-muted small note-preview" id="note-<?php echo $note['id']; ?>">
                                                <?php echo htmlspecialchars($note['content']); ?>
                                            </p>
                                            <?php if(strlen($note['content']) > 150): ?>
                                                <button class="btn btn-link btn-sm p-0 read-more-btn" data-note-id="<?php echo $note['id']; ?>">
                                                    Read more
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($note['tags']): ?>
                                            <div class="mb-2">
                                                <?php 
                                                $tags = explode(',', $note['tags']);
                                                foreach(array_slice($tags, 0, 3) as $tag):
                                                    if(trim($tag)): ?>
                                                        <span class="badge tag-badge bg-light text-dark"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                    <?php endif;
                                                endforeach; 
                                                if(count($tags) > 3): ?>
                                                    <span class="badge tag-badge bg-secondary">+<?php echo count($tags) - 3; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo date('M d', strtotime($note['created_at'])); ?>
                                            </small>
                                            <span class="badge bg-light text-dark">
                                                <?php echo round(strlen($note['content']) / 100); ?> min read
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Note Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-lightning me-2"></i> Quick Note</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="quickNoteForm">
                            <input type="hidden" name="action" value="create_note">
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">TITLE</label>
                                <input type="text" name="title" class="form-control form-control-sm" 
                                       placeholder="e.g., Physics formulas" maxlength="50" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">CONTENT</label>
                                <textarea name="content" class="form-control" rows="5" 
                                          placeholder="Jot down your thoughts..." maxlength="1000" required></textarea>
                                <div class="form-text text-end small">
                                    <span id="charCount">0</span>/1000 characters
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">TAGS (comma separated)</label>
                                <input type="text" name="tags" class="form-control form-control-sm" 
                                       placeholder="physics, exam, formulas">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Note
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('quickNoteForm').reset(); document.getElementById('charCount').textContent = '0';">
                                    Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notes Stats -->
                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-3">Notes Overview</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Notes</span>
                            <span class="fw-bold"><?php echo count($notes); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">This Month</span>
                            <span class="fw-bold">
                                <?php 
                                $thisMonth = date('Y-m');
                                $monthCount = 0;
                                foreach($notes as $note) {
                                    if(date('Y-m', strtotime($note['created_at'])) == $thisMonth) {
                                        $monthCount++;
                                    }
                                }
                                echo $monthCount;
                                ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total Words</span>
                            <span class="fw-bold">
                                <?php
                                $totalWords = 0;
                                foreach($notes as $note) {
                                    $totalWords += str_word_count($note['content']);
                                }
                                echo number_format($totalWords);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Note Modal (Full Version) -->
    <div class="modal fade" id="createNoteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Create Detailed Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_note">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="Enter note title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="8" placeholder="Write your note here..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tags (comma separated)</label>
                            <input type="text" name="tags" class="form-control" placeholder="e.g., study, important, exam">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Character counter
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="content"]');
            const charCount = document.getElementById('charCount');
            
            if (textarea && charCount) {
                textarea.addEventListener('input', function() {
                    charCount.textContent = this.value.length;
                });
            }
            
            // Read more functionality
            document.querySelectorAll('.read-more-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const noteId = this.getAttribute('data-note-id');
                    const noteContent = document.getElementById('note-' + noteId);
                    const noteContainer = noteContent.parentElement;
                    
                    if (noteContainer.classList.contains('expanded')) {
                        noteContainer.classList.remove('expanded');
                        this.textContent = 'Read more';
                        noteContent.classList.add('note-preview');
                    } else {
                        noteContainer.classList.add('expanded');
                        this.textContent = 'Show less';
                        noteContent.classList.remove('note-preview');
                    }
                });
            });
        });
        
        function editNote(noteId) {
            // In a real app, this would fetch note data and populate an edit form
            alert('Edit functionality would open a form for note ID: ' + noteId);
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>