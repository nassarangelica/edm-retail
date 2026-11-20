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
<div class="products-page container py-4 py-lg-5">
  <div class="products-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
      <div class="products-eyebrow"><i class="bi bi-grid-3x3-gap-fill"></i> Storefront</div>
      <h1 class="products-title mb-1">Products</h1>
      <p class="products-subtitle mb-0">Browse our products and find what you need.</p>
    </div>
    <div class="stock-live"><span class="live-dot"></span> Live stock <span id="stock-updated"></span></div>
  </div>

  <form class="product-filter" method="get">
    <div class="filter-field search-field">
      <label for="product-search">Search</label>
      <div class="input-icon-wrap">
        <i class="bi bi-search"></i>
        <input id="product-search" class="form-control" name="q" placeholder="Search by product name or SKU" value="<?= e($q) ?>">
      </div>
    </div>
    <div class="filter-field category-field">
      <label for="product-category">Category</label>
      <select id="product-category" class="form-select" name="category">
        <option value="0">All categories</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary filter-button" type="submit">
      <i class="bi bi-funnel-fill"></i>
      <span>Apply filters</span>
    </button>
  </form>

  <?php if (!$products): ?>
    <div class="empty-products">
      <div class="empty-products-icon"><i class="bi bi-search"></i></div>
      <h3>No products found</h3>
      <p>Try a different search term or category.</p>
      <a href="<?= url('products.php') ?>" class="btn btn-outline-primary">Clear filters</a>
    </div>
  <?php endif; ?>

  <?php if ($products): ?>
    <div class="products-toolbar">
      <span><strong><?= count($products) ?></strong> product<?= count($products) === 1 ? '' : 's' ?> available</span>
      <?php if ($q !== '' || $cat): ?><a href="<?= url('products.php') ?>" class="clear-filters"><i class="bi bi-x-circle"></i> Clear filters</a><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($products as $p):
      [$pct, $unitDisc] = unit_discount($p, $offers);
      [$label, $color] = stock_status((int)$p['stock_qty'], (int)$p['reorder_level']); ?>
      <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <article class="product-card-clean h-100">
          <div class="product-visual">
            <span class="category-chip"><?= e($p['category']) ?></span>
            <?php if ($pct > 0): ?><span class="discount-chip">-<?= rtrim(rtrim(number_format($pct, 2), '0'), '.') ?>%</span><?php endif; ?>
            <div class="product-emoji-clean"><?= e($p['emoji']) ?></div>
          </div>

          <div class="product-content">
            <div class="product-sku">SKU <?= e($p['sku']) ?></div>
            <h2 class="product-name"><?= e($p['name']) ?></h2>

            <div class="product-price-row">
              <?php if ($pct > 0): ?>
                <span class="price-now"><?= money($p['price'] - $unitDisc) ?></span>
                <span class="price-old"><?= money($p['price']) ?></span>
              <?php else: ?>
                <span class="price-now"><?= money($p['price']) ?></span>
              <?php endif; ?>
            </div>

            <div class="product-stock stock-<?= e($color) ?>">
              <i class="bi <?= $p['stock_qty'] > 0 ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
              <span><?= e($label) ?><?= $p['stock_qty'] > 0 ? ' · ' . (int)$p['stock_qty'] . ' available' : '' ?></span>
            </div>

            <?php if ($canBuy): ?>
            <form method="post" action="<?= url('cart.php') ?>" class="product-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <div class="qty-control">
                <label for="qty-<?= (int)$p['id'] ?>">Qty</label>
                <input id="qty-<?= (int)$p['id'] ?>" type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock_qty']) ?>" class="form-control">
              </div>
              <button class="btn btn-primary add-button" data-add-for="<?= e($p['sku']) ?>" <?= $p['stock_qty'] <= 0 ? 'disabled' : '' ?> type="submit">
                <i class="bi bi-cart-plus-fill"></i>
                <span>Add to cart</span>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
