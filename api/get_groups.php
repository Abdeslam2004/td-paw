<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$courseId) {
    jsonResponse(['error' => 'Course ID required'], 400);
}

try {
    $db = Database::getInstance();
    $groups = $db->fetchAll("
        SELECT g.* FROM groups g
        JOIN course_groups cg ON g.id = cg.group_id
        WHERE cg.course_id = ?
        ORDER BY g.name
    ", [$courseId]);
    
    jsonResponse($groups);
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(['error' => 'Failed to fetch groups'], 500);
}
?>
