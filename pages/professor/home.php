<?php
$pageTitle = 'Professor Dashboard';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('professor');

$db = Database::getInstance();
$professorId = getUserId();

$courses = $db->fetchAll("
    SELECT c.*, 
           (SELECT COUNT(DISTINCT cg.group_id) FROM course_groups cg WHERE cg.course_id = c.id) as group_count,
           (SELECT COUNT(*) FROM attendance_sessions s WHERE s.course_id = c.id) as session_count
    FROM courses c 
    WHERE c.professor_id = ?
    ORDER BY c.name
", [$professorId]);

$recentSessions = $db->fetchAll("
    SELECT s.*, c.name as course_name, c.code as course_code, g.name as group_name
    FROM attendance_sessions s
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN groups g ON s.group_id = g.id
    WHERE c.professor_id = ?
    ORDER BY s.session_date DESC, s.created_at DESC
    LIMIT 5
", [$professorId]);

$totalStudents = $db->fetchOne("
    SELECT COUNT(DISTINCT sg.student_id) as count
    FROM student_groups sg
    JOIN course_groups cg ON sg.group_id = cg.group_id
    JOIN courses c ON cg.course_id = c.id
    WHERE c.professor_id = ?
", [$professorId])['count'] ?? 0;

$totalSessions = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM attendance_sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE c.professor_id = ?
", [$professorId])['count'] ?? 0;

$pendingJustifications = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM justifications j
    JOIN attendance_sessions s ON j.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE c.professor_id = ? AND j.status = 'pending'
", [$professorId])['count'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Welcome, Professor <?php echo htmlspecialchars($_SESSION['last_name']); ?></h1>
    <p class="page-subtitle">Manage your courses and track student attendance</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($courses); ?></h3>
            <p>Courses</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalStudents; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalSessions; ?></h3>
            <p>Sessions Created</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pendingJustifications; ?></h3>
            <p>Pending Justifications</p>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">My Courses</h2>
            <a href="sessions.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> New Session
            </a>
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h3>No courses assigned</h3>
                <p>You don't have any courses assigned yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <div class="session-card">
                    <div class="session-info">
                        <h4><?php echo htmlspecialchars($course['name']); ?></h4>
                        <p><strong><?php echo htmlspecialchars($course['code']); ?></strong> - <?php echo $course['group_count']; ?> groups</p>
                    </div>
                    <div class="session-meta">
                        <span class="badge badge-info"><?php echo $course['session_count']; ?> sessions</span>
                        <a href="sessions.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Sessions</h2>
            <a href="sessions.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        
        <?php if (empty($recentSessions)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar"></i>
                <h3>No sessions yet</h3>
                <p>Create your first attendance session.</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentSessions as $session): ?>
                <div class="session-card">
                    <div class="session-info">
                        <h4><?php echo htmlspecialchars($session['course_name']); ?></h4>
                        <p>
                            <?php echo date('M d, Y', strtotime($session['session_date'])); ?>
                            <?php if ($session['group_name']): ?>
                                - <?php echo htmlspecialchars($session['group_name']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="session-meta">
                        <span class="badge <?php echo $session['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo ucfirst($session['status']); ?>
                        </span>
                        <a href="mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-clipboard-check"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
