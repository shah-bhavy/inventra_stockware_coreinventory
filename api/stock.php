<?php
/**
 * API: Get current stock quantity for a product at a location (or total).
 * GET /api/stock.php?product_id=X&location_id=Y
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$productId  = isset($_GET['product_id'])  ? (int)$_GET['product_id']  : 0;
$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

$db = getDB();

if ($locationId) {
    $stmt = $db->prepare(
        "SELECT COALESCE(qty,0) FROM stock_levels WHERE product_id=? AND location_id=?"
    );
    $stmt->execute([$productId, $locationId]);
} else {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(qty),0) FROM stock_levels WHERE product_id=?"
    );
    $stmt->execute([$productId]);
}

$qty = (float)$stmt->fetchColumn();

header('Content-Type: application/json');
echo json_encode(['qty' => $qty]);
