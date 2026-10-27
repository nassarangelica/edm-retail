<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Dashboard';
$pdo = db();

/* All numbers below come from the VIEW v_sales_unified (data virtualization: POS + online, no copying). */
$kpi = $pdo->query("SELECT COALESCE(SUM(revenue),0) rev, COALESCE(SUM(profit),0) profit, COUNT(DISTINCT order_id) orders,
                           COALESCE(SUM(CASE WHEN channel='ONLINE' THEN revenue END),0) online_rev,
                           COALESCE(SUM(CASE WHEN channel='POS' THEN revenue END),0) pos_rev FROM v_sales_unified")->fetch();
$lowStock  = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty <= reorder_level')->fetchColumn();
$customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND deleted_at IS NULL")->fetchColumn();

$days = []; for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = ['ONLINE' => 0, 'POS' => 0];
foreach ($pdo->query("SELECT sale_date d, channel, SUM(revenue) r FROM v_sales_unified WHERE sale_date >= CURDATE() - INTERVAL 6 DAY GROUP BY d, channel") as $r)
    if (isset($days[$r['d']])) $days[$r['d']][$r['channel']] = (float)$r['r'];

$byCat = $pdo->query('SELECT category, SUM(revenue) r FROM v_sales_unified GROUP BY category ORDER BY r DESC')->fetchAll();
$top   = $pdo->query('SELECT product_name, SUM(qty) q FROM v_sales_unified GROUP BY product_name ORDER BY q DESC LIMIT 5')->fetchAll();
$low   = $pdo->query('SELECT sku,name,stock_qty,reorder_level FROM products WHERE is_active=1 AND stock_qty <= reorder_level ORDER BY stock_qty')->fetchAll();

$chart = json_encode([
    'labels' => array_map(fn($d) => date('M d', strtotime($d)), array_keys($days)),
    'online' => array_column($days, 'ONLINE'), 'pos' => array_column($days, 'POS'),
    'catLabels' => array_column($byCat, 'category'), 'catData' => array_map('floatval', array_column($byCat, 'r')),
    'topLabels' => array_column($top, 'product_name'), 'topData' => array_map('intval', array_column($top, 'q')),
]);
$pageScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const D = $chart;
new Chart(document.getElementById('c1'), {type:'bar', data:{labels:D.labels, datasets:[
  {label:'Online', data:D.online, backgroundColor:'#0f4c5c'}, {label:'POS', data:D.pos, backgroundColor:'#f4a261'}]},
  options:{responsive:true, scales:{x:{stacked:true}, y:{stacked:true, beginAtZero:true}}}});
new Chart(document.getElementById('c2'), {type:'doughnut', data:{labels:D.catLabels, datasets:[{data:D.catData,
  backgroundColor:['#0f4c5c','#2a7f8f','#f4a261','#e76f51','#8ab17d']}]}});
new Chart(document.getElementById('c3'), {type:'bar', data:{labels:D.topLabels, datasets:[{label:'Units sold', data:D.topData, backgroundColor:'#2a7f8f'}]},
  options:{indexAxis:'y', plugins:{legend:{display:false}}}});
</script>
HTML;
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2 class="mb-3">Sales analytics dashboard</h2>
  <div class="row g-3 mb-4">
    <?php foreach ([['Total revenue', money($kpi['rev']), 'cash-stack'], ['Gross profit', money($kpi['profit']), 'graph-up-arrow'],
                    ['Orders', (int)$kpi['orders'], 'receipt'], ['Online / POS', money($kpi['online_rev']) . ' / ' . money($kpi['pos_rev']), 'shop'],
                    ['Low-stock items', $lowStock, 'exclamation-triangle'], ['Customers', $customers, 'people']] as [$l, $v, $ic]): ?>
      <div class="col-6 col-lg-4 col-xl-2"><div class="card kpi h-100"><div class="card-body">
        <div class="small text-muted"><i class="bi bi-<?= $ic ?>"></i> <?= $l ?></div><div class="value" style="font-size:<?= strlen((string)$v) > 14 ? '1rem' : '1.5rem' ?>"><?= e($v) ?></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body"><h6>Revenue - last 7 days by channel</h6><canvas id="c1" height="120"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6>Revenue by category</h6><canvas id="c2"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><h6>Top 5 products (units)</h6><canvas id="c3" height="140"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><h6><i class="bi bi-exclamation-triangle text-warning"></i> Items to restock</h6>
      <table class="table table-sm mb-0"><thead><tr><th>SKU</th><th>Product</th><th>Stock</th><th>Reorder at</th></tr></thead><tbody>
      <?php foreach ($low as $l): ?><tr><td><?= e($l['sku']) ?></td><td><?= e($l['name']) ?></td><td><span class="badge text-bg-<?= $l['stock_qty'] <= 0 ? 'danger' : 'warning' ?>"><?= (int)$l['stock_qty'] ?></span></td><td><?= (int)$l['reorder_level'] ?></td></tr><?php endforeach; ?>
      <?php if (!$low): ?><tr><td colspan="4" class="text-muted">All items are above their reorder level.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
