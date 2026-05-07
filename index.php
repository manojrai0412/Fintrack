<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();

$uid = $_SESSION['user_id'];


$income = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND type='income'");
$income->execute([$uid]); $incomeTotal = $income->fetchColumn();

$expense = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND type='expense'");
$expense->execute([$uid]); $expenseTotal = $expense->fetchColumn();

$balance  = $incomeTotal - $expenseTotal;
$savings  = max(0, $balance);

$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income' AND DATE_FORMAT(date,'%Y-%m')=?");
    $stmt->execute([$uid, $month]); $inc = $stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND DATE_FORMAT(date,'%Y-%m')=?");
    $stmt->execute([$uid, $month]); $exp = $stmt->fetchColumn();
    $chartData[] = ['label'=>$label,'income'=>(float)$inc,'expense'=>(float)$exp];
}

$catStmt = $conn->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id=? AND type='expense' GROUP BY category ORDER BY total DESC LIMIT 6");
$catStmt->execute([$uid]);
$categories = $catStmt->fetchAll();

$recent = $conn->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY date DESC, id DESC LIMIT 10");
$recent->execute([$uid]);
$transactions = $recent->fetchAll();

$chartJson    = json_encode($chartData);
$catJson      = json_encode($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — FinTrack</title>
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
            <h1 class="page-title">Dashboard Overview</h1>
            <p class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> 👋</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addTxModal')">＋ Add Transaction</button>
    </div>

    <?php flash(); ?>

    
    <div class="stat-grid">
        <div class="stat-card blue">
            <div class="stat-label blue">Total Balance</div>
            <div class="stat-value"><?= formatINR($balance) ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label green">Total Income</div>
            <div class="stat-value"><?= formatINR($incomeTotal) ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-label red">Total Expenses</div>
            <div class="stat-value"><?= formatINR($expenseTotal) ?></div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label amber">Savings</div>
            <div class="stat-value"><?= formatINR($savings) ?></div>
        </div>
    </div>


    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">Income vs Expenses</div>
            <canvas id="barChart" height="200"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">Expense Breakdown</div>
            <canvas id="pieChart" height="200"></canvas>
        </div>
    </div>
  
    <div class="card">
        <div class="card-header">
            <div class="card-title">Recent Transactions</div>
            <a href="expenses.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No transactions yet. Add your first one!</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Amount</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($tx['date'])) ?></td>
                    <td><span class="badge badge-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
                    <td><?= htmlspecialchars($tx['category']) ?></td>
                    <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                    <td style="font-weight:600;color:<?= $tx['type']==='income' ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                        <?= ($tx['type']==='income'?'+':'-') . formatINR($tx['amount']) ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm btn-icon" title="Edit"
                            onclick="editTx(<?= htmlspecialchars(json_encode($tx)) ?>)">✏️</button>
                        <button class="btn btn-danger btn-sm btn-icon" title="Delete"
                            onclick="deleteTx(<?= $tx['id'] ?>)">🗑️</button>
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


<div class="modal-overlay" id="addTxModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Add Transaction</h2>
            <button class="modal-close" onclick="closeModal('addTxModal')">✕</button>
        </div>
        <form method="POST" action="actions/add_transaction.php">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Type</label>
                <select name="type" required onchange="updateCategories(this.value)">
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="categorySelect" required></select>
            </div>
            <div class="form-group">
                <label>Amount (₹)</label>
                <input type="number" name="amount" placeholder="0.00" min="0.01" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" placeholder="Brief note..."></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Add Transaction</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTxModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editTxModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Edit Transaction</h2>
            <button class="modal-close" onclick="closeModal('editTxModal')">✕</button>
        </div>
        <form method="POST" action="actions/add_transaction.php" id="editTxForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="edit_type" required onchange="updateEditCategories(this.value)">
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="edit_category" required></select>
            </div>
            <div class="form-group">
                <label>Amount (₹)</label>
                <input type="number" name="amount" id="edit_amount" min="0.01" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" id="edit_date" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTxModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>


<form method="POST" action="actions/add_transaction.php" id="deleteTxForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<?php include 'includes/confirm.php'; ?>

<script src="assets/js/main.js"></script>
<script>
// Categories
const incomeCategories  = ['Salary','Freelance','Business','Investment','Rental','Gift','Bonus','Other'];
const expenseCategories = ['Food','Rent','Transport','Shopping','Bills','Entertainment','Health','Education','Fuel','Subscriptions','Others'];

function updateCategories(type) {
    const sel = document.getElementById('categorySelect');
    const cats = type === 'income' ? incomeCategories : expenseCategories;
    sel.innerHTML = cats.map(c => `<option>${c}</option>`).join('');
}
function updateEditCategories(type, selected = '') {
    const sel = document.getElementById('edit_category');
    const cats = type === 'income' ? incomeCategories : expenseCategories;
    sel.innerHTML = cats.map(c => `<option${c===selected?' selected':''}>${c}</option>`).join('');
}
updateCategories('income');

function editTx(tx) {
    document.getElementById('edit_id').value = tx.id;
    document.getElementById('edit_type').value = tx.type;
    updateEditCategories(tx.type, tx.category);
    document.getElementById('edit_amount').value = tx.amount;
    document.getElementById('edit_date').value = tx.date;
    document.getElementById('edit_description').value = tx.description || '';
    openModal('editTxModal');
}
function deleteTx(id) {
    confirmAction('This transaction will be permanently deleted.', () => {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteTxForm').submit();
    }, 'Delete Transaction', '🗑️');
}

const chartData = <?= $chartJson ?>;
const catData   = <?= $catJson ?>;

const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [
            { label: 'Income',   data: chartData.map(d => d.income),  backgroundColor: 'rgba(57,211,83,0.7)',  borderRadius: 4 },
            { label: 'Expenses', data: chartData.map(d => d.expense), backgroundColor: 'rgba(248,81,73,0.7)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#8b949e', font: { size: 12 } } } },
        scales: {
            x: { ticks: { color: '#8b949e' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#8b949e', callback: v => '₹' + v.toLocaleString('en-IN') }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    }
});

const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: catData.map(d => d.category),
        datasets: [{ data: catData.map(d => d.total), backgroundColor: ['#39d353','#58a6ff','#e3b341','#f85149','#a371f7','#fd7e14'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { color: '#8b949e', padding: 16, font: { size: 11 } } } }
    }
});
</script>
</body>
</html>
