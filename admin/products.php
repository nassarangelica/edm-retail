<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Products';
$pdo = db();
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$sups = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $d = [
            'sku' => strtoupper(trim($_POST['sku'] ?? '')), 'name' => trim($_POST['name'] ?? ''), 'category_id' => (int)($_POST['category_id'] ?? 0),
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0) ?: null, 'price' => (float)($_POST['price'] ?? 0), 'cost_price' => (float)($_POST['cost_price'] ?? 0),
            'reorder_level' => (int)($_POST['reorder_level'] ?? 10), 'emoji' => trim($_POST['emoji'] ?? '') ?: '📦',
        ];
        $err = null;
        if ($d['sku'] === '' || $d['name'] === '') $err = 'SKU and name are required.';
        elseif ($d['price'] <= 0) $err = 'Price must be greater than zero.';
        elseif ($d['cost_price'] < 0 || $d['cost_price'] > $d['price']) $err = 'Cost price must be between 0 and the selling price.';
        elseif (!$d['category_id']) $err = 'Choose a category.';
        else {
            $dup = $pdo->prepare('SELECT id FROM products WHERE sku=? AND id<>?'); $dup->execute([$d['sku'], $id]);
            if ($dup->fetch()) $err = 'That SKU already exists (duplicate master data is not allowed).';
        }
        if ($err) { flash('danger', $err); redirect('admin/products.php?' . ($id ? "edit=$id" : 'new=1')); }
        if ($id) {
            $o = $pdo->prepare('SELECT sku,name,price,cost_price,reorder_level FROM products WHERE id=?'); $o->execute([$id]); $old = $o->fetch();
            $pdo->prepare('UPDATE products SET sku=?,name=?,category_id=?,supplier_id=?,price=?,cost_price=?,reorder_level=?,emoji=? WHERE id=?')
                ->execute([...array_values($d), $id]);
            audit('PRODUCT_UPDATE', 'products', $id, $old, $d);
            flash('success', 'Product updated.');
        } else {
            $stock = max(0, (int)($_POST['stock_qty'] ?? 0));
            $pdo->prepare('INSERT INTO products(sku,name,category_id,supplier_id,price,cost_price,reorder_level,emoji,stock_qty,description) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([...array_values($d), $stock, 'Quality ' . strtolower($d['name']) . ' for everyday use.']);
            audit('PRODUCT_CREATE', 'products', $pdo->lastInsertId(), null, $d);
            flash('success', 'Product created.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        audit('PRODUCT_TOGGLE', 'products', $id);
        flash('info', 'Product visibility changed.');
    }
    redirect('admin/products.php');
}

$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM products WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$showForm = $edit || isset($_GET['new']);
$list = $pdo->query('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.id')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between mb-3"><h2>Products (product master data)</h2>
    <a class="btn btn-primary" href="?new=1"><i class="bi bi-plus-lg"></i> New product</a></div>

  <?php if ($showForm): $v = fn($k, $dflt = '') => e($edit[$k] ?? $dflt); ?>
  <div class="card card-body mb-4">
    <h5><?= $edit ? 'Edit product' : 'New product' ?></h5>
    <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="col-md-3"><label class="form-label">SKU</label><input name="sku" class="form-control" value="<?= $v('sku') ?>" required></div>
      <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Emoji icon</label><input name="emoji" class="form-control" value="<?= $v('emoji', '📦') ?>"></div>
      <div class="col-md-4"><label class="form-label">Category</label><select name="category_id" class="form-select" required>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="0">-</option>
        <?php foreach ($sups as $s): ?><option value="<?= $s['id'] ?>" <?= ($edit['supplier_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label">Reorder level</label><input type="number" name="reorder_level" min="0" class="form-control" value="<?= $v('reorder_level', 10) ?>"></div>
      <div class="col-md-4"><label class="form-label">Selling price</label><input type="number" step="0.01" name="price" class="form-control" value="<?= $v('price') ?>" required></div>
      <div class="col-md-4"><label class="form-label">Cost price</label><input type="number" step="0.01" name="cost_price" class="form-control" value="<?= $v('cost_price') ?>" required></div>
      <?php if (!$edit): ?><div class="col-md-4"><label class="form-label">Opening stock</label><input type="number" name="stock_qty" min="0" class="form-control" value="0"></div>
      <?php else: ?><div class="col-md-4 d-flex align-items-end small text-muted">Change stock from the Inventory page so it is logged.</div><?php endif; ?>
      <div class="col-12"><button class="btn btn-success">Save</button> <a href="<?= url('admin/products.php') ?>" class="btn btn-link">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th></th><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Cost</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $p): ?><tr class="<?= $p['is_active'] ? '' : 'table-secondary' ?>">
      <td class="fs-4"><?= e($p['emoji']) ?></td><td><?= e($p['sku']) ?></td><td><?= e($p['name']) ?></td><td><?= e($p['category']) ?></td>
      <td><?= money($p['price']) ?></td><td><?= money($p['cost_price']) ?></td><td><?= (int)$p['stock_qty'] ?></td>
      <td><?= $p['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Hidden</span>' ?></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-outline-secondary"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
