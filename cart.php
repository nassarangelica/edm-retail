<?php
require __DIR__ . '/includes/bootstrap.php';
if (has_role('admin', 'manager', 'cashier', 'logistics')) redirect(home_for_role(user()['role']));
$pageTitle = 'Shopping cart';
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($action === 'add' && $pid) {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
        flash('success', 'Added to cart.');
        redirect('products.php');
    } elseif ($action === 'update') {
        foreach ($_POST['qty'] ?? [] as $id => $q) {
            $q = (int)$q;
            if ($q <= 0) unset($_SESSION['cart'][(int)$id]); else $_SESSION['cart'][(int)$id] = $q;
        }
        flash('info', 'Cart updated.');
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$pid]);
    }
    redirect('cart.php');
}

$lines = []; $subtotal = 0; $discount = 0;
$offers = (user() && user()['customer_id']) ? eligible_offers((int)user()['customer_id']) : [];
if ($_SESSION['cart']) {
    $ids = array_keys($_SESSION['cart']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM products WHERE is_active=1 AND id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $p) {
        $qty = min($_SESSION['cart'][$p['id']], max(0, (int)$p['stock_qty']));
        [$pct, $ud] = unit_discount($p, $offers);
        $lines[] = ['p' => $p, 'qty' => $qty, 'disc' => $ud * $qty, 'total' => ($p['price'] - $ud) * $qty, 'pct' => $pct];
        $subtotal += $p['price'] * $qty; $discount += $ud * $qty;
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h2 class="mb-3">Shopping cart</h2>
  <?php if (!$lines): ?>
    <div class="alert alert-secondary">Your cart is empty. <a href="<?= url('products.php') ?>">Continue shopping</a>.</div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
        <div class="card"><div class="table-responsive"><table class="table mb-0">
          <thead><tr><th>Product</th><th>Price</th><th style="width:110px">Qty</th><th class="text-end">Total</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lines as $l): $p = $l['p']; ?>
            <tr>
              <td><?= e($p['emoji']) ?> <?= e($p['name']) ?><?php if ($l['pct'] > 0): ?> <span class="badge text-bg-warning">-<?= (float)$l['pct'] ?>%</span><?php endif; ?></td>
              <td><?= money($p['price']) ?></td>
              <td><input type="number" class="form-control form-control-sm" name="qty[<?= (int)$p['id'] ?>]" value="<?= (int)$l['qty'] ?>" min="0" max="<?= (int)$p['stock_qty'] ?>"></td>
              <td class="text-end"><?= money($l['total']) ?></td>
              <td><button class="btn btn-sm btn-outline-danger" form="rm-<?= (int)$p['id'] ?>"><i class="bi bi-trash"></i></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div></div>
        <button class="btn btn-outline-primary mt-3">Update quantities</button>
      </form>
      <?php foreach ($lines as $l): ?>
        <form id="rm-<?= (int)$l['p']['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int)$l['p']['id'] ?>"></form>
      <?php endforeach; ?>
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body">
        <h5>Order summary</h5>
        <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
        <div class="d-flex justify-content-between text-success"><span>Personalized discount</span><span>-<?= money($discount) ?></span></div>
        <hr><div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= money($subtotal - $discount) ?></span></div>
        <a href="<?= url('checkout.php') ?>" class="btn btn-primary w-100 mt-3">Proceed to checkout</a>
      </div></div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
