<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Uniform response helper
function send_response($status, $data = null, $message = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'status' => $status,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// Validate and sanitize input
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';

if (empty($barcode)) {
    send_response('error', null, 'Barcode parameter is required', 400);
}

if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $barcode)) {
    send_response('error', null, 'Invalid barcode format', 400);
}

try {
    $stmt = $conn->prepare("
        SELECT ProductID, ProductName, Price, Barcode, StockQuantity
        FROM Products 
        WHERE Barcode = ? 
        LIMIT 1
    ");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Clean output
        $product_data = [
            'ProductID' => (int)$product['ProductID'],
            'ProductName' => htmlspecialchars($product['ProductName']),
            'Price' => (float)$product['Price'],
            'Barcode' => htmlspecialchars($product['Barcode']),
            'StockQuantity' => (int)$product['StockQuantity']
        ];
        send_response('success', $product_data, null, 200);
    } else {
        send_response('error', null, 'Product not found', 404);
    }
} catch (PDOException $e) {
    error_log("Barcode lookup error: " . $e->getMessage());
    send_response('error', null, 'Database error', 500);
}
