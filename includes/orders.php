<?php
/**
 * Order engine - the single place where BOTH channels (online storefront + POS)
 * create sales, so inventory, CRM, logistics and reporting stay consistent.
 */

/** Customer's favourite categories (CRM data) used for personalized offers. */
function customer_top_categories(int $customerId, int $limit = 2): array
{
    $st = db()->prepare("SELECT p.category_id FROM order_items oi
        JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
        WHERE o.customer_id = ? AND o.status <> 'cancelled'
        GROUP BY p.category_id ORDER BY SUM(oi.qty) DESC LIMIT " . (int)$limit);
    $st->execute([$customerId]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        $st = db()->prepare('SELECT preferred_category_id FROM customers WHERE id = ?');
        $st->execute([$customerId]);
        $pref = $st->fetchColumn();
        $ids = $pref ? [$pref] : [];
    }
    return array_map('intval', $ids);
}

/** Active offers this customer qualifies for: [category_id => offer row]. */
function eligible_offers(?int $customerId): array
{
    if (!$customerId) return [];
    $cats = customer_top_categories($customerId);
    if (!$cats) return [];
    $in = implode(',', array_fill(0, count($cats), '?'));
    $st = db()->prepare("SELECT o.*, c.name AS category_name FROM offers o JOIN categories c ON c.id = o.category_id
        WHERE o.is_active = 1 AND CURDATE() BETWEEN o.valid_from AND o.valid_to AND o.category_id IN ($in)");
    $st->execute($cats);
    $out = [];
    foreach ($st->fetchAll() as $o) {
        $k = (int)$o['category_id'];
        if (!isset($out[$k]) || $o['discount_percent'] > $out[$k]['discount_percent']) $out[$k] = $o;
    }
    return $out;
}

/** [discount %, discount per unit] for a product given the customer's offers. */
function unit_discount(array $product, array $offers): array
{
    $pct = isset($offers[$product['category_id']]) ? (float)$offers[$product['category_id']]['discount_percent'] : 0.0;
    return [$pct, round($product['price'] * $pct / 100, 2)];
}

/**
 * Creates an order inside ONE database transaction:
 * lock stock rows -> validate -> insert order + items -> deduct stock -> log movements -> shipment.
 * $o = ['channel'=>'online|pos','customer_id'=>?int,'cashier_id'=>?int,'items'=>[product_id=>qty],
 *       'payment_method'=>..., 'shipping_address'=>?string]
 */
function create_order(array $o): int
{
    $pdo   = db();
    $items = array_filter(array_map('intval', $o['items'] ?? []), fn($q) => $q > 0);
    if (!$items) throw new RuntimeException('The order has no items.');
    ksort($items);                                  // consistent lock order avoids deadlocks
    $customerId = $o['customer_id'] ?? null;
    $offers     = eligible_offers($customerId);

    $pdo->beginTransaction();
    try {
        $lines = []; $subtotal = 0.0; $discount = 0.0;
        foreach ($items as $pid => $qty) {
            $st = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p || !$p['is_active']) throw new RuntimeException('A product in your order is no longer available.');
            if ($p['stock_qty'] < $qty)  throw new RuntimeException("Not enough stock for {$p['name']} (available: {$p['stock_qty']}).");
            [, $unitDisc] = unit_discount($p, $offers);
            $lineDisc  = round($unitDisc * $qty, 2);
            $lineTotal = round($p['price'] * $qty - $lineDisc, 2);
            $subtotal += $p['price'] * $qty;
            $discount += $lineDisc;
            $lines[] = [$p, $qty, $lineDisc, $lineTotal];
        }
        $total  = round($subtotal - $discount, 2);
        $isPos  = $o['channel'] === 'pos';

        $pdo->prepare('INSERT INTO orders(order_no,channel,customer_id,cashier_id,status,subtotal,discount,total,payment_method,shipping_address)
                       VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute(['TMP' . bin2hex(random_bytes(8)), $o['channel'], $customerId, $o['cashier_id'] ?? null,
                       $isPos ? 'completed' : 'processing', round($subtotal, 2), round($discount, 2), $total,
                       $o['payment_method'], $isPos ? null : ($o['shipping_address'] ?? null)]);
        $orderId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE orders SET order_no = CONCAT('ORD-', DATE_FORMAT(created_at,'%Y%m%d'), '-', LPAD(id,5,'0')) WHERE id = ?")->execute([$orderId]);

        $insItem = $pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,qty,unit_price,discount_amount,line_total) VALUES (?,?,?,?,?,?,?)');
        $updStk  = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?');
        $insMov  = $pdo->prepare("INSERT INTO stock_movements(product_id,change_qty,reason,ref_order_id,user_id) VALUES (?,?, 'sale', ?, ?)");
        foreach ($lines as [$p, $qty, $lineDisc, $lineTotal]) {
            $insItem->execute([$orderId, $p['id'], $p['name'], $qty, $p['price'], $lineDisc, $lineTotal]);
            $updStk->execute([$qty, $p['id']]);
            $insMov->execute([$p['id'], -$qty, $orderId, user()['id'] ?? null]);
        }
        if ($customerId) {
            $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?')->execute([(int)floor($total / 100), $customerId]);
        }
        if (!$isPos) {
            $pdo->prepare("INSERT INTO shipments(order_id,carrier,tracking_no,status) VALUES (?, 'Swift Courier', ?, 'pending')")
                ->execute([$orderId, 'TRK' . strtoupper(bin2hex(random_bytes(5)))]);
        }
        audit('ORDER_CREATED', 'orders', $orderId, null, ['channel' => $o['channel'], 'total' => $total, 'items' => count($lines)]);
        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Cancels an order and returns the stock (transactional). */
function cancel_order(int $orderId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) throw new RuntimeException('Order not found.');
        if (!in_array($order['status'], ['processing', 'completed'], true)) {
            throw new RuntimeException('Only processing or completed (POS) orders can be cancelled.');
        }
        $items = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
        $items->execute([$orderId]);
        foreach ($items->fetchAll() as $it) {
            $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')->execute([$it['qty'], $it['product_id']]);
            $pdo->prepare("INSERT INTO stock_movements(product_id,change_qty,reason,ref_order_id,user_id) VALUES (?,?, 'cancel_return', ?, ?)")
                ->execute([$it['product_id'], $it['qty'], $orderId, user()['id'] ?? null]);
        }
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        audit('ORDER_CANCELLED', 'orders', $orderId, ['status' => $order['status']], ['status' => 'cancelled']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
