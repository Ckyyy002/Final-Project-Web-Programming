<?php
// auth/register.php
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(SITE_URL . 'pages/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        try {
            require_once '../config/db.php';
            $pdo = Database::getConnection();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, xp_points) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password_hash, 100]); // Start with 100 XP
                
                $userId = $pdo->lastInsertId();
                
                // Auto-login
                loginUser($userId);
                
                // Redirect to dashboard
                redirect(SITE_URL . 'pages/dashboard.php');
            }
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #4361ee, #3a0ca3); min-height: 100vh; }
        .register-card { max-width: 500px; margin: 50px auto; border-radius: 20px; }
        .premium-badge { background: linear-gradient(45deg, #f72585, #4361ee); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card register-card shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-primary">Join StudySync</h2>
                    <p class="text-muted">Start your productive student journey</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Benefits -->
                <div class="alert alert-info mb-4">
                    <h6 class="fw-bold"><i class="bi bi-stars me-2"></i>Get Started with Benefits:</h6>
                    <ul class="mb-0">
                        <li>100 Bonus XP Points</li>
                        <li>Access to Study Groups</li>
                        <li>Google Calendar Sync</li>
                        <li>Task Management Tools</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo $_POST['name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo $_POST['email'] ?? ''; ?>" required>
                        <div class="form-text">Use your university email for best experience</div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="bi bi-rocket-takeoff me-2"></i>Create Account & Start Learning
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-muted">Already have an account? 
                        <a href="login.php" class="text-decoration-none fw-bold">Sign In</a>
                    </p>
                    <a href="../index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Home
                    </a>
                </div>
                
                <!-- Or Google Sign Up -->
                <div class="text-center mt-4">
                    <hr class="text-muted">
                    <p class="text-muted mb-2">Or sign up with</p>
                    <a href="auth_google.php" class="btn btn-outline-dark">
                        <i class="bi bi-google me-2"></i>Google Account
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
