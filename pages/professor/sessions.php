<?php
$pageTitle = 'Manage Sessions';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('professor');

$db = Database::getInstance();
$professorId = getUserId();

$courses = $db->fetchAll("
    SELECT c.* FROM courses c 
    WHERE c.professor_id = ?
    ORDER BY c.name
", [$professorId]);

$selectedCourse = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

if ($selectedCourse) {
    $groups = $db->fetchAll("
        SELECT g.* FROM groups g
        JOIN course_groups cg ON g.id = cg.group_id
        WHERE cg.course_id = ?
        ORDER BY g.name
    ", [$selectedCourse]);
    
    $sessions = $db->fetchAll("
        SELECT s.*, g.name as group_name,
               (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id AND r.status = 'present') as present_count,
               (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id) as total_marked
        FROM attendance_sessions s
        LEFT JOIN groups g ON s.group_id = g.id
        WHERE s.course_id = ?
        ORDER BY s.session_date DESC
    ", [$selectedCourse]);
} else {
    $groups = [];
    $sessions = $db->fetchAll("
        SELECT s.*, c.name as course_name, c.code as course_code, g.name as group_name,
               (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id AND r.status = 'present') as present_count,
               (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id) as total_marked
        FROM attendance_sessions s
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN groups g ON s.group_id = g.id
        WHERE c.professor_id = ?
        ORDER BY s.session_date DESC
    ", [$professorId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_session') {
        $courseId = (int)$_POST['course_id'];
        $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
        $sessionDate = $_POST['session_date'];
        $sessionTime = $_POST['session_time'] ?? null;
        $sessionType = $_POST['session_type'] ?? 'lecture';
        
        $db->query("
            INSERT INTO attendance_sessions (course_id, group_id, session_date, session_time, session_type, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$courseId, $groupId, $sessionDate, $sessionTime, $sessionType, $professorId]);
        
        header("Location: sessions.php?course_id=$courseId&success=1");
        exit;
    }
    
    if ($action === 'close_session') {
        $sessionId = (int)$_POST['session_id'];
        $db->query("UPDATE attendance_sessions SET status = 'closed' WHERE id = ?", [$sessionId]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if ($action === 'delete_session') {
        $sessionId = (int)$_POST['session_id'];
        $db->query("DELETE FROM attendance_sessions WHERE id = ?", [$sessionId]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Attendance Sessions</h1>
    <p class="page-subtitle">Create and manage attendance sessions for your courses</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Session created successfully!</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filter by Course</h2>
    </div>
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
            <label class="form-label">Select Course</label>
            <select name="course_id" class="form-control form-select" onchange="this.form.submit()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $selectedCourse == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModal('createSessionModal')">
            <i class="fas fa-plus"></i> New Session
        </button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Sessions List</h2>
    </div>
    
    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-plus"></i>
            <h3>No sessions found</h3>
            <p>Create your first attendance session to get started.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <?php if (!$selectedCourse): ?><th>Course</th><?php endif; ?>
                        <th>Group</th>
                        <th>Type</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M d, Y', strtotime($session['session_date'])); ?></strong>
                                <?php if ($session['session_time']): ?>
                                    <br><small><?php echo date('H:i', strtotime($session['session_time'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if (!$selectedCourse): ?>
                                <td><?php echo htmlspecialchars($session['course_code'] ?? ''); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($session['group_name'] ?? 'All Groups'); ?></td>
                            <td><span class="badge badge-info"><?php echo ucfirst($session['session_type']); ?></span></td>
                            <td>
                                <?php if ($session['total_marked'] > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="progress-bar" style="width: 80px;">
                                            <div class="progress-fill success" style="width: <?php echo ($session['present_count'] / $session['total_marked']) * 100; ?>%;"></div>
                                        </div>
                                        <span><?php echo $session['present_count']; ?>/<?php echo $session['total_marked']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Not marked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $session['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-clipboard-check"></i>
                                    </a>
                                    <?php if ($session['status'] === 'open'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="close_session">
                                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Close Session">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this session?');">
                                        <input type="hidden" name="action" value="delete_session">
                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="createSessionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create New Session</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_session">
                
                <div class="form-group">
                    <label class="form-label">Course *</label>
                    <select name="course_id" id="modalCourse" class="form-control form-select" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $selectedCourse == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Group</label>
                    <select name="group_id" id="modalGroup" class="form-control form-select">
                        <option value="">All Groups</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="session_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" name="session_time" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Session Type</label>
                    <select name="session_type" class="form-control form-select">
                        <option value="lecture">Lecture</option>
                        <option value="td">TD (Tutorial)</option>
                        <option value="tp">TP (Lab)</option>
                        <option value="exam">Exam</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createSessionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Session</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#modalCourse').on('change', function() {
        const courseId = $(this).val();
        if (courseId) {
            $.get('../../api/get_groups.php?course_id=' + courseId, function(groups) {
                const $select = $('#modalGroup');
                $select.html('<option value="">All Groups</option>');
                groups.forEach(function(group) {
                    $select.append(`<option value="${group.id}">${group.name}</option>`);
                });
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
