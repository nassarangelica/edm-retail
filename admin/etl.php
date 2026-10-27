<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'ETL / CDC';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $r = run_etl(user()['email']);
        flash('success', "ETL finished: {$r['extracted']} extracted, {$r['loaded']} loaded, {$r['rejected']} rejected, {$r['duplicates']} duplicates skipped, {$r['removed']} removed.");
    } catch (Throwable $e) { flash('danger', 'ETL failed: ' . $e->getMessage()); }
    redirect('admin/etl.php');
}
$runs = $pdo->query('SELECT * FROM etl_runs ORDER BY id DESC LIMIT 8')->fetchAll();
$cdc  = $pdo->query('SELECT l.*, p.sku, p.name FROM cdc_product_log l JOIN products p ON p.id=l.product_id ORDER BY l.id DESC LIMIT 12')->fetchAll();
$dw   = $pdo->query('SELECT COUNT(*) n, COALESCE(SUM(revenue),0) rev FROM dw_sales_fact')->fetch();
$src  = $pdo->query('SELECT COUNT(*) n, COALESCE(SUM(revenue),0) rev FROM v_sales_unified')->fetch();
$sample = $pdo->query('SELECT sale_date, channel, customer_name, product_name, qty, revenue, profit FROM dw_sales_fact ORDER BY id DESC LIMIT 6')->fetchAll();
$inSync = ((float)$dw['rev'] === (float)$src['rev']);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2>ETL pipeline &amp; Change Data Capture</h2>
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="flow-step"><b>1. Extract</b><br><span class="small">Read order lines from POS + online storefront, joined with CRM (customers), inventory (products) and categories.</span></div></div>
    <div class="col-md-4"><div class="flow-step"><b>2. Transform</b><br><span class="small">Standardize names (PEDRO PENDUKO &rarr; Pedro Penduko), channel codes and dates; compute profit; drop cancelled/invalid rows; skip duplicates.</span></div></div>
    <div class="col-md-4"><div class="flow-step"><b>3. Load</b><br><span class="small">Insert into <code>dw_sales_fact</code> and rebuild <code>dw_daily_sales</code> for reports and analytics.</span></div></div>
  </div>
  <div class="card card-body mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
    <div><b>Warehouse:</b> <?= (int)$dw['n'] ?> fact rows &middot; revenue <?= money($dw['rev']) ?>
      &nbsp;|&nbsp; <b>Live source (view):</b> <?= (int)$src['n'] ?> rows &middot; <?= money($src['rev']) ?>
      <?= $inSync ? '<span class="badge text-bg-success ms-2">In sync</span>' : '<span class="badge text-bg-warning ms-2">Out of sync - run ETL</span>' ?></div>
    <form method="post"><?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-play-fill"></i> Run ETL now</button></form>
  </div>
  <div class="row g-4">
    <div class="col-lg-6"><div class="card"><div class="card-body"><h6>ETL run history</h6>
      <table class="table table-sm"><thead><tr><th>#</th><th>Started</th><th>Ext.</th><th>Load</th><th>Rej.</th><th>Dup.</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($runs as $r): ?><tr><td><?= (int)$r['id'] ?></td><td class="small"><?= e($r['started_at']) ?></td><td><?= (int)$r['extracted'] ?></td><td><?= (int)$r['loaded'] ?></td><td><?= (int)$r['rejected'] ?></td><td><?= (int)$r['duplicates'] ?></td>
        <td><span class="badge text-bg-<?= $r['status'] === 'success' ? 'success' : ($r['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= e($r['status']) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$runs): ?><tr><td colspan="7" class="text-muted">No runs yet - click "Run ETL now".</td></tr><?php endif; ?></tbody></table>
      <?php if ($sample): ?><h6 class="mt-3">Latest warehouse rows</h6><?php foreach ($sample as $s): ?><div class="small border-bottom py-1"><?= e($s['sale_date']) ?> &middot; <?= e($s['channel']) ?> &middot; <?= e($s['customer_name']) ?> &middot; <?= (int)$s['qty'] ?>&times; <?= e($s['product_name']) ?> &middot; <?= money($s['revenue']) ?></div><?php endforeach; endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><h6>CDC log (captured by MySQL triggers)</h6>
      <p class="small text-muted">Every price or stock change on <code>products</code> is recorded automatically &mdash; try a sale or a restock, then refresh.</p>
      <table class="table table-sm"><thead><tr><th>When</th><th>Product</th><th>Op</th><th>Price</th><th>Stock</th></tr></thead><tbody>
      <?php foreach ($cdc as $c): ?><tr><td class="small"><?= e($c['changed_at']) ?></td><td class="small"><?= e($c['sku']) ?></td><td><?= e($c['op']) ?></td>
        <td class="small"><?= $c['old_price'] !== null ? money($c['old_price']) . ' &rarr; ' : '' ?><?= $c['new_price'] !== null ? money($c['new_price']) : '' ?></td>
        <td class="small"><?= $c['old_stock'] ?? '-' ?> &rarr; <?= $c['new_stock'] ?? '-' ?></td></tr><?php endforeach; ?>
      <?php if (!$cdc): ?><tr><td colspan="5" class="text-muted">No changes captured yet.</td></tr><?php endif; ?></tbody></table>
    </div></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
