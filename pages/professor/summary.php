<?php
$pageTitle = 'Attendance Summary';
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

$selectedCourse = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['id'] ?? null);
$selectedGroup = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

$groups = [];
$studentStats = [];
$sessionStats = [];

if ($selectedCourse) {
    $groups = $db->fetchAll("
        SELECT g.* FROM groups g
        JOIN course_groups cg ON g.id = cg.group_id
        WHERE cg.course_id = ?
        ORDER BY g.name
    ", [$selectedCourse]);
    
    $groupFilter = $selectedGroup ? "AND sg.group_id = $selectedGroup" : "";
    
    $studentStats = $db->fetchAll("
        SELECT u.id, u.first_name, u.last_name, u.student_id, u.email, g.name as group_name,
               COUNT(DISTINCT s.id) as total_sessions,
               COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
               COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
               COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
               COUNT(CASE WHEN ar.status = 'excused' THEN 1 END) as excused_count,
               COALESCE(AVG(ar.participation_score), 0) as avg_participation
        FROM users u
        JOIN student_groups sg ON u.id = sg.student_id
        JOIN groups g ON sg.group_id = g.id
        JOIN course_groups cg ON sg.group_id = cg.group_id
        LEFT JOIN attendance_sessions s ON s.course_id = cg.course_id AND (s.group_id IS NULL OR s.group_id = sg.group_id)
        LEFT JOIN attendance_records ar ON ar.student_id = u.id AND ar.session_id = s.id
        WHERE cg.course_id = ? AND u.role = 'student' $groupFilter
        GROUP BY u.id, u.first_name, u.last_name, u.student_id, u.email, g.name
        ORDER BY u.last_name, u.first_name
    ", [$selectedCourse]);
    
    $sessionStats = $db->fetchAll("
        SELECT s.id, s.session_date, s.session_type, g.name as group_name,
               COUNT(ar.id) as total_marked,
               COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
               COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count
        FROM attendance_sessions s
        LEFT JOIN groups g ON s.group_id = g.id
        LEFT JOIN attendance_records ar ON ar.session_id = s.id
        WHERE s.course_id = ?
        " . ($selectedGroup ? "AND (s.group_id = $selectedGroup OR s.group_id IS NULL)" : "") . "
        GROUP BY s.id, s.session_date, s.session_type, g.name
        ORDER BY s.session_date DESC
    ", [$selectedCourse]);
}

$overallStats = [
    'total_students' => count($studentStats),
    'total_sessions' => count($sessionStats),
    'avg_attendance' => 0,
    'avg_participation' => 0
];

if (!empty($studentStats)) {
    $totalPresent = array_sum(array_column($studentStats, 'present_count'));
    $totalSessions = array_sum(array_column($studentStats, 'total_sessions'));
    $overallStats['avg_attendance'] = $totalSessions > 0 ? round(($totalPresent / $totalSessions) * 100, 1) : 0;
    $overallStats['avg_participation'] = round(array_sum(array_column($studentStats, 'avg_participation')) / count($studentStats), 1);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Attendance Summary</h1>
    <p class="page-subtitle">View comprehensive attendance statistics for your courses</p>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filter Options</h2>
    </div>
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
            <label class="form-label">Course</label>
            <select name="course_id" class="form-control form-select" onchange="this.form.submit()">
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $selectedCourse == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
            <label class="form-label">Group</label>
            <select name="group_id" class="form-control form-select" onchange="this.form.submit()">
                <option value="">All Groups</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?php echo $group['id']; ?>" <?php echo $selectedGroup == $group['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="?course_id=<?php echo $selectedCourse; ?>&export=csv" class="btn btn-success">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['total_students']; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['total_sessions']; ?></h3>
            <p>Total Sessions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['avg_attendance']; ?>%</h3>
            <p>Avg Attendance</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['avg_participation']; ?></h3>
            <p>Avg Participation</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Student Attendance Details</h2>
    </div>
    
    <?php if (empty($studentStats)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>No data available</h3>
            <p>No attendance records found for the selected filters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Group</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Excused</th>
                        <th>Attendance %</th>
                        <th>Participation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentStats as $stat): ?>
                        <?php 
                        $attendanceRate = $stat['total_sessions'] > 0 
                            ? round((($stat['present_count'] + $stat['late_count']) / $stat['total_sessions']) * 100, 1) 
                            : 0;
                        $rateClass = $attendanceRate >= 75 ? 'success' : ($attendanceRate >= 50 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($stat['last_name'] . ' ' . $stat['first_name']); ?></strong>
                                <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($stat['student_id'] ?? $stat['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($stat['group_name']); ?></td>
                            <td><span class="badge badge-success"><?php echo $stat['present_count']; ?></span></td>
                            <td><span class="badge badge-danger"><?php echo $stat['absent_count']; ?></span></td>
                            <td><span class="badge badge-warning"><?php echo $stat['late_count']; ?></span></td>
                            <td><span class="badge badge-info"><?php echo $stat['excused_count']; ?></span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="progress-bar" style="width: 60px;">
                                        <div class="progress-fill <?php echo $rateClass; ?>" style="width: <?php echo $attendanceRate; ?>%;"></div>
                                    </div>
                                    <span><?php echo $attendanceRate; ?>%</span>
                                </div>
                            </td>
                            <td><?php echo round($stat['avg_participation'], 1); ?>/10</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Session-wise Attendance</h2>
    </div>
    
    <div class="chart-container">
        <canvas id="sessionChart"></canvas>
    </div>
</div>

<script>
$(document).ready(function() {
    const sessionData = <?php echo json_encode($sessionStats); ?>;
    
    if (sessionData.length > 0) {
        const ctx = document.getElementById('sessionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sessionData.map(s => s.session_date + (s.group_name ? ' (' + s.group_name + ')' : '')),
                datasets: [
                    {
                        label: 'Present',
                        data: sessionData.map(s => s.present_count),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    },
                    {
                        label: 'Absent',
                        data: sessionData.map(s => s.absent_count),
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
