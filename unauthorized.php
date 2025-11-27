<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized - Algiers University</title>
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card" style="text-align: center;">
            <div class="login-logo" style="background: var(--danger-color);">
                <i class="fas fa-lock"></i>
            </div>
            <h1 style="margin-bottom: 1rem;">Access Denied</h1>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">You don't have permission to access this page.</p>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Go to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
