<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar bg-dark text-white d-flex flex-column vh-100 shadow-sm">
    <!-- Sidebar Header -->
    <header class="sidebar-header p-3 border-bottom border-secondary">
        <h1 class="h5 m-0 d-flex align-items-center gap-2">
            <i class="fas fa-store"></i>
            <span>POS System</span>
        </h1>
    </header>

    <!-- Sidebar Menu -->
    <nav class="sidebar-menu flex-grow-1 overflow-auto" aria-label="Main Navigation">
        <ul class="nav flex-column p-3">
            <li class="nav-item mb-2">
                <a href="dashboard.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'dashboard.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="products.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'products.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-box"></i> <span>Products</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="customers.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'customers.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-users"></i> <span>Customers</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="transactions.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'transactions.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-receipt"></i> <span>Transactions</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="cashier.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'cashier.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-cash-register"></i> <span>Cashier</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="reports.php" class="nav-link d-flex align-items-center gap-2 <?= $currentPage === 'reports.php' ? 'active text-white bg-secondary rounded' : 'text-white' ?>">
                    <i class="fas fa-chart-line"></i> <span>Reports</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <footer class="sidebar-footer p-3 border-top border-secondary">
        <a href="logout.php" class="nav-link d-flex align-items-center gap-2 text-white">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </footer>
</aside>
