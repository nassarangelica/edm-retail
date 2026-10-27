<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('cashier', 'admin');
$pageTitle = 'POS Terminal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $items = [];
    foreach ((json_decode($_POST['items'] ?? '{}', true) ?: []) as $pid => $qty) $items[(int)$pid] = (int)$qty;
    $customerId = null;
    $email = strtolower(trim($_POST['customer_email'] ?? ''));
    if ($email !== '') {
        $c = db()->prepare("SELECT c.id FROM customers c JOIN users u ON u.id=c.user_id WHERE u.email=? AND u.deleted_at IS NULL");
        $c->execute([$email]);
        $customerId = $c->fetchColumn() ?: null;
        if (!$customerId) flash('warning', 'Customer email not found - sale recorded as walk-in.');
    }
    try {
        $id = create_order(['channel' => 'pos', 'customer_id' => $customerId ? (int)$customerId : null, 'cashier_id' => user()['id'],
                            'items' => $items, 'payment_method' => 'cash']);
        $o = db()->prepare('SELECT order_no,total FROM orders WHERE id=?'); $o->execute([$id]); $o = $o->fetch();
        flash('success', "Sale {$o['order_no']} completed - total " . money($o['total']) . '. Stock updated everywhere.');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('pos/index.php');
}

$products = db()->query('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.name')->fetchAll();
$recent = db()->prepare("SELECT order_no,total,created_at,status FROM orders WHERE channel='pos' AND cashier_id=? ORDER BY id DESC LIMIT 6");
$recent->execute([user()['id']]); $recent = $recent->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4" id="pos">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="mb-0"><i class="bi bi-upc-scan"></i> Select items</h4></div>
      <input id="pos-search" class="form-control mb-3" placeholder="Search product or SKU...">
      <div class="row g-2 pos-grid">
        <?php foreach ($products as $p): ?>
          <div class="col-6 col-md-4 pos-col" data-search="<?= e(strtolower($p['name'] . ' ' . $p['sku'])) ?>">
            <button type="button" class="pos-item <?= $p['stock_qty'] <= 0 ? 'disabled' : '' ?>"
              data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e($p['price']) ?>" data-stock="<?= (int)$p['stock_qty'] ?>">
              <div class="fs-2"><?= e($p['emoji']) ?></div>
              <div class="small fw-semibold"><?= e($p['name']) ?></div>
              <div><?= money($p['price']) ?></div>
              <div class="small text-muted">Stock: <?= (int)$p['stock_qty'] ?></div>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card"><div class="card-body">
        <h4><i class="bi bi-receipt"></i> Current sale</h4>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="items" id="items-json" value="{}">
          <table class="table table-sm"><thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead><tbody id="ticket-body"></tbody></table>
          <div class="d-flex justify-content-between fs-4 fw-bold mb-3"><span>Total</span><span id="ticket-total">₱0.00</span></div>
          <input type="email" name="customer_email" class="form-control mb-3" placeholder="Customer email (optional - loyalty & offers)">
          <button id="pay-btn" class="btn btn-success w-100 btn-lg" disabled><i class="bi bi-cash-coin"></i> Complete sale (cash)</button>
        </form>
      </div></div>
      <div class="card mt-3"><div class="card-body">
        <h6>My recent sales</h6>
        <?php foreach ($recent as $r): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($r['order_no']) ?></span><span><?= money($r['total']) ?></span></div><?php endforeach; ?>
        <?php if (!$recent): ?><span class="small text-muted">No sales yet.</span><?php endif; ?>
      </div></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
