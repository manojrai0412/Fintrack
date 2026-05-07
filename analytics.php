<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
$uid = $_SESSION['user_id'];

$monthly = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M y', strtotime("-$i months"));
    $inc = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income' AND DATE_FORMAT(date,'%Y-%m')=?");
    $inc->execute([$uid, $month]); $incVal = $inc->fetchColumn();
    $exp = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND DATE_FORMAT(date,'%Y-%m')=?");
    $exp->execute([$uid, $month]); $expVal = $exp->fetchColumn();
    $monthly[] = ['label'=>$label,'income'=>(float)$incVal,'expense'=>(float)$expVal,'savings'=>max(0,(float)$incVal-(float)$expVal)];
}

$topCats = $conn->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id=? AND type='expense' GROUP BY category ORDER BY total DESC LIMIT 8");
$topCats->execute([$uid]); $expCats = $topCats->fetchAll();


$topIncome = $conn->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id=? AND type='income' GROUP BY category ORDER BY total DESC");
$topIncome->execute([$uid]); $incCats = $topIncome->fetchAll();


$thisMonth = date('Y-m');
$tmInc = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income' AND DATE_FORMAT(date,'%Y-%m')=?");
$tmInc->execute([$uid,$thisMonth]); $thisMonthIncome = $tmInc->fetchColumn();
$tmExp = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND DATE_FORMAT(date,'%Y-%m')=?");
$tmExp->execute([$uid,$thisMonth]); $thisMonthExpense = $tmExp->fetchColumn();


$lastMonth = date('Y-m', strtotime('-1 month'));
$lmInc = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income' AND DATE_FORMAT(date,'%Y-%m')=?");
$lmInc->execute([$uid,$lastMonth]); $lastMonthIncome = $lmInc->fetchColumn();
$lmExp = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND DATE_FORMAT(date,'%Y-%m')=?");
$lmExp->execute([$uid,$lastMonth]); $lastMonthExpense = $lmExp->fetchColumn();

$incChange = $lastMonthIncome > 0 ? round((($thisMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100, 1) : 0;
$expChange = $lastMonthExpense > 0 ? round((($thisMonthExpense - $lastMonthExpense) / $lastMonthExpense) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Analytics — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">Analytics</h1>
            <p class="page-subtitle">Your financial trends & insights</p>
        </div>
    </div>

    <!-- This month mini-stats -->
    <div class="stat-grid" style="margin-bottom:24px;">
        <div class="stat-card green">
            <div class="stat-label green">This Month Income</div>
            <div class="stat-value"><?= formatINR($thisMonthIncome) ?></div>
            <div style="font-size:12px;margin-top:6px;color:<?= $incChange >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                <?= $incChange >= 0 ? '▲' : '▼' ?> <?= abs($incChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-label red">This Month Expenses</div>
            <div class="stat-value"><?= formatINR($thisMonthExpense) ?></div>
            <div style="font-size:12px;margin-top:6px;color:<?= $expChange <= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                <?= $expChange >= 0 ? '▲' : '▼' ?> <?= abs($expChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label amber">This Month Savings</div>
            <div class="stat-value"><?= formatINR(max(0, $thisMonthIncome - $thisMonthExpense)) ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label blue">Savings Rate</div>
            <div class="stat-value"><?= $thisMonthIncome > 0 ? round(max(0,$thisMonthIncome-$thisMonthExpense)/$thisMonthIncome*100) : 0 ?>%</div>
        </div>
    </div>

    <!-- Line Chart -->
    <div class="chart-card" style="margin-bottom:20px;">
        <div class="chart-title">12-Month Financial Trend</div>
        <canvas id="trendChart" height="120"></canvas>
    </div>

    <!-- Category Charts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
        <div class="chart-card">
            <div class="chart-title">Top Expense Categories</div>
            <?php if (empty($expCats)): ?>
                <div class="empty-state" style="padding:30px 0"><p>No expense data yet.</p></div>
            <?php else: ?>
            <?php
            $totalExpCat = array_sum(array_column($expCats,'total'));
            $colors = ['#f85149','#ff7b72','#e3b341','#a371f7','#58a6ff','#39d353','#fd7e14','#20c997'];
            foreach ($expCats as $i => $cat):
                $pct = $totalExpCat > 0 ? round($cat['total']/$totalExpCat*100) : 0;
            ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span><?= htmlspecialchars($cat['category']) ?></span>
                    <span style="color:var(--text-secondary);"><?= formatINR($cat['total']) ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $colors[$i%count($colors)] ?>;"></div></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <div class="chart-title">Income Sources</div>
            <?php if (empty($incCats)): ?>
                <div class="empty-state" style="padding:30px 0"><p>No income data yet.</p></div>
            <?php else: ?>
            <?php
            $totalIncCat = array_sum(array_column($incCats,'total'));
            $greenShades = ['#39d353','#2ea043','#56d364','#3fb950','#23a54c','#1a7f37','#0f5a24'];
            foreach ($incCats as $i => $cat):
                $pct = $totalIncCat > 0 ? round($cat['total']/$totalIncCat*100) : 0;
            ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span><?= htmlspecialchars($cat['category']) ?></span>
                    <span style="color:var(--text-secondary);"><?= formatINR($cat['total']) ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $greenShades[$i%count($greenShades)] ?>;"></div></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
<script src="assets/js/main.js"></script>
<script>
const monthly = <?= json_encode($monthly) ?>;
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: monthly.map(d => d.label),
        datasets: [
            { label:'Income', data: monthly.map(d=>d.income), borderColor:'#39d353', backgroundColor:'rgba(57,211,83,0.08)', tension:0.4, fill:true, pointRadius:4, pointBackgroundColor:'#39d353' },
            { label:'Expenses', data: monthly.map(d=>d.expense), borderColor:'#f85149', backgroundColor:'rgba(248,81,73,0.08)', tension:0.4, fill:true, pointRadius:4, pointBackgroundColor:'#f85149' },
            { label:'Savings', data: monthly.map(d=>d.savings), borderColor:'#e3b341', backgroundColor:'rgba(227,179,65,0.06)', tension:0.4, fill:true, pointRadius:4, pointBackgroundColor:'#e3b341', borderDash:[4,4] }
        ]
    },
    options: {
        responsive:true,
        plugins:{ legend:{ labels:{ color:'#8b949e', font:{size:12} } } },
        scales:{
            x:{ ticks:{color:'#8b949e'}, grid:{color:'rgba(255,255,255,0.05)'} },
            y:{ ticks:{color:'#8b949e', callback:v=>'₹'+v.toLocaleString('en-IN')}, grid:{color:'rgba(255,255,255,0.05)'} }
        }
    }
});
</script>
</body>
</html>
