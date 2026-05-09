<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
$uid = $_SESSION['user_id'];

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; $offset = ($page - 1) * $limit;
$month = $_GET['month'] ?? '';
$cat   = $_GET['category'] ?? '';

$where = "WHERE user_id=? AND type='income'";
$params = [$uid];
if ($month) { $where .= " AND DATE_FORMAT(date,'%Y-%m')=?"; $params[] = $month; }
if ($cat)   { $where .= " AND category=?"; $params[] = $cat; }

$total = $conn->prepare("SELECT COUNT(*) FROM transactions $where");
$total->execute($params); $totalRows = $total->fetchColumn();
$pages = ceil($totalRows / $limit);

$stmt = $conn->prepare("SELECT * FROM transactions $where ORDER BY date DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();

$sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions $where");
$sumStmt->execute($params); $sum = $sumStmt->fetchColumn();

$cats = $conn->prepare("SELECT DISTINCT category FROM transactions WHERE user_id=? AND type='income'");
$cats->execute([$uid]); $catList = $cats->fetchAll(PDO::FETCH_COLUMN);
$incomeCategories = ['Salary','Freelance','Business','Investment','Rental','Gift','Bonus','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Income — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">Income</h1>
            <p class="page-subtitle">Total: <strong style="color:var(--accent-green)"><?= formatINR($sum) ?></strong></p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addTxModal')">＋ Add Income</button>
    </div>
    <?php flash(); ?>

    <div class="filter-row">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" placeholder="Filter by month">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($incomeCategories as $c): ?>
                <option<?= $cat===$c?' selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <a href="income.php" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><div class="empty-icon">💵</div><p>No income records found.</p></div>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $tx): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($tx['date'])) ?></td>
                    <td><span style="background:rgba(57,211,83,0.1);color:var(--accent-green);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;"><?= htmlspecialchars($tx['category']) ?></span></td>
                    <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                    <td style="font-weight:700;color:var(--accent-green);">+<?= formatINR($tx['amount']) ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm btn-icon" onclick="editTx(<?= htmlspecialchars(json_encode($tx)) ?>)">✏️</button>
                        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteTx(<?= $tx['id'] ?>)">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="?page=<?= $p ?>&month=<?= urlencode($month) ?>&category=<?= urlencode($cat) ?>" class="page-btn<?= $p==$page?' active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>


<div class="modal-overlay" id="addTxModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Add Income</h2><button class="modal-close" onclick="closeModal('addTxModal')">✕</button></div>
        <form method="POST" action="actions/add_transaction.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="type" value="income">
            <div class="form-group"><label>Category</label>
                <select name="category" required><?php foreach($incomeCategories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" min="0.01" step="0.01" required></div>
            <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label>Description</label><textarea name="description"></textarea></div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Add Income</button><button type="button" class="btn btn-secondary" onclick="closeModal('addTxModal')">Cancel</button></div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editTxModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Edit Income</h2><button class="modal-close" onclick="closeModal('editTxModal')">✕</button></div>
        <form method="POST" action="actions/add_transaction.php" id="editTxForm">
            <input type="hidden" name="action" value="edit"><input type="hidden" name="type" value="income"><input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label>Category</label>
                <select name="category" id="edit_category" required><?php foreach($incomeCategories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" id="edit_amount" min="0.01" step="0.01" required></div>
            <div class="form-group"><label>Date</label><input type="date" name="date" id="edit_date" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_description"></textarea></div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Save</button><button type="button" class="btn btn-secondary" onclick="closeModal('editTxModal')">Cancel</button></div>
        </form>
    </div>
</div>

<form method="POST" action="actions/add_transaction.php" id="deleteTxForm" style="display:none;">
    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete_id">
</form>
<?php include 'includes/confirm.php'; ?>
<script src="assets/js/main.js"></script>
<script>
function editTx(tx) {
    document.getElementById('edit_id').value = tx.id;
    document.getElementById('edit_category').value = tx.category;
    document.getElementById('edit_amount').value = tx.amount;
    document.getElementById('edit_date').value = tx.date;
    document.getElementById('edit_description').value = tx.description || '';
    openModal('editTxModal');
}
function deleteTx(id) {
    confirmAction('This income record will be permanently deleted.', () => {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteTxForm').submit();
    }, 'Delete Income', '🗑️');
}
</script>
</body>
</html>
