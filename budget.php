<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_budget') {
        $cat    = sanitize($_POST['category']);
        $amount = (float)$_POST['amount'];
        $month  = (int)$_POST['month'];
        $year   = (int)$_POST['year'];
        if ($cat && $amount > 0 && $month && $year) {
            $existing = $conn->prepare("SELECT id FROM budgets WHERE user_id=? AND category=? AND month=? AND year=?");
            $existing->execute([$uid,$cat,$month,$year]);
            if ($existing->fetch()) {
                $conn->prepare("UPDATE budgets SET amount=? WHERE user_id=? AND category=? AND month=? AND year=?")
                     ->execute([$amount,$uid,$cat,$month,$year]);
            } else {
                $conn->prepare("INSERT INTO budgets (user_id,category,amount,month,year) VALUES (?,?,?,?,?)")
                     ->execute([$uid,$cat,$amount,$month,$year]);
            }
            redirect('budget.php','Budget saved!');
        }
    } elseif ($action === 'delete_budget') {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM budgets WHERE id=? AND user_id=?")->execute([$id,$uid]);
        redirect('budget.php','Budget deleted.');
    }
}

$selMonth = (int)($_GET['month'] ?? date('n'));
$selYear  = (int)($_GET['year']  ?? date('Y'));
$budgets = $conn->prepare("SELECT b.*, COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id=b.user_id AND type='expense' AND category=b.category AND MONTH(date)=b.month AND YEAR(date)=b.year),0) as spent FROM budgets b WHERE b.user_id=? AND b.month=? AND b.year=? ORDER BY b.category");
$budgets->execute([$uid,$selMonth,$selYear]);
$budgetList = $budgets->fetchAll();
$expenseCategories = ['Food','Rent','Transport','Shopping','Bills','Entertainment','Health','Education','Fuel','Subscriptions','Others'];
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$years  = range(date('Y')+1, date('Y')-3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Budgets — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">Budgets</h1>
            <p class="page-subtitle">Set and track spending limits</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addBudgetModal')">＋ Set Budget</button>
    </div>
    <?php flash(); ?>
    <div class="filter-row">
        <form method="GET" style="display:flex;gap:10px;">
            <select name="month">
                <?php foreach($months as $i=>$mn): ?><option value="<?= $i+1 ?>"<?= ($i+1)===$selMonth?' selected':'' ?>><?= $mn ?></option><?php endforeach; ?>
            </select>
            <select name="year">
                <?php foreach(range(date('Y')+1, date('Y')-3) as $y): ?><option<?= $y===$selYear?' selected':'' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">View</button>
        </form>
    </div>
    <?php if (empty($budgetList)): ?>
    <div class="card"><div class="card-body-pad">
        <div class="empty-state"><div class="empty-icon">🎯</div><p>No budgets set for <?= $months[$selMonth-1].' '.$selYear ?>. Click "Set Budget" to get started.</p></div>
    </div></div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
        <?php foreach ($budgetList as $b):
            $pct  = $b['amount'] > 0 ? min(100, round($b['spent']/$b['amount']*100)) : 0;
            $over = $b['spent'] > $b['amount'];
            $color = $pct >= 90 ? 'var(--accent-red)' : ($pct >= 70 ? 'var(--accent-amber)' : 'var(--accent-green)');
        ?>
        <div class="stat-card" style="border-color:<?= $over ? 'rgba(248,81,73,0.4)' : 'var(--border)' ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <div>
                    <div class="stat-label" style="color:<?= $color ?>"><?= htmlspecialchars($b['category']) ?></div>
                    <div style="font-size:20px;font-weight:700;font-family:'Syne',sans-serif;"><?= formatINR($b['spent']) ?> <span style="font-size:13px;color:var(--text-secondary);font-weight:400;">/ <?= formatINR($b['amount']) ?></span></div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <span style="font-size:18px;font-weight:700;color:<?= $color ?>"><?= $pct ?>%</span>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete_budget">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete this budget?')">🗑️</button>
                    </form>
                </div>
            </div>
            <div class="progress-bar" style="height:8px;">
                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
            </div>
            <?php if ($over): ?>
            <div style="margin-top:8px;font-size:12px;color:var(--accent-red);font-weight:500;">⚠️ Over budget by <?= formatINR($b['spent'] - $b['amount']) ?></div>
            <?php else: ?>
            <div style="margin-top:8px;font-size:12px;color:var(--text-secondary);"><?= formatINR($b['amount'] - $b['spent']) ?> remaining</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</div>


<div class="modal-overlay" id="addBudgetModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Set Budget</h2><button class="modal-close" onclick="closeModal('addBudgetModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_budget">
            <div class="form-group"><label>Category</label>
                <select name="category" required><?php foreach($expenseCategories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Budget Amount (RS.)</label><input type="number" name="amount" min="1" step="0.01" required></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group"><label>Month</label>
                    <select name="month"><?php foreach($months as $i=>$mn): ?><option value="<?= $i+1 ?>"<?= ($i+1)===$selMonth?' selected':'' ?>><?= $mn ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Year</label>
                    <select name="year"><?php foreach(range(date('Y')+1, date('Y')-2) as $y): ?><option<?= $y===$selYear?' selected':'' ?>><?= $y ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Save Budget</button><button type="button" class="btn btn-secondary" onclick="closeModal('addBudgetModal')">Cancel</button></div>
        </form>
    </div>
</div>

<?php include 'includes/confirm.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
