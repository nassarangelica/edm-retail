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
<div class="login-page">
  <div class="login-card">
    <section class="login-brand">
      <div class="login-brand-content">
        <div class="login-brand-icon"><i class="bi bi-bag-heart-fill"></i></div>
        <h1>EDM Retail</h1>
        <p>A simple, comfortable retail experience for browsing products, managing orders, and keeping your shopping activity in one place.</p>
        <div class="login-feature"><i class="bi bi-check-circle-fill"></i><span>Secure account access</span></div>
        <div class="login-feature"><i class="bi bi-check-circle-fill"></i><span>Real-time product availability</span></div>
        <div class="login-feature"><i class="bi bi-check-circle-fill"></i><span>Easy order and profile management</span></div>
      </div>
    </section>

    <section class="login-form">
      <div class="login-form-inner">
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to continue to your EDM Retail account.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger login-alert"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email address</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" autocomplete="email" required autofocus>
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn login-submit"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
        </form>

        <div class="login-register">Don't have an account? <a href="<?= url('register.php') ?>">Create an account</a></div>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
