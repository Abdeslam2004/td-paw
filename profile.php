<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
requireLogin();

$db = Database::getInstance();
$userId = getUserId();

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone'] ?? '');
        
        $db->query("
            UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$firstName, $lastName, $phone, $userId]);
        
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        
        header("Location: profile.php?success=1");
        exit;
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (!password_verify($currentPassword, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$hashedPassword, $userId]);
            header("Location: profile.php?password_changed=1");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your account settings</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Profile updated successfully!</span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['password_changed'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Password changed successfully!</span>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Profile Information</h2>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                <small style="color: var(--text-secondary);">Email cannot be changed</small>
            </div>
            
            <?php if ($user['student_id']): ?>
                <div class="form-group">
                    <label class="form-label">Student ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_id']); ?>" disabled>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Change Password</h2>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-key"></i> Change Password
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
