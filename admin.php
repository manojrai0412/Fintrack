<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
requireLogin();
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id === $_SESSION['user_id']) redirect('admin.php','You cannot delete yourself.','error');
        $conn->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        redirect('admin.php','User deleted successfully.');
    } elseif ($action === 'change_role') {
        $id   = (int)$_POST['id'];
        $role = in_array($_POST['role'],['user','admin']) ? $_POST['role'] : 'user';
        if ($id === $_SESSION['user_id']) redirect('admin.php','You cannot change your own role.','error');
        $conn->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$id]);
        redirect('admin.php','User role updated.');
    } elseif ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 8) redirect('admin.php','Password must be at least 8 characters.','error');
        $conn->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);
        redirect('admin.php','Password reset successfully.');

    } elseif ($action === 'delete_transaction') {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM transactions WHERE id=?")->execute([$id]);
        redirect('admin.php','Transaction deleted.');

    } elseif ($action === 'edit_transaction') {
        $id       = (int)$_POST['id'];
        $type     = in_array($_POST['type'],['income','expense'])?$_POST['type']:'expense';
        $category = sanitize($_POST['category']);
        $amount   = (float)$_POST['amount'];
        $date     = $_POST['date'];
        $desc     = sanitize($_POST['description'] ?? '');
        $conn->prepare("UPDATE transactions SET type=?,category=?,amount=?,date=?,description=? WHERE id=?")->execute([$type,$category,$amount,$date,$desc,$id]);
        redirect('admin.php','Transaction updated.');

    } elseif ($action === 'add_user') {
        $name  = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $pass  = $_POST['password'];
        $role  = in_array($_POST['role'],['user','admin'])?$_POST['role']:'user';
        if (!$name||!$email||strlen($pass)<6) redirect('admin.php','Invalid data.','error');
        $chk = $conn->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
        if ($chk->fetch()) redirect('admin.php','Email already exists.','error');
        $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role]);
        redirect('admin.php','User created successfully.');

    } elseif ($action === 'edit_user') {
        $id    = (int)$_POST['id'];
        $name  = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $role  = in_array($_POST['role'],['user','admin'])?$_POST['role']:'user';
        if ($id === $_SESSION['user_id'] && $role === 'user') redirect('admin.php','Cannot demote yourself.','error');
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $chk->execute([$email,$id]);
        if ($chk->fetch()) redirect('admin.php','Email already in use.','error');
        $conn->prepare("UPDATE users SET name=?,email=?,role=? WHERE id=?")->execute([$name,$email,$role,$id]);
        redirect('admin.php','User updated.');
    }
}


$users = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM transactions WHERE user_id=u.id) as tx_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
$transactions = $conn->query("SELECT t.*, u.name as user_name, u.email as user_email FROM transactions t JOIN users u ON t.user_id=u.id ORDER BY t.date DESC, t.id DESC LIMIT 100")->fetchAll();


$totalUsers    = count($users);
$totalTx       = $conn->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalIncome   = $conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income'")->fetchColumn();
$totalExpenses = $conn->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='expense'")->fetchColumn();

$expenseCategories = ['Food','Rent','Transport','Shopping','Bills','Entertainment','Health','Education','Fuel','Subscriptions','Others'];
$incomeCategories  = ['Salary','Freelance','Business','Investment','Rental','Gift','Bonus','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel — FinTrack</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1 class="page-title">Admin Panel</h1>
            <p class="page-subtitle">Full system control & management</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')">＋ Add User</button>
    </div>
    <?php flash(); ?>


    <div class="stat-grid" style="margin-bottom:24px;">
        <div class="stat-card blue"><div class="stat-label blue">Total Users</div><div class="stat-value"><?= $totalUsers ?></div></div>
        <div class="stat-card amber"><div class="stat-label amber">Total Transactions</div><div class="stat-value"><?= $totalTx ?></div></div>
        <div class="stat-card green"><div class="stat-label green">Platform Income</div><div class="stat-value"><?= formatINR($totalIncome) ?></div></div>
        <div class="stat-card red"><div class="stat-label red">Platform Expenses</div><div class="stat-value"><?= formatINR($totalExpenses) ?></div></div>
    </div>

    <div class="tabs" data-tabs="1">
        <button class="tab-btn active" data-tab="tab-users">👥 Users (<?= $totalUsers ?>)</button>
        <button class="tab-btn" data-tab="tab-transactions">💳 Transactions (<?= min($totalTx,100) ?>)</button>
    </div>

   
    <div class="tab-content active" id="tab-users">
        <div class="card">
            <div class="card-body">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Transactions</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="color:var(--text-muted);font-size:12px;"><?= $u['id'] ?></td>
                        <td style="font-weight:600;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:28px;height:28px;background:linear-gradient(135deg,var(--accent-green),var(--accent-blue));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#0d1117;flex-shrink:0;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if($u['id']===$_SESSION['user_id']): ?><span style="font-size:10px;color:var(--accent-amber);font-weight:500;">(you)</span><?php endif; ?>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;"><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= $u['tx_count'] ?></td>
                        <td style="font-size:12px;color:var(--text-secondary);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <button class="btn btn-secondary btn-sm btn-icon" title="Edit" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">✏️</button>
                                <button class="btn btn-warning btn-sm btn-icon" title="Reset Password" onclick="resetPwd(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">🔑</button>
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TRANSACTIONS TAB -->
    <div class="tab-content" id="tab-transactions">
        <div class="card">
            <div class="card-header"><div class="card-title">All Transactions (latest 100)</div></div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                <div class="empty-state"><div class="empty-icon">💳</div><p>No transactions found.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Category</th><th>Amount</th><th>Date</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td style="font-size:11px;color:var(--text-muted);"><?= $tx['id'] ?></td>
                        <td style="font-size:12px;">
                            <div><?= htmlspecialchars($tx['user_name']) ?></div>
                            <div style="color:var(--text-muted);font-size:11px;"><?= htmlspecialchars($tx['user_email']) ?></div>
                        </td>
                        <td><span class="badge badge-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
                        <td style="font-size:13px;"><?= htmlspecialchars($tx['category']) ?></td>
                        <td style="font-weight:600;color:<?= $tx['type']==='income'?'var(--accent-green)':'var(--accent-red)' ?>">
                            <?= ($tx['type']==='income'?'+':'-') . formatINR($tx['amount']) ?>
                        </td>
                        <td style="font-size:12px;"><?= date('d M Y', strtotime($tx['date'])) ?></td>
                        <td style="font-size:12px;color:var(--text-secondary);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button class="btn btn-secondary btn-sm btn-icon" onclick="editTx(<?= htmlspecialchars(json_encode($tx)) ?>)">✏️</button>
                                <button class="btn btn-danger btn-sm btn-icon" onclick="deleteTx(<?= $tx['id'] ?>)">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Add User</h2><button class="modal-close" onclick="closeModal('addUserModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Role</label>
                <select name="role"><option value="user">User</option><option value="admin">Admin</option></select>
            </div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Create User</button><button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button></div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Edit User</h2><button class="modal-close" onclick="closeModal('editUserModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="id" id="eu_id">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" id="eu_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="eu_email" required></div>
            <div class="form-group"><label>Role</label>
                <select name="role" id="eu_role"><option value="user">User</option><option value="admin">Admin</option></select>
            </div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button><button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button></div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPwdModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Reset Password</h2><button class="modal-close" onclick="closeModal('resetPwdModal')">✕</button></div>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px;">Reset password for: <strong id="resetPwdName"></strong></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetPwdId">
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-warning" style="flex:1;">Reset Password</button><button type="button" class="btn btn-secondary" onclick="closeModal('resetPwdModal')">Cancel</button></div>
        </form>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal-overlay" id="editTxModal">
    <div class="modal">
        <div class="modal-header"><h2 class="modal-title">Edit Transaction</h2><button class="modal-close" onclick="closeModal('editTxModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_transaction">
            <input type="hidden" name="id" id="etx_id">
            <div class="form-group"><label>Type</label>
                <select name="type" id="etx_type" onchange="updateTxCats(this.value)">
                    <option value="income">Income</option><option value="expense">Expense</option>
                </select>
            </div>
            <div class="form-group"><label>Category</label><select name="category" id="etx_cat"></select></div>
            <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" id="etx_amount" min="0.01" step="0.01" required></div>
            <div class="form-group"><label>Date</label><input type="date" name="date" id="etx_date" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="etx_desc"></textarea></div>
            <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary" style="flex:1;">Save</button><button type="button" class="btn btn-secondary" onclick="closeModal('editTxModal')">Cancel</button></div>
        </form>
    </div>
</div>

<form method="POST" id="deleteUserForm" style="display:none;"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" id="du_id"></form>
<form method="POST" id="deleteTxAdminForm" style="display:none;"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="id" id="dtx_id"></form>

<?php include 'includes/confirm.php'; ?>
<script src="assets/js/main.js"></script>
<script>
const incCats = ['Salary','Freelance','Business','Investment','Rental','Gift','Bonus','Other'];
const expCats = ['Food','Rent','Transport','Shopping','Bills','Entertainment','Health','Education','Fuel','Subscriptions','Others'];

function updateTxCats(type, selected='') {
    const cats = type==='income' ? incCats : expCats;
    const sel = document.getElementById('etx_cat');
    sel.innerHTML = cats.map(c => `<option${c===selected?' selected':''}>${c}</option>`).join('');
}

function editUser(u) {
    document.getElementById('eu_id').value = u.id;
    document.getElementById('eu_name').value = u.name;
    document.getElementById('eu_email').value = u.email;
    document.getElementById('eu_role').value = u.role;
    openModal('editUserModal');
}
function resetPwd(id, name) {
    document.getElementById('resetPwdId').value = id;
    document.getElementById('resetPwdName').textContent = name;
    openModal('resetPwdModal');
}
function deleteUser(id, name) {
    confirmAction(`User "${name}" and all their data will be permanently deleted.`, () => {
        document.getElementById('du_id').value = id;
        document.getElementById('deleteUserForm').submit();
    }, 'Delete User', '🗑️');
}
function editTx(tx) {
    document.getElementById('etx_id').value = tx.id;
    document.getElementById('etx_type').value = tx.type;
    updateTxCats(tx.type, tx.category);
    document.getElementById('etx_amount').value = tx.amount;
    document.getElementById('etx_date').value = tx.date;
    document.getElementById('etx_desc').value = tx.description||'';
    openModal('editTxModal');
}
function deleteTx(id) {
    confirmAction('This transaction will be permanently deleted.', () => {
        document.getElementById('dtx_id').value = id;
        document.getElementById('deleteTxAdminForm').submit();
    }, 'Delete Transaction', '🗑️');
}
// Activate tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab)?.classList.add('active');
    });
});
</script>
</body>
</html>
