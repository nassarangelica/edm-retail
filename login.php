<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) redirect(home_for_role(user()['role']));
$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    // brute-force protection: max 5 failed logins per IP per 10 minutes
    $st = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE action='LOGIN_FAILED' AND ip_address=? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
    $st->execute([client_ip()]);
    if ($st->fetchColumn() >= 5) {
        $error = 'Too many failed attempts. Please try again in 10 minutes.';
    } else {
        $st = db()->prepare('SELECT u.*, c.id AS customer_id FROM users u LEFT JOIN customers c ON c.user_id = u.id
                             WHERE u.email = ? AND u.is_active = 1 AND u.deleted_at IS NULL');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'],
                                 'customer_id' => $u['customer_id'] ? (int)$u['customer_id'] : null];
            audit('LOGIN', 'users', $u['id']);
            redirect(home_for_role($u['role']));
        }
        audit('LOGIN_FAILED', 'users', null, null, ['email' => $email], $email);
        $error = 'Invalid email or password.';
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:440px">
  <div class="card card-body shadow-sm">
    <h3 class="mb-3">Login</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <div class="small mt-3">No account? <a href="<?= url('register.php') ?>">Register as customer</a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
