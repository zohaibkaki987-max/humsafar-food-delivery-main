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

$orderIds = [];
if (isset($_GET['order_ids'])) {
    foreach (explode(',', (string)$_GET['order_ids']) as $id) {
        $id = (int)trim($id);
        if ($id > 0) $orderIds[$id] = $id;
    }
}

$data = [];
if ($orderIds) {
    $ids = array_values($orderIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids) + 1);
    $sql = "SELECT order_id, amount, status FROM rider_payouts WHERE rider_id=? AND order_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = [$types, $riderId, ...$ids];
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[(string)$row['order_id']] = [
                'amount' => (float)$row['amount'],
                'status' => (string)$row['status']
            ];
        }
        $stmt->close();
    }
}

echo json_encode(['ok'=>true,'payouts'=>$data]);
?>