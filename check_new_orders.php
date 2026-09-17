<?php
session_start();
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

// DB check
if (!file_exists(__DIR__ . '/db.php')) {
    echo json_encode(['orders' => []]);
    exit();
}

include __DIR__ . '/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['orders' => []]);
    exit();
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$sql = "SELECT o.id, o.total_amount, o.created_at, u.user_name 
        FROM orders o 
        JOIN Users u ON o.user_id = u.id 
        WHERE o.status = 'pending' AND o.id > ? 
        ORDER BY o.id ASC 
        LIMIT 10";

$orders = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $last_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = [
            'id' => (int)$row['id'],
            'total_amount' => (float)$row['total_amount'],
            'user_name' => $row['user_name'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
}

echo json_encode(['orders' => $orders]);