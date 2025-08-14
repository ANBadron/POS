<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Check authorization
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Admin access required']));
}

// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid payment ID']));
}

try {
    // Update payment status and set payment date to now
    $stmt = $conn->prepare("
        UPDATE CreditPayments 
        SET IsPaid = 1, 
            PaymentDate = CURRENT_TIMESTAMP 
        WHERE CreditPaymentID = ?
    ");
    
    if (!$stmt->execute([$_GET['id']])) {
        throw new PDOException("Update failed");
    }
    
    if ($stmt->rowCount() === 0) {
        die(json_encode(['success' => false, 'message' => 'Payment not found']));
    }
    
    echo json_encode(['success' => true, 'message' => 'Payment updated']);

} catch (PDOException $e) {
    error_log("Mark Paid Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
