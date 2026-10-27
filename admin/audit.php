<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'Audit Logs';
$act = trim($_GET['action'] ?? '');
$sql = 'SELECT * FROM audit_logs'; $args = [];
if ($act !== '') { $sql .= ' WHERE action = ?'; $args[] = $act; }
$st = db()->prepare($sql . ' ORDER BY id DESC LIMIT 200'); $st->execute($args); $rows = $st->fetchAll();
$actions = db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/staff_nav.php';
?>
<div class="container py-4">
  <h2>Audit trail <small class="text-muted fs-6">(governance: who did what, when, from where)</small></h2>
  <form class="row g-2 mb-3" method="get"><div class="col-auto"><select name="action" class="form-select"><option value="">All actions</option>
    <?php foreach ($actions as $a): ?><option <?= $act === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div></form>
  <div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Table</th><th>Record</th><th>Old</th><th>New</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr class="small"><td class="text-nowrap"><?= e($r['created_at']) ?></td><td><?= e($r['actor']) ?></td><td><span class="badge text-bg-<?= str_contains($r['action'], 'FAILED') ? 'danger' : 'secondary' ?>"><?= e($r['action']) ?></span></td>
      <td><?= e($r['table_name']) ?></td><td><?= e($r['record_id']) ?></td><td style="max-width:180px" class="text-truncate" title="<?= e($r['old_value']) ?>"><?= e($r['old_value']) ?></td>
      <td style="max-width:180px" class="text-truncate" title="<?= e($r['new_value']) ?>"><?= e($r['new_value']) ?></td><td><?= e($r['ip_address']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
