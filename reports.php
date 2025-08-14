<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

include 'db.php';

// Default date range (last 30 days)
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');
$report_type = 'daily'; // daily, weekly, monthly
$payment_method = 'all'; // all, cash, credit

// Handle report filter submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['filter_report'])) {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
    $report_type = $_POST['report_type'] ?? 'daily';
    $payment_method = $_POST['payment_method'] ?? 'all';
    
    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }
}

try {
    // Prepare payment method condition for queries
    $payment_condition = $payment_method !== 'all' ? " AND PaymentMethod = :payment_method" : "";
    $payment_params = [];
    if ($payment_method !== 'all') {
        $payment_params[':payment_method'] = ucfirst($payment_method);
    }

    // Total Sales
    $stmt = $conn->prepare("SELECT COALESCE(SUM(TotalAmount), 0) AS total_sales 
                           FROM Transactions 
                           WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition");
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $total_sales = $stmt->fetchColumn();

    // Total Transactions
    $stmt = $conn->prepare("SELECT COUNT(*) 
                           FROM Transactions 
                           WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition");
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $total_transactions = $stmt->fetchColumn();

    // Average Transaction Value
    $stmt = $conn->prepare("SELECT COALESCE(AVG(TotalAmount), 0) 
                           FROM Transactions 
                           WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition");
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $avg_transaction = $stmt->fetchColumn();

    // Top Selling Products
    $top_products_query = "
        SELECT p.ProductName, SUM(ti.Quantity) AS total_sold, SUM(ti.Quantity * ti.Price) AS total_revenue
        FROM TransactionItems ti
        JOIN Products p ON ti.ProductID = p.ProductID
        JOIN Transactions t ON ti.TransactionID = t.TransactionID
        WHERE DATE(t.TransactionDate) BETWEEN ? AND ? $payment_condition
        GROUP BY p.ProductID
        ORDER BY total_sold DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($top_products_query);
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $top_products = $stmt->fetchAll();

    // Sales by time period
    $sales_data = [];
    $labels = [];
    
    switch ($report_type) {
        case 'daily':
            $query = "
                SELECT 
                    DATE(TransactionDate) AS day,
                    SUM(TotalAmount) AS daily_sales,
                    COUNT(*) AS transaction_count
                FROM Transactions
                WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition
                GROUP BY DATE(TransactionDate)
                ORDER BY day
            ";
            break;
            
        case 'weekly':
            $query = "
                SELECT 
                    YEARWEEK(TransactionDate, 1) AS week,
                    MIN(DATE(TransactionDate)) AS week_start,
                    SUM(TotalAmount) AS weekly_sales,
                    COUNT(*) AS transaction_count
                FROM Transactions
                WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition
                GROUP BY YEARWEEK(TransactionDate, 1)
                ORDER BY week
            ";
            break;
            
        case 'monthly':
            $query = "
                SELECT 
                    DATE_FORMAT(TransactionDate, '%Y-%m') AS month,
                    SUM(TotalAmount) AS monthly_sales,
                    COUNT(*) AS transaction_count
                FROM Transactions
                WHERE DATE(TransactionDate) BETWEEN ? AND ? $payment_condition
                GROUP BY DATE_FORMAT(TransactionDate, '%Y-%m')
                ORDER BY month
            ";
            break;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $result = $stmt->fetchAll();
    
    foreach ($result as $row) {
        if ($report_type === 'daily') {
            $labels[] = date('M j', strtotime($row['day']));
            $sales_data[] = [
                'sales' => $row['daily_sales'],
                'transactions' => $row['transaction_count']
            ];
        } elseif ($report_type === 'weekly') {
            $labels[] = 'Week of ' . date('M j', strtotime($row['week_start']));
            $sales_data[] = [
                'sales' => $row['weekly_sales'],
                'transactions' => $row['transaction_count']
            ];
        } elseif ($report_type === 'monthly') {
            $labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $sales_data[] = [
                'sales' => $row['monthly_sales'],
                'transactions' => $row['transaction_count']
            ];
        }
    }

    // Recent Transactions
    $recent_transactions_query = "
        SELECT 
            t.TransactionID, 
            DATE_FORMAT(t.TransactionDate, '%b %d, %Y %h:%i %p') AS FormattedDate,
            COALESCE(c.CustomerName, 'Walk-in') AS CustomerName, 
            t.TotalAmount,
            t.PaymentMethod,
            u.Username AS CashierName
        FROM Transactions t
        LEFT JOIN Customers c ON t.CustomerID = c.CustomerID
        JOIN Users u ON t.UserID = u.UserID
        WHERE DATE(t.TransactionDate) BETWEEN ? AND ? $payment_condition
        ORDER BY t.TransactionDate DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($recent_transactions_query);
    $stmt->bindValue(1, $start_date);
    $stmt->bindValue(2, $end_date);
    if ($payment_method !== 'all') {
        $stmt->bindValue(':payment_method', ucfirst($payment_method));
    }
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    die("Error loading report data. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #43aa8b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: all 0.3s;
        }
        
        .report-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            height: calc(100vh - 180px);
        }

        .stats-panel, .chart-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 25px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            border-left: 4px solid var(--primary-color);
            background-color: white;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .stat-card .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .activity-list {
            flex-grow: 1;
            overflow-y: auto;
            margin-top: 20px;
            padding-right: 10px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }

        .activity-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: #343a40;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .activity-amount {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
            min-width: 120px;
            text-align: right;
        }

        .top-products-list {
            list-style: none;
            padding: 0;
            margin: 20px 0 0 0;
        }

        .top-product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }

        .product-info {
            display: flex;
            align-items: center;
        }

        .product-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 14px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .product-sales {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Scrollbar styling */
        .activity-list::-webkit-scrollbar,
        .chart-panel-content::-webkit-scrollbar {
            width: 6px;
        }
        .activity-list::-webkit-scrollbar-track,
        .chart-panel-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .activity-list::-webkit-scrollbar-thumb,
        .chart-panel-content::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        .activity-list::-webkit-scrollbar-thumb:hover,
        .chart-panel-content::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .report-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .stats-panel, .chart-panel {
                height: auto;
                min-height: 400px;
                margin-bottom: 25px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        .payment-badge {
            padding: 0.4em 0.8em;
            font-size: 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .payment-cash {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .payment-credit {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        /* Date Range Picker Styles */
        .date-range-container {
            position: relative;
        }
        
        .date-range-input {
            background-color: white;
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            width: 100%;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .date-range-input:hover {
            border-color: #86b7fe;
        }
        
        .date-range-input i {
            margin-right: 8px;
        }
        
        /* Recent Transactions Header */
        .recent-transactions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        /* Card header styling */
        .card-header-style {
            padding: 1rem 1.5rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Form elements spacing */
        .form-group-spaced {
            margin-bottom: 1.25rem;
        }
        
        /* Chart container */
        .chart-container {
            height: 280px;
            margin-bottom: 20px;
            position: relative;
        }
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <h1 class="mb-0"><i class="fas fa-chart-line me-2"></i> Sales Reports</h1>
                <div class="d-flex align-items-center gap-2">
                    <div>
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

            <!-- Date Filter Form -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" class="row g-4">
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Date Range</label>
                            <div class="date-range-container">
                                <input type="text" id="date-range" class="form-control date-range-input" 
                                       value="<?= date('M j, Y', strtotime($start_date)) ?> - <?= date('M j, Y', strtotime($end_date)) ?>">
                                <input type="hidden" name="start_date" id="start-date" value="<?= $start_date ?>">
                                <input type="hidden" name="end_date" id="end-date" value="<?= $end_date ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Report Type</label>
                            <select name="report_type" class="form-select">
                                <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $report_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="all" <?= $payment_method === 'all' ? 'selected' : '' ?>>All Methods</option>
                                <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash Only</option>
                                <option value="credit" <?= $payment_method === 'credit' ? 'selected' : '' ?>>Credit Only</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Quick Select</label>
                            <select id="quick-select" class="form-select">
                                <option value="">Custom Range</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="last7">Last 7 Days</option>
                                <option value="last30">Last 30 Days</option>
                                <option value="thisMonth">This Month</option>
                                <option value="lastMonth">Last Month</option>
                                <option value="thisYear">This Year</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" name="filter_report" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="report-container">
                <!-- Left Panel - Stats and Recent Activity -->
                <div class="stats-panel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Key Metrics</h5>
                        <span class="badge bg-primary">
                            <?= date('M j, Y', strtotime($start_date)) ?> - <?= date('M j, Y', strtotime($end_date)) ?>
                            <?= $payment_method !== 'all' ? '('.ucfirst($payment_method).')' : '' ?>
                        </span>
                    </div>
                    
                    <div class="stats-grid">
                        <!-- Sales Card -->
                        <div class="stat-card">
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-value">₱<?= number_format($total_sales, 2) ?></div>
                            <div class="stat-change">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>
                            </div>
                        </div>
                        
                        <!-- Transactions Card -->
                        <div class="stat-card">
                            <div class="stat-label">Transactions</div>
                            <div class="stat-value"><?= number_format($total_transactions) ?></div>
                            <div class="stat-change">
                                <i class="fas fa-exchange-alt me-1"></i>
                                <?= number_format($total_transactions / max(1, count($sales_data)), 1) ?> per <?= $report_type ?>
                            </div>
                        </div>
                        
                        <!-- Avg Transaction Card -->
                        <div class="stat-card">
                            <div class="stat-label">Avg. Transaction</div>
                            <div class="stat-value">₱<?= number_format($avg_transaction, 2) ?></div>
                            <div class="stat-change">
                                <i class="fas fa-chart-line me-1"></i>
                                <?= number_format($total_sales / max(1, $total_transactions), 2) ?> per transaction
                            </div>
                        </div>
                        
                        <!-- Period Card -->
                        <div class="stat-card">
                            <div class="stat-label">Reporting Period</div>
                            <div class="stat-value"><?= ucfirst($report_type) ?></div>
                            <div class="stat-change">
                                <i class="fas fa-clock me-1"></i>
                                <?= count($sales_data) ?> <?= $report_type ?> periods
                            </div>
                        </div>
                    </div>
                    
                    <div class="recent-transactions-header mt-4">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Transactions</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary <?= $payment_method === 'all' ? 'active' : '' ?>" onclick="filterRecentTransactions('all')">All</button>
                            <button type="button" class="btn btn-outline-primary <?= $payment_method === 'cash' ? 'active' : '' ?>" onclick="filterRecentTransactions('cash')">Cash</button>
                            <button type="button" class="btn btn-outline-primary <?= $payment_method === 'credit' ? 'active' : '' ?>" onclick="filterRecentTransactions('credit')">Credit</button>
                        </div>
                    </div>
                    <div class="activity-list">
                        <?php if (!empty($recent_transactions)): ?>
                            <?php foreach ($recent_transactions as $txn): ?>
                                <div class="activity-item" data-payment-method="<?= strtolower($txn['PaymentMethod']) ?>">
                                    <div class="activity-icon">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?= htmlspecialchars($txn['CustomerName']) ?>
                                            <span class="badge bg-light text-dark ms-2"><?= $txn['CashierName'] ?></span>
                                        </div>
                                        <div class="activity-time">
                                            <?= $txn['FormattedDate'] ?>
                                        </div>
                                    </div>
                                    <div class="activity-amount">
                                        ₱<?= number_format($txn['TotalAmount'], 2) ?>
                                        <span class="payment-badge payment-<?= strtolower($txn['PaymentMethod']) ?>">
                                            <?= ucfirst($txn['PaymentMethod']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p class="mb-0">No recent transactions found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Panel - Charts and Top Products -->
                <div class="chart-panel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Sales Trend</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary <?= $report_type === 'daily' ? 'active' : '' ?>" onclick="updateReportType('daily')">Daily</button>
                            <button type="button" class="btn btn-outline-primary <?= $report_type === 'weekly' ? 'active' : '' ?>" onclick="updateReportType('weekly')">Weekly</button>
                            <button type="button" class="btn btn-outline-primary <?= $report_type === 'monthly' ? 'active' : '' ?>" onclick="updateReportType('monthly')">Monthly</button>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div id="chartSummary" class="text-muted small">
                            Showing <?= count($sales_data) ?> <?= $report_type ?> periods with <?= $total_transactions ?> transactions totaling ₱<?= number_format($total_sales, 2) ?>
                            <?= $payment_method !== 'all' ? '(Payment method: '.ucfirst($payment_method).')' : '' ?>
                        </div>
                        <button id="exportChartBtn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>

                    <h5 class="mt-4 mb-3"><i class="fas fa-star me-2"></i> Top Selling Products</h5>
                    <ul class="top-products-list">
                        <?php if (!empty($top_products)): ?>
                            <?php foreach ($top_products as $index => $product): ?>
                                <li class="top-product-item">
                                    <div class="product-info">
                                        <div class="product-rank"><?= $index + 1 ?></div>
                                        <div>
                                            <div><?= htmlspecialchars($product['ProductName']) ?></div>
                                            <small class="text-muted"><?= $product['total_sold'] ?> sold</small>
                                        </div>
                                    </div>
                                    <div class="product-sales">₱<?= number_format($product['total_revenue'], 2) ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p class="mb-0">No product sales data available</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
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

        // Initialize date range picker
        $('#date-range').daterangepicker({
            opens: 'right',
            startDate: moment('<?= $start_date ?>'),
            endDate: moment('<?= $end_date ?>'),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')]
            },
            locale: {
                format: 'MMM D, YYYY',
                applyLabel: 'Apply',
                cancelLabel: 'Cancel',
                fromLabel: 'From',
                toLabel: 'To',
                customRangeLabel: 'Custom Range',
                weekLabel: 'W',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                firstDay: 1
            }
        }, function(start, end, label) {
            $('#start-date').val(start.format('YYYY-MM-DD'));
            $('#end-date').val(end.format('YYYY-MM-DD'));
            
            // Update quick select dropdown
            if (label) {
                $('#quick-select').val('');
            }
        });

        // Quick select handler
        $('#quick-select').change(function() {
            const value = $(this).val();
            let startDate, endDate;
            
            switch(value) {
                case 'today':
                    startDate = endDate = moment();
                    break;
                case 'yesterday':
                    startDate = endDate = moment().subtract(1, 'days');
                    break;
                case 'last7':
                    startDate = moment().subtract(6, 'days');
                    endDate = moment();
                    break;
                case 'last30':
                    startDate = moment().subtract(29, 'days');
                    endDate = moment();
                    break;
                case 'thisMonth':
                    startDate = moment().startOf('month');
                    endDate = moment().endOf('month');
                    break;
                case 'lastMonth':
                    startDate = moment().subtract(1, 'month').startOf('month');
                    endDate = moment().subtract(1, 'month').endOf('month');
                    break;
                case 'thisYear':
                    startDate = moment().startOf('year');
                    endDate = moment().endOf('year');
                    break;
                default:
                    return;
            }
            
            $('#date-range').data('daterangepicker').setStartDate(startDate);
            $('#date-range').data('daterangepicker').setEndDate(endDate);
            $('#start-date').val(startDate.format('YYYY-MM-DD'));
            $('#end-date').val(endDate.format('YYYY-MM-DD'));
        });

        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    {
                        label: 'Sales Amount',
                        data: <?= json_encode(array_column($sales_data, 'sales')) ?>,
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Transactions',
                        data: <?= json_encode(array_column($sales_data, 'transactions')) ?>,
                        backgroundColor: 'rgba(76, 201, 240, 0.7)',
                        borderColor: 'rgba(76, 201, 240, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y1',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.datasetIndex === 0) {
                                    label += '₱' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                        align: 'end'
                    },
                    title: {
                        display: true,
                        text: 'Sales Trend - <?= ucfirst($report_type) ?> View' + 
                              '<?= $payment_method !== 'all' ? ' ('.ucfirst($payment_method).')' : '' ?>',
                        font: {
                            size: 14
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Sales Amount (₱)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Transactions'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Export button
        document.getElementById('exportChartBtn').addEventListener('click', function() {
            const link = document.createElement('a');
            link.download = 'sales-report-<?= $report_type ?>-<?= date('Y-m-d') ?>.png';
            link.href = salesChart.toBase64Image();
            link.click();
        });
        
        // Filter recent transactions by payment method
        window.filterRecentTransactions = function(method) {
            const items = document.querySelectorAll('.activity-item');
            
            items.forEach(item => {
                if (method === 'all') {
                    item.style.display = 'flex';
                } else {
                    if (item.dataset.paymentMethod === method) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
            
            // Update the active button state
            const header = document.querySelector('.recent-transactions-header');
            header.querySelectorAll('button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        };
        
        // Update report type
        window.updateReportType = function(type) {
            $('select[name="report_type"]').val(type);
            $('form').submit();
        };
    });
    </script>
</body>
</html>