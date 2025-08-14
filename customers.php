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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: customers.php');
        exit();
    }

    // Add new customer
    if (isset($_POST['add_customer'])) {
        $customerName = trim($_POST['customer_name']);
        $email        = trim($_POST['email']);
        $phone        = trim($_POST['phone']);
        $address      = trim($_POST['address']);

        if (empty($customerName)) {
            $_SESSION['error'] = "Customer name is required!";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email format!";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO Customers (CustomerName, Email, Phone, Address) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$customerName, $email, $phone, $address]);

                $_SESSION['success'] = $stmt->rowCount() > 0
                    ? "Customer added successfully!"
                    : "Failed to add customer. Please try again.";
            } catch (PDOException $e) {
                error_log("Error adding customer: " . $e->getMessage());
                $_SESSION['error'] = strpos($e->getMessage(), 'Duplicate entry') !== false
                    ? "Customer with this email or phone already exists!"
                    : "Error adding customer. Please try again.";
            }
        }
        header('Location: customers.php');
        exit();
    }

    // Delete customer
    if (isset($_POST['delete_customer'])) {
        $customerId = (int)$_POST['customer_id'];
        
        try {
            // First delete related transactions to maintain referential integrity
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("DELETE FROM Transactions WHERE CustomerID = ?");
            $stmt->execute([$customerId]);
            
            $stmt = $conn->prepare("DELETE FROM Customers WHERE CustomerID = ?");
            $stmt->execute([$customerId]);
            
            $conn->commit();
            
            $_SESSION['success'] = "Customer deleted successfully!";
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error deleting customer: " . $e->getMessage());
            $_SESSION['error'] = "Error deleting customer. Please try again.";
        }
        header('Location: customers.php');
        exit();
    }

    // Clear all credit owed
    if (isset($_POST['clear_all_credit'])) {
        try {
            $conn->beginTransaction();
            
            // Get total credit owed before clearing for the success message
            $totalCredit = $conn->query("
                SELECT COALESCE(SUM(TotalAmount), 0) AS total 
                FROM Transactions 
                WHERE PaymentMethod = 'credit'
            ")->fetchColumn();
            
            // Update all credit transactions to paid
            $stmt = $conn->prepare("
                UPDATE Transactions 
                SET PaymentMethod = 'cash', 
                    PaymentStatus = 'paid',
                    DatePaid = NOW()
                WHERE PaymentMethod = 'credit'
            ");
            $stmt->execute();
            
            // Log this action
            $stmt = $conn->prepare("
                INSERT INTO AdminLogs (UserID, Action, Details) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                'CLEAR_ALL_CREDIT',
                "Cleared ₱" . number_format($totalCredit, 2) . " in credit balances"
            ]);
            
            $conn->commit();
            
            $_SESSION['success'] = sprintf(
                "All credit balances (₱%s) have been cleared and marked as paid!",
                number_format($totalCredit, 2)
            );
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error clearing credit: " . $e->getMessage());
            $_SESSION['error'] = "Error clearing credit balances. Please try again.";
        }
        header('Location: customers.php');
        exit();
    }
}

// Check if we're filtering for credit customers
$creditOnly = isset($_GET['credit_only']) && $_GET['credit_only'] == '1';

// Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Build the customer query
$sql = "
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
";

// Add HAVING clause if filtering for credit customers
if ($creditOnly) {
    $sql .= " HAVING TotalCreditOwed > 0";
}

$sql .= " ORDER BY c.CustomerName";

try {
    $customers = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
    die("Error fetching customers. Please try again later.");
}

// Calculate total credit owed
$totalCreditOwed = array_sum(array_column($customers, 'TotalCreditOwed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - POS System</title>
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
        
        .customers-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .customers-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }
        
        .customer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .customer-table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .customer-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .customer-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .customer-name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .customer-contact {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 5px;
        }
        
        .empty-customers {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .empty-customers i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }
        
        .credit-owed {
            font-weight: 600;
        }
        
        .credit-owed.positive {
            color: #d9534f;
        }
        
        .credit-owed.zero {
            color: #5cb85c;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .main-content, .main-content * {
                visibility: visible;
            }
            .main-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }
            .customers-panel {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .customer-header, .action-btn, .modal, .sidebar, .alert, .summary-card {
                display: none !important;
            }
            .customer-table {
                width: 100%;
                border-collapse: collapse;
            }
            .customer-table th, .customer-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .customer-table th {
                background-color: #f2f2f2 !important;
            }
            .customer-avatar {
                display: none;
            }
            h1 {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }
            @page {
                size: auto;
                margin: 5mm;
            }
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            .print-header p {
                margin: 5px 0;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .customer-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .customer-table th,
            .customer-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-users me-2"></i> Customers</h1>
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

            <div class="row">
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="card-title"><i class="fas fa-users me-2"></i> 
                            <?= $creditOnly ? 'Customers with Credit' : 'Total Customers' ?>
                        </h5>
                        <div class="summary-value text-primary"><?= count($customers) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i> Total Credit Owed</h5>
                        <div class="summary-value text-danger">₱<?= number_format($totalCreditOwed, 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="card-title"><i class="fas fa-calendar-alt me-2"></i> Report Date</h5>
                        <div class="summary-value text-secondary"><?= date('M d, Y') ?></div>
                    </div>
                </div>
            </div>

            <div class="customers-container">
                <div class="customers-panel">
                    <div class="customer-header">
                        <h5><i class="fas fa-user-friends me-2"></i> Customer List</h5>
                        <div class="d-flex">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search customers...">
                            </div>
                            <a href="?credit_only=<?= $creditOnly ? '0' : '1' ?>" 
                               class="btn <?= $creditOnly ? 'btn-danger' : 'btn-outline-danger' ?> ms-2"
                               title="<?= $creditOnly ? 'Show all customers' : 'Show only customers with credit' ?>">
                                <i class="fas fa-money-bill-wave me-1"></i>
                                <?= $creditOnly ? 'All Customers' : 'Credit Only' ?>
                            </a>
                            <button class="btn btn-outline-secondary ms-2" onclick="window.print()" title="Print">
                                <i class="fas fa-print"></i>
                            </button>
                            <a href="print_customers.php" class="btn btn-outline-secondary ms-2" target="_blank" title="Print Detailed">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <?php if ($totalCreditOwed > 0): ?>
                                <button class="btn btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#clearCreditModal"
                                        title="Clear all credit balances">
                                    <i class="fas fa-broom me-1"></i> Clear Credit
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                <i class="fas fa-plus me-1"></i> Add Customer
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="customer-table" id="customerTable">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact</th>
                                    <th>
                                        Credit Owed
                                        <?php if ($creditOnly): ?>
                                            <span class="badge bg-danger ms-1">Filtered</span>
                                        <?php endif; ?>
                                    </th>
                                    <th>Member Since</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($customers)): ?>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="customer-avatar">
                                                        <?= strtoupper(substr($customer['CustomerName'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="customer-name"><?= htmlspecialchars($customer['CustomerName']) ?></div>
                                                        <div class="customer-contact">
                                                            <?= htmlspecialchars($customer['Address']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="customer-contact">
                                                    <?php if ($customer['Email']): ?>
                                                        <div><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($customer['Email']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($customer['Phone']): ?>
                                                        <div><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($customer['Phone']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="credit-owed <?= $customer['TotalCreditOwed'] > 0 ? 'positive' : 'zero' ?>">
                                                    ₱<?= number_format($customer['TotalCreditOwed'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('M d, Y', strtotime($customer['CreatedAt'])) ?>
                                            </td>
                                            <td>
                                                <a href="customer_profile.php?id=<?= $customer['CustomerID'] ?>"
                                                   class="action-btn btn btn-sm btn-info" title="View Profile">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                                <a href="edit_customer.php?id=<?= $customer['CustomerID'] ?>"
                                                   class="action-btn btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="action-btn btn btn-sm btn-danger" 
                                                        title="Delete" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-customer-id="<?= $customer['CustomerID'] ?>"
                                                        data-customer-name="<?= htmlspecialchars($customer['CustomerName']) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-customers">
                                                <i class="fas fa-user-friends"></i>
                                                <p>No customers found</p>
                                                <?php if ($creditOnly): ?>
                                                    <p class="small">No customers with credit owed</p>
                                                <?php endif; ?>
                                                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                                    <i class="fas fa-plus me-1"></i> Add First Customer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">
                        <i class="fas fa-user-plus me-2"></i> Add New Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_customer" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="customer_id" id="deleteCustomerId">
                    <input type="hidden" name="delete_customer" value="1">
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong><span id="deleteCustomerName"></span></strong>?</p>
                        <p class="text-danger">This action cannot be undone and will also delete all related transactions!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clear Credit Confirmation Modal -->
    <div class="modal fade" id="clearCreditModal" tabindex="-1" aria-labelledby="clearCreditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="clearCreditModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i> Clear All Credit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-body">
                        <p>Are you sure you want to clear all credit balances?</p>
                        <p class="text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            This will mark all credit transactions as paid and cannot be undone!
                        </p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Total credit to be cleared: <strong>₱<?= number_format($totalCreditOwed, 2) ?></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="clear_all_credit" class="btn btn-warning">
                            <i class="fas fa-broom me-1"></i> Clear All Credit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Print Header (hidden on screen) -->
    <div class="print-header" style="display: none;">
        <h2>Customer List</h2>
        <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
        <p>Total Customers: <?= count($customers) ?></p>
        <p>Total Credit Owed: ₱<?= number_format($totalCreditOwed, 2) ?></p>
        <?php if ($creditOnly): ?>
            <p class="text-danger">Showing only customers with credit owed</p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update current time
            function updateTime() {
                const now = new Date();
                document.getElementById('current-time').textContent =
                    now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                    + ' • ' +
                    now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            }
            updateTime();
            setInterval(updateTime, 1000);
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#customerTable tbody tr');
                    
                    rows.forEach(row => {
                        const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
                        const customerContact = row.querySelector('.customer-contact').textContent.toLowerCase();
                        
                        if (customerName.includes(searchTerm) || customerContact.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Delete modal setup
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const customerId = button.getAttribute('data-customer-id');
                    const customerName = button.getAttribute('data-customer-name');
                    
                    document.getElementById('deleteCustomerId').value = customerId;
                    document.getElementById('deleteCustomerName').textContent = customerName;
                });
            }
            
            // Auto-focus search input
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>