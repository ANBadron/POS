<?php
// Database configuration with enhanced security
$config = [
    'db' => [
        'host' => 'localhost',
        'name' => 'pos_system',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]
];

// Establish database connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $conn = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $config['options']);
    
    // Set timezone for consistent timestamps
    $conn->exec("SET time_zone = '+00:00'");
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header('Location: error.php?code=500');
    exit();
}

/**
 * Initialize all database tables with proper constraints
 */
function initializeDatabase($conn) {
    $tables = [
        // Users table with enhanced security fields
        "CREATE TABLE IF NOT EXISTS Users (
            UserID INT AUTO_INCREMENT PRIMARY KEY,
            Username VARCHAR(50) NOT NULL UNIQUE,
            Password VARCHAR(255) NOT NULL,
            Role ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
            LastLogin DATETIME NULL,
            FailedAttempts INT DEFAULT 0,
            LastAttemptIP VARCHAR(45),
            IsLocked BOOLEAN DEFAULT FALSE,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_role (Role),
            INDEX idx_user_status (IsLocked)
        ) ENGINE=InnoDB",
        
        // Products table with inventory tracking
        "CREATE TABLE IF NOT EXISTS Products (
            ProductID INT AUTO_INCREMENT PRIMARY KEY,
            ProductName VARCHAR(100) NOT NULL,
            Description TEXT,
            Price DECIMAL(10,2) NOT NULL CHECK (Price > 0),
            Cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            Category VARCHAR(50) NOT NULL,
            StockQuantity INT NOT NULL DEFAULT 0 CHECK (StockQuantity >= 0),
            Barcode VARCHAR(50) UNIQUE,
            Image VARCHAR(255),
            IsActive BOOLEAN DEFAULT TRUE,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product_name (ProductName),
            INDEX idx_category (Category),
            INDEX idx_stock (StockQuantity),
            FULLTEXT idx_search (ProductName, Description, Category)
        ) ENGINE=InnoDB",
        
        // Customers table with contact info
        "CREATE TABLE IF NOT EXISTS Customers (
            CustomerID INT AUTO_INCREMENT PRIMARY KEY,
            CustomerName VARCHAR(100) NOT NULL,
            Email VARCHAR(100),
            Phone VARCHAR(20),
            Address TEXT,
            CreditLimit DECIMAL(10,2) DEFAULT 0,
            Balance DECIMAL(10,2) DEFAULT 0,
            Notes TEXT,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer_name (CustomerName),
            INDEX idx_customer_contact (Email, Phone)
        ) ENGINE=InnoDB",
        
        // Transactions table with payment tracking
        "CREATE TABLE IF NOT EXISTS Transactions (
            TransactionID INT AUTO_INCREMENT PRIMARY KEY,
            CustomerID INT NULL,
            UserID INT NOT NULL,
            TotalAmount DECIMAL(10,2) NOT NULL CHECK (TotalAmount >= 0),
            AmountTendered DECIMAL(10,2) NOT NULL DEFAULT 0,
            ChangeDue DECIMAL(10,2) NOT NULL DEFAULT 0,
            PaymentMethod ENUM('cash','credit','card','mobile') DEFAULT 'cash',
            TransactionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Notes TEXT,
            Status ENUM('completed','voided','refunded') DEFAULT 'completed',
            FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE SET NULL,
            FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE RESTRICT,
            INDEX idx_transaction_date (TransactionDate),
            INDEX idx_transaction_status (Status),
            INDEX idx_transaction_customer (CustomerID)
        ) ENGINE=InnoDB",
        
        // Transaction items with cost tracking
        "CREATE TABLE IF NOT EXISTS TransactionItems (
            TransactionItemID INT AUTO_INCREMENT PRIMARY KEY,
            TransactionID INT NOT NULL,
            ProductID INT NOT NULL,
            Quantity INT NOT NULL CHECK (Quantity > 0),
            Price DECIMAL(10,2) NOT NULL CHECK (Price >= 0),
            Cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            Discount DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (TransactionID) REFERENCES Transactions(TransactionID) ON DELETE CASCADE,
            FOREIGN KEY (ProductID) REFERENCES Products(ProductID) ON DELETE RESTRICT,
            INDEX idx_transaction_product (TransactionID, ProductID)
        ) ENGINE=InnoDB",
        
        // Credit payments tracking system
        "CREATE TABLE IF NOT EXISTS CreditPayments (
            CreditPaymentID INT AUTO_INCREMENT PRIMARY KEY,
            CustomerID INT NOT NULL,
            TransactionID INT NOT NULL,
            Amount DECIMAL(10,2) NOT NULL CHECK (Amount > 0),
            PaymentDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            IsPaid BOOLEAN DEFAULT FALSE,
            CollectedBy INT,
            Notes TEXT,
            FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
            FOREIGN KEY (TransactionID) REFERENCES Transactions(TransactionID) ON DELETE CASCADE,
            FOREIGN KEY (CollectedBy) REFERENCES Users(UserID) ON DELETE SET NULL,
            INDEX idx_credit_payment_date (PaymentDate),
            INDEX idx_credit_payment_status (IsPaid),
            INDEX idx_credit_customer (CustomerID)
        ) ENGINE=InnoDB",
        
        // Inventory adjustments log
        "CREATE TABLE IF NOT EXISTS InventoryLog (
            LogID INT AUTO_INCREMENT PRIMARY KEY,
            ProductID INT NOT NULL,
            UserID INT NOT NULL,
            Adjustment INT NOT NULL,
            OldQuantity INT NOT NULL,
            NewQuantity INT NOT NULL,
            Reason ENUM('sale','restock','adjustment','damaged'),
            Notes TEXT,
            LogDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ProductID) REFERENCES Products(ProductID) ON DELETE CASCADE,
            FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE RESTRICT,
            INDEX idx_inventory_product (ProductID),
            INDEX idx_inventory_date (LogDate)
        ) ENGINE=InnoDB"
    ];

    // Execute table creation with error handling
    foreach ($tables as $query) {
        try {
            $conn->exec($query);
        } catch (PDOException $e) {
            error_log("Table creation error: " . $e->getMessage());
            // Continue even if some tables fail
        }
    }
}

// Initialize the database structure
initializeDatabase($conn);

/**
 * Create default admin user if none exists
 */
try {
    $adminCount = $conn->query("SELECT COUNT(*) FROM Users WHERE Role = 'admin'")->fetchColumn();
    
    if ($adminCount == 0) {
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $conn->prepare("
            INSERT INTO Users (Username, Password, Role) 
            VALUES (?, ?, 'admin')
        ");
        $stmt->execute([$username, $password]);
        
        error_log("Default admin user created with username: admin");
    }
} catch (PDOException $e) {
    error_log("Admin user creation error: " . $e->getMessage());
}

/**
 * Database maintenance functions
 */
function backupDatabase($conn, $backupPath) {
    try {
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $backupSQL = "";
        
        foreach ($tables as $table) {
            // Get table structure
            $createTable = $conn->query("SHOW CREATE TABLE `$table`")->fetch();
            $backupSQL .= "\n\n" . $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($conn) {
                    return $value === null ? 'NULL' : $conn->quote($value);
                }, array_values($row));
                
                $backupSQL .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        
        file_put_contents($backupPath, $backupSQL);
        return true;
    } catch (Exception $e) {
        error_log("Backup failed: " . $e->getMessage());
        return false;
    }
}

// Register shutdown function for cleanup
register_shutdown_function(function() use ($conn) {
    // Close any open transactions
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
});
