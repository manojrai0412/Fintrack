<?php
/**
 * FinTrack - Create Admin User
 * Run this ONCE to create your first admin account.
 * Then DELETE this file for security.
 */
require_once 'config/db.php';

// --- CONFIGURE THESE ---
$name     = 'Admin';
$email    = 'admin@fintrack.com';
$password = 'Admin@123';
// -----------------------

$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    // Update to admin if exists
    $conn->prepare("UPDATE users SET role='admin' WHERE email=?")->execute([$email]);
    echo "<div style='font-family:sans-serif;padding:20px;background:#1c2230;color:#39d353;border-radius:8px;'>";
    echo "<strong>✅ Admin role set for existing user:</strong><br>Email: $email";
    echo "<br><br><em style='color:#8b949e'>⚠️ Delete this file (create_admin.php) now!</em></div>";
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'admin')")
         ->execute([$name, $email, $hash]);
    echo "<div style='font-family:sans-serif;padding:20px;background:#1c2230;color:#39d353;border-radius:8px;'>";
    echo "<strong>✅ Admin account created!</strong><br>";
    echo "Email: <strong>$email</strong><br>Password: <strong>$password</strong>";
    echo "<br><br><em style='color:#8b949e'>⚠️ Delete this file (create_admin.php) immediately after use!</em></div>";
}
?>
