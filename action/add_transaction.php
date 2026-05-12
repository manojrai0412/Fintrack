<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$uid    = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$ref    = $_SERVER['HTTP_REFERER'] ?? '../index.php';

if ($action === 'add') {
    $type     = in_array($_POST['type'], ['income','expense']) ? $_POST['type'] : null;
    $category = sanitize($_POST['category'] ?? '');
    $amount   = (float)($_POST['amount'] ?? 0);
    $date     = $_POST['date'] ?? date('Y-m-d');
    $desc     = sanitize($_POST['description'] ?? '');

    if (!$type || !$category || $amount <= 0 || !$date) {
        redirect($ref, 'Please fill in all required fields.', 'error');
    }

    $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, date, description) VALUES (?,?,?,?,?,?)")
         ->execute([$uid, $type, $category, $amount, $date, $desc]);
    redirect($ref, 'Transaction added successfully!');

} elseif ($action === 'edit') {
    $id       = (int)($_POST['id'] ?? 0);
    $type     = in_array($_POST['type'], ['income','expense']) ? $_POST['type'] : null;
    $category = sanitize($_POST['category'] ?? '');
    $amount   = (float)($_POST['amount'] ?? 0);
    $date     = $_POST['date'] ?? '';
    $desc     = sanitize($_POST['description'] ?? '');

    if (!$id || !$type || !$category || $amount <= 0 || !$date) {
        redirect($ref, 'Invalid data. Please try again.', 'error');
    }

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM transactions WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    if (!$stmt->fetch()) redirect($ref, 'Transaction not found.', 'error');

    $conn->prepare("UPDATE transactions SET type=?, category=?, amount=?, date=?, description=? WHERE id=? AND user_id=?")
         ->execute([$type, $category, $amount, $date, $desc, $id, $uid]);
    redirect($ref, 'Transaction updated successfully!');

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) redirect($ref, 'Invalid transaction.', 'error');

    $stmt = $conn->prepare("SELECT id FROM transactions WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    if (!$stmt->fetch()) redirect($ref, 'Transaction not found.', 'error');

    $conn->prepare("DELETE FROM transactions WHERE id=? AND user_id=?")->execute([$id, $uid]);
    redirect($ref, 'Transaction deleted.');

} else {
    redirect($ref, 'Invalid action.', 'error');
}
?>
