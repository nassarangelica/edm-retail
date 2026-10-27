<?php
require __DIR__ . '/includes/bootstrap.php';
if (user() && user()['role'] !== 'customer') redirect(home_for_role(user()['role']));
$pageTitle = 'Home';

$offers = (user() && user()['customer_id']) ? eligible_offers((int)user()['customer_id']) : [];
$featured = db()->query('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.stock_qty>0 ORDER BY p.id DESC LIMIT 8')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="hero p-5 mb-4">
    <h1 class="fw-bold">Fresh finds, live stock, fair prices.</h1>
    <p class="lead mb-3">Shop online or in-store &mdash; our inventory is synchronized in real time across the website and the POS.</p>
    <a href="<?= url('products.php') ?>" class="btn btn-warning btn-lg">Browse products</a>
  </div>

  <?php if ($offers): ?>
    <h4 class="mb-3"><i class="bi bi-stars text-warning"></i> Offers picked for you</h4>
    <div class="row g-3 mb-4">
      <?php foreach ($offers as $o): ?>
        <div class="col-md-4"><div class="offer-pill p-3 h-100">
          <div class="fw-semibold"><?= e($o['title']) ?></div>
          <div class="small text-muted">Based on your shopping history &middot; valid until <?= e(date('M d, Y', strtotime($o['valid_to']))) ?></div>
          <a class="btn btn-sm btn-outline-dark mt-2" href="<?= url('products.php?category=' . (int)$o['category_id']) ?>">Shop <?= e($o['category_name']) ?></a>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php elseif (!user()): ?>
    <div class="alert alert-info"><a href="<?= url('login.php') ?>">Log in</a> or <a href="<?= url('register.php') ?>">register</a> to receive personalized offers.</div>
  <?php endif; ?>

  <h4 class="mb-3">Newest products</h4>
  <div class="row g-3">
    <?php foreach ($featured as $p): ?>
      <div class="col-6 col-md-3"><div class="card product-card h-100">
        <div class="product-emoji"><?= e($p['emoji']) ?></div>
        <div class="card-body">
          <div class="small text-muted"><?= e($p['category']) ?></div>
          <div class="fw-semibold"><?= e($p['name']) ?></div>
          <div class="price-now"><?= money($p['price']) ?></div>
        </div></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
