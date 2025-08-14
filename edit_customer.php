<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Validate customer ID
$customerID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$customerID) {
    $_SESSION['error'] = "Invalid customer ID";
    header('Location: customers.php');
    exit();
}

// Initialize variables
$customer = [];
$errors = [];

// Fetch customer details
try {
    $stmt = $conn->prepare("
        SELECT 
            CustomerID,
            CustomerName,
            Email,
            Phone,
            Address,
            CreatedAt
        FROM Customers 
        WHERE CustomerID = ?
        LIMIT 1
    ");
    $stmt->execute([$customerID]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception("Customer not found");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading customer data";
    header('Location: customers.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: customers.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    }

    // Get and validate form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Customer name is required";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // If no errors, update customer
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE Customers 
                SET 
                    CustomerName = ?,
                    Email = ?,
                    Phone = ?,
                    Address = ?
                WHERE CustomerID = ?
            ");
            $stmt->execute([$name, $email, $phone, $address, $customerID]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Customer updated successfully";
                header("Location: customer_profile.php?id=$customerID");
                exit();
            } else {
                $errors[] = "No changes were made";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors[] = "Error updating customer. Please try again.";
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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

        body {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            transition: all 0.3s;
            z-index: 1000;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            transition: all 0.3s;
        }

        .edit-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .edit-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 25px;
            margin-top: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(72, 149, 239, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-outline-secondary {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .badge {
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
        }

        .alert {
            border-radius: 8px;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                position: fixed;
            }
            .sidebar.active {
                width: 250px;
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* Mobile menu toggle button */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar bg-dark text-white" id="sidebar">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-user-edit me-2"></i> Edit Customer</h1>
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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= implode('<br>', $errors) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="edit-container">
                <div class="edit-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5><i class="fas fa-user-circle me-2"></i> Edit Customer Details</h5>
                        <span class="badge bg-primary">
                            <i class="fas fa-id-card me-1"></i> ID: <?= $customerID ?>
                        </span>
                    </div>

                    <form method="POST" action="edit_customer.php?id=<?= $customerID ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($customer['CustomerName']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($customer['Email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($customer['Phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?= htmlspecialchars($customer['Address'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="customer_profile.php?id=<?= $customerID ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Profile
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Time display function
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const dateString = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                const timeEl = document.getElementById('current-time');
                if(timeEl) {
                    timeEl.textContent = `${timeString} • ${dateString}`;
                }
            }

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    if (!sidebar.contains(event.target) && event.target !== menuToggle) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>
</body>
</html>