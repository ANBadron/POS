<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'db.php';

// Default date ranges
$dateRange = $_GET['range'] ?? 'today';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

// Calculate dates based on range
switch ($dateRange) {
    case 'yesterday':
        $startDate = date('Y-m-d', strtotime('yesterday'));
        $endDate = date('Y-m-d', strtotime('yesterday'));
        $compareStart = date('Y-m-d', strtotime('2 days ago'));
        $compareEnd = date('Y-m-d', strtotime('2 days ago'));
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d');
        $compareStart = date('Y-m-d', strtotime('monday last week'));
        $compareEnd = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
        $compareStart = date('Y-m-01', strtotime('last month'));
        $compareEnd = date('Y-m-t', strtotime('last month'));
        break;
    case 'custom':
        $startDate = $customStart;
        $endDate = $customEnd;
        // For comparison, use the same duration before the custom range
        $days = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
        $compareStart = date('Y-m-d', strtotime("$startDate - $days days"));
        $compareEnd = date('Y-m-d', strtotime("$endDate - $days days"));
        break;
    default: // today
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $compareStart = date('Y-m-d', strtotime('yesterday'));
        $compareEnd = date('Y-m-d', strtotime('yesterday'));
}

// Enhanced dashboard statistics with date ranges
try {
    // Current period sales
    $current_sales = $conn->query("
        SELECT COALESCE(SUM(TotalAmount), 0) AS total_sales 
        FROM Transactions 
        WHERE DATE(TransactionDate) BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn();
    
    // Comparison period sales
    $compare_sales = $conn->query("
        SELECT COALESCE(SUM(TotalAmount), 0) AS total_sales 
        FROM Transactions 
        WHERE DATE(TransactionDate) BETWEEN '$compareStart' AND '$compareEnd'
    ")->fetchColumn();
    
    // Sales change percentage
    $sales_change = 0;
    if ($compare_sales > 0) {
        $sales_change = (($current_sales - $compare_sales) / $compare_sales) * 100;
    }
    
    // Total products (low stock)
    $low_stock_products = $conn->query("
        SELECT COUNT(*) AS total_products 
        FROM Products 
        WHERE StockQuantity < 10
    ")->fetchColumn();
    
    // Out of stock products
    $out_of_stock = $conn->query("
        SELECT COUNT(*) AS total_products 
        FROM Products 
        WHERE StockQuantity <= 0
    ")->fetchColumn();
    
    // Total customers
    $total_customers = $conn->query("
        SELECT COUNT(*) AS total_customers 
        FROM Customers
    ")->fetchColumn();
    
    // New customers in current date range
    $new_customers = $conn->query("
        SELECT COUNT(*) AS total_customers 
        FROM Customers
        WHERE DATE(CreatedAt) BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn();
    
    // Recent transactions (enhanced query to match reports.php)
    $recent_transactions = $conn->query("
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
        ORDER BY t.TransactionDate DESC
        LIMIT 5
    ")->fetchAll();
    
    // Top selling products in current date range
    $top_products = $conn->query("
        SELECT 
            p.ProductName,
            SUM(ti.Quantity) AS total_sold,
            SUM(ti.Quantity * ti.Price) AS total_revenue
        FROM TransactionItems ti
        JOIN Products p ON ti.ProductID = p.ProductID
        JOIN Transactions tr ON ti.TransactionID = tr.TransactionID
        WHERE DATE(tr.TransactionDate) BETWEEN '$startDate' AND '$endDate'
        GROUP BY p.ProductID
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("Error loading dashboard data. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <!-- Date range picker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
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
        
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 120px);
        }

        .stats-panel, .chart-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .stat-card {
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .stat-card .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* Updated Recent Transactions Styles */
        .recent-transactions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .activity-list {
            flex-grow: 1;
            overflow-y: auto;
            margin-top: 15px;
            padding-right: 5px;
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

        .top-products-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .top-product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .top-product-item:last-child {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
        }

        .product-rank {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: rgba(var(--primary-color), 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .product-sales {
            font-weight: 600;
        }

        /* Date range selector styles */
        .date-range-selector {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .date-range-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .custom-range-inputs {
            display: none;
            margin-top: 10px;
        }
        .custom-range-inputs.active {
            display: flex;
            gap: 10px;
        }
        .custom-range-inputs input {
            flex: 1;
        }
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .stats-period {
            font-size: 0.9rem;
            color: #6c757d;
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
            .dashboard-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .stats-panel, .chart-panel {
                height: auto;
                min-height: 400px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h1>
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

            <!-- Date Range Selector -->
            <div class="date-range-selector">
                <h5><i class="far fa-calendar-alt me-2"></i> Date Range</h5>
                <div class="date-range-buttons mb-2">
                    <button class="btn btn-sm <?= $dateRange === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>" data-range="today">Today</button>
                    <button class="btn btn-sm <?= $dateRange === 'yesterday' ? 'btn-primary' : 'btn-outline-primary' ?>" data-range="yesterday">Yesterday</button>
                    <button class="btn btn-sm <?= $dateRange === 'week' ? 'btn-primary' : 'btn-outline-primary' ?>" data-range="week">This Week</button>
                    <button class="btn btn-sm <?= $dateRange === 'month' ? 'btn-primary' : 'btn-outline-primary' ?>" data-range="month">This Month</button>
                    <button class="btn btn-sm <?= $dateRange === 'custom' ? 'btn-primary' : 'btn-outline-primary' ?>" id="customRangeBtn">Custom Range</button>
                </div>
                <div class="custom-range-inputs <?= $dateRange === 'custom' ? 'active' : '' ?>">
                    <input type="date" class="form-control form-control-sm" id="startDate" value="<?= htmlspecialchars($customStart) ?>">
                    <span class="align-self-center">to</span>
                    <input type="date" class="form-control form-control-sm" id="endDate" value="<?= htmlspecialchars($customEnd) ?>">
                    <button class="btn btn-sm btn-primary" id="applyCustomRange">Apply</button>
                </div>
                <div class="stats-period">
                    <?php if ($dateRange === 'custom'): ?>
                        Showing data from <?= htmlspecialchars(date('M j, Y', strtotime($startDate))) ?> to <?= htmlspecialchars(date('M j, Y', strtotime($endDate))) ?>
                        <br>Compared to <?= htmlspecialchars(date('M j, Y', strtotime($compareStart))) ?> to <?= htmlspecialchars(date('M j, Y', strtotime($compareEnd))) ?>
                    <?php else: ?>
                        Showing <?= ucfirst($dateRange) ?> data (<?= htmlspecialchars(date('M j, Y', strtotime($startDate))) ?><?= $startDate !== $endDate ? ' to ' . htmlspecialchars(date('M j, Y', strtotime($endDate))) : '' ?>)
                        <br>Compared to previous <?= $dateRange === 'today' ? 'day' : ($dateRange === 'week' ? 'week' : 'month') ?> (<?= htmlspecialchars(date('M j, Y', strtotime($compareStart))) ?><?= $compareStart !== $compareEnd ? ' to ' . htmlspecialchars(date('M j, Y', strtotime($compareEnd))) : '' ?>)
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-container">
                <!-- Left Panel - Stats and Activity -->
                <div class="stats-panel">
                    <div class="stats-header">
                        <h5><i class="fas fa-chart-pie me-2"></i> Key Metrics</h5>
                        <span class="badge bg-primary">
                            <?= ucfirst($dateRange) ?>
                            <?php if ($dateRange === 'custom'): ?>
                                (<?= htmlspecialchars(date('M j', strtotime($startDate))) ?>-<?= htmlspecialchars(date('j', strtotime($endDate))) ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="stats-grid">
                        <!-- Sales Card -->
                        <div class="stat-card">
                            <div class="stat-label">Current Period Sales</div>
                            <div class="stat-value">₱<?= number_format($current_sales, 2) ?></div>
                            <div class="stat-change <?= $sales_change >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-<?= $sales_change >= 0 ? 'arrow-up' : 'arrow-down' ?> me-1"></i>
                                <?= number_format(abs($sales_change), 2) ?>% vs previous
                            </div>
                        </div>
                        
                        <!-- Products Card -->
                        <div class="stat-card">
                            <div class="stat-label">Inventory Status</div>
                            <div class="stat-value"><?= $low_stock_products ?> low stock</div>
                            <div class="stat-change <?= $out_of_stock > 0 ? 'negative' : 'positive' ?>">
                                <?= $out_of_stock ?> out of stock
                            </div>
                        </div>
                        
                        <!-- Customers Card -->
                        <div class="stat-card">
                            <div class="stat-label">Customers</div>
                            <div class="stat-value"><?= number_format($total_customers) ?></div>
                            <div class="stat-change positive">
                                +<?= $new_customers ?> in period
                            </div>
                        </div>
                        
                        <!-- Transactions Card -->
                        <div class="stat-card">
                            <div class="stat-label">Transactions</div>
                            <div class="stat-value"><?= count($recent_transactions) ?></div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up me-1"></i>
                                Recent
                            </div>
                        </div>
                    </div>
                    
                    <!-- Updated Recent Transactions Section -->
                    <div class="recent-transactions-header mt-4">
                        <h5><i class="fas fa-history me-2"></i>  Transactions</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" onclick="filterRecentTransactions('all')">All</button>
                            <button type="button" class="btn btn-outline-primary" onclick="filterRecentTransactions('cash')">Cash</button>
                            <button type="button" class="btn btn-outline-primary" onclick="filterRecentTransactions('credit')">Credit</button>
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-chart-line me-2"></i> Sales Analytics</h5>
                        <div class="btn-group btn-group-sm" role="group" id="reportPeriod">
                            <button type="button" class="btn btn-outline-primary active" data-period="daily">Daily</button>
                            <button type="button" class="btn btn-outline-primary" data-period="weekly">Weekly</button>
                            <button type="button" class="btn btn-outline-primary" data-period="monthly">Monthly</button>
                        </div>
                    </div>
                    
                    <div style="flex-grow: 1; position: relative;">
                        <canvas id="salesChart" height="250"></canvas>
                        <div id="chartLoading" class="position-absolute top-50 start-50 translate-middle text-center" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading data...</p>
                        </div>
                    </div>
                    
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div id="chartSummary" class="text-muted small">
                            <!-- Summary text will be inserted here by JavaScript -->
                        </div>
                        <button id="exportChartBtn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>

                    <h5 class="mt-4"><i class="fas fa-star me-2"></i> Top Products</h5>
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
                            <li class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle me-2"></i> No product data
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

        let salesChart;
        const ctx = document.getElementById('salesChart').getContext('2d');
        const chartLoading = document.getElementById('chartLoading');
        const chartSummary = document.getElementById('chartSummary');
        const exportBtn = document.getElementById('exportChartBtn');
        
        // Date range selector functionality
        document.querySelectorAll('.date-range-buttons button[data-range]').forEach(btn => {
            btn.addEventListener('click', function() {
                const range = this.dataset.range;
                updateUrl({ range: range, start: '', end: '' });
            });
        });
        
        // Custom range button
        document.getElementById('customRangeBtn').addEventListener('click', function() {
            document.querySelector('.custom-range-inputs').classList.add('active');
        });
        
        // Apply custom range
        document.getElementById('applyCustomRange').addEventListener('click', function() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            
            if (start && end) {
                updateUrl({ range: 'custom', start: start, end: end });
            } else {
                alert('Please select both start and end dates');
            }
        });
        
        // Helper function to update URL with new parameters
        function updateUrl(params) {
            const url = new URL(window.location.href);
            
            // Update or add parameters
            for (const key in params) {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                } else {
                    url.searchParams.delete(key);
                }
            }
            
            // Reload the page with new parameters
            window.location.href = url.toString();
        }
        
        // Initialize date inputs with current values
        if (document.getElementById('startDate').value === '') {
            document.getElementById('startDate').valueAsDate = new Date();
        }
        if (document.getElementById('endDate').value === '') {
            document.getElementById('endDate').valueAsDate = new Date();
        }
        
        // Initialize with daily data
        loadChartData('daily');
        
        // Period selector event listeners
        document.querySelectorAll('#reportPeriod button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#reportPeriod button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const period = this.dataset.period;
                loadChartData(period);
            });
        });
        
        // Export button
        exportBtn.addEventListener('click', function() {
            if(salesChart) {
                const link = document.createElement('a');
                link.download = 'sales-report.png';
                link.href = salesChart.toBase64Image();
                link.click();
            }
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
        
        // Load chart data based on period
        async function loadChartData(period) {
            try {
                chartLoading.style.display = 'block';
                
                // Fetch data from server
                const response = await fetch(`get_sales_data.php?period=${period}`);
                const data = await response.json();
                
                // Destroy existing chart if it exists
                if(salesChart) {
                    salesChart.destroy();
                }
                
                // Create new chart
                salesChart = new Chart(ctx, getChartConfig(data, period));
                
                // Update summary
                updateChartSummary(data, period);
                
            } catch (error) {
                console.error('Error loading chart data:', error);
                alert('Error loading chart data. Please try again.');
            } finally {
                chartLoading.style.display = 'none';
            }
        }
        
        // Chart configuration
        function getChartConfig(data, period) {
            const isDaily = period === 'daily';
            const isWeekly = period === 'weekly';
            
            return {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Sales Amount',
                            data: data.sales,
                            backgroundColor: 'rgba(67, 97, 238, 0.7)',
                            borderColor: 'rgba(67, 97, 238, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Transactions',
                            data: data.transactions,
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
                                },
                                footer: function(tooltipItems) {
                                    if (isDaily && tooltipItems[0] && data.daily_averages) {
                                        const dataIndex = tooltipItems[0].dataIndex;
                                        return `Avg: ₱${data.daily_averages[dataIndex].toFixed(2)}`;
                                    }
                                    return '';
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            align: 'end'
                        },
                        title: {
                            display: true,
                            text: getChartTitle(period),
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
            };
        }
        
        function getChartTitle(period) {
            const now = new Date();
            switch(period) {
                case 'daily':
                    return `Daily Sales - ${now.toLocaleDateString('default', { month: 'long', year: 'numeric' })}`;
                case 'weekly':
                    return `Weekly Sales - ${now.toLocaleDateString('default', { year: 'numeric' })}`;
                case 'monthly':
                    return `Monthly Sales - ${now.toLocaleDateString('default', { year: 'numeric' })}`;
                default:
                    return 'Sales Report';
            }
        }
        
        function updateChartSummary(data, period) {
            const totalSales = data.sales.reduce((a, b) => a + b, 0);
            const totalTransactions = data.transactions.reduce((a, b) => a + b, 0);
            const avgSale = totalSales / (data.sales.length || 1);
            
            let summaryText = '';
            switch(period) {
                case 'daily':
                    summaryText = `Showing ${data.labels.length} days with ${totalTransactions} transactions totaling ₱${totalSales.toLocaleString()}`;
                    break;
                case 'weekly':
                    summaryText = `Showing ${data.labels.length} weeks with ${totalTransactions} transactions totaling ₱${totalSales.toLocaleString()}`;
                    break;
                case 'monthly':
                    summaryText = `Showing ${data.labels.length} months with ${totalTransactions} transactions totaling ₱${totalSales.toLocaleString()}`;
                    break;
            }
            
            summaryText += ` | Avg: ₱${avgSale.toFixed(2)} per ${period === 'daily' ? 'day' : period === 'weekly' ? 'week' : 'month'}`;
            
            if (data.change_percentage !== undefined) {
                const changeClass = data.change_percentage >= 0 ? 'text-success' : 'text-danger';
                const changeIcon = data.change_percentage >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                summaryText += ` | <span class="${changeClass}"><i class="fas ${changeIcon}"></i> ${Math.abs(data.change_percentage)}%</span> from previous period`;
            }
            
            chartSummary.innerHTML = summaryText;
        }
    });
    </script>
</body>
</html>