<?php
/**
 * ETL pipeline  (slides 4-7: Extract -> Transform -> Load)
 *  EXTRACT   : read order lines from the operational tables (POS + online storefront + CRM + inventory)
 *  TRANSFORM : standardize names/channels/dates, calculate revenue/cost/profit, drop cancelled / invalid
 *              rows, skip duplicates already loaded
 *  LOAD      : insert into the data warehouse tables dw_sales_fact + dw_daily_sales
 */
function run_etl(string $runBy = 'system'): array
{
    $pdo = db();
    $pdo->prepare("INSERT INTO etl_runs(started_at,status,run_by) VALUES (NOW(),'running',?)")->execute([$runBy]);
    $runId = (int)$pdo->lastInsertId();

    try {
        /* ---------- EXTRACT ---------- */
        $rows = $pdo->query("SELECT oi.id AS order_item_id, o.id AS order_id, o.order_no, o.created_at, o.channel, o.status,
                    u.name AS customer_name, c.name AS category, p.sku, oi.product_name,
                    oi.qty, oi.unit_price, oi.line_total, p.cost_price
                FROM order_items oi
                JOIN orders o       ON o.id = oi.order_id
                JOIN products p     ON p.id = oi.product_id
                JOIN categories c   ON c.id = p.category_id
                LEFT JOIN customers cu ON cu.id = o.customer_id
                LEFT JOIN users u      ON u.id = cu.user_id")->fetchAll();
        $extracted = count($rows);

        // keep the warehouse in sync: remove facts of orders cancelled after an earlier load
        $removed = $pdo->exec("DELETE f FROM dw_sales_fact f JOIN orders o ON o.id = f.order_id WHERE o.status = 'cancelled'");

        $existing = array_flip($pdo->query('SELECT order_item_id FROM dw_sales_fact')->fetchAll(PDO::FETCH_COLUMN));

        /* ---------- TRANSFORM ---------- */
        $clean = []; $rejected = 0; $duplicates = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'cancelled' || $r['qty'] <= 0 || $r['unit_price'] < 0) { $rejected++; continue; }   // business rules
            if (isset($existing[$r['order_item_id']])) { $duplicates++; continue; }                                    // de-duplication
            $name = trim(preg_replace('/\s+/', ' ', (string)$r['customer_name']));
            $name = $name === '' ? 'Walk-in Customer' : title_case($name);   // "PEDRO PENDUKO" -> "Pedro Penduko"
            $cost = round($r['qty'] * $r['cost_price'], 2);
            $clean[] = [
                $r['order_item_id'], $r['order_id'], $r['order_no'], substr($r['created_at'], 0, 10),   // standard date format
                strtoupper($r['channel']), $name, $r['category'], strtoupper($r['sku']), $r['product_name'],
                (int)$r['qty'], (float)$r['line_total'], $cost, round($r['line_total'] - $cost, 2),
            ];
        }

        /* ---------- LOAD ---------- */
        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO dw_sales_fact(order_item_id,order_id,order_no,sale_date,channel,customer_name,category,product_sku,product_name,qty,revenue,cost,profit)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($clean as $c) $ins->execute($c);
        $pdo->exec('DELETE FROM dw_daily_sales');
        $pdo->exec('INSERT INTO dw_daily_sales(sale_date,channel,category,units,revenue,profit)
                    SELECT sale_date, channel, category, SUM(qty), SUM(revenue), SUM(profit) FROM dw_sales_fact GROUP BY sale_date, channel, category');
        $pdo->commit();

        $msg = count($clean) . " rows loaded, $removed removed (cancelled orders).";
        $pdo->prepare("UPDATE etl_runs SET finished_at=NOW(), extracted=?, loaded=?, rejected=?, duplicates=?, status='success', message=? WHERE id=?")
            ->execute([$extracted, count($clean), $rejected, $duplicates, $msg, $runId]);
        audit('ETL_RUN', 'etl_runs', $runId, null, ['extracted' => $extracted, 'loaded' => count($clean)]);
        return ['extracted' => $extracted, 'loaded' => count($clean), 'rejected' => $rejected, 'duplicates' => $duplicates, 'removed' => $removed];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare("UPDATE etl_runs SET finished_at=NOW(), status='failed', message=? WHERE id=?")->execute([substr($e->getMessage(), 0, 250), $runId]);
        throw $e;
    }
}
