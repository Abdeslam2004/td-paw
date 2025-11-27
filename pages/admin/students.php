<?php
$pageTitle = 'Student Management';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
requireRole('admin');

$db = Database::getInstance();

$groups = $db->fetchAll("SELECT * FROM groups ORDER BY name");
$selectedGroup = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

$whereClause = $selectedGroup ? "AND sg.group_id = $selectedGroup" : "";

$students = $db->fetchAll("
    SELECT u.*, g.name as group_name, g.id as group_id
    FROM users u
    LEFT JOIN student_groups sg ON u.id = sg.student_id
    LEFT JOIN groups g ON sg.group_id = g.id
    WHERE u.role = 'student' $whereClause
    ORDER BY u.last_name, u.first_name
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_student') {
        $email = sanitize($_POST['email']);
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $studentId = sanitize($_POST['student_id']);
        $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
        $password = password_hash('student123', PASSWORD_DEFAULT);
        
        try {
            $db->query("
                INSERT INTO users (email, password, first_name, last_name, role, student_id)
                VALUES (?, ?, ?, ?, 'student', ?)
            ", [$email, $password, $firstName, $lastName, $studentId]);
            
            $newUserId = $db->getConnection()->lastInsertId();
            
            if ($groupId) {
                $db->query("INSERT INTO student_groups (student_id, group_id) VALUES (?, ?)", [$newUserId, $groupId]);
            }
            
            header("Location: students.php?success=added");
            exit;
        } catch (Exception $e) {
            $error = "Error adding student. Email may already exist.";
        }
    }
    
    if ($action === 'delete_student') {
        $userId = (int)$_POST['user_id'];
        $db->query("DELETE FROM users WHERE id = ? AND role = 'student'", [$userId]);
        header("Location: students.php?success=deleted");
        exit;
    }
    
    if ($action === 'import_excel') {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['excel_file']['tmp_name'];
            $extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
            
            if ($extension === 'csv') {
                $handle = fopen($file, 'r');
                $header = fgetcsv($handle);
                $imported = 0;
                $groupId = !empty($_POST['import_group_id']) ? (int)$_POST['import_group_id'] : null;
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 4) {
                        $studentIdCol = $data[0] ?? '';
                        $lastName = $data[1] ?? '';
                        $firstName = $data[2] ?? '';
                        $email = $data[3] ?? $studentIdCol . '@univ-alger.dz';
                        
                        if (!empty($firstName) && !empty($lastName)) {
                            try {
                                $password = password_hash('student123', PASSWORD_DEFAULT);
                                $db->query("
                                    INSERT INTO users (email, password, first_name, last_name, role, student_id)
                                    VALUES (?, ?, ?, ?, 'student', ?)
                                    ON CONFLICT (email) DO NOTHING
                                ", [$email, $password, $firstName, $lastName, $studentIdCol]);
                                
                                $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                                if ($user && $groupId) {
                                    $db->query("
                                        INSERT INTO student_groups (student_id, group_id) VALUES (?, ?)
                                        ON CONFLICT DO NOTHING
                                    ", [$user['id'], $groupId]);
                                }
                                $imported++;
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }
                }
                fclose($handle);
                header("Location: students.php?success=imported&count=$imported");
                exit;
            } else {
                $error = "Please upload a CSV file. For Excel files, save as CSV first.";
            }
        }
    }
    
    if ($action === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student ID', 'Last Name', 'First Name', 'Email', 'Group']);
        
        foreach ($students as $student) {
            fputcsv($output, [
                $student['student_id'] ?? '',
                $student['last_name'],
                $student['first_name'],
                $student['email'],
                $student['group_name'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Student Management</h1>
    <p class="page-subtitle">Add, remove, and manage student accounts</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>
            <?php 
            if ($_GET['success'] === 'added') echo 'Student added successfully!';
            elseif ($_GET['success'] === 'deleted') echo 'Student removed successfully!';
            elseif ($_GET['success'] === 'imported') echo 'Imported ' . ($_GET['count'] ?? 0) . ' students successfully!';
            ?>
        </span>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Filter & Actions</h2>
    </div>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <form method="GET" style="flex: 1; min-width: 200px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Filter by Group</label>
                <select name="group_id" class="form-control form-select" onchange="this.form.submit()">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" <?php echo $selectedGroup == $group['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <button type="button" class="btn btn-primary" onclick="openModal('addStudentModal')">
            <i class="fas fa-plus"></i> Add Student
        </button>
        <button type="button" class="btn btn-success" onclick="openModal('importModal')">
            <i class="fas fa-file-import"></i> Import CSV
        </button>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Students (<?php echo count($students); ?>)</h2>
        <input type="text" class="form-control" style="max-width: 300px;" placeholder="Search students..." data-search=".student-row">
    </div>
    
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No students found</h3>
            <p>Add students manually or import from a CSV file.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Group</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr class="student-row">
                            <td><?php echo htmlspecialchars($student['student_id'] ?? '-'); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td>
                                <?php if ($student['group_name']): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($student['group_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No Group</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this student?');">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="addStudentModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Student</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_student">
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Student ID</label>
                        <input type="text" name="student_id" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-control form-select">
                            <option value="">Select Group</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Default password will be: student123</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Student</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Import Students from CSV</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="import_excel">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>CSV Format (Progres Compatible):</strong>
                        <p style="margin: 0.5rem 0 0 0;">Student ID, Last Name, First Name, Email</p>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem;">Save your Excel file as CSV before uploading.</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">CSV File *</label>
                    <div class="file-upload">
                        <input type="file" name="excel_file" accept=".csv" required>
                        <i class="fas fa-file-csv"></i>
                        <p>Click to select CSV file</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assign to Group</label>
                    <select name="import_group_id" class="form-control form-select">
                        <option value="">No Group</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Import Students</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
