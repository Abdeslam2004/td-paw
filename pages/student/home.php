<?php
$pageTitle = 'My Courses';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('student');

$db = Database::getInstance();
$studentId = getUserId();

$courses = $db->fetchAll("
    SELECT c.*, u.first_name as prof_first, u.last_name as prof_last,
           (SELECT COUNT(*) FROM attendance_sessions s 
            JOIN course_groups cg ON s.course_id = cg.course_id 
            WHERE cg.course_id = c.id) as total_sessions,
           (SELECT COUNT(*) FROM attendance_records ar 
            JOIN attendance_sessions s ON ar.session_id = s.id 
            WHERE s.course_id = c.id AND ar.student_id = ? AND ar.status = 'present') as present_count
    FROM courses c
    JOIN course_groups cg ON c.id = cg.course_id
    JOIN student_groups sg ON cg.group_id = sg.group_id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE sg.student_id = ?
    GROUP BY c.id, c.code, c.name, c.description, c.professor_id, c.credits, c.created_at, u.first_name, u.last_name
    ORDER BY c.name
", [$studentId, $studentId]);

$attendanceStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT s.id) as total_sessions,
        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_id = cg.course_id
    JOIN student_groups sg ON cg.group_id = sg.group_id
    LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = sg.student_id
    WHERE sg.student_id = ?
", [$studentId]);

$pendingJustifications = $db->fetchOne("
    SELECT COUNT(*) as count FROM justifications WHERE student_id = ? AND status = 'pending'
", [$studentId])['count'] ?? 0;

$recentAttendance = $db->fetchAll("
    SELECT ar.*, s.session_date, s.session_type, c.name as course_name, c.code as course_code
    FROM attendance_records ar
    JOIN attendance_sessions s ON ar.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE ar.student_id = ?
    ORDER BY s.session_date DESC
    LIMIT 5
", [$studentId]);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
    <p class="page-subtitle">View your enrolled courses and track your attendance</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($courses); ?></h3>
            <p>Enrolled Courses</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $attendanceStats['present_count'] ?? 0; ?></h3>
            <p>Present</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $attendanceStats['absent_count'] ?? 0; ?></h3>
            <p>Absent</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
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
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses enrolled</h3>
                <p>You are not enrolled in any courses yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <?php 
                $attendanceRate = $course['total_sessions'] > 0 
                    ? round(($course['present_count'] / $course['total_sessions']) * 100, 1) 
                    : 0;
                $rateClass = $attendanceRate >= 75 ? 'success' : ($attendanceRate >= 50 ? 'warning' : 'danger');
                ?>
                <div class="session-card">
                    <div class="session-info">
                        <h4><?php echo htmlspecialchars($course['name']); ?></h4>
                        <p>
                            <strong><?php echo htmlspecialchars($course['code']); ?></strong>
                            <?php if ($course['prof_first']): ?>
                                - Prof. <?php echo htmlspecialchars($course['prof_last']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="session-meta">
                        <div style="text-align: right;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <div class="progress-bar" style="width: 60px;">
                                    <div class="progress-fill <?php echo $rateClass; ?>" style="width: <?php echo $attendanceRate; ?>%;"></div>
                                </div>
                                <span style="font-size: 0.875rem;"><?php echo $attendanceRate; ?>%</span>
                            </div>
                            <small style="color: var(--text-secondary);"><?php echo $course['present_count']; ?>/<?php echo $course['total_sessions']; ?> sessions</small>
                        </div>
                        <a href="attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Attendance</h2>
            <a href="attendance.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        
        <?php if (empty($recentAttendance)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No records yet</h3>
                <p>Your attendance records will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentAttendance as $record): ?>
                <div class="session-card">
                    <div class="session-info">
                        <h4><?php echo htmlspecialchars($record['course_name']); ?></h4>
                        <p><?php echo date('M d, Y', strtotime($record['session_date'])); ?> - <?php echo ucfirst($record['session_type']); ?></p>
                    </div>
                    <span class="badge <?php 
                        echo $record['status'] === 'present' ? 'badge-success' : 
                            ($record['status'] === 'late' ? 'badge-warning' : 
                            ($record['status'] === 'excused' ? 'badge-info' : 'badge-danger')); 
                    ?>">
                        <?php echo ucfirst($record['status']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
