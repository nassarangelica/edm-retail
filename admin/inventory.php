<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Inventory';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid = (int)($_POST['product_id'] ?? 0); $qty = (int)($_POST['qty'] ?? 0);
    $reason = $_POST['reason'] ?? 'restock'; $note = trim($_POST['note'] ?? '');
    if (!in_array($reason, ['restock', 'adjustment'], true) || $qty === 0) { flash('danger', 'Enter a non-zero quantity.'); redirect('admin/inventory.php'); }
    if ($reason === 'restock' && $qty < 0) { flash('danger', 'Restock quantity must be positive. Use "adjustment" for negative corrections.'); redirect('admin/inventory.php'); }
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT stock_qty FROM products WHERE id=? FOR UPDATE'); $s->execute([$pid]); $old = $s->fetchColumn();
    if ($old === false || $old + $qty < 0) { $pdo->rollBack(); flash('danger', 'Stock cannot go below zero.'); redirect('admin/inventory.php'); }
    $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id=?')->execute([$qty, $pid]);
    $pdo->prepare('INSERT INTO stock_movements(product_id,change_qty,reason,user_id,note) VALUES (?,?,?,?,?)')->execute([$pid, $qty, $reason, user()['id'], $note ?: null]);
    audit('STOCK_' . strtoupper($reason), 'products', $pid, ['stock' => (int)$old], ['stock' => (int)$old + $qty]);
    $pdo->commit();
    flash('success', 'Stock updated. Storefront and POS will show the new quantity immediately.');
    redirect('admin/inventory.php');
}
$list = $pdo->query('SELECT p.*, c.name category, s.name supplier FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.is_active=1 ORDER BY (p.stock_qty <= p.reorder_level) DESC, p.name')->fetchAll();
$moves = $pdo->query('SELECT m.*, p.name pname, u.name uname FROM stock_movements m JOIN products p ON p.id=m.product_id LEFT JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 12')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2 class="mb-3">Inventory management</h2>
  <div class="row g-4">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>Product</th><th>Supplier</th><th>Stock</th><th>Reorder at</th><th>Update stock</th></tr></thead><tbody>
      <?php foreach ($list as $p): [$lbl, $col] = stock_status((int)$p['stock_qty'], (int)$p['reorder_level']); ?>
        <tr><td><?= e($p['emoji'] . ' ' . $p['name']) ?><div class="small text-muted"><?= e($p['sku']) ?></div></td><td class="small"><?= e($p['supplier']) ?></td>
          <td><span class="badge text-bg-<?= $col ?>"><?= (int)$p['stock_qty'] ?> - <?= $lbl ?></span></td><td><?= (int)$p['reorder_level'] ?></td>
          <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="number" name="qty" class="form-control form-control-sm" style="width:80px" placeholder="+qty" required>
            <select name="reason" class="form-select form-select-sm" style="width:110px"><option value="restock">Restock</option><option value="adjustment">Adjust</option></select>
            <button class="btn btn-sm btn-primary">Save</button></form></td></tr>
      <?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6>Recent stock movements</h6>
      <?php foreach ($moves as $m): ?><div class="small border-bottom py-1"><b class="<?= $m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['change_qty'] > 0 ? '+' : '' ?><?= (int)$m['change_qty'] ?></b>
        <?= e($m['pname']) ?> <span class="text-muted">(<?= e($m['reason']) ?>)</span><br><span class="text-muted"><?= e($m['created_at']) ?> <?= e($m['uname']) ?></span></div><?php endforeach; ?>
      <?php if (!$moves): ?><span class="small text-muted">No movements yet.</span><?php endif; ?></div></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
