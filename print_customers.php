<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'db.php';

try {
    $customers = $conn->query("
        SELECT 
            c.CustomerID,
            c.CustomerName,
            c.Email,
            c.Phone,
            c.Address,
            c.CreatedAt,
            COALESCE(SUM(t.TotalAmount), 0) AS TotalCreditOwed
        FROM Customers c
        LEFT JOIN Transactions t 
            ON c.CustomerID = t.CustomerID 
            AND t.PaymentMethod = 'credit'
        GROUP BY c.CustomerID
        ORDER BY c.CustomerName
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching customers: " . $e->getMessage());
}

// Calculate totals
$totalCustomers = count($customers);
$totalCreditOwed = array_sum(array_column($customers, 'TotalCreditOwed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer List - Print View</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .credit-positive {
            color: #d9534f;
        }
        .summary-box {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .summary-item {
            text-align: center;
            padding: 0 15px;
        }
        .summary-value {
            font-weight: bold;
            font-size: 1.1em;
        }
        @page {
            size: auto;
            margin: 5mm;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <h1>Customer List</h1>
    
    <div class="summary-box">
        <div class="summary-item">
            <div>Report Date</div>
            <div class="summary-value"><?= date('M d, Y H:i') ?></div>
        </div>
        <div class="summary-item">
            <div>Total Customers</div>
            <div class="summary-value"><?= $totalCustomers ?></div>
        </div>
        <div class="summary-item">
            <div>Total Credit Owed</div>
            <div class="summary-value text-danger">₱<?= number_format($totalCreditOwed, 2) ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Credit Owed</th>
                <th>Member Since</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $index => $customer): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($customer['CustomerName']) ?></td>
                <td>
                    <?php if ($customer['Email']): ?>
                        <?= htmlspecialchars($customer['Email']) ?><br>
                    <?php endif; ?>
                    <?php if ($customer['Phone']): ?>
                        <?= htmlspecialchars($customer['Phone']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($customer['Address']) ?></td>
                <td class="<?= $customer['TotalCreditOwed'] > 0 ? 'credit-positive' : '' ?>">
                    ₱<?= number_format($customer['TotalCreditOwed'], 2) ?>
                </td>
                <td><?= date('M d, Y', strtotime($customer['CreatedAt'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4">Total Credit Owed</td>
                <td>₱<?= number_format($totalCreditOwed, 2) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer;">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background: #f44336; color: white; border: none; cursor: pointer; margin-left: 10px;">
            <i class="fas fa-times me-1"></i> Close Window
        </button>
    </div>
    
    <script>
        window.onload = function() {
            // Auto-print if desired
            // window.print();
        };
    </script>
</body>
</html>