<?php
$pageTitle = $pageTitle ?? APP_NAME;
$u = user();
$cartCount = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$isStaff = $u && $u['role'] !== 'customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
  <script>window.EDM = { base: '<?= e(BASE_URL) ?>' };</script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-nav sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>"><i class="bi bi-bag-heart-fill me-1"></i><?= e(APP_NAME) ?></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <?php if (!$isStaff): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('products.php') ?>">Products</a></li>
        <?php endif; ?>
        <?php if ($u && $u['role'] === 'customer'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('orders.php') ?>">My Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('profile.php') ?>">My Profile</a></li>
        <?php elseif ($isStaff): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url(home_for_role($u['role'])) ?>"><i class="bi bi-speedometer2"></i> Workspace</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('products.php') ?>">View storefront</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <?php if (!$isStaff): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('cart.php') ?>"><i class="bi bi-cart3"></i> Cart
            <?php if ($cartCount): ?><span class="badge rounded-pill bg-warning text-dark"><?= (int)$cartCount ?></span><?php endif; ?></a></li>
        <?php endif; ?>
        <?php if ($u): ?>
          <li class="nav-item"><span class="navbar-text me-2 small"><i class="bi bi-person-circle"></i> <?= e($u['name']) ?>
            <span class="badge bg-light text-dark"><?= e($u['role']) ?></span></span></li>
          <li class="nav-item"><a class="btn btn-sm btn-outline-light" href="<?= url('logout.php') ?>">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Login</a></li>
          <li class="nav-item"><a class="btn btn-sm btn-warning" href="<?= url('register.php') ?>">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main>
<?php foreach (flash() as [$t, $m]): ?>
  <div class="container mt-3"><div class="alert alert-<?= e($t) ?> alert-dismissible fade show auto-dismiss" role="alert"><?= e($m) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endforeach; ?>
