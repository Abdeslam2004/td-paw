<?php
$pageTitle = 'Statistics';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('admin');

$db = Database::getInstance();

$overallStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT ar.id) as total_records,
        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN ar.status = 'excused' THEN 1 END) as excused_count
    FROM attendance_records ar
");

$courseStats = $db->fetchAll("
    SELECT c.id, c.code, c.name,
           COUNT(DISTINCT s.id) as session_count,
           COUNT(ar.id) as total_records,
           COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
           COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count
    FROM courses c
    LEFT JOIN attendance_sessions s ON s.course_id = c.id
    LEFT JOIN attendance_records ar ON ar.session_id = s.id
    GROUP BY c.id, c.code, c.name
    ORDER BY c.name
");

$groupStats = $db->fetchAll("
    SELECT g.id, g.name, g.year, g.specialization,
           COUNT(DISTINCT sg.student_id) as student_count,
           COUNT(ar.id) as total_records,
           COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count
    FROM groups g
    LEFT JOIN student_groups sg ON sg.group_id = g.id
    LEFT JOIN attendance_records ar ON ar.student_id = sg.student_id
    GROUP BY g.id, g.name, g.year, g.specialization
    ORDER BY g.name
");

$monthlyStats = $db->fetchAll("
    SELECT 
        TO_CHAR(s.session_date, 'YYYY-MM') as month,
        COUNT(DISTINCT s.id) as session_count,
        COUNT(ar.id) as total_records,
        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count
    FROM attendance_sessions s
    LEFT JOIN attendance_records ar ON ar.session_id = s.id
    GROUP BY TO_CHAR(s.session_date, 'YYYY-MM')
    ORDER BY month DESC
    LIMIT 12
");

$topAbsentees = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.student_id, u.email,
           COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
           COUNT(ar.id) as total_records
    FROM users u
    LEFT JOIN attendance_records ar ON ar.student_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.student_id, u.email
    HAVING COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) > 0
    ORDER BY absent_count DESC
    LIMIT 10
");

$attendanceRate = $overallStats['total_records'] > 0 
    ? round(($overallStats['present_count'] / $overallStats['total_records']) * 100, 1) 
    : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Attendance Statistics</h1>
    <p class="page-subtitle">Comprehensive analytics and reports</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $attendanceRate; ?>%</h3>
            <p>Overall Attendance Rate</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['present_count'] ?? 0; ?></h3>
            <p>Total Present</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['absent_count'] ?? 0; ?></h3>
            <p>Total Absent</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overallStats['late_count'] ?? 0; ?></h3>
            <p>Total Late</p>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Attendance Distribution</h2>
        </div>
        <div class="chart-container">
            <canvas id="distributionChart"></canvas>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Monthly Trend</h2>
        </div>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Course Statistics</h2>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Sessions</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courseStats as $course): ?>
                    <?php 
                    $rate = $course['total_records'] > 0 
                        ? round(($course['present_count'] / $course['total_records']) * 100, 1) 
                        : 0;
                    $rateClass = $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($course['code']); ?></strong>
                            <br><small><?php echo htmlspecialchars($course['name']); ?></small>
                        </td>
                        <td><?php echo $course['session_count']; ?></td>
                        <td><span class="badge badge-success"><?php echo $course['present_count']; ?></span></td>
                        <td><span class="badge badge-danger"><?php echo $course['absent_count']; ?></span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div class="progress-bar" style="width: 80px;">
                                    <div class="progress-fill <?php echo $rateClass; ?>" style="width: <?php echo $rate; ?>%;"></div>
                                </div>
                                <span><?php echo $rate; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Group Statistics</h2>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Students</th>
                        <th>Attendance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupStats as $group): ?>
                        <?php 
                        $rate = $group['total_records'] > 0 
                            ? round(($group['present_count'] / $group['total_records']) * 100, 1) 
                            : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($group['specialization'] ?? ''); ?></small>
                            </td>
                            <td><?php echo $group['student_count']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="progress-bar" style="width: 60px;">
                                        <div class="progress-fill" style="width: <?php echo $rate; ?>%;"></div>
                                    </div>
                                    <span><?php echo $rate; ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Top Absentees</h2>
        </div>
        
        <?php if (empty($topAbsentees)): ?>
            <div class="empty-state">
                <i class="fas fa-smile"></i>
                <h3>Great attendance!</h3>
                <p>No significant absences recorded.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Absences</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topAbsentees as $student): ?>
                            <?php 
                            $absentRate = $student['total_records'] > 0 
                                ? round(($student['absent_count'] / $student['total_records']) * 100, 1) 
                                : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($student['student_id'] ?? $student['email']); ?></small>
                                </td>
                                <td><span class="badge badge-danger"><?php echo $student['absent_count']; ?></span></td>
                                <td><?php echo $absentRate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    const distributionCtx = document.getElementById('distributionChart').getContext('2d');
    new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late', 'Excused'],
            datasets: [{
                data: [
                    <?php echo $overallStats['present_count'] ?? 0; ?>,
                    <?php echo $overallStats['absent_count'] ?? 0; ?>,
                    <?php echo $overallStats['late_count'] ?? 0; ?>,
                    <?php echo $overallStats['excused_count'] ?? 0; ?>
                ],
                backgroundColor: [
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(37, 99, 235, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    const monthlyData = <?php echo json_encode(array_reverse($monthlyStats)); ?>;
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(m => m.month),
            datasets: [{
                label: 'Attendance Rate (%)',
                data: monthlyData.map(m => m.total_records > 0 ? Math.round((m.present_count / m.total_records) * 100) : 0),
                borderColor: 'rgba(37, 99, 235, 1)',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
