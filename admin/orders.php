<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Orders';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try { cancel_order((int)$_POST['order_id']); flash('success', 'Order cancelled and stock returned to inventory.'); }
    catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    redirect('admin/orders.php');
}
$ch = $_GET['channel'] ?? ''; $stt = $_GET['status'] ?? '';
$sql = 'SELECT o.*, u.name cname, cu.name cashier FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN users u ON u.id=c.user_id LEFT JOIN users cu ON cu.id=o.cashier_id WHERE 1=1';
$args = [];
if (in_array($ch, ['online', 'pos'], true)) { $sql .= ' AND o.channel=?'; $args[] = $ch; }
if (in_array($stt, ['processing', 'shipped', 'delivered', 'completed', 'cancelled'], true)) { $sql .= ' AND o.status=?'; $args[] = $stt; }
$st = $pdo->prepare($sql . ' ORDER BY o.id DESC LIMIT 100'); $st->execute($args); $orders = $st->fetchAll();
$detail = null;
if (isset($_GET['id'])) {
    $d = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $d->execute([(int)$_GET['id']]); $detail = $d->fetchAll();
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2 class="mb-3">Orders (online + POS)</h2>
  <form class="row g-2 mb-3" method="get">
    <div class="col-auto"><select name="channel" class="form-select"><option value="">All channels</option><option value="online" <?= $ch === 'online' ? 'selected' : '' ?>>Online</option><option value="pos" <?= $ch === 'pos' ? 'selected' : '' ?>>POS</option></select></div>
    <div class="col-auto"><select name="status" class="form-select"><option value="">All statuses</option>
      <?php foreach (['processing', 'shipped', 'delivered', 'completed', 'cancelled'] as $s): ?><option <?= $stt === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
  </form>
  <?php if ($detail !== null): ?><div class="card card-body mb-3"><b>Items of order #<?= (int)$_GET['id'] ?></b>
    <?php foreach ($detail as $i): ?><div class="small"><?= (int)$i['qty'] ?> &times; <?= e($i['product_name']) ?> &mdash; <?= money($i['line_total']) ?></div><?php endforeach; ?></div><?php endif; ?>
  <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Order</th><th>Date</th><th>Channel</th><th>Customer / Cashier</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><?= e($o['order_no']) ?></td><td class="small"><?= e($o['created_at']) ?></td>
      <td><span class="badge text-bg-<?= $o['channel'] === 'pos' ? 'warning' : 'primary' ?>"><?= e(strtoupper($o['channel'])) ?></span></td>
      <td class="small"><?= e($o['cname'] ?? 'Walk-in') ?><?= $o['cashier'] ? '<br><span class="text-muted">cashier: ' . e($o['cashier']) . '</span>' : '' ?></td>
      <td class="small"><?= e(pay_label($o['payment_method'])) ?></td><td><?= order_badge($o['status']) ?></td><td class="text-end"><?= money($o['total']) ?></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="?id=<?= (int)$o['id'] ?>">Items</a>
        <?php if (in_array($o['status'], ['processing', 'completed'], true)): ?>
        <form method="post" class="d-inline" data-confirm="Cancel this order and return stock?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
