<?php
$pageTitle = 'My Attendance';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('student');

$db = Database::getInstance();
$studentId = getUserId();

$courses = $db->fetchAll("
    SELECT DISTINCT c.* FROM courses c
    JOIN course_groups cg ON c.id = cg.course_id
    JOIN student_groups sg ON cg.group_id = sg.group_id
    WHERE sg.student_id = ?
    ORDER BY c.name
", [$studentId]);

$selectedCourse = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

$whereClause = $selectedCourse ? "AND s.course_id = $selectedCourse" : "";

$attendance = $db->fetchAll("
    SELECT ar.*, s.session_date, s.session_time, s.session_type, 
           c.name as course_name, c.code as course_code,
           j.id as justification_id, j.status as justification_status
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_id = cg.course_id
    JOIN student_groups sg ON cg.group_id = sg.group_id
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = sg.student_id
    LEFT JOIN justifications j ON j.session_id = s.id AND j.student_id = sg.student_id
    WHERE sg.student_id = ? $whereClause
    ORDER BY s.session_date DESC, s.session_time DESC
", [$studentId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_justification') {
    $sessionId = (int)$_POST['session_id'];
    $reason = sanitize($_POST['reason']);
    $filePath = null;
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../public/uploads/justifications/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . $studentId . '_' . basename($_FILES['document']['name']);
        $filePath = 'uploads/justifications/' . $fileName;
        
        move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $fileName);
    }
    
    $db->query("
        INSERT INTO justifications (student_id, session_id, reason, file_path, status)
        VALUES (?, ?, ?, ?, 'pending')
        ON CONFLICT (student_id, session_id) DO UPDATE SET reason = ?, file_path = ?, status = 'pending'
    ", [$studentId, $sessionId, $reason, $filePath, $reason, $filePath]);
    
    header("Location: attendance.php" . ($selectedCourse ? "?course_id=$selectedCourse" : "") . "&submitted=1");
    exit;
}

$attendanceStats = [];
foreach ($courses as $course) {
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT s.id) as total,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent,
            COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late
        FROM attendance_sessions s
        JOIN course_groups cg ON s.course_id = cg.course_id
        JOIN student_groups sg ON cg.group_id = sg.group_id
        LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = sg.student_id
        WHERE sg.student_id = ? AND s.course_id = ?
    ", [$studentId, $course['id']]);
    $attendanceStats[$course['id']] = $stats;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">My Attendance</h1>
    <p class="page-subtitle">View your attendance records and submit justifications</p>
</div>

<?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Justification submitted successfully! It will be reviewed by an administrator.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filter by Course</h2>
    </div>
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
            <select name="course_id" class="form-control form-select" onchange="this.form.submit()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $selectedCourse == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (!$selectedCourse): ?>
<div class="stats-grid">
    <?php foreach ($courses as $course): ?>
        <?php 
        $stats = $attendanceStats[$course['id']];
        $rate = $stats['total'] > 0 ? round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1) : 0;
        $rateClass = $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
        ?>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location='?course_id=<?php echo $course['id']; ?>'">
            <div class="stat-icon <?php echo $rateClass === 'success' ? 'green' : ($rateClass === 'warning' ? 'yellow' : 'red'); ?>">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $rate; ?>%</h3>
                <p><?php echo htmlspecialchars($course['code']); ?></p>
                <small style="color: var(--text-secondary);"><?php echo $stats['present'] + $stats['late']; ?>/<?php echo $stats['total']; ?> sessions</small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Attendance Records</h2>
    </div>
    
    <?php if (empty($attendance)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No attendance records</h3>
            <p>You don't have any attendance records yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Justification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M d, Y', strtotime($record['session_date'])); ?></strong>
                                <?php if ($record['session_time']): ?>
                                    <br><small><?php echo date('H:i', strtotime($record['session_time'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['course_code']); ?></strong>
                                <br><small><?php echo htmlspecialchars($record['course_name']); ?></small>
                            </td>
                            <td><span class="badge badge-info"><?php echo ucfirst($record['session_type']); ?></span></td>
                            <td>
                                <span class="badge <?php 
                                    $status = $record['status'] ?? 'absent';
                                    echo $status === 'present' ? 'badge-success' : 
                                        ($status === 'late' ? 'badge-warning' : 
                                        ($status === 'excused' ? 'badge-info' : 'badge-danger')); 
                                ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['justification_id']): ?>
                                    <span class="badge <?php 
                                        echo $record['justification_status'] === 'approved' ? 'badge-success' : 
                                            ($record['justification_status'] === 'rejected' ? 'badge-danger' : 'badge-warning'); 
                                    ?>">
                                        <?php echo ucfirst($record['justification_status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($record['status'] ?? 'absent') === 'absent' && !$record['justification_id']): ?>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            onclick="openJustificationModal(<?php echo $record['session_id']; ?>, '<?php echo htmlspecialchars($record['course_name']); ?>', '<?php echo date('M d, Y', strtotime($record['session_date'])); ?>')">
                                        <i class="fas fa-file-upload"></i> Justify
                                    </button>
                                <?php elseif ($record['justification_status'] === 'rejected'): ?>
                                    <button type="button" class="btn btn-warning btn-sm"
                                            onclick="openJustificationModal(<?php echo $record['session_id']; ?>, '<?php echo htmlspecialchars($record['course_name']); ?>', '<?php echo date('M d, Y', strtotime($record['session_date'])); ?>')">
                                        <i class="fas fa-redo"></i> Resubmit
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="justificationModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Submit Justification</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="submit_justification">
                <input type="hidden" name="session_id" id="justificationSessionId">
                
                <div class="alert alert-info" style="margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i>
                    <span id="justificationInfo">Submitting justification for...</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Absence *</label>
                    <textarea name="reason" class="form-control" rows="4" placeholder="Please explain why you were absent..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Supporting Document (Optional)</label>
                    <div class="file-upload">
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload a document (PDF, Image, or Word)</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('justificationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Justification</button>
            </div>
        </form>
    </div>
</div>

<script>
function openJustificationModal(sessionId, courseName, date) {
    $('#justificationSessionId').val(sessionId);
    $('#justificationInfo').text(`Submitting justification for ${courseName} on ${date}`);
    openModal('justificationModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
