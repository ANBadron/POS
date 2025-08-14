<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_customer'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$customerID = (int)($_POST['customer_id'] ?? 0);
$customerName = trim($_POST['customer_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$creditLimit = (float)($_POST['credit_limit'] ?? 0);

if ($customerID <= 0 || empty($customerName)) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer data']);
    exit();
}

try {
    $stmt = $conn->prepare("
        UPDATE Customers 
        SET CustomerName = ?, Email = ?, Phone = ?, Address = ?, CreditLimit = ?
        WHERE CustomerID = ?
    ");
    $stmt->execute([$customerName, $email, $phone, $address, $creditLimit, $customerID]);

    echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
} catch (PDOException $e) {
    error_log("Error updating customer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating customer']);
}
