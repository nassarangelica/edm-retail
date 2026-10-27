<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'Users';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = $_POST['action'] ?? '';
    if ($a === 'create') {
        $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $role = $_POST['role'] ?? ''; $pw = $_POST['password'] ?? '';
        if (!in_array($role, ['admin', 'manager', 'cashier', 'logistics'], true) || text_len($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) {
            flash('danger', 'Fill in all fields (password min. 8 characters).');
        } else {
            try {
                $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)')->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
                audit('USER_CREATE', 'users', $pdo->lastInsertId(), null, ['email' => $email, 'role' => $role]);
                flash('success', 'Staff account created.');
            } catch (PDOException $e) { flash('danger', 'Email already exists.'); }
        }
    } elseif ($a === 'toggle' && (int)$_POST['id'] !== user()['id']) {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=? AND deleted_at IS NULL')->execute([(int)$_POST['id']]);
        audit('USER_TOGGLE', 'users', (int)$_POST['id']);
        flash('info', 'Account status changed.');
    }
    redirect('admin/users.php');
}
$list = $pdo->query("SELECT * FROM users ORDER BY FIELD(role,'admin','manager','cashier','logistics','customer'), id")->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2>User & role management (RBAC)</h2>
  <div class="card card-body mb-4"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="col-md-3"><input name="name" class="form-control" placeholder="Full name" required></div>
    <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
    <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
    <div class="col-md-2"><select name="role" class="form-select"><option>manager</option><option>cashier</option><option>logistics</option><option>admin</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Create staff</button></div></form></div>
  <div class="card"><table class="table mb-0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $u): ?><tr><td><?= (int)$u['id'] ?></td><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><span class="badge text-bg-dark"><?= e($u['role']) ?></span></td>
      <td><?= $u['deleted_at'] ? '<span class="badge text-bg-secondary">Erased (GDPR)</span>' : ($u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Disabled</span>') ?></td>
      <td><?php if (!$u['deleted_at'] && $u['id'] !== user()['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Enable / disable</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
