<?php
// Ensure we have access to customers data
$customers = $customers ?? [];
$cart = $_SESSION['cart'] ?? [];
?>

<div class="cart-header">
    <h5><i class="fas fa-shopping-cart me-2"></i> Current Sale</h5>
    <span class="badge bg-primary" id="cart-count">
        <?= count($cart) ?> <?= count($cart) === 1 ? 'item' : 'items' ?>
    </span>
</div>

<div class="cart-items">
    <?php if (empty($cart)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-basket"></i>
            <p>Your cart is empty</p>
            <p class="small">Start by adding products</p>
        </div>
    <?php else: ?>
        <?php $cartTotal = 0; ?>
        <?php foreach ($cart as $index => $item): ?>
            <?php 
            $subtotal = $item['price'] * $item['quantity']; 
            $cartTotal += $subtotal;
            ?>
            <div class="cart-item" data-item-id="<?= $item['id'] ?>" data-item-index="<?= $index ?>">
                <div class="cart-item-info">
                    <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="cart-item-price">₱<?= number_format($item['price'], 2) ?></div>
                    <div class="cart-item-subtotal">₱<?= number_format($subtotal, 2) ?></div>
                </div>
                <div class="cart-item-qty">
                    <button type="button"
                            class="decrease-qty btn btn-sm btn-outline-secondary" 
                            title="Decrease quantity">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="mx-2"><?= $item['quantity'] ?></span>
                    <button type="button"
                            class="increase-qty btn btn-sm btn-outline-secondary" 
                            title="Increase quantity">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button"
                            class="remove-item btn btn-sm btn-danger ms-2" 
                            title="Remove item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($cart)): ?>
    <div class="cart-total text-end mb-3">
        <h5>Total: ₱<?= number_format($cartTotal, 2) ?></h5>
    </div>

    <form method="POST" id="transactionForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <!-- Customer Type Selection -->
        <div class="mb-3">
            <label class="form-label">Customer Type</label>
            <div class="btn-group w-100" role="group" id="customerTypeGroup">
                <input type="radio" class="btn-check" name="customer_type" id="walkin" value="walkin" checked>
                <label class="btn btn-outline-primary" for="walkin">Walk-in</label>
                
                <input type="radio" class="btn-check" name="customer_type" id="member" value="member">
                <label class="btn btn-outline-primary" for="member">Member</label>
            </div>
        </div>
        
        <!-- Customer Selection (hidden by default) -->
        <div class="mb-3" id="customerSelectContainer" style="display: none;">
            <label class="form-label">Select Member</label>
            <select name="customer_id" class="form-select" id="customerSelect" required>
                <option value="">-- Select Member --</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= $customer['CustomerID'] ?>">
                        <?= htmlspecialchars($customer['CustomerName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="mt-2" id="availableCreditContainer" style="display: none;">
                <strong>Available Credit: </strong>
                <span id="availableCredit">₱0.00</span>
            </div>
        </div>
        
        <!-- Payment Method -->
        <div class="mb-3" id="paymentMethodContainer">
            <label class="form-label">Payment Method</label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="payment_method" id="cash" value="cash" checked>
                <label class="btn btn-outline-success" for="cash">Cash</label>
                
                <input type="radio" class="btn-check" name="payment_method" id="credit" value="credit">
                <label class="btn btn-outline-success" for="credit">Credit</label>
            </div>
        </div>
        
        <!-- Cash Payment Fields -->
        <div class="mb-3" id="cashPaymentFields">
            <label class="form-label">Amount Received</label>
            <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" 
                       step="0.01" 
                       min="0" 
                       class="form-control" 
                       id="amountReceived" 
                       name="amount_received" 
                       placeholder="Enter amount received from customer"
                       value="0.00">
            </div>
            <div class="mt-2">
                <strong>Change Due: </strong>
                <span id="changeDue">₱0.00</span>
            </div>
        </div>
        
        <button type="submit" 
                name="process_transaction" 
                class="checkout-btn btn-primary w-100 py-2">
            <i class="fas fa-check-circle me-2"></i> Complete Sale
        </button>
    </form>
<?php endif; ?>

<style>
.cart-item {
    transition: all 0.3s ease;
    padding: 10px;
    border-radius: 5px;
}

.cart-item:hover {
    background-color: #f8f9fa;
}

.cart-item-subtotal {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 3px;
}

#amountReceived {
    font-size: 1.1rem;
    font-weight: 500;
    padding: 0.75rem;
}

#changeDue, #availableCredit {
    font-weight: bold;
    font-size: 1.1rem;
}

.empty-cart {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
}

.empty-cart i {
    font-size: 3.5rem;
    margin-bottom: 20px;
    color: #e9ecef;
}

.input-group-text {
    font-weight: 500;
}

#availableCreditContainer {
    padding: 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.checkout-btn {
    transition: all 0.2s;
}

.checkout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.checkout-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.cart-item-qty button {
    transition: all 0.2s;
}

.cart-item-qty button:hover {
    transform: scale(1.1);
}

.cart-item-qty button.remove-item:hover {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Customer type toggle
    const customerTypeGroup = document.getElementById('customerTypeGroup');
    const customerSelectContainer = document.getElementById('customerSelectContainer');
    const availableCreditContainer = document.getElementById('availableCreditContainer');
    const paymentMethodContainer = document.getElementById('paymentMethodContainer');
    const cashPaymentFields = document.getElementById('cashPaymentFields');

    function toggleMemberOptions() {
        const isMember = document.querySelector('input[name="customer_type"]:checked').value === 'member';
        customerSelectContainer.style.display = isMember ? 'block' : 'none';
        paymentMethodContainer.style.display = isMember ? 'block' : 'none';
        document.getElementById('customerSelect').required = isMember;
        
        if (!isMember) {
            // Reset to cash payment when switching to walk-in
            document.getElementById('cash').checked = true;
            handlePaymentMethodChange();
        }
    }

    customerTypeGroup?.addEventListener('change', toggleMemberOptions);
    
    // Payment method handling
    function handlePaymentMethodChange() {
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        cashPaymentFields.style.display = method === 'cash' ? 'block' : 'none';
        availableCreditContainer.style.display = method === 'credit' ? 'block' : 'none';
        
        if (method === 'cash') {
            calculateChange();
        } else if (method === 'credit') {
            updateCreditBalance();
        }
    }

    // Calculate change
    function calculateChange() {
        const total = parseFloat(document.querySelector('.cart-total h5').textContent.replace(/[^\d.]/g, ''));
        const received = parseFloat(document.getElementById('amountReceived').value) || 0;
        const change = received - total;
        const changeDueEl = document.getElementById('changeDue');
        
        if (changeDueEl) {
            changeDueEl.textContent = '₱' + Math.max(0, change).toFixed(2);
            changeDueEl.style.color = change >= 0 ? '#28a745' : '#dc3545';
        }
    }

    // Update credit balance
    function updateCreditBalance() {
        const customerSelect = document.getElementById('customerSelect');
        const customerId = customerSelect ? customerSelect.value : null;
        const availableCreditEl = document.getElementById('availableCredit');

        if (customerId && availableCreditEl) {
            fetch(`get_credit_balance.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        availableCreditEl.textContent = '₱' + parseFloat(data.balance).toFixed(2);
                    } else {
                        availableCreditEl.textContent = '₱0.00';
                    }
                })
                .catch(error => {
                    availableCreditEl.textContent = '₱0.00';
                });
        } else if (availableCreditEl) {
            availableCreditEl.textContent = '₱0.00';
        }
    }

    // Event listeners
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', handlePaymentMethodChange);
    });
    
    document.getElementById('amountReceived')?.addEventListener('input', calculateChange);
    document.getElementById('customerSelect')?.addEventListener('change', updateCreditBalance);

    // Initialize
    toggleMemberOptions();
    handlePaymentMethodChange();
});
</script>