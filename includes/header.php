<?php
require_once __DIR__ . '/config.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$role = getUserRole();
$firstName = $_SESSION['first_name'] ?? 'User';
$lastName = $_SESSION['last_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - Algiers University</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="<?php echo BASE_URL; ?>index.php" class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Algiers University</span>
            </a>
            
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-menu">
                <?php if ($role === 'professor'): ?>
                    <a href="<?php echo BASE_URL; ?>pages/professor/home.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/professor/sessions.php" class="<?php echo $currentPage === 'sessions' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i> Sessions
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/professor/summary.php" class="<?php echo $currentPage === 'summary' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Summary
                    </a>
                <?php elseif ($role === 'student'): ?>
                    <a href="<?php echo BASE_URL; ?>pages/student/home.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> My Courses
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/student/attendance.php" class="<?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i> Attendance
                    </a>
                <?php elseif ($role === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>pages/admin/home.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/admin/statistics.php" class="<?php echo $currentPage === 'statistics' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i> Statistics
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/admin/students.php" class="<?php echo $currentPage === 'students' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Students
                    </a>
                <?php endif; ?>
                
                <div class="user-menu">
                    <a href="#" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($firstName); ?>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </a>
                    <div class="user-dropdown">
                        <a href="<?php echo BASE_URL; ?>profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="<?php echo BASE_URL; ?>logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
        <div class="container">
