<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/init_db.php';

initializeDatabase();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$role = getUserRole();

switch ($role) {
    case 'professor':
        header('Location: pages/professor/home.php');
        break;
    case 'student':
        header('Location: pages/student/home.php');
        break;
    case 'admin':
        header('Location: pages/admin/home.php');
        break;
    default:
        header('Location: login.php');
}
exit;
?>
