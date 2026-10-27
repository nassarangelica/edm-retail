<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('customer');
$pageTitle = 'My profile';
$uid = user()['id']; $cid = user()['customer_id'];

$load = function () use ($uid, $cid) {
    $st = db()->prepare('SELECT u.name,u.email,u.created_at,c.phone,c.address,c.loyalty_points,c.consent_at FROM users u JOIN customers c ON c.user_id=u.id WHERE u.id=?');
    $st->execute([$uid]); return $st->fetch();
};

/* GDPR: right of access / portability - download all my data as JSON */
if (isset($_GET['export'])) {
    $profile = $load();
    $o = db()->prepare('SELECT order_no,channel,status,subtotal,discount,total,payment_method,shipping_address,created_at FROM orders WHERE customer_id=?');
    $o->execute([$cid]);
    audit('GDPR_EXPORT', 'users', $uid);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="my-data.json"');
    echo json_encode(['profile' => $profile, 'orders' => $o->fetchAll()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'update') {
        $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $addr = trim($_POST['address'] ?? '');
        if (text_len($name) < 2) { flash('danger', 'Name is too short.'); redirect('profile.php'); }
        $old = $load();
        db()->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name, $uid]);
        db()->prepare('UPDATE customers SET phone=?, address=? WHERE id=?')->execute([$phone ?: null, $addr ?: null, $cid]);
        $_SESSION['user']['name'] = $name;
        audit('PROFILE_UPDATE', 'users', $uid, $old, ['name' => $name, 'phone' => $phone, 'address' => $addr]);
        flash('success', 'Profile updated.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        /* GDPR: right to erasure - anonymize personal data, keep order rows for accounting */
        $st = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $st->execute([$uid]);
        if (!password_verify($_POST['password'] ?? '', $st->fetchColumn())) { flash('danger', 'Wrong password. Account not deleted.'); redirect('profile.php'); }
        db()->prepare("UPDATE users SET name='Deleted User', email=CONCAT('deleted_', id, '@anonymized.local'), password_hash='', is_active=0, deleted_at=NOW() WHERE id=?")->execute([$uid]);
        db()->prepare('UPDATE customers SET phone=NULL, address=NULL, preferred_category_id=NULL WHERE id=?')->execute([$cid]);
        audit('GDPR_ERASURE', 'users', $uid);
        $_SESSION = []; session_destroy(); session_start();
        flash('info', 'Your personal data was erased and your account closed.');
        redirect('index.php');
    }
    redirect('profile.php');
}
$p = $load();
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:760px">
  <h2 class="mb-3">My profile</h2>
  <div class="card card-body mb-4">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
      <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($p['email']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($p['name']) ?>" required></div>
      <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($p['phone']) ?>"></div>
      <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($p['address']) ?></textarea></div>
      <button class="btn btn-primary">Save changes</button>
    </form>
  </div>
  <div class="card card-body border-info mb-4">
    <h5><i class="bi bi-shield-check"></i> Your data rights (GDPR)</h5>
    <p class="small mb-2">Consent given on <?= e($p['consent_at'] ?? 'n/a') ?>.</p>
    <a class="btn btn-outline-info" href="?export=1"><i class="bi bi-download"></i> Download my data (JSON)</a>
  </div>
  <div class="card card-body border-danger">
    <h5 class="text-danger">Delete my account</h5>
    <p class="small">Your name, email, phone and address are erased. Order totals are kept anonymously for accounting.</p>
    <form method="post" class="row g-2" data-confirm="This cannot be undone. Delete your account?"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <div class="col-sm-8"><input type="password" name="password" class="form-control" placeholder="Confirm with your password" required></div>
      <div class="col-sm-4"><button class="btn btn-danger w-100">Delete</button></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
