<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'cashier') {
    header('Location: login.php');
    exit();
}

include 'db.php';

// Check if viewing a specific transaction
$transactionDetails = [];
if (isset($_GET['view_transaction'])) {
    $transactionID = (int)$_GET['view_transaction'];
    
    try {
        // Get transaction header
        $stmt = $conn->prepare("
            SELECT t.*, c.CustomerName, c.Email, c.Phone, u.Username as CashierName
            FROM Transactions t
            LEFT JOIN Customers c ON t.CustomerID = c.CustomerID
            JOIN Users u ON t.UserID = u.UserID
            WHERE t.TransactionID = ?
        ");
        $stmt->execute([$transactionID]);
        $transactionDetails = $stmt->fetch();
        
        // Get transaction items if header exists
        if ($transactionDetails) {
            $stmt = $conn->prepare("
                SELECT ti.*, p.ProductName, p.Barcode
                FROM TransactionItems ti
                JOIN Products p ON ti.ProductID = p.ProductID
                WHERE ti.TransactionID = ?
            ");
            $stmt->execute([$transactionID]);
            $transactionDetails['items'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching transaction details: " . $e->getMessage());
        $_SESSION['error'] = "Error loading transaction details. Please try again.";
        header('Location: transactions.php');
        exit();
    }
}

// Fetch all transactions with customer names and payment method
try {
    $transactions = $conn->query("
        SELECT t.TransactionID, t.TransactionDate, c.CustomerName, 
               t.TotalAmount, t.PaymentMethod, t.AmountTendered, t.ChangeDue,
               u.Username as CashierName
        FROM Transactions t
        LEFT JOIN Customers c ON t.CustomerID = c.CustomerID
        JOIN Users u ON t.UserID = u.UserID
        ORDER BY t.TransactionDate DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching transactions: " . $e->getMessage());
    die("Error fetching transactions. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - POS System</title>
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
            --warning-color: #ffc107;
        }
        
        .transactions-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .transactions-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }

        .transaction-items {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 5px;
            max-height: calc(100vh - 400px);
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-item-info {
            flex-grow: 1;
            margin-right: 10px;
        }

        .transaction-item-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .transaction-item-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .transaction-item-price {
            color: var(--primary-color);
            font-size: 0.85rem;
        }

        .transaction-item-qty {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .payment-badge {
            padding: 0.4em 0.7em;
            font-size: 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .payment-cash {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .payment-credit {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .payment-default {
            background-color: #d3d3d4;
            color: #3e3e3e;
        }

        .transaction-totals {
            font-size: 1rem;
            text-align: right;
            margin-bottom: 15px;
        }
        
        .transaction-total {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .transaction-amount {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .transaction-change {
            font-weight: 600;
            color: var(--accent-color);
        }

        .empty-transactions {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .empty-transactions i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        /* Filter buttons styles */
        .btn-group .btn {
            border-radius: 0.25rem !important;
            margin-right: 0.25rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-group .btn:first-child {
            border-top-left-radius: 0.25rem !important;
            border-bottom-left-radius: 0.25rem !important;
        }

        .btn-group .btn:last-child {
            border-top-right-radius: 0.25rem !important;
            border-bottom-right-radius: 0.25rem !important;
            margin-right: 0;
        }

        .btn-group .btn.filter-btn {
            background-color: var(--light-color);
            color: var(--dark-color);
            border-color: #dee2e6;
        }

        .btn-group .btn.filter-btn:hover {
            background-color: #e9ecef;
        }

        .btn-group .btn.filter-btn.active {
            color: white;
            border-color: transparent;
        }

        .btn-group .btn.filter-btn[data-filter="all"].active {
            background-color: var(--primary-color);
        }

        .btn-group .btn.filter-btn[data-filter="cash"].active {
            background-color: var(--success-color);
        }

        .btn-group .btn.filter-btn[data-filter="credit"].active {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .transactions-panel, .transactions-panel * {
                visibility: visible;
            }
            .transactions-panel {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                box-shadow: none;
                border: none;
                background: white;
            }
            .btn, .transaction-header div {
                display: none !important;
            }
            .transaction-header h5 {
                display: block !important;
                text-align: center;
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            .transaction-totals {
                font-size: 1.2rem;
                margin-top: 30px;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .transactions-container {
                grid-template-columns: 1fr;
            }
            .transaction-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .transaction-header > div {
                width: 100%;
            }
            .input-group {
                width: 100% !important;
            }
            .btn-group {
                width: 100%;
            }
            .btn-group .btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-receipt me-2"></i> Transactions</h1>
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

            <div class="transactions-container">
                <?php if (!empty($transactionDetails)): ?>
                    <!-- Transaction Details View -->
                    <div class="transactions-panel">
                        <div class="transaction-header">
                            <h5>
                                <i class="fas fa-receipt me-2"></i> 
                                Transaction #<?= $transactionDetails['TransactionID'] ?>
                            </h5>
                            <div>
                                <a href="transactions.php" class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </a>
                                <button onclick="printReceipt()" class="btn btn-sm btn-primary">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Customer Information</h6>
                                    <p class="mb-1">
                                        <strong><?= htmlspecialchars($transactionDetails['CustomerName'] ?? 'Walk-in Customer') ?></strong>
                                    </p>
                                    <?php if (!empty($transactionDetails['Email'])): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-envelope me-2"></i>
                                            <?= htmlspecialchars($transactionDetails['Email']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($transactionDetails['Phone'])): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-phone me-2"></i>
                                            <?= htmlspecialchars($transactionDetails['Phone']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6>Transaction Details</h6>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar me-2"></i>
                                        <?= date('M d, Y h:i A', strtotime($transactionDetails['TransactionDate'])) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-user-tag me-2"></i>
                                        <?= htmlspecialchars($transactionDetails['CashierName']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        <span class="payment-badge payment-<?= strtolower($transactionDetails['PaymentMethod']) ?>">
                                            <?= ucfirst($transactionDetails['PaymentMethod']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <h6>Items Purchased</h6>
                        <div class="transaction-items">
                            <?php foreach ($transactionDetails['items'] as $item): ?>
                                <div class="transaction-item">
                                    <div class="transaction-item-info">
                                        <div class="transaction-item-name">
                                            <?= htmlspecialchars($item['ProductName']) ?>
                                        </div>
                                        <div class="transaction-item-meta">
                                            <?= $item['Barcode'] ? 'Barcode: ' . htmlspecialchars($item['Barcode']) : '' ?>
                                        </div>
                                    </div>
                                    <div class="transaction-item-qty">
                                        <span><?= $item['Quantity'] ?> × ₱<?= number_format($item['Price'], 2) ?></span>
                                        <span class="transaction-item-price">
                                            ₱<?= number_format($item['Price'] * $item['Quantity'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="transaction-totals">
                            <?php if ($transactionDetails['PaymentMethod'] === 'cash'): ?>
                                <div class="mb-1">
                                    <span>Amount Paid: </span>
                                    <span class="transaction-amount">₱<?= number_format($transactionDetails['AmountTendered'], 2) ?></span>
                                </div>
                                <div class="mb-1">
                                    <span>Change: </span>
                                    <span class="transaction-change">₱<?= number_format($transactionDetails['ChangeDue'], 2) ?></span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span>Total: </span>
                                <span class="transaction-total">₱<?= number_format($transactionDetails['TotalAmount'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Transactions List View -->
                    <div class="transactions-panel">
                        <div class="transaction-header">
                            <h5><i class="fas fa-history me-2"></i> Transaction History</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <div class="input-group" style="width: 300px;">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Search transactions..." id="searchInput">
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-primary filter-btn active" data-filter="all">
                                        <i class="fas fa-list me-1"></i> All
                                    </button>
                                    <button class="btn btn-success filter-btn" data-filter="cash">
                                        <i class="fas fa-money-bill-wave me-1"></i> Cash
                                    </button>
                                    <button class="btn btn-warning filter-btn" data-filter="credit">
                                        <i class="fas fa-credit-card me-1"></i> Credit
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Change</th>
                                        <th>Cashier</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?= $transaction['TransactionID'] ?></td>
                                                <td><?= date('M d, Y h:i A', strtotime($transaction['TransactionDate'])) ?></td>
                                                <td><?= htmlspecialchars($transaction['CustomerName'] ?? 'Walk-in') ?></td>
                                                <td>
                                                    <span class="payment-badge payment-<?= strtolower($transaction['PaymentMethod']) ?>">
                                                        <?= ucfirst($transaction['PaymentMethod']) ?>
                                                    </span>
                                                </td>
                                                <td>₱<?= number_format($transaction['TotalAmount'], 2) ?></td>
                                                <td>
                                                    <?php if ($transaction['PaymentMethod'] === 'cash'): ?>
                                                        ₱<?= number_format($transaction['AmountTendered'], 2) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['PaymentMethod'] === 'cash'): ?>
                                                        ₱<?= number_format($transaction['ChangeDue'], 2) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['CashierName']) ?></td>
                                                <td>
                                                    <a href="transactions.php?view_transaction=<?= $transaction['TransactionID'] ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9">
                                                <div class="empty-transactions">
                                                    <i class="fas fa-receipt"></i>
                                                    <p>No transactions found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateString = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            const timeEl = document.getElementById('current-time');
            if(timeEl) {
                timeEl.textContent = `${timeString} • ${dateString}`;
            }
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Print function for receipts
        window.printReceipt = function() {
            // Store original content
            const originalContent = document.body.innerHTML;
            
            // Get receipt content
            const receiptContent = document.querySelector('.transactions-panel').outerHTML;
            
            // Replace body with just the receipt
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    ${receiptContent}
                    <div style="text-align: center; margin-top: 30px; font-style: italic;">
                        Thank you for your purchase!
                    </div>
                </div>
                <button onclick="window.location.reload()" 
                        style="position: fixed; top: 20px; right: 20px; z-index: 9999;" 
                        class="btn btn-danger">
                    Close Print View
                </button>
            `;
            
            // Add print-specific styles
            const style = document.createElement('style');
            style.innerHTML = `
                @media print {
                    button {
                        display: none !important;
                    }
                    body {
                        padding: 20px !important;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Print and then restore
            setTimeout(() => {
                window.print();
                document.body.innerHTML = originalContent;
            }, 500);
        };

        // Payment filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons first
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.remove('btn-primary', 'btn-success', 'btn-warning');
                    b.classList.add('btn-outline-secondary');
                });
                
                // Add appropriate active class to clicked button
                this.classList.remove('btn-outline-secondary');
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                if (filter === 'all') {
                    this.classList.add('btn-primary');
                } else if (filter === 'cash') {
                    this.classList.add('btn-success');
                } else if (filter === 'credit') {
                    this.classList.add('btn-warning');
                }
                
                // Filter the rows
                const rows = document.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const paymentMethod = row.querySelector('td:nth-child(4) span').textContent.toLowerCase();
                    
                    if (filter === 'all') {
                        row.style.display = '';
                    } else {
                        row.style.display = paymentMethod.includes(filter) ? '' : 'none';
                    }
                });
                
                // Apply search filter if any
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                if (searchTerm) {
                    filterBySearch(searchTerm);
                }
            });
        });

        // Helper function for search filtering
        function filterBySearch(term) {
            const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
            
            visibleRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(term) ? '' : 'none';
            });
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterBySearch(this.value.toLowerCase());
            });
        }
    });
    </script>
</body>
</html>
