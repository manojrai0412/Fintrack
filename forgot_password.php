<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (isLoggedIn()) { header('Location: index.php'); exit; }

$step    = 'email'; /
$message = '';
$error   = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step_email'])) {
    $email = sanitize($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?")
                 ->execute([$token, $expires, $email]);
            // In production: send email. Here we show the token for demo.
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_token'] = $token;
            $message = "A reset token has been generated. <br><strong>Demo token (normally sent via email):</strong><br><code style='word-break:break-all;font-size:12px;'>{$token}</code>";
            $step = 'verify';
        } else {
           
            $_SESSION['reset_email'] = $email;
            $message = "If that email exists, a reset link has been sent.";
            $step = 'verify';
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step_verify'])) {
    $inputToken = trim($_POST['token'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';
    $step = 'verify';
    if (!$inputToken) {
        $error = 'Please enter the reset token.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND reset_token=? AND reset_expires > NOW()");
        $stmt->execute([$email, $inputToken]);
        if ($stmt->fetch()) {
            $_SESSION['reset_verified'] = true;
            $step = 'reset';
        } else {
            $error = 'Invalid or expired token. Please try again.';
        }
    }
}

// Step 3: New password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step_reset'])) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $email    = $_SESSION['reset_email'] ?? '';
    $step = 'reset';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!($SESSION['reset_verified'] ?? false) && !isset($_SESSION['reset_verified'])) {
        $error = 'Verification required.';
        $step = 'email';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE email=?")
             ->execute([$hash, $email]);
        unset($_SESSION['reset_email'], $_SESSION['reset_token'], $_SESSION['reset_verified']);
        redirect('login.php', 'Password reset successful! Please sign in.');
    }
}

// Restore step from session
if (isset($_SESSION['reset_verified']) && $step === 'email') $step = 'reset';
elseif (isset($_SESSION['reset_email']) && $step === 'email') $step = 'verify';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-box">
    <div class="auth-logo">
        <span class="logo-icon">💰</span>
        <span class="logo-text">FinTrack</span>
    </div>

  
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:24px;">
        <?php foreach(['email'=>'1. Email','verify'=>'2. Verify','reset'=>'3. Reset'] as $s=>$label): ?>
        <div style="flex:1;text-align:center;">
            <div style="height:3px;border-radius:2px;background:<?= in_array($step, array_slice(array_keys(['email'=>1,'verify'=>2,'reset'=>3]), array_search($s, array_keys(['email'=>1,'verify'=>2,'reset'=>3]))) ) || $step===$s ? 'var(--accent-green)' : 'var(--border)' ?>;margin-bottom:6px;"></div>
            <span style="font-size:11px;color:<?= $step===$s ? 'var(--accent-green)' : 'var(--text-muted)' ?>;font-weight:600;"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>

    <?php if ($step === 'email'): ?>
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-subtitle">Enter your email to receive a reset token</p>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com" required>
            </div>
            <button type="submit" name="step_email" class="btn btn-primary">Send Reset Token</button>
        </form>

    <?php elseif ($step === 'verify'): ?>
        <h1 class="auth-title">Enter Reset Token</h1>
        <p class="auth-subtitle">Paste the token you received</p>
        <form method="POST">
            <div class="form-group">
                <label>Reset Token</label>
                <input type="text" name="token" placeholder="Paste your token here" required style="font-family:monospace;font-size:12px;">
            </div>
            <button type="submit" name="step_verify" class="btn btn-primary">Verify Token</button>
        </form>

    <?php elseif ($step === 'reset'): ?>
        <h1 class="auth-title">New Password</h1>
        <p class="auth-subtitle">Choose a strong new password</p>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" placeholder="At least 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm" placeholder="Repeat your new password" required>
            </div>
            <button type="submit" name="step_reset" class="btn btn-primary">Reset Password</button>
        </form>
    <?php endif; ?>

    <div class="auth-link"><a href="login.php">← Back to Sign In</a></div>
</div>
</body>
</html>
