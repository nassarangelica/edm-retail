<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) redirect(home_for_role(user()['role']));
$pageTitle = 'Register';
$errors = [];
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $pref  = (int)($_POST['preferred_category_id'] ?? 0) ?: null;

    if (text_len($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $errors[] = 'Password must be at least 8 characters with letters and numbers.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'Phone number format is invalid.';
    if (empty($_POST['consent'])) $errors[] = 'You must accept the privacy notice (GDPR consent).';
    if (!$errors) {
        $ex = db()->prepare('SELECT 1 FROM users WHERE email = ?'); $ex->execute([$email]);
        if ($ex->fetch()) $errors[] = 'That email is already registered.';
    }
    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?, 'customer')")->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO customers(user_id,phone,preferred_category_id,consent_at) VALUES (?,?,?,NOW())')->execute([$uid, $phone ?: null, $pref]);
        $pdo->commit();
        audit('REGISTER', 'users', $uid, null, ['email' => $email], $email);
        flash('success', 'Account created. You can now log in.');
        redirect('login.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:520px">
  <div class="card card-body shadow-sm">
    <h3 class="mb-3">Create customer account</h3>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Full name</label><input name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required>
        <div class="form-text">At least 8 characters, letters and numbers.</div></div>
      <div class="mb-3"><label class="form-label">Phone (optional)</label><input name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Favourite category (for personalized offers)</label>
        <select name="preferred_category_id" class="form-select"><option value="0">- none -</option>
          <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent" id="consent" value="1">
        <label class="form-check-label small" for="consent">I agree that my data is stored to process orders and personalize offers. I can export or delete it anytime (GDPR).</label></div>
      <button class="btn btn-primary w-100">Register</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
