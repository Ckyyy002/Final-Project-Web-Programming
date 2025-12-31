<?php
// index.php - Landing Page
require_once 'config/constants.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Student Productivity & Social Hub</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Custom styles from the landing page */
        :root { --primary: #4361ee; --secondary: #3a0ca3; --accent: #f72585; }
        .hero-gradient { background: linear-gradient(135deg, var(--primary), var(--secondary)); }
        .glassmorphism { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .feature-icon { width: 80px; height: 80px; border-radius: 20px; background: linear-gradient(135deg, var(--primary), var(--accent)); }
        .btn-google { background: white; color: #444; border: 1px solid #ddd; border-radius: 50px; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="#">
                <span class="text-primary">Study</span><span class="text-accent">Sync</span>
            </a>
            <a href="auth/login.php" class="btn btn-primary">Get Started</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-white py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold mb-4">
                        Supercharge Your <span class="text-warning">Student Life</span>
                    </h1>
                    <p class="lead mb-4">
                        The ultimate platform combining productivity tools, social networking, 
                        and Google integration for your academic journey.
                    </p>
                    <a href="auth/login.php" class="btn btn-light btn-lg px-4 py-3 fw-bold">
                        <i class="bi bi-google me-2"></i>Start Free with Google
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Rest of landing page... -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
