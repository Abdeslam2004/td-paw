<?php
session_start();

define('BASE_URL', '/');
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/justifications/');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$log_file = __DIR__ . '/../logs/error.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array(getUserRole(), $roles)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit;
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
