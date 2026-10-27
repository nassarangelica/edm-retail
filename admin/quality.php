<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Data Quality';
$pdo = db();

/* DQM (slide 3 + slide 11): accuracy, completeness, consistency, uniqueness, timeliness, integrity.
   severity: 'fail' = must be fixed, 'warn' = needs attention. Each query returns the number of bad records. */
$checks = [
 ['Completeness', 'Customers missing phone or address', 'warn', "SELECT COUNT(*) FROM customers c JOIN users u ON u.id=c.user_id WHERE u.deleted_at IS NULL AND (c.phone IS NULL OR c.phone='' OR c.address IS NULL OR c.address='')"],
 ['Validity',     'Users with an invalid email format', 'fail', "SELECT COUNT(*) FROM users WHERE email NOT LIKE '%_@_%.__%'"],
 ['Uniqueness',   'Possible duplicate customers (same name, different email)', 'warn', "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(name)) n FROM users WHERE role='customer' AND deleted_at IS NULL GROUP BY n HAVING COUNT(*)>1) t"],
 ['Uniqueness',   'Duplicate product names', 'warn', "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(name)) n FROM products GROUP BY n HAVING COUNT(*)>1) t"],
 ['Accuracy',     'Products with price <= 0, negative stock or price below cost', 'fail', "SELECT COUNT(*) FROM products WHERE price<=0 OR stock_qty<0 OR price<cost_price"],
 ['Consistency',  'Orders whose total differs from the sum of their items', 'fail', "SELECT COUNT(*) FROM orders o WHERE ROUND(o.total,2) <> ROUND((SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id=o.id),2)"],
 ['Integrity',    'Orders without any item', 'fail', "SELECT COUNT(*) FROM orders o WHERE NOT EXISTS (SELECT 1 FROM order_items i WHERE i.order_id=o.id)"],
 ['Integrity',    'Online orders (not cancelled) without a shipment record', 'fail', "SELECT COUNT(*) FROM orders o WHERE o.channel='online' AND o.status<>'cancelled' AND NOT EXISTS (SELECT 1 FROM shipments s WHERE s.order_id=o.id)"],
 ['Timestamp consistency', 'Shipments updated before their order was created', 'fail', "SELECT COUNT(*) FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.updated_at < o.created_at"],
 ['Timeliness',   'Products at or below their reorder level', 'warn', "SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty <= reorder_level"],
];
$results = []; $pass = 0;
foreach ($checks as [$dim, $name, $sev, $sql]) {
    $n = (int)$pdo->query($sql)->fetchColumn();
    if ($n === 0) $pass++;
    $results[] = [$dim, $name, $sev, $n];
}
$score = round($pass / count($checks) * 100);

$sample = $pdo->query("SELECT u.name,u.email,c.phone,c.address FROM customers c JOIN users u ON u.id=c.user_id WHERE u.deleted_at IS NULL AND (c.phone IS NULL OR c.phone='' OR c.address IS NULL OR c.address='')")->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="mb-0">Data Quality Management</h2>
    <span class="badge text-bg-<?= $score >= 90 ? 'success' : ($score >= 70 ? 'warning' : 'danger') ?> fs-5">Quality score: <?= $score ?>%</span></div>
  <div class="card mb-4"><table class="table mb-0"><thead><tr><th>Dimension</th><th>Rule</th><th class="text-center">Bad records</th><th>Result</th></tr></thead><tbody>
    <?php foreach ($results as [$dim, $name, $sev, $n]): ?><tr><td><?= e($dim) ?></td><td><?= e($name) ?></td><td class="text-center"><?= $n ?></td>
      <td><?= $n === 0 ? '<span class="badge text-bg-success">Pass</span>' : ($sev === 'fail' ? '<span class="badge text-bg-danger">Fail</span>' : '<span class="badge text-bg-warning">Warning</span>') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($sample): ?><div class="card card-body"><h6>Incomplete customer records to cleanse</h6>
    <?php foreach ($sample as $s): ?><div class="small"><?= e($s['name']) ?> &lt;<?= e($s['email']) ?>&gt; &mdash; phone: <?= e($s['phone'] ?: 'MISSING') ?>, address: <?= e($s['address'] ?: 'MISSING') ?></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
