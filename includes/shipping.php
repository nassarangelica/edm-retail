<?php
/** Shared by api/shipment.php (external logistics provider) and admin/shipments.php (staff screen). */
function apply_shipment_update(string $tracking, string $status, string $actor): array
{
    $allowed = ['pending', 'picked_up', 'in_transit', 'delivered', 'failed'];
    if (!in_array($status, $allowed, true)) throw new InvalidArgumentException('Invalid status. Allowed: ' . implode(', ', $allowed));
    $pdo = db();
    $st = $pdo->prepare('SELECT s.*, o.status AS order_status FROM shipments s JOIN orders o ON o.id = s.order_id WHERE s.tracking_no = ?');
    $st->execute([$tracking]);
    $s = $st->fetch();
    if (!$s) throw new InvalidArgumentException('Tracking number not found.');
    if ($s['order_status'] === 'cancelled') throw new InvalidArgumentException('Order was cancelled.');

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE shipments SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $s['id']]);
    $orderStatus = match ($status) { 'picked_up', 'in_transit' => 'shipped', 'delivered' => 'delivered', default => $s['order_status'] };
    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$orderStatus, $s['order_id']]);
    $pdo->prepare('INSERT INTO audit_logs(user_id,actor,action,table_name,record_id,old_value,new_value,ip_address) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$_SESSION['user']['id'] ?? null, $actor, 'SHIPMENT_UPDATE', 'shipments', $s['id'],
                   json_encode(['status' => $s['status']]), json_encode(['status' => $status]), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    $pdo->commit();
    return ['tracking_no' => $tracking, 'shipment_status' => $status, 'order_status' => $orderStatus];
}
