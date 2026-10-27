<?php
/**
 * LOGISTICS PROVIDER API  (third-party actor pushes delivery updates)
 * POST /api/shipment.php
 * Header : X-API-KEY: <LOGISTICS_API_KEY from config.php>
 * Body   : {"tracking_no":"TRK00001201","status":"in_transit"}
 * status : pending | picked_up | in_transit | delivered | failed
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/shipping.php';
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $body): never { http_response_code($code); echo json_encode($body); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'Use POST']);
$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(LOGISTICS_API_KEY, $key)) out(401, ['error' => 'Invalid or missing API key']);

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$tracking = trim($in['tracking_no'] ?? '');
$status   = $in['status'] ?? '';
try {
    $res = apply_shipment_update($tracking, $status, 'logistics-api');
    out(200, ['ok' => true] + $res);
} catch (InvalidArgumentException $e) {
    out(422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    out(500, ['error' => 'Server error']);
}
