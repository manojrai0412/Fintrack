<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        if (!$name || !$email) redirect('profile.php','Name and email are required.','error');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) redirect('profile.php','Invalid email.','error');
        // Check email taken by another user
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->execute([$email, $uid]);
        if ($chk->fetch()) redirect('profile.php','Email already in use.','error');
        $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$name,$email,$uid]);
        $_SESSION['name']  = $name;
        $_SESSION['email'] = $email;
        redirect('profile.php','Profile updated successfully!');

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user = $conn->prepare("SELECT password FROM users WHERE id=?");
        $user->execute([$uid]); $u = $user->fetch();
        if (!password_verify($current, $u['password'])) redirect('profile.php','Current password is incorrect.','error');
        if (strlen($new) < 6) redirect('profile.php','New password must be at least 6 characters.','error');
        if ($new !== $confirm) redirect('profile.php','Passwords do not match.','error');
        $conn->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
        redirect('profile.php','Password changed successfully!');

    } elseif ($action === 'delete_account') {
        $conn->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        session_destroy();
        header('Location: register.php');
        exit;
    }
}

$user = $conn->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$uid]); $u = $user->fetch();

$txCount = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=?");
$txCount->execute([$uid]); $txTotal = $txCount->fetchColumn();
$joined = date('d M Y', strtotime($u['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Manage your account settings</p>
        </div>
    </div>
    <?php flash(); ?>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;">
        <!-- Profile Card -->
        <div>
            <div class="card card-body-pad" style="text-align:center;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--accent-green),var(--accent-blue));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;font-family:'Syne',sans-serif;font-weight:700;color:#0d1117;">
                    <?= strtoupper(substr($u['name'],0,1)) ?>
                </div>
                <div style="font-size:18px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($u['name']) ?></div>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;"><?= htmlspecialchars($u['email']) ?></div>
                <span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-secondary)">Joined</span><span><?= $joined ?></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-secondary)">Transactions</span><span><?= $txTotal ?></span></div>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Edit Profile -->
            <div class="card card-body-pad">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:18px;">Edit Profile</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                    <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 28px;">Save Changes</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card card-body-pad">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:18px;">Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
                    <button type="submit" class="btn btn-warning" style="width:auto;padding:10px 28px;">Update Password</button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="card card-body-pad" style="border-color:rgba(248,81,73,0.3);">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--accent-red);">⚠️ Danger Zone</h3>
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Once you delete your account, all your data including transactions will be permanently erased. This cannot be undone.</p>
                <button class="btn btn-danger" onclick="confirmDeleteAccount()">Delete My Account</button>
            </div>
        </div>
    </div>
</main>
</div>

<form method="POST" id="deleteAccountForm" style="display:none;">
    <input type="hidden" name="action" value="delete_account">
</form>

<?php include 'includes/confirm.php'; ?>
<script src="assets/js/main.js"></script>
<script>
function confirmDeleteAccount() {
    confirmAction(
        'All your data will be permanently deleted. This action cannot be undone.',
        () => document.getElementById('deleteAccountForm').submit(),
        'Delete Account', '⚠️'
    );
}
</script>
</body>
</html>
