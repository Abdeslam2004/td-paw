<?php
$pageTitle = 'Mark Attendance';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('professor');

$db = Database::getInstance();
$professorId = getUserId();
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

$session = $db->fetchOne("
    SELECT s.*, c.name as course_name, c.code as course_code, g.name as group_name
    FROM attendance_sessions s
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN groups g ON s.group_id = g.id
    WHERE s.id = ? AND c.professor_id = ?
", [$sessionId, $professorId]);

if (!$session) {
    header('Location: sessions.php');
    exit;
}

if ($session['group_id']) {
    $students = $db->fetchAll("
        SELECT u.*, sg.group_id,
               ar.status as attendance_status, ar.participation_score, ar.behavior_note
        FROM users u
        JOIN student_groups sg ON u.id = sg.student_id
        LEFT JOIN attendance_records ar ON ar.student_id = u.id AND ar.session_id = ?
        WHERE sg.group_id = ? AND u.role = 'student'
        ORDER BY u.last_name, u.first_name
    ", [$sessionId, $session['group_id']]);
} else {
    $students = $db->fetchAll("
        SELECT DISTINCT u.*, sg.group_id,
               ar.status as attendance_status, ar.participation_score, ar.behavior_note
        FROM users u
        JOIN student_groups sg ON u.id = sg.student_id
        JOIN course_groups cg ON sg.group_id = cg.group_id
        LEFT JOIN attendance_records ar ON ar.student_id = u.id AND ar.session_id = ?
        WHERE cg.course_id = ? AND u.role = 'student'
        ORDER BY u.last_name, u.first_name
    ", [$sessionId, $session['course_id']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $studentId => $status) {
            $participation = isset($_POST['participation'][$studentId]) ? (int)$_POST['participation'][$studentId] : 0;
            $behavior = isset($_POST['behavior'][$studentId]) ? sanitize($_POST['behavior'][$studentId]) : '';
            
            $exists = $db->fetchOne("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?", [$sessionId, $studentId]);
            
            if ($exists) {
                $db->query("
                    UPDATE attendance_records 
                    SET status = ?, participation_score = ?, behavior_note = ?, marked_at = CURRENT_TIMESTAMP, marked_by = ?
                    WHERE session_id = ? AND student_id = ?
                ", [$status, $participation, $behavior, $professorId, $sessionId, $studentId]);
            } else {
                $db->query("
                    INSERT INTO attendance_records (session_id, student_id, status, participation_score, behavior_note, marked_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$sessionId, $studentId, $status, $participation, $behavior, $professorId]);
            }
        }
        
        header("Location: mark_attendance.php?session_id=$sessionId&saved=1");
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title">Mark Attendance</h1>
        <p class="page-subtitle">
            <?php echo htmlspecialchars($session['course_name']); ?> 
            (<?php echo htmlspecialchars($session['course_code']); ?>)
            - <?php echo date('F d, Y', strtotime($session['session_date'])); ?>
            <?php if ($session['group_name']): ?>
                - <?php echo htmlspecialchars($session['group_name']); ?>
            <?php endif; ?>
        </p>
    </div>
    <div>
        <a href="sessions.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Attendance saved successfully!</span>
    </div>
<?php endif; ?>

<?php if ($session['status'] === 'closed'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-lock"></i>
        <span>This session is closed. Attendance records are read-only.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Students (<?php echo count($students); ?>)</h2>
        <?php if ($session['status'] === 'open'): ?>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-success btn-sm" id="markAllPresent">
                    <i class="fas fa-check-double"></i> All Present
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="markAllAbsent">
                    <i class="fas fa-times"></i> All Absent
                </button>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No students found</h3>
            <p>No students are enrolled in this course/group.</p>
        </div>
    <?php else: ?>
        <form method="POST" id="attendanceForm">
            <ul class="attendance-list">
                <?php foreach ($students as $student): ?>
                    <li class="attendance-item">
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?></strong>
                                <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($student['student_id'] ?? $student['email']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                            <div class="attendance-actions">
                                <button type="button" class="attendance-btn present <?php echo ($student['attendance_status'] ?? '') === 'present' ? 'active' : ''; ?>" 
                                        data-student="<?php echo $student['id']; ?>" data-status="present"
                                        <?php echo $session['status'] === 'closed' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check"></i> Present
                                </button>
                                <button type="button" class="attendance-btn absent <?php echo ($student['attendance_status'] ?? 'absent') === 'absent' ? 'active' : ''; ?>" 
                                        data-student="<?php echo $student['id']; ?>" data-status="absent"
                                        <?php echo $session['status'] === 'closed' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-times"></i> Absent
                                </button>
                                <button type="button" class="attendance-btn late <?php echo ($student['attendance_status'] ?? '') === 'late' ? 'active' : ''; ?>" 
                                        data-student="<?php echo $student['id']; ?>" data-status="late"
                                        <?php echo $session['status'] === 'closed' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-clock"></i> Late
                                </button>
                                <input type="hidden" name="attendance[<?php echo $student['id']; ?>]" 
                                       value="<?php echo $student['attendance_status'] ?? 'absent'; ?>"
                                       id="status_<?php echo $student['id']; ?>">
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <label style="font-size: 0.875rem; color: var(--text-secondary);">Participation:</label>
                                <select name="participation[<?php echo $student['id']; ?>]" class="form-control" style="width: 70px; padding: 0.5rem;"
                                        <?php echo $session['status'] === 'closed' ? 'disabled' : ''; ?>>
                                    <?php for ($i = 0; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($student['participation_score'] ?? 0) == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ($session['status'] === 'open'): ?>
                <div style="padding: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('.attendance-btn').on('click', function() {
        const studentId = $(this).data('student');
        const status = $(this).data('status');
        
        $(this).siblings('.attendance-btn').removeClass('active');
        $(this).addClass('active');
        $(`#status_${studentId}`).val(status);
    });
    
    $('#markAllPresent').on('click', function() {
        $('.attendance-btn.present').each(function() {
            $(this).click();
        });
    });
    
    $('#markAllAbsent').on('click', function() {
        $('.attendance-btn.absent').each(function() {
            $(this).click();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
