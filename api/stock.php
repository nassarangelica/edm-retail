<?php
/**
 * REAL-TIME STOCK API  (slide: "API Integration - real-time app connections")
 * GET /api/stock.php            -> all active products
 * GET /api/stock.php?sku=GRC-001 -> one product
 * Public, read-only, exposes only SKU + availability (no cost, no supplier data).
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$sql = 'SELECT sku, name, stock_qty, reorder_level FROM products WHERE is_active = 1';
$args = [];
if (!empty($_GET['sku'])) { $sql .= ' AND sku = ?'; $args[] = $_GET['sku']; }
$st = db()->prepare($sql . ' ORDER BY sku');
$st->execute($args);

$items = [];
foreach ($st->fetchAll() as $p) {
    $q = (int)$p['stock_qty'];
    [$label, $color] = $q <= 0 ? ['Out of stock', 'danger'] : ($q <= (int)$p['reorder_level'] ? ['Low stock', 'warning'] : ['In stock', 'success']);
    $items[] = ['sku' => $p['sku'], 'name' => $p['name'], 'stock_qty' => $q, 'status' => $label, 'color' => $color];
}
echo json_encode(['generated_at' => date('c'), 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
