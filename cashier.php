<?php
session_start();
require 'db.php';

// Authentication/Authorization Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'cashier') {
    header('Location: login.php');
    exit();
}

// Helper function for AJAX cart update responses
function sendCartUpdateResponse(PDO $conn): void {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $customers = [];
    try {
        $stmt = $conn->query("SELECT CustomerID, CustomerName FROM Customers ORDER BY CustomerName");
        if ($stmt) {
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching customers for cart update: " . $e->getMessage());
    }

    ob_start();
    include 'cart_contents.php';
    $cart_html = ob_get_clean();

    $response_data = [
        'status'     => 'success',
        'cart_html'  => $cart_html,
        'cart_count' => count($_SESSION['cart'] ?? []),
        'customers'  => $customers,
        'cart'       => $_SESSION['cart'] ?? []
    ];

    echo json_encode($response_data);
    exit();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        error_log("Failed to generate CSRF token: " . $e->getMessage());
        die("A security error occurred. Please try refreshing the page.");
    }
}

// AJAX Request Handlers
if (isset($_GET['get_cart'])) {
    $customers = [];
    try {
        $stmt = $conn->query("SELECT CustomerID, CustomerName FROM Customers ORDER BY CustomerName");
        if ($stmt) {
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching customers for get_cart: " . $e->getMessage());
    }
    include 'cart_contents.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']);
        exit();
    }

    $productID = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

    if (!$productID || $productID <= 0 || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid product or quantity provided.']);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT ProductID, ProductName, Price, StockQuantity FROM Products WHERE ProductID = ?");
        $stmt->execute([$productID]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $itemIndex = -1;
            foreach ($_SESSION['cart'] as $index => $item) {
                if ($item['id'] == $productID) {
                    $itemIndex = $index;
                    break;
                }
            }

            $requestedTotalQuantity = ($itemIndex >= 0 ? $_SESSION['cart'][$itemIndex]['quantity'] : 0) + $quantity;

            if ($requestedTotalQuantity > $product['StockQuantity']) {
                echo json_encode(['status' => 'error', 'message' => 'Not enough stock available. Only ' . $product['StockQuantity'] . ' left.']);
                exit();
            }

            if ($itemIndex >= 0) {
                $_SESSION['cart'][$itemIndex]['quantity'] = $requestedTotalQuantity;
            } else {
                $_SESSION['cart'][] = [
                    'id'       => $product['ProductID'],
                    'name'     => $product['ProductName'],
                    'price'    => $product['Price'],
                    'quantity' => $quantity
                ];
            }

            sendCartUpdateResponse($conn);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error adding to cart: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error while adding item.']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_item'])) {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
        exit();
    }

    $index = filter_input(INPUT_POST, 'item_index', FILTER_VALIDATE_INT);
    $change = filter_input(INPUT_POST, 'quantity_change', FILTER_VALIDATE_INT);

    if ($index === false || $index === null || $index < 0 || $change === null || $change === false || !isset($_SESSION['cart'][$index])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid item index or quantity change.']);
        exit();
    }

    $newQuantity = $_SESSION['cart'][$index]['quantity'] + $change;

    if ($newQuantity <= 0) {
        array_splice($_SESSION['cart'], $index, 1);
    } else {
        try {
            $stmt = $conn->prepare("SELECT StockQuantity FROM Products WHERE ProductID = ?");
            $stmt->execute([$_SESSION['cart'][$index]['id']]);
            $stock = $stmt->fetchColumn();

            if ($stock === false) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Product not found during stock check.']);
                array_splice($_SESSION['cart'], $index, 1);
                exit();
            } elseif ($newQuantity > $stock) {
                echo json_encode(['status' => 'error', 'message' => 'Not enough stock. Only ' . $stock . ' available.']);
                exit();
            }

            $_SESSION['cart'][$index]['quantity'] = $newQuantity;
        } catch (PDOException $e) {
            error_log("Error checking stock during quantity update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error during stock check.']);
            exit();
        }
    }

    sendCartUpdateResponse($conn);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
        exit();
    }

    $index = filter_input(INPUT_POST, 'item_index', FILTER_VALIDATE_INT);

    if ($index !== false && $index !== null && $index >= 0 && isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
        sendCartUpdateResponse($conn); // Use the same response function as other actions
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid item index.']);
        exit();
    }
}

// Handle completing transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_transaction'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request token. Please try again.";
        header('Location: cashier.php');
        exit();
    }

    if (empty($_SESSION['cart'])) {
        $_SESSION['error'] = "Cannot process transaction: Cart is empty!";
        header('Location: cashier.php');
        exit();
    }

    $customerID = null;
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    
    if (!in_array($paymentMethod, ['cash', 'credit'])) {
        $paymentMethod = 'cash';
    }

    $customer_type = $_POST['customer_type'] ?? 'walkin';

    if ($customer_type === 'member') {
        $customerID_input = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
        if ($customerID_input) {
            $customerID = $customerID_input;
        } else {
            $_SESSION['error'] = "Please select a member.";
            header('Location: cashier.php');
            exit();
        }
    }

    // Calculate total
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        if (is_numeric($item['price']) && is_numeric($item['quantity'])) {
            $total += $item['price'] * $item['quantity'];
        } else {
            throw new Exception("Invalid item data in cart session.");
        }
    }

    $amountReceived = 0;
    $changeDue = 0;

    if ($paymentMethod === 'cash') {
        $amountReceived = (float)($_POST['amount_received'] ?? 0);
        $changeDue = $amountReceived - $total;
        
        if ($amountReceived < $total) {
            $_SESSION['error'] = "Insufficient payment!";  
            header('Location: cashier.php');
            exit();
        }
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO Transactions (CustomerID, UserID, TotalAmount, AmountTendered, ChangeDue, PaymentMethod)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customerID, 
            $_SESSION['user_id'], 
            $total,
            $paymentMethod === 'cash' ? $amountReceived : 0,
            $paymentMethod === 'cash' ? $changeDue : 0,
            $paymentMethod
        ]);
        $transactionID = $conn->lastInsertId();

        $itemStmt = $conn->prepare("
            INSERT INTO TransactionItems (TransactionID, ProductID, Quantity, Price, Cost)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stockUpdateStmt = $conn->prepare("
            UPDATE Products
            SET StockQuantity = StockQuantity - ?
            WHERE ProductID = ? AND StockQuantity >= ?
        ");
        $costStmt = $conn->prepare("SELECT Cost FROM Products WHERE ProductID = ?");

        foreach ($_SESSION['cart'] as $item) {
            $itemID = $item['id'];
            $itemQty = $item['quantity'];
            $itemPrice = $item['price'];

            $costStmt->execute([$itemID]);
            $cost = $costStmt->fetchColumn();
            if ($cost === false) $cost = 0;

            $itemStmt->execute([$transactionID, $itemID, $itemQty, $itemPrice, $cost]);
            $stockUpdateStmt->execute([$itemQty, $itemID, $itemQty]);

            if ($stockUpdateStmt->rowCount() == 0) {
                throw new Exception("Insufficient stock for ProductID: " . $itemID . " during final transaction processing.");
            }
        }

        $conn->commit();

        $_SESSION['cart'] = [];
        $_SESSION['success'] = "Transaction completed successfully! Transaction ID: #{$transactionID}";

    } catch (PDOException | Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Transaction error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing transaction: {$e->getMessage()} Please try again.";

    } finally {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("Failed to regenerate CSRF token post-transaction: " . $e->getMessage());
        }
        header('Location: cashier.php');
        exit();
    }
}

// Initial Page Load Data Fetching
try {
    $products = $conn->query("
        SELECT ProductID, ProductName, Price, Barcode, StockQuantity, Category
        FROM Products
        WHERE StockQuantity > 0
        ORDER BY Category, ProductName
    ")->fetchAll(PDO::FETCH_ASSOC);

    $customers = $conn->query("
        SELECT CustomerID, CustomerName
        FROM Customers
        ORDER BY CustomerName
    ")->fetchAll(PDO::FETCH_ASSOC);

    $productsByCategory = [];
    foreach ($products as $product) {
        $category = $product['Category'] ?? 'Uncategorized';
        $productsByCategory[$category][] = $product;
    }
    $categories = array_keys($productsByCategory);

} catch (PDOException $e) {
    error_log("Error fetching initial data for cashier page: " . $e->getMessage());
    die("Error loading essential page data. Please contact support or try again later.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        .cashier-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 80px);
        }

        .product-panel, .cart-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .product-panel-content {
            overflow-y: auto;
            flex-grow: 1;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            text-align: center;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(67,97,238,0.2);
            border-color: var(--primary-color);
        }

        .product-card .product-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.6em;
            flex-grow: 1;
        }

        .product-card .product-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: auto;
        }

        .product-card .product-stock {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 3px;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }

        .cart-items {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 5px;
        }

        .cart-items::-webkit-scrollbar,
        .product-panel-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .cart-items::-webkit-scrollbar-track,
        .product-panel-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .cart-items::-webkit-scrollbar-thumb,
        .product-panel-content::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        
        .cart-items::-webkit-scrollbar-thumb:hover,
        .product-panel-content::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            flex-grow: 1;
            margin-right: 10px;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .cart-item-price {
            color: var(--primary-color);
            font-size: 0.85rem;
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cart-item-qty button {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: #fff;
            color: #555;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
            padding: 0;
            font-size: 0.9rem;
            line-height: 1;
        }

        .cart-item-qty button.remove-item {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            width: 28px;
            height: 28px;
        }
        
        .cart-item-qty button.remove-item i {
            font-size: 0.8rem;
        }

        .cart-item-qty button:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .cart-item-qty button.remove-item:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }

        .cart-item-qty span {
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }

        .cart-footer {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
            flex-shrink: 0;
        }

        .cart-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: right;
            margin-bottom: 15px;
        }

        .checkout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkout-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .checkout-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .search-box {
            position: relative;
            margin-bottom: 15px;
        }

        .search-box input {
            padding-left: 40px;
            height: 40px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .empty-cart i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }
        
        .empty-cart p {
            margin-bottom: 5px;
        }
        
        .empty-cart p.small {
            font-size: 0.9rem;
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
            flex-shrink: 0;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .category-tabs::-webkit-scrollbar {
            display: none;
        }

        .category-tab {
            padding: 6px 15px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            white-space: nowrap;
            border: 1px solid transparent;
            transition: all 0.2s;
            color: #495057;
        }

        .category-tab:hover {
            background-color: #dee2e6;
        }

        .category-tab.active {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            border-color: var(--primary-color);
        }

        .barcode-scanner-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1050;
            transition: background-color 0.2s;
        }
        
        .barcode-scanner-btn:hover {
            background-color: var(--secondary-color);
        }

        @media (max-width: 992px) {
            .cashier-container {
                grid-template-columns: 1fr;
                height: auto;
                max-height: none;
            }
            
            .product-panel, .cart-panel {
                height: 60vh;
                min-height: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .product-panel, .cart-panel {
                height: 50vh;
                min-height: 350px;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
        
        #interactive canvas, #interactive video {
            max-width: 100%;
            height: auto !important;
        }
        
        #scannerModal .modal-body {
            position: relative;
            overflow: hidden;
            padding: 1rem;
        }
        
        #interactive {
            width: 100%;
            position: relative;
        }
        
        #interactive canvas.drawingBuffer {
            position: absolute;
            top: 0;
            left: 0;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-cash-register me-2"></i> Point of Sale</h1>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-primary fs-6">
                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </span>
                        <span class="badge bg-secondary fs-6 ms-1">
                            <?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Role')) ?>
                        </span>
                    </div>
                    <div id="current-time" class="badge bg-dark fs-6"></div>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="cashier-container">
                <div class="product-panel">
                    <div style="flex-shrink: 0;">
                        <h5><i class="fas fa-boxes me-2"></i> Products</h5>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="product-search" class="form-control" placeholder="Search products by name or barcode...">
                        </div>
                        <div class="category-tabs">
                            <div class="category-tab active" data-category="all">All</div>
                            <?php foreach ($categories as $category): ?>
                                <div class="category-tab" data-category="<?= htmlspecialchars($category) ?>">
                                    <?= htmlspecialchars($category) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="product-panel-content">
                        <div class="product-grid" id="product-grid">
                            <?php if (empty($products)): ?>
                                <p class="text-muted p-3">No products available.</p>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <div class="product-card"
                                         data-id="<?= $product['ProductID'] ?>"
                                         data-name="<?= htmlspecialchars($product['ProductName']) ?>"
                                         data-price="<?= $product['Price'] ?>"
                                         data-stock="<?= $product['StockQuantity'] ?>"
                                         data-category="<?= htmlspecialchars($product['Category'] ?? 'Uncategorized') ?>"
                                         data-barcode="<?= htmlspecialchars($product['Barcode'] ?? '') ?>"
                                         title="<?= htmlspecialchars($product['ProductName']) ?> (Stock: <?= $product['StockQuantity'] ?>)">
                                        <div class="product-name"><?= htmlspecialchars($product['ProductName']) ?></div>
                                        <div>
                                            <div class="product-price">₱<?= number_format($product['Price'], 2) ?></div>
                                            <div class="product-stock">Stock: <?= $product['StockQuantity'] ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="cart-panel" id="cart-panel">
                    <?php include 'cart_contents.php'; ?>
                </div>
            </div>

            <div class="barcode-scanner-btn" data-bs-toggle="modal" data-bs-target="#scannerModal" title="Scan Barcode">
                <i class="fas fa-barcode"></i>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scannerModalLabel"><i class="fas fa-barcode me-2"></i>Barcode Scanner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="interactive" class="viewport">
                        <div style="min-height: 200px;"></div>
                    </div>
                    <div id="scan-status" class="mt-2 alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
        const cartPanel = document.getElementById('cart-panel');
        const productGrid = document.getElementById('product-grid');
        const productSearchInput = document.getElementById('product-search');
        const categoryTabsContainer = document.querySelector('.category-tabs');
        const scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
        const interactiveDiv = document.getElementById('interactive');
        const scanStatusDiv = document.getElementById('scan-status');
        let quaggaInitialized = false;
        let quaggaRunning = false;

        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateString = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            const timeEl = document.getElementById('current-time');
            if(timeEl) {
                timeEl.textContent = `${timeString} • ${dateString}`;
            }
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function updateCartDisplay(data) {
            if (cartPanel) {
                // If we received HTML directly (from initial load)
                if (typeof data === 'string') {
                    cartPanel.innerHTML = data;
                } 
                // If we received a JSON response with cart_html
                else if (data.cart_html) {
                    cartPanel.innerHTML = data.cart_html;
                }
                
                attachCartActionListeners();
                setupCustomerTypeToggle();
                setupPaymentHandlers();
                updateCheckoutButtonState();
            } else {
                console.error("Cart panel element not found");
            }
        }

        function fetchCartContents() {
            fetch('cashier.php?get_cart=1')
                .then(response => response.text())
                .then(html => {
                    updateCartDisplay(html);
                })
                .catch(error => {
                    console.error('Error fetching cart:', error);
                });
        }

        function updateCheckoutButtonState() {
            const checkoutBtn = document.querySelector('.checkout-btn');
            const cartItemsContainer = document.querySelector('.cart-items');
            if (checkoutBtn && cartItemsContainer) {
                const hasItems = cartItemsContainer.querySelector('.cart-item') !== null;
                checkoutBtn.disabled = !hasItems;
            }
        }

        async function sendAjaxRequest(url, options) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    let errorMsg = `HTTP error! Status: ${response.status} ${response.statusText}`;
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.message || errorMsg;
                    } catch(e) {}
                    throw new Error(errorMsg);
                }
                return await response.json();
            } catch (error) {
                console.error('AJAX Error:', error);
                showTemporaryAlert(`Request failed: ${error.message}`, 'danger');
                throw error;
            }
        }

        function showTemporaryAlert(message, type = 'info', duration = 4000, containerSelector = '.main-content > .container-fluid') {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            const alertEl = document.createElement('div');
            alertEl.className = `alert alert-${type} alert-dismissible fade show m-3`;
            alertEl.setAttribute('role', 'alert');
            alertEl.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            container.insertBefore(alertEl, container.firstChild);

            setTimeout(() => {
                const alertInstance = bootstrap.Alert.getOrCreateInstance(alertEl);
                if (alertInstance) {
                    alertInstance.close();
                } else if (alertEl.parentNode) {
                    alertEl.parentNode.removeChild(alertEl);
                }
            }, duration);
        }

        function filterProducts() {
            const searchTerm = productSearchInput.value.toLowerCase().trim();
            const activeCategory = categoryTabsContainer.querySelector('.category-tab.active')?.dataset.category || 'all';
            const productCards = productGrid.querySelectorAll('.product-card');
            let visibleCount = 0;

            productCards.forEach(card => {
                const name = card.dataset.name?.toLowerCase() || '';
                const barcode = card.dataset.barcode?.toLowerCase() || '';
                const category = card.dataset.category || 'Uncategorized';

                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || barcode.includes(searchTerm);
                const matchesCategory = activeCategory === 'all' || category === activeCategory;

                if (matchesSearch && matchesCategory) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const noMatchMsg = document.getElementById('no-product-match');
            if (visibleCount === 0 && productCards.length > 0) {
                if (!noMatchMsg) {
                    const msgDiv = document.createElement('div');
                    msgDiv.id = 'no-product-match';
                    msgDiv.className = 'text-muted p-3 w-100 text-center';
                    msgDiv.textContent = 'No products match your current filter.';
                    productGrid.appendChild(msgDiv);
                } else {
                    noMatchMsg.style.display = '';
                }
            } else if (noMatchMsg) {
                noMatchMsg.style.display = 'none';
            }
        }

        const debouncedFilter = debounce(filterProducts, 300);

        if (productSearchInput) {
            productSearchInput.addEventListener('input', debouncedFilter);
        } else {
            console.warn("Product search input not found.");
        }

        if (categoryTabsContainer) {
            categoryTabsContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('category-tab')) {
                    categoryTabsContainer.querySelector('.category-tab.active')?.classList.remove('active');
                    event.target.classList.add('active');
                    filterProducts();
                }
            });
        } else {
            console.warn("Category tabs container not found.");
        }

        if (productGrid) {
            productGrid.addEventListener('click', function(event) {
                const card = event.target.closest('.product-card');
                if (card) {
                    const productId = card.dataset.id;
                    const productName = card.dataset.name;
                    const stock = parseInt(card.dataset.stock, 10);

                    if (stock <= 0) {
                        showTemporaryAlert(`"${productName}" is out of stock.`, 'warning');
                        return;
                    }

                    if (productId) {
                        const formData = new FormData();
                        formData.append('add_to_cart', '1');
                        formData.append('product_id', productId);
                        formData.append('quantity', '1');
                        formData.append('csrf_token', csrfToken);

                        sendAjaxRequest('cashier.php', { method: 'POST', body: formData })
                            .then(data => {
                                if (data.status === 'success') {
                                    updateCartDisplay(data);
                                } else {
                                    showTemporaryAlert(data.message || 'Failed to add item.', 'danger');
                                }
                            })
                            .catch(error => {});
                    }
                }
            });
        } else {
            console.warn("Product grid not found.");
        }

        function attachCartActionListeners() {
            if (!cartPanel) return;

            cartPanel.addEventListener('click', function(event) {
                const button = event.target.closest('button');
                if (!button) return;

                const itemIndex = button.dataset.index;
                let action = '';
                let change = 0;

                if (button.classList.contains('increase-qty')) {
                    action = 'update_item';
                    change = 1;
                } else if (button.classList.contains('decrease-qty')) {
                    action = 'update_item';
                    change = -1;
                } else if (button.classList.contains('remove-item')) {
                    action = 'remove_item';
                }

                if (action && itemIndex !== undefined) {
                    const formData = new FormData();
                    formData.append(action, '1');
                    formData.append('item_index', itemIndex);
                    if (action === 'update_item') {
                        formData.append('quantity_change', change);
                    }
                    formData.append('csrf_token', csrfToken);

                    sendAjaxRequest('cashier.php', { method: 'POST', body: formData })
                        .then(data => {
                            if (data.status === 'success') {
                                updateCartDisplay(data);
                            } else {
                                showTemporaryAlert(data.message || `Failed to ${action.replace('_', ' ')}.`, 'danger');
                            }
                        })
                        .catch(error => {});
                }
            });
        }

        function setupCustomerTypeToggle() {
            const customerTypeGroup = document.getElementById('customerTypeGroup');  
            const customerSelectContainer = document.getElementById('customerSelectContainer');
            const paymentMethodContainer = document.getElementById('paymentMethodContainer');
            const customerSelect = document.getElementById('customerSelect');

            if (!customerTypeGroup || !customerSelectContainer || !paymentMethodContainer || !customerSelect) {
                return;
            }

            function toggleMemberOptions() {
                const selectedType = customerTypeGroup.querySelector('input[name="customer_type"]:checked')?.value;
                if (selectedType === 'member') {
                    customerSelectContainer.style.display = '';
                    paymentMethodContainer.style.display = '';
                    customerSelect.required = true;
                } else {
                    customerSelectContainer.style.display = 'none';
                    paymentMethodContainer.style.display = 'none';
                    customerSelect.required = false;
                    customerSelect.value = "";
                }
            }

            customerTypeGroup.addEventListener('change', toggleMemberOptions);
            toggleMemberOptions();
        }

        function handlePaymentMethodChange() {
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            document.getElementById('cashPaymentFields').style.display = method === 'cash' ? 'block' : 'none';

            if (method === 'cash') calculateChange();
            if (method === 'credit') updateCreditBalance();
        }

        function calculateChange() {
            const totalEl = document.querySelector('.cart-total h5');
            if (!totalEl) return;

            const total = parseFloat(totalEl.textContent.replace(/[^\d.]/g, ''));
            const received = parseFloat(document.getElementById('amountReceived').value) || 0;
            const changeDueEl = document.getElementById('changeDue');
            if (changeDueEl) {
                changeDueEl.textContent = '₱' + Math.max(0, received - total).toFixed(2);
            }
        }

        function updateCreditBalance() {
            const customerSelect = document.getElementById('customerSelect');
            const customerId = customerSelect ? customerSelect.value : null;
            const availableCreditEl = document.getElementById('availableCredit');

            if (customerId && availableCreditEl) {
                fetch(`get_credit_balance.php?id=${customerId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            availableCreditEl.textContent = '₱' + parseFloat(data.balance).toFixed(2);
                        } else {
                            availableCreditEl.textContent = '₱0.00';
                        }
                    })
                    .catch(error => {
                        availableCreditEl.textContent = '₱0.00';
                    });
            } else if (availableCreditEl) {
                availableCreditEl.textContent = '₱0.00';
            }
        }

        function setupPaymentHandlers() {
            const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
            paymentMethodRadios.forEach(radio => radio.addEventListener('change', handlePaymentMethodChange));

            const amountReceivedInput = document.getElementById('amountReceived');
            if (amountReceivedInput) {
                amountReceivedInput.addEventListener('input', calculateChange);
            }

            const customerSelect = document.getElementById('customerSelect');
            if (customerSelect) {
                customerSelect.addEventListener('change', function() {
                    if (document.querySelector('input[name="payment_method"]:checked').value === 'credit') {
                        updateCreditBalance();
                    }
                });
            }

            handlePaymentMethodChange();
        }

        function initializeQuagga() {
            if (!quaggaInitialized) {
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: interactiveDiv,
                        constraints: {
                            width: { min: 640 },
                            height: { min: 480 },
                            facingMode: "environment",
                            aspectRatio: { min: 1, max: 2 }
                        },
                    },
                    decoder: {
                        readers: [
                            "code_128_reader", "ean_reader", "ean_8_reader",
                            "code_39_reader", "code_39_vin_reader",
                            "codabar_reader", "upc_reader", "upc_e_reader",
                            "i2of5_reader"
                        ],
                    },
                    locate: true,
                    locator: {
                        patchSize: "medium",
                        halfSample: true
                    },
                    numOfWorkers: navigator.hardwareConcurrency || 4,
                    frequency: 10,
                }, function(err) {
                    if (err) {
                        showScanStatus(`Error initializing scanner: ${err.message || err}`, 'danger');
                        return;
                    }
                    quaggaInitialized = true;
                    startQuagga();
                });

                Quagga.onDetected(debounce(handleBarcodeDetection, 500));
            } else {
                startQuagga();
            }
        }

        function startQuagga() {
            if (quaggaInitialized && !quaggaRunning) {
                Quagga.start();
                quaggaRunning = true;
                showScanStatus("Scanner active. Point camera at barcode.", "info");
                interactiveDiv.style.display = '';
            }
        }

        function stopQuagga() {
            if (quaggaRunning) {
                Quagga.stop();
                quaggaRunning = false;
                interactiveDiv.style.display = 'none';
            }
        }

        function showScanStatus(message, type = 'info') {
            scanStatusDiv.textContent = message;
            scanStatusDiv.className = `mt-2 alert alert-${type}`;
            scanStatusDiv.style.display = 'block';
        }

        function handleBarcodeDetection(result) {
            if (!result || !result.codeResult || !result.codeResult.code) return;
            const code = result.codeResult.code;
            showScanStatus(`Detected: ${code}. Looking up...`, 'info');

            sendAjaxRequest(`barcode_lookup.php?barcode=${encodeURIComponent(code)}`, { method: 'GET' })
                .then(data => {
                    if (data.status === 'success' && data.product) {
                        const product = data.product;
                        showScanStatus(`Found: ${product.ProductName} (₱${product.Price})`, 'success');

                        const formData = new FormData();
                        formData.append('add_to_cart', '1');
                        formData.append('product_id', product.ProductID);
                        formData.append('quantity', '1');
                        formData.append('csrf_token', csrfToken);

                        return sendAjaxRequest('cashier.php', { method: 'POST', body: formData });
                    } else {
                        showScanStatus(`Barcode lookup failed`, 'warning');
                        return Promise.reject();
                    }
                })
                .then(cartData => {
                    if (cartData && cartData.status === 'success') {
                        updateCartDisplay(cartData);
                    }
                })
                .catch(() => {});
        }

        document.getElementById('scannerModal').addEventListener('shown.bs.modal', initializeQuagga);
        document.getElementById('scannerModal').addEventListener('hidden.bs.modal', stopQuagga);

        updateTime();
        setInterval(updateTime, 1000);
        attachCartActionListeners();
        setupCustomerTypeToggle();
        setupPaymentHandlers();
        updateCheckoutButtonState();
    });
    </script>
</body>
</html>