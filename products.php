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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: products.php');
        exit();
    }

    if (isset($_POST['add_product'])) {
        // Add product logic
        $name = trim($_POST['product_name']);
        $price = (float)$_POST['price'];
        $category = trim($_POST['category']);
        $stock = (int)$_POST['stock'];
        $barcode = trim($_POST['barcode']);
        $description = trim($_POST['description']);

        // Validate inputs
        if (empty($name) || $price <= 0 || empty($category)) {
            $_SESSION['error'] = "Please fill all required fields with valid data";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO Products (ProductName, Price, Category, StockQuantity, Barcode, Description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $price, $category, $stock, $barcode, $description]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Product added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add product. Please try again.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error'] = "Barcode already exists for another product";
                } else {
                    error_log("Error adding product: " . $e->getMessage());
                    $_SESSION['error'] = "Error adding product. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['update_product'])) {
        // Update product logic
        $id = (int)$_POST['product_id'];
        $name = trim($_POST['product_name']);
        $price = (float)$_POST['price'];
        $category = trim($_POST['category']);
        $stock = (int)$_POST['stock'];
        $barcode = trim($_POST['barcode']);
        $description = trim($_POST['description']);

        // Validate inputs
        if ($id <= 0 || empty($name) || $price <= 0 || empty($category)) {
            $_SESSION['error'] = "Please fill all required fields with valid data";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE Products 
                    SET ProductName = ?, Price = ?, Category = ?, StockQuantity = ?, 
                        Barcode = ?, Description = ?, UpdatedAt = CURRENT_TIMESTAMP
                    WHERE ProductID = ?
                ");
                $stmt->execute([$name, $price, $category, $stock, $barcode, $description, $id]);

                $_SESSION['success'] = "Product updated successfully!";
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error'] = "Barcode already exists for another product";
                } else {
                    error_log("Error updating product: " . $e->getMessage());
                    $_SESSION['error'] = "Error updating product. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        // Delete product logic
        $id = (int)$_POST['product_id'];

        if ($id > 0) {
            try {
                // Check if product is in any transactions
                $stmt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM TransactionItems 
                    WHERE ProductID = ?
                ");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    $_SESSION['error'] = "Cannot delete product with transaction history";
                } else {
                    $stmt = $conn->prepare("DELETE FROM Products WHERE ProductID = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success'] = "Product deleted successfully!";
                }
            } catch (PDOException $e) {
                error_log("Error deleting product: " . $e->getMessage());
                $_SESSION['error'] = "Error deleting product. Please try again.";
            }
        }
    }

    header('Location: products.php');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all products
try {
    $products = $conn->query("
        SELECT ProductID, ProductName, Price, Category, StockQuantity, Barcode, Description
        FROM Products 
        ORDER BY ProductName
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    die("Error loading products. Please try again later.");
}

// Fetch distinct categories
try {
    $categories = $conn->query("
        SELECT DISTINCT Category 
        FROM Products 
        WHERE Category IS NOT NULL AND Category != ''
        ORDER BY Category
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        /* Match cashier.php styles */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        .products-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .products-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            text-align: center;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(67,97,238,0.2);
            border-color: var(--primary-color);
        }

        .product-card .product-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.6em;
            flex-grow: 1;
        }

        .product-card .product-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: auto;
        }

        .product-card .product-stock {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 3px;
        }

        .search-box {
            position: relative;
            margin-bottom: 15px;
        }

        .search-box input {
            padding-left: 40px;
            height: 40px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .category-tabs::-webkit-scrollbar {
            display: none;
        }

        .category-tab {
            padding: 6px 15px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            white-space: nowrap;
            border: 1px solid transparent;
            transition: all 0.2s;
            color: #495057;
        }

        .category-tab:hover {
            background-color: #dee2e6;
        }

        .category-tab.active {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            border-color: var(--primary-color);
        }

        .barcode-scanner-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1050;
            transition: background-color 0.2s;
        }
        .barcode-scanner-btn:hover {
            background-color: var(--secondary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .products-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .products-panel {
                height: auto;
                min-height: 400px;
            }
        }
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1><i class="fas fa-boxes me-2"></i> Products</h1>
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

            <div class="products-container">
                <div class="products-panel">
                    <div class="product-header">
                        <h5><i class="fas fa-boxes me-2"></i> Product Inventory</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-1"></i> Add Product
                        </button>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="product-search" class="form-control" placeholder="Search products by name or barcode...">
                    </div>

                    <div class="category-tabs">
                        <div class="category-tab active" data-category="all">All</div>
                        <?php foreach ($categories as $category): ?>
                            <div class="category-tab" data-category="<?= htmlspecialchars($category) ?>">
                                <?= htmlspecialchars($category) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="product-grid" id="product-grid">
                        <?php if (empty($products)): ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3"></i>
                                <p>No products found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <div class="product-card"
                                    data-id="<?= $product['ProductID'] ?>"
                                    data-name="<?= htmlspecialchars($product['ProductName']) ?>"
                                    data-price="<?= $product['Price'] ?>"
                                    data-stock="<?= $product['StockQuantity'] ?>"
                                    data-category="<?= htmlspecialchars($product['Category'] ?? 'Uncategorized') ?>"
                                    data-barcode="<?= htmlspecialchars($product['Barcode'] ?? '') ?>"
                                    data-description="<?= htmlspecialchars($product['Description'] ?? '') ?>"
                                    title="<?= htmlspecialchars($product['ProductName']) ?> (Stock: <?= $product['StockQuantity'] ?>)">
                                    <div class="product-name"><?= htmlspecialchars($product['ProductName']) ?></div>
                                    <div>
                                        <div class="product-price">₱<?= number_format($product['Price'], 2) ?></div>
                                        <div class="product-stock">Stock: <?= $product['StockQuantity'] ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Barcode Scanner Button -->
            <div class="barcode-scanner-btn" data-bs-toggle="modal" data-bs-target="#scannerModal" title="Scan Barcode">
                <i class="fas fa-barcode"></i>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <input type="text" class="form-control" id="category" name="category" list="categories" required>
                            <datalist id="categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label for="stock" class="form-label">Stock Quantity</label>
                            <input type="number" min="0" class="form-control" id="stock" name="stock" value="0">
                        </div>
                        <div class="mb-3">
                            <label for="barcode" class="form-label">Barcode</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcode" name="barcode">
                                <button class="btn btn-outline-secondary" type="button" id="scan-barcode-btn">
                                    <i class="fas fa-barcode"></i> Scan
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_product_name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="edit_product_name" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category *</label>
                            <input type="text" class="form-control" id="edit_category" name="category" list="categories" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_stock" class="form-label">Stock Quantity</label>
                            <input type="number" min="0" class="form-control" id="edit_stock" name="stock">
                        </div>
                        <div class="mb-3">
                            <label for="edit_barcode" class="form-label">Barcode</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="edit_barcode" name="barcode">
                                <button class="btn btn-outline-secondary" type="button" id="edit-scan-barcode-btn">
                                    <i class="fas fa-barcode"></i> Scan
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                        <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Barcode Scanner Modal -->
    <div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scannerModalLabel">Barcode Scanner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="interactive" style="width: 100%; height: 300px; background: #000;"></div>
                    <div id="scan-status" class="mt-2 alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
        const productGrid = document.getElementById('product-grid');
        const productSearchInput = document.getElementById('product-search');
        const categoryTabsContainer = document.querySelector('.category-tabs');
        const scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
        const interactiveDiv = document.getElementById('interactive');
        const scanStatusDiv = document.getElementById('scan-status');
        let currentBarcodeField = '';

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

        // Edit product modal
        const editButtons = document.querySelectorAll('.product-card');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
                
                document.getElementById('edit_product_id').value = this.dataset.id;
                document.getElementById('edit_product_name').value = this.dataset.name;
                document.getElementById('edit_price').value = this.dataset.price;
                document.getElementById('edit_category').value = this.dataset.category;
                document.getElementById('edit_stock').value = this.dataset.stock;
                document.getElementById('edit_barcode').value = this.dataset.barcode;
                document.getElementById('edit_description').value = this.dataset.description;
                
                modal.show();
            });
        });

        // Barcode scanner buttons
        document.getElementById('scan-barcode-btn').addEventListener('click', function() {
            const scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
            scannerModal.show();
            currentBarcodeField = 'barcode';
        });

        document.getElementById('edit-scan-barcode-btn').addEventListener('click', function() {
            const scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
            scannerModal.show();
            currentBarcodeField = 'edit_barcode';
        });

        // Barcode scanner functionality
        const scannerModalEl = document.getElementById('scannerModal');
        if (scannerModalEl) {
            scannerModalEl.addEventListener('shown.bs.modal', function() {
                initScanner();
            });
            
            scannerModalEl.addEventListener('hidden.bs.modal', function() {
                if (window.Quagga) {
                    Quagga.stop();
                }
            });
        }

        function initScanner() {
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.getElementById('interactive'),
                    constraints: {
                        width: 640,
                        height: 480,
                        facingMode: "environment"
                    },
                },
                decoder: {
                    readers: ["ean_reader", "ean_8_reader", "code_128_reader"]
                },
                locate: true
            }, function(err) {
                if (err) {
                    document.getElementById('scan-status').textContent = "Scanner error: " + err.message;
                    document.getElementById('scan-status').className = "alert alert-danger";
                    document.getElementById('scan-status').style.display = "block";
                    console.error(err);
                    return;
                }
                Quagga.start();
                document.getElementById('scan-status').textContent = "Ready to scan - point at barcode";
                document.getElementById('scan-status').className = "alert alert-info";
                document.getElementById('scan-status').style.display = "block";
            });

            Quagga.onDetected(function(result) {
                const code = result.codeResult.code;
                document.getElementById('scan-status').textContent = "Scanned: " + code;
                document.getElementById('scan-status').className = "alert alert-success";
                
                // Update the appropriate barcode field
                if (currentBarcodeField) {
                    document.getElementById(currentBarcodeField).value = code;
                }
                
                // Close scanner after short delay
                setTimeout(() => {
                    bootstrap.Modal.getInstance(scannerModalEl).hide();
                }, 1000);
            });
        }

        // Product filtering
        function filterProducts() {
            const searchTerm = productSearchInput.value.toLowerCase().trim();
            const activeCategory = categoryTabsContainer.querySelector('.category-tab.active')?.dataset.category || 'all';
            const productCards = productGrid.querySelectorAll('.product-card');
            let visibleCount = 0;

            productCards.forEach(card => {
                const name = card.dataset.name?.toLowerCase() || '';
                const barcode = card.dataset.barcode?.toLowerCase() || '';
                const category = card.dataset.category || 'Uncategorized';

                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || barcode.includes(searchTerm);
                const matchesCategory = activeCategory === 'all' || category === activeCategory;

                if (matchesSearch && matchesCategory) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show message if no products match
            const noMatchMsg = document.getElementById('no-product-match');
            if (visibleCount === 0 && productCards.length > 0) {
                if (!noMatchMsg) {
                    const msgDiv = document.createElement('div');
                    msgDiv.id = 'no-product-match';
                    msgDiv.className = 'text-muted p-3 w-100 text-center';
                    msgDiv.innerHTML = '<i class="fas fa-search fa-2x mb-2"></i><p>No products match your search</p>';
                    productGrid.appendChild(msgDiv);
                } else {
                    noMatchMsg.style.display = '';
                }
            } else if (noMatchMsg) {
                noMatchMsg.style.display = 'none';
            }
        }

        // Debounced filter function for search input
        const debouncedFilter = debounce(filterProducts, 300);

        // Product Search Input
        if (productSearchInput) {
            productSearchInput.addEventListener('input', debouncedFilter);
        }

        // Category Tabs
        if (categoryTabsContainer) {
            categoryTabsContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('category-tab')) {
                    // Remove active class from previously active tab
                    categoryTabsContainer.querySelector('.category-tab.active')?.classList.remove('active');
                    // Add active class to clicked tab
                    event.target.classList.add('active');
                    // Trigger filtering
                    filterProducts();
                }
            });
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    });
    </script>
</body>
</html>