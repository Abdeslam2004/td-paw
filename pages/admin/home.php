<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('admin');

$db = Database::getInstance();

$stats = [
    'students' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'],
    'professors' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'professor'")['count'],
    'courses' => $db->fetchOne("SELECT COUNT(*) as count FROM courses")['count'],
    'groups' => $db->fetchOne("SELECT COUNT(*) as count FROM groups")['count'],
    'sessions' => $db->fetchOne("SELECT COUNT(*) as count FROM attendance_sessions")['count'],
    'pending_justifications' => $db->fetchOne("SELECT COUNT(*) as count FROM justifications WHERE status = 'pending'")['count']
];

$pendingJustifications = $db->fetchAll("
    SELECT j.*, u.first_name, u.last_name, u.student_id, u.email,
           s.session_date, c.name as course_name, c.code as course_code
    FROM justifications j
    JOIN users u ON j.student_id = u.id
    JOIN attendance_sessions s ON j.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE j.status = 'pending'
    ORDER BY j.created_at DESC
    LIMIT 10
");

$recentActivity = $db->fetchAll("
    SELECT 'attendance' as type, ar.marked_at as created_at, 
           u.first_name, u.last_name, ar.status, c.name as course_name
    FROM attendance_records ar
    JOIN users u ON ar.student_id = u.id
    JOIN attendance_sessions s ON ar.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    ORDER BY ar.marked_at DESC
    LIMIT 5
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $justificationId = (int)$_POST['justification_id'];
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $notes = sanitize($_POST['notes'] ?? '');
        
        $db->query("
            UPDATE justifications 
            SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$status, $notes, getUserId(), $justificationId]);
        
        if ($action === 'approve') {
            $just = $db->fetchOne("SELECT student_id, session_id FROM justifications WHERE id = ?", [$justificationId]);
            $db->query("
                UPDATE attendance_records SET status = 'excused' 
                WHERE student_id = ? AND session_id = ?
            ", [$just['student_id'], $just['session_id']]);
        }
        
        header("Location: home.php?processed=1");
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Administrator Dashboard</h1>
    <p class="page-subtitle">Manage the attendance system and review justifications</p>
</div>

<?php if (isset($_GET['processed'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Justification processed successfully!</span>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['students']; ?></h3>
            <p>Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['professors']; ?></h3>
            <p>Professors</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['courses']; ?></h3>
            <p>Courses</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['pending_justifications']; ?></h3>
            <p>Pending Justifications</p>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Pending Justifications</h2>
            <?php if ($stats['pending_justifications'] > 0): ?>
                <span class="badge badge-warning"><?php echo $stats['pending_justifications']; ?> pending</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($pendingJustifications)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>All caught up!</h3>
                <p>No pending justifications to review.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingJustifications as $just): ?>
                <div class="session-card">
                    <div class="session-info">
                        <h4><?php echo htmlspecialchars($just['last_name'] . ' ' . $just['first_name']); ?></h4>
                        <p>
                            <?php echo htmlspecialchars($just['course_code']); ?> - 
                            <?php echo date('M d, Y', strtotime($just['session_date'])); ?>
                        </p>
                        <small style="color: var(--text-secondary);">
                            <?php echo strlen($just['reason']) > 50 ? substr($just['reason'], 0, 50) . '...' : $just['reason']; ?>
                        </small>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn btn-success btn-sm" onclick="reviewJustification(<?php echo $just['id']; ?>, 'approve', '<?php echo htmlspecialchars(addslashes($just['reason'])); ?>')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="reviewJustification(<?php echo $just['id']; ?>, 'reject', '<?php echo htmlspecialchars(addslashes($just['reason'])); ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php if ($just['file_path']): ?>
                            <a href="<?php echo BASE_URL . 'public/' . $just['file_path']; ?>" target="_blank" class="btn btn-secondary btn-sm">
                                <i class="fas fa-file"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Quick Actions</h2>
        </div>
        
        <div style="display: grid; gap: 1rem;">
            <a href="students.php" class="session-card" style="text-decoration: none;">
                <div class="session-info">
                    <h4><i class="fas fa-users" style="margin-right: 0.5rem;"></i> Manage Students</h4>
                    <p>Add, remove, or import students</p>
                </div>
                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
            </a>
            
            <a href="statistics.php" class="session-card" style="text-decoration: none;">
                <div class="session-info">
                    <h4><i class="fas fa-chart-pie" style="margin-right: 0.5rem;"></i> View Statistics</h4>
                    <p>Attendance analytics and reports</p>
                </div>
                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
            </a>
            
            <a href="students.php?action=import" class="session-card" style="text-decoration: none;">
                <div class="session-info">
                    <h4><i class="fas fa-file-excel" style="margin-right: 0.5rem;"></i> Import/Export</h4>
                    <p>Progres Excel format support</p>
                </div>
                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
            </a>
        </div>
    </div>
</div>

<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="reviewModalTitle">Review Justification</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="reviewAction">
                <input type="hidden" name="justification_id" id="reviewJustificationId">
                
                <div class="form-group">
                    <label class="form-label">Student's Reason:</label>
                    <p id="studentReason" style="background: var(--bg-color); padding: 1rem; border-radius: 0.5rem;"></p>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Admin Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                <button type="submit" class="btn" id="reviewSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function reviewJustification(id, action, reason) {
    $('#reviewJustificationId').val(id);
    $('#reviewAction').val(action);
    $('#studentReason').text(reason);
    
    if (action === 'approve') {
        $('#reviewModalTitle').text('Approve Justification');
        $('#reviewSubmitBtn').removeClass('btn-danger').addClass('btn-success').text('Approve');
    } else {
        $('#reviewModalTitle').text('Reject Justification');
        $('#reviewSubmitBtn').removeClass('btn-success').addClass('btn-danger').text('Reject');
    }
    
    openModal('reviewModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
