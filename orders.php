<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('customer');
$pageTitle = 'My orders';
$cid = (int)user()['customer_id'];

if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT o.*, s.tracking_no, s.carrier, s.status AS ship_status, s.updated_at AS ship_updated FROM orders o LEFT JOIN shipments s ON s.order_id=o.id WHERE o.id=? AND o.customer_id=?');
    $st->execute([(int)$_GET['id'], $cid]);
    $order = $st->fetch();
    if (!$order) { flash('danger', 'Order not found.'); redirect('orders.php'); }
    $it = db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$order['id']]); $items = $it->fetchAll();
    require __DIR__ . '/includes/header.php'; ?>
    <div class="container py-4" style="max-width:860px">
      <a href="<?= url('orders.php') ?>">&larr; All orders</a>
      <h2 class="my-3"><?= e($order['order_no']) ?> <?= order_badge($order['status']) ?></h2>
      <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="card card-body"><b>Delivery</b><br><?= e($order['shipping_address']) ?><br>
          <span class="small text-muted">Payment: <?= e(pay_label($order['payment_method'])) ?></span></div></div>
        <div class="col-md-6"><div class="card card-body"><b>Shipment tracking</b><br>
          <?php if ($order['tracking_no']): ?><?= e($order['carrier']) ?> &middot; <code><?= e($order['tracking_no']) ?></code><br>
            Status: <span class="badge text-bg-info"><?= e(str_replace('_', ' ', $order['ship_status'])) ?></span>
            <span class="small text-muted">(updated <?= e($order['ship_updated']) ?>)</span><?php else: ?>No shipment.<?php endif; ?></div></div>
      </div>
      <div class="card"><table class="table mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th class="text-end">Total</th></tr></thead><tbody>
        <?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?></td><td><?= (int)$i['qty'] ?></td><td><?= money($i['unit_price']) ?></td><td class="text-end"><?= money($i['line_total']) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot><tr><td colspan="3" class="text-end">Discount</td><td class="text-end">-<?= money($order['discount']) ?></td></tr>
        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end"><?= money($order['total']) ?></td></tr></tfoot></table></div>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}
$st = db()->prepare('SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC'); $st->execute([$cid]); $orders = $st->fetchAll();
$pts = db()->prepare('SELECT loyalty_points FROM customers WHERE id=?'); $pts->execute([$cid]);
require __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="mb-0">My orders</h2>
    <span class="badge text-bg-warning fs-6"><i class="bi bi-star-fill"></i> <?= (int)$pts->fetchColumn() ?> loyalty points</span></div>
  <?php if (!$orders): ?><div class="alert alert-secondary">No orders yet. <a href="<?= url('products.php') ?>">Start shopping</a>.</div><?php else: ?>
  <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Order</th><th>Date</th><th>Channel</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><?= e($o['order_no']) ?></td><td><?= e($o['created_at']) ?></td>
      <td><?= $o['channel'] === 'pos' ? 'In-store' : 'Online' ?></td><td><?= order_badge($o['status']) ?></td><td class="text-end"><?= money($o['total']) ?></td>
      <td><?php if ($o['channel'] === 'online'): ?><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int)$o['id'] ?>">View</a><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
