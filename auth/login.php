<?php
// auth/login.php - Modern Eye-catching Login Page
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(SITE_URL . 'pages/dashboard.php');
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    try {
        require_once '../config/db.php';
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user['id'], $remember);
            
            // Redirect to intended page or dashboard
            $redirect = $_SESSION['redirect_to'] ?? SITE_URL . 'pages/dashboard.php';
            unset($_SESSION['redirect_to']);
            redirect($redirect);
            
        } else {
            $error = "Invalid email or password";
        }
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "Login failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --gradient: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow-x: hidden;
        }
        
        .login-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero-section {
            background: var(--gradient);
            border-radius: 20px 0 0 20px;
            color: white;
            padding: 60px 40px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.3;
            animation: float 20s linear infinite;
        }
        
        .login-card {
            background: white;
            border-radius: 0 20px 20px 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .brand-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 2.5rem;
            margin-bottom: 20px;
            display: inline-block;
            background: linear-gradient(45deg, #fff, #ffd166);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .feature-icon:hover {
            transform: translateY(-5px) scale(1.1);
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .btn-google {
            background: white;
            color: #444;
            border: 2px solid #ddd;
            border-radius: 50px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-google:hover {
            background: #f8f9fa;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
        }
        
        .google-icon {
            background: conic-gradient(from -45deg, #ea4335 110deg, #4285f4 90deg 180deg, #34a853 180deg 270deg, #fbbc05 270deg) 73% 55%/150% 150% no-repeat;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.2rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .btn-login {
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 8px 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
            backdrop-filter: blur(5px);
        }
        
        .floating-1 { top: 10%; left: 10%; width: 60px; height: 60px; animation-delay: 0s; }
        .floating-2 { top: 70%; right: 15%; width: 40px; height: 40px; animation-delay: 1s; }
        .floating-3 { bottom: 20%; left: 20%; width: 30px; height: 30px; animation-delay: 2s; }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            font-weight: 700;
            color: #212529;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #6c757d;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dee2e6;
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                border-radius: 20px 20px 0 0;
                padding: 40px 20px;
            }
            
            .login-card {
                border-radius: 0 0 20px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid login-container">
        <div class="row g-0">
            <div class="col-lg-6">
                <div class="hero-section">
                    <div class="floating-element floating-1"></div>
                    <div class="floating-element floating-2"></div>
                    <div class="floating-element floating-3"></div>
                    
                    <div class="position-relative" style="z-index: 2">
                        <h1 class="brand-logo">StudySync</h1>
                        <p class="lead mb-5 opacity-90">Your all-in-one student productivity hub</p>
                        
                        <div class="mb-5">
                            <div class="stats-badge">
                                <i class="bi bi-rocket-takeoff"></i>
                                <span>10K+ Active Students</span>
                            </div>
                            <div class="stats-badge">
                                <i class="bi bi-trophy"></i>
                                <span>4.9★ Rating</span>
                            </div>
                            <div class="stats-badge">
                                <i class="bi bi-lightning-charge"></i>
                                <span>98% Productivity Boost</span>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="bi bi-kanban"></i>
                                    </div>
                                    <h5 class="text-white">Smart Tasks</h5>
                                    <p class="small opacity-90 mb-0">AI-powered task management</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <h5 class="text-white">Study Groups</h5>
                                    <p class="small opacity-90 mb-0">Collaborate with classmates</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="bi bi-trophy"></i>
                                    </div>
                                    <h5 class="text-white">Gamified XP</h5>
                                    <p class="small opacity-90 mb-0">Earn rewards for progress</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="bi bi-google"></i>
                                    </div>
                                    <h5 class="text-white">Google Sync</h5>
                                    <p class="small opacity-90 mb-0">Calendar & Drive integration</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-5 text-center">
                            <div class="p-4 rounded" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                                <i class="bi bi-star-fill text-warning fs-4"></i>
                                <p class="mb-0 mt-2 small opacity-90">Join thousands of students boosting their productivity</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="login-card p-4 p-md-5">
                    <div class="login-header">
                        <h2>Welcome Back! 👋</h2>
                        <p>Sign in to continue your productivity journey</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <a href="auth_google.php" class="btn btn-google mb-4">
                        <i class="bi bi-google google-icon"></i>
                        <span>Continue with Google</span>
                    </a>
                    
                    <div class="divider">
                        <span>Or continue with email</span>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" 
                                       placeholder="student@university.edu" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <a href="#" class="forgot-link small">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" 
                                        onclick="togglePassword()">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me for 30 days</label>
                        </div>
                        
                        <button type="submit" class="btn btn-login mb-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted mb-2">New to StudySync?</p>
                        <a href="register.php" class="btn btn-outline-primary px-4">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </a>
                        <div class="mt-3">
                            <a href="../index.php" class="text-decoration-none small">
                                <i class="bi bi-arrow-left me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            Your data is securely encrypted
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = event.currentTarget;
            const icon = toggleButton.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
            
            // Add subtle animations to feature icons
            const featureIcons = document.querySelectorAll('.feature-icon');
            featureIcons.forEach((icon, index) => {
                setTimeout(() => {
                    icon.style.animationDelay = `${index * 0.2}s`;
                }, 100);
            });
            
            // Animate stats badges on hover
            const statsBadges = document.querySelectorAll('.stats-badge');
            statsBadges.forEach(badge => {
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Add loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Signing in...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });
    </script>
</body>
</html>