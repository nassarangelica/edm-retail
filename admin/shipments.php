<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager', 'logistics');
$pageTitle = 'Shipments';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        apply_shipment_update($_POST['tracking_no'] ?? '', $_POST['status'] ?? '', user()['email']);
        flash('success', 'Shipment updated; order status synchronized.');
    } catch (InvalidArgumentException $e) { flash('danger', $e->getMessage()); }
    redirect('admin/shipments.php');
}
$rows = db()->query("SELECT s.*, o.order_no, o.shipping_address, o.status AS order_status, u.name cname FROM shipments s
    JOIN orders o ON o.id=s.order_id LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN users u ON u.id=c.user_id
    WHERE o.status <> 'cancelled' ORDER BY FIELD(s.status,'pending','picked_up','in_transit','failed','delivered'), s.id DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2 class="mb-1">Shipments (logistics)</h2>
  <p class="text-muted small">External couriers can also update these records through <code>POST /api/shipment.php</code> with an API key.</p>
  <div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Tracking</th><th>Order</th><th>Customer</th><th>Deliver to</th><th>Shipment</th><th>Update</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><code><?= e($r['tracking_no']) ?></code></td><td><?= e($r['order_no']) ?><br><?= order_badge($r['order_status']) ?></td>
      <td><?= e($r['cname']) ?></td><td class="small"><?= e($r['shipping_address']) ?></td><td><span class="badge text-bg-info"><?= e(str_replace('_', ' ', $r['status'])) ?></span><div class="small text-muted"><?= e($r['updated_at']) ?></div></td>
      <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="tracking_no" value="<?= e($r['tracking_no']) ?>">
        <select name="status" class="form-select form-select-sm" style="width:130px"><?php foreach (['pending', 'picked_up', 'in_transit', 'delivered', 'failed'] as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-primary">Update</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
