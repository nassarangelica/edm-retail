<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin', 'manager');
$pageTitle = 'Offers';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'create') {
        $title = trim($_POST['title'] ?? ''); $cat = (int)$_POST['category_id']; $pct = (float)$_POST['discount_percent'];
        $from = $_POST['valid_from'] ?? ''; $to = $_POST['valid_to'] ?? '';
        if ($title === '' || $pct <= 0 || $pct > 50 || !$cat || $from === '' || $to < $from) flash('danger', 'Check the form: discount must be 1-50% and the dates valid.');
        else {
            $pdo->prepare('INSERT INTO offers(title,category_id,discount_percent,valid_from,valid_to) VALUES (?,?,?,?,?)')->execute([$title, $cat, $pct, $from, $to]);
            audit('OFFER_CREATE', 'offers', $pdo->lastInsertId(), null, compact('title', 'cat', 'pct'));
            flash('success', 'Offer created.');
        }
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare('UPDATE offers SET is_active = 1 - is_active WHERE id=?')->execute([(int)$_POST['id']]);
        audit('OFFER_TOGGLE', 'offers', (int)$_POST['id']);
    }
    redirect('admin/offers.php');
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$list = $pdo->query('SELECT o.*, c.name cat FROM offers o JOIN categories c ON c.id=o.category_id ORDER BY o.id DESC')->fetchAll();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2>Personalized offers</h2>
  <p class="text-muted small">Customers automatically receive an offer when its category matches their purchase history (CRM data) or favourite category.</p>
  <div class="card card-body mb-4"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="col-md-4"><input name="title" class="form-control" placeholder="Offer title" required></div>
    <div class="col-md-2"><select name="category_id" class="form-select"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><input type="number" step="0.5" name="discount_percent" class="form-control" placeholder="%" required></div>
    <div class="col-md-2"><input type="date" name="valid_from" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-2"><input type="date" name="valid_to" class="form-control" value="<?= date('Y-m-d', strtotime('+30 day')) ?>" required></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div></form></div>
  <div class="card"><table class="table mb-0"><thead><tr><th>Title</th><th>Category</th><th>Discount</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $o): ?><tr><td><?= e($o['title']) ?></td><td><?= e($o['cat']) ?></td><td><?= (float)$o['discount_percent'] ?>%</td><td class="small"><?= e($o['valid_from']) ?> &rarr; <?= e($o['valid_to']) ?></td>
      <td><?= $o['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Off</span>' ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Toggle</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
