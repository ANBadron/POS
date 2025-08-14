<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Validate period parameter
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
    $period = 'daily';
}

try {
    $response = [
        'labels' => [],
        'sales' => [],
        'transactions' => [],
        'daily_averages' => [],
        'change_percentage' => null
    ];

    // Get data based on period
    switch ($period) {
        case 'daily':
            // Last 30 days
            $stmt = $conn->prepare("
                SELECT 
                    DATE(TransactionDate) AS day,
                    SUM(TotalAmount) AS daily_sales,
                    COUNT(*) AS transaction_count,
                    AVG(TotalAmount) AS daily_avg
                FROM Transactions
                WHERE TransactionDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(TransactionDate)
                ORDER BY day ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();

            foreach ($data as $row) {
                $response['labels'][] = date('M j', strtotime($row['day']));
                $response['sales'][] = (float)$row['daily_sales'];
                $response['transactions'][] = (int)$row['transaction_count'];
                $response['daily_averages'][] = (float)$row['daily_avg'];
            }
            break;

        case 'weekly':
            // Last 12 weeks
            $stmt = $conn->prepare("
                SELECT 
                    YEARWEEK(TransactionDate, 1) AS week,
                    MIN(DATE(TransactionDate)) AS week_start,
                    SUM(TotalAmount) AS weekly_sales,
                    COUNT(*) AS transaction_count
                FROM Transactions
                WHERE TransactionDate >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                GROUP BY YEARWEEK(TransactionDate, 1)
                ORDER BY week ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();

            foreach ($data as $row) {
                $response['labels'][] = date('M j', strtotime($row['week_start']));
                $response['sales'][] = (float)$row['weekly_sales'];
                $response['transactions'][] = (int)$row['transaction_count'];
            }
            break;

        case 'monthly':
            // Last 12 months
            $stmt = $conn->prepare("
                SELECT 
                    DATE_FORMAT(TransactionDate, '%Y-%m') AS month,
                    SUM(TotalAmount) AS monthly_sales,
                    COUNT(*) AS transaction_count
                FROM Transactions
                WHERE TransactionDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(TransactionDate, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();

            foreach ($data as $row) {
                $response['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
                $response['sales'][] = (float)$row['monthly_sales'];
                $response['transactions'][] = (int)$row['transaction_count'];
            }
            break;
    }

    // Calculate percentage change from previous period
    if (count($response['sales']) > 1) {
        $current = end($response['sales']);
        $previous = $response['sales'][count($response['sales']) - 2];
        $response['change_percentage'] = $previous != 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}