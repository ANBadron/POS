<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$customer = [];
$transactions = [];
$totalSpent = 0;
$totalCreditSpent = 0;

// Validate and sanitize customer ID
$customerID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$customerID) {
    $_SESSION['error'] = "Invalid customer ID specified";
    header('Location: customers.php');
    exit();
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch customer details
    $stmt = $conn->prepare("
        SELECT 
            CustomerID,
            CustomerName,
            Email,
            Phone,
            Address,
            CreatedAt,
            (SELECT COUNT(*) FROM Transactions WHERE CustomerID = Customers.CustomerID) AS TransactionCount
        FROM Customers 
        WHERE CustomerID = :customerID
        LIMIT 1
    ");
    $stmt->bindParam(':customerID', $customerID, PDO::PARAM_INT);
    $stmt->execute();
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception("Customer not found in database");
    }

    // 2. Fetch recent transactions (last 50)
    $stmt = $conn->prepare("
        SELECT 
            t.TransactionID, 
            t.TransactionDate, 
            t.TotalAmount, 
            t.PaymentMethod, 
            u.Username AS CashierName, 
            COUNT(ti.TransactionItemID) AS ItemCount,
            GROUP_CONCAT(p.ProductName SEPARATOR ', ') AS Products
        FROM Transactions t 
        JOIN Users u ON t.UserID = u.UserID 
        LEFT JOIN TransactionItems ti ON t.TransactionID = ti.TransactionID
        LEFT JOIN Products p ON ti.ProductID = p.ProductID
        WHERE t.CustomerID = :customerID 
        GROUP BY t.TransactionID 
        ORDER BY t.TransactionDate DESC
        LIMIT 50
    ");
    $stmt->bindParam(':customerID', $customerID, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate spending totals
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN PaymentMethod = 'credit' THEN TotalAmount ELSE 0 END), 0) AS CreditTotal,
            COALESCE(SUM(TotalAmount), 0) AS OverallTotal
        FROM Transactions 
        WHERE CustomerID = :customerID
    ");
    $stmt->bindParam(':customerID', $customerID, PDO::PARAM_INT);
    $stmt->execute();
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSpent       = $totals['OverallTotal']   ?? 0;
    $totalCreditSpent = $totals['CreditTotal']    ?? 0;

} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Database Error in " 
             . basename(__FILE__) . " (Line " . $e->getLine() 
             . "): " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred while loading customer data";
    header('Location: customers.php');
    exit();
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Application Error in " 
             . basename(__FILE__) . ": " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: customers.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['CustomerName'] ?? 'Customer') ?> - POS System</title>
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

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 80px);
        }

        .profile-panel, .transactions-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-content {
            overflow-y: auto;
            flex-grow: 1;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-right: 20px;
        }

        .profile-info h2 {
            margin: 0;
            color: var(--dark-color);
        }

        .profile-meta {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }

        .profile-meta span {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .detail-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .detail-card h5 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }

        .detail-label {
            flex: 0 0 120px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .detail-value {
            flex: 1;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-meta {
            display: flex;
            gap: 10px;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .transaction-amount {
            font-weight: 600;
            color: var(--primary-color);
        }

        .payment-method {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .payment-cash {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .payment-credit {
            background-color: #fff3cd;
            color: #664d03;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .profile-panel, .transactions-panel {
                height: auto;
                min-height: 400px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            .profile-meta {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-user me-2"></i> Customer Profile</h1>
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

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="customers.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Customers
                    </a>
                    <a href="edit_customer.php?id=<?= $customerID ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Profile
                    </a>
                </div>
                <span class="badge bg-primary">
                    <i class="fas fa-id-card me-1"></i> ID: <?= $customerID ?>
                </span>
            </div>

            <div class="profile-container">
                <div class="profile-panel">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= substr($customer['CustomerName'], 0, 1) ?>
                        </div>
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($customer['CustomerName']) ?></h2>
                            <div class="profile-meta">
                                <span><i class="fas fa-calendar-alt me-1"></i> Member since <?= date('M j, Y', strtotime($customer['CreatedAt'])) ?></span>
                                <span><i class="fas fa-shopping-cart me-1"></i> <?= $customer['TransactionCount'] ?> transactions</span>
                            </div>
                        </div>
                    </div>

                    <div class="panel-content">
                        <div class="detail-card">
                            <h5><i class="fas fa-info-circle me-2"></i>Contact Information</h5>
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?= $customer['Email'] ? htmlspecialchars($customer['Email']) : '<span class="text-muted">Not provided</span>' ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value"><?= $customer['Phone'] ? htmlspecialchars($customer['Phone']) : '<span class="text-muted">Not provided</span>' ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Address</div>
                                <div class="detail-value"><?= $customer['Address'] ? htmlspecialchars($customer['Address']) : '<span class="text-muted">Not provided</span>' ?></div>
                            </div>
                        </div>

                        <div class="detail-card">
                            <h5><i class="fas fa-chart-line me-2"></i>Purchase Statistics</h5>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value">₱<?= number_format($totalSpent, 2) ?></div>
                                    <div class="stat-label">Total Spent</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value">₱<?= number_format($totalCreditSpent, 2) ?></div>
                                    <div class="stat-label">Credit Purchases</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?= $customer['TransactionCount'] ?></div>
                                    <div class="stat-label">Total Transactions</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value">₱<?= $customer['TransactionCount'] > 0 ? number_format($totalSpent / $customer['TransactionCount'], 2) : '0.00' ?></div>
                                    <div class="stat-label">Avg. Transaction</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="transactions-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                        <span class="badge bg-primary">Last 50</span>
                    </div>

                    <div class="panel-content">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $txn): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="d-flex justify-content-between">
                                            <strong>#<?= $txn['TransactionID'] ?></strong>
                                            <span class="transaction-amount">₱<?= number_format($txn['TotalAmount'], 2) ?></span>
                                        </div>
                                        <div class="transaction-meta">
                                            <span><?= date('M j, Y', strtotime($txn['TransactionDate'])) ?></span>
                                            <span class="payment-method payment-<?= strtolower($txn['PaymentMethod']) ?>">
                                                <?= ucfirst($txn['PaymentMethod']) ?>
                                            </span>
                                            <span><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($txn['CashierName']) ?></span>
                                            <span><i class="fas fa-boxes me-1"></i> <?= $txn['ItemCount'] ?> items</span>
                                        </div>
                                        <div class="text-truncate" style="max-width: 100%;" title="<?= htmlspecialchars($txn['Products']) ?>">
                                            <small class="text-muted"><?= htmlspecialchars($txn['Products']) ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center ms-2">
                                        <a href="transactions.php?view_transaction=<?= $txn['TransactionID'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No transaction history found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const dateString = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                const timeEl = document.getElementById('current-time');
                if(timeEl) {
                    timeEl.textContent = `${timeString} • ${dateString}`;
                }
            }

            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>
</body>
</html>