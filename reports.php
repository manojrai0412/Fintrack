<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
$uid = $_SESSION['user_id'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = $_GET['month'] ?? '';

// Build filter
$where  = "WHERE user_id=?";
$params = [$uid];
if ($month) {
    $where  .= " AND DATE_FORMAT(date,'%Y-%m')=?";
    $params[] = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
} else {
    $where  .= " AND YEAR(date)=?";
    $params[] = $year;
}

$income   = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions $where AND type='income'");
$income->execute($params); $totalIncome = $income->fetchColumn();

$expense  = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions $where AND type='expense'");
$expense->execute($params); $totalExpense = $expense->fetchColumn();

$txStmt = $conn->prepare("SELECT * FROM transactions $where ORDER BY date DESC, id DESC");
$txStmt->execute($params); $txs = $txStmt->fetchAll();

// Monthly summary (for yearly view)
$monthlySummary = [];
if (!$month) {
    for ($m = 1; $m <= 12; $m++) {
        $mStr = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        $inc = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income' AND DATE_FORMAT(date,'%Y-%m')=?");
        $inc->execute([$uid,$mStr]); $i = $inc->fetchColumn();
        $exp = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND DATE_FORMAT(date,'%Y-%m')=?");
        $exp->execute([$uid,$mStr]); $e = $exp->fetchColumn();
        if ($i > 0 || $e > 0) $monthlySummary[] = ['month'=>date('F', mktime(0,0,0,$m,1)),'income'=>(float)$i,'expense'=>(float)$e,'savings'=>max(0,(float)$i-(float)$e)];
    }
}

$years = range(date('Y'), date('Y')-5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Detailed financial summaries</p>
        </div>
    </div>
    <?php flash(); ?>

    <!-- Filters -->
    <div class="filter-row">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <select name="year">
                <?php foreach($years as $y): ?><option<?= $y==$year?' selected':'' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
            <select name="month">
                <option value="">Full Year</option>
                <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>"<?= (int)$month===$m?' selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Generate Report</button>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="stat-grid" style="margin-bottom:20px;">
        <div class="stat-card green"><div class="stat-label green">Total Income</div><div class="stat-value"><?= formatINR($totalIncome) ?></div></div>
        <div class="stat-card red"><div class="stat-label red">Total Expenses</div><div class="stat-value"><?= formatINR($totalExpense) ?></div></div>
        <div class="stat-card blue"><div class="stat-label blue">Net Balance</div><div class="stat-value"><?= formatINR($totalIncome - $totalExpense) ?></div></div>
        <div class="stat-card amber"><div class="stat-label amber">Transactions</div><div class="stat-value"><?= count($txs) ?></div></div>
    </div>

    <!-- Monthly Breakdown (yearly view) -->
    <?php if (!$month && !empty($monthlySummary)): ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Monthly Breakdown — <?= $year ?></div></div>
        <div class="card-body">
            <table>
                <thead><tr><th>Month</th><th>Income</th><th>Expenses</th><th>Savings</th><th>Savings Rate</th></tr></thead>
                <tbody>
                <?php foreach ($monthlySummary as $ms): ?>
                <tr>
                    <td><?= $ms['month'] ?></td>
                    <td style="color:var(--accent-green);font-weight:600;"><?= formatINR($ms['income']) ?></td>
                    <td style="color:var(--accent-red);font-weight:600;"><?= formatINR($ms['expense']) ?></td>
                    <td style="font-weight:600;"><?= formatINR($ms['savings']) ?></td>
                    <td><?= $ms['income']>0 ? round($ms['savings']/$ms['income']*100) : 0 ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Transactions -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">All Transactions</div>
            <span style="font-size:12px;color:var(--text-secondary)"><?= count($txs) ?> records</span>
        </div>
        <div class="card-body">
        <?php if (empty($txs)): ?>
            <div class="empty-state"><div class="empty-icon">📋</div><p>No transactions in this period.</p></div>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
                <tbody>
                <?php foreach ($txs as $tx): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($tx['date'])) ?></td>
                    <td><span class="badge badge-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
                    <td><?= htmlspecialchars($tx['category']) ?></td>
                    <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                    <td style="font-weight:600;color:<?= $tx['type']==='income' ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= ($tx['type']==='income'?'+':'-') . formatINR($tx['amount']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
