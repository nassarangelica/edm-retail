<?php
$items = [
  ['admin/index.php',     'Dashboard',    'speedometer2',  ['admin','manager']],
  ['pos/index.php',       'POS',          'upc-scan',      ['admin','cashier']],
  ['admin/products.php',  'Products',     'box-seam',      ['admin','manager']],
  ['admin/inventory.php', 'Inventory',    'boxes',         ['admin','manager']],
  ['admin/orders.php',    'Orders',       'receipt',       ['admin','manager']],
  ['admin/shipments.php', 'Shipments',    'truck',         ['admin','manager','logistics']],
  ['admin/offers.php',    'Offers',       'tags',          ['admin','manager']],
  ['admin/users.php',     'Users',        'people',        ['admin']],
  ['admin/quality.php',   'Data Quality', 'shield-check',  ['admin','manager']],
  ['admin/etl.php',       'ETL / CDC',    'diagram-3',     ['admin']],
  ['admin/audit.php',     'Audit Logs',   'journal-text',  ['admin']],
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="staff-nav">
  <div class="container">
    <ul class="nav nav-pills py-2 flex-nowrap overflow-auto">
      <?php foreach ($items as [$path, $label, $icon, $roles]): if (!in_array(user()['role'], $roles, true)) continue; ?>
        <li class="nav-item"><a class="nav-link py-1 <?= basename($path) === $current ? 'active' : '' ?>" href="<?= url($path) ?>">
          <i class="bi bi-<?= $icon ?>"></i> <?= $label ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
