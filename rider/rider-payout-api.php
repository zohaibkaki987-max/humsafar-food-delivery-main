<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['rider_logged_in']) || $_SESSION['rider_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
    exit;
}

$riderId = (int)($_SESSION['rider_id'] ?? 0);
if ($riderId < 1) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
    exit;
}

$data = [];
$sql = "SELECT rp.order_id, rp.amount, rp.status, o.order_number
        FROM rider_payouts rp
        LEFT JOIN orders o ON o.id=rp.order_id
        WHERE rp.rider_id=?
        ORDER BY rp.id DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = (string)($row['order_number'] ?: $row['order_id']);
        $data[$key] = [
            'order_id' => (int)$row['order_id'],
            'amount' => (float)$row['amount'],
            'status' => (string)$row['status']
        ];
    }
    $stmt->close();
}

echo json_encode(['ok'=>true,'payouts'=>$data]);
?>