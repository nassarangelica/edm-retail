<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Products';
$cat = (int)($_GET['category'] ?? 0);
$q   = trim($_GET['q'] ?? '');

$sql = 'SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1';
$args = [];
if ($cat) { $sql .= ' AND p.category_id = ?'; $args[] = $cat; }
if ($q !== '') { $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
$st = db()->prepare($sql . ' ORDER BY c.name, p.name');
$st->execute($args);
$products = $st->fetchAll();
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$offers = (user() && user()['customer_id']) ? eligible_offers((int)user()['customer_id']) : [];
$canBuy = !user() || has_role('customer');

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h2 class="mb-0">Products</h2>
    <span class="small text-muted" id="stock-updated"></span>
  </div>
  <form class="row g-2 mb-4" method="get">
    <div class="col-md-5"><input class="form-control" name="q" placeholder="Search name or SKU" value="<?= e($q) ?>"></div>
    <div class="col-md-4"><select class="form-select" name="category">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
  </form>

  <?php if (!$products): ?><div class="alert alert-secondary">No products found.</div><?php endif; ?>
  <div class="row g-3">
    <?php foreach ($products as $p):
      [$pct, $unitDisc] = unit_discount($p, $offers);
      [$label, $color] = stock_status((int)$p['stock_qty'], (int)$p['reorder_level']); ?>
      <div class="col-6 col-md-4 col-lg-3"><div class="card product-card h-100">
        <div class="product-emoji"><?= e($p['emoji']) ?></div>
        <div class="card-body d-flex flex-column">
          <div class="small text-muted"><?= e($p['category']) ?> &middot; <?= e($p['sku']) ?></div>
          <div class="fw-semibold mb-1"><?= e($p['name']) ?></div>
          <div class="mb-2">
            <?php if ($pct > 0): ?>
              <span class="price-old"><?= money($p['price']) ?></span>
              <span class="price-now"><?= money($p['price'] - $unitDisc) ?></span>
              <span class="badge text-bg-warning">-<?= rtrim(rtrim(number_format($pct, 2), '0'), '.') ?>%</span>
            <?php else: ?><span class="price-now"><?= money($p['price']) ?></span><?php endif; ?>
          </div>
          <span class="badge stock-badge text-bg-<?= $color ?> align-self-start mb-3" data-sku="<?= e($p['sku']) ?>">
            <?= e($label) ?><?= $p['stock_qty'] > 0 ? ' (' . (int)$p['stock_qty'] . ')' : '' ?></span>
          <?php if ($canBuy): ?>
          <form method="post" action="<?= url('cart.php') ?>" class="mt-auto d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock_qty']) ?>" class="form-control form-control-sm" style="width:70px">
            <button class="btn btn-sm btn-primary flex-grow-1" data-add-for="<?= e($p['sku']) ?>" <?= $p['stock_qty'] <= 0 ? 'disabled' : '' ?>>
              <i class="bi bi-cart-plus"></i> Add</button>
          </form>
          <?php endif; ?>
        </div></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
