/**
 * Town Gas POS - Point of Sale JavaScript
 */

// Cart management
let cart = [];
let cartTotal = 0;
let cartProfit = 0;

// Initialize POS
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    updateCartDisplay();
    
    // Product search
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            filterProducts(this.value);
        });
    }
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    paymentMethods.forEach(function(radio) {
        radio.addEventListener('change', function() {
            updatePaymentFields(this.value);
        });
    });
    
    // Calculate change
    const amountReceived = document.getElementById('amount_received');
    if (amountReceived) {
        amountReceived.addEventListener('input', function() {
            calculateChange();
        });
    }
});

// Add product to cart
function addToCart(productId, productName, price, unit, purchasePrice) {
    const existingItem = cart.find(item => item.product_id === productId);
    
    if (existingItem) {
        existingItem.quantity++;
        existingItem.subtotal = existingItem.quantity * existingItem.price;
        existingItem.profit = existingItem.quantity * (existingItem.price - existingItem.purchase_price);
    } else {
        cart.push({
            product_id: productId,
            product_name: productName,
            price: parseFloat(price),
            purchase_price: parseFloat(purchasePrice),
            unit: unit,
            quantity: 1,
            subtotal: parseFloat(price),
            profit: parseFloat(price) - parseFloat(purchasePrice)
        });
    }
    
    updateCartDisplay();
    saveCart();
    showSuccess('Added to cart: ' + productName);
}

// Update quantity
function updateQuantity(productId, change) {
    const item = cart.find(item => item.product_id === productId);
    
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            removeFromCart(productId);
            return;
        }
        
        item.subtotal = item.quantity * item.price;
        item.profit = item.quantity * (item.price - item.purchase_price);
        
        updateCartDisplay();
        saveCart();
    }
}

// Remove from cart
function removeFromCart(productId) {
    cart = cart.filter(item => item.product_id !== productId);
    updateCartDisplay();
    saveCart();
}

// Clear cart
function clearCart() {
    if (confirm('Clear all items from cart?')) {
        cart = [];
        cartTotal = 0;
        cartProfit = 0;
        updateCartDisplay();
        saveCart();
        showSuccess('Cart cleared');
    }
}

// Update cart display
function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartTotalElement = document.getElementById('cartTotal');
    const cartProfitElement = document.getElementById('cartProfit');
    const cartCount = document.getElementById('cartCount');
    
    if (!cartItems) return;
    
    // Calculate totals
    cartTotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    cartProfit = cart.reduce((sum, item) => sum + item.profit, 0);
    
    // Update cart count
    if (cartCount) {
        cartCount.textContent = cart.length;
    }
    
    // Update cart items display
    if (cart.length === 0) {
        cartItems.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-cart-x display-1"></i><p class="mt-3">Cart is empty</p></div>';
    } else {
        cartItems.innerHTML = cart.map(item => `
            <div class="cart-item border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="flex-grow-1">
                        <strong>${item.product_name}</strong><br>
                        <small class="text-muted">₱${item.price.toFixed(2)} / ${item.unit}</small>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${item.product_id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.product_id}, -1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.product_id}, 1)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <strong>₱${item.subtotal.toFixed(2)}</strong>
                </div>
            </div>
        `).join('');
    }
    
    // Update totals
    if (cartTotalElement) {
        cartTotalElement.textContent = '₱' + cartTotal.toFixed(2);
    }
    if (cartProfitElement) {
        cartProfitElement.textContent = '₱' + cartProfit.toFixed(2);
    }
    
    // Enable/disable checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.disabled = cart.length === 0;
    }
}

// Filter products
function filterProducts(searchTerm) {
    const products = document.querySelectorAll('.product-card');
    const term = searchTerm.toLowerCase();
    
    products.forEach(function(product) {
        const name = product.dataset.name.toLowerCase();
        product.style.display = name.includes(term) ? '' : 'none';
    });
}

// Calculate change
function calculateChange() {
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('₱', ''));
    const received = parseFloat(document.getElementById('amount_received').value) || 0;
    const change = received - total;
    
    const changeDisplay = document.getElementById('changeAmount');
    if (changeDisplay) {
        changeDisplay.textContent = '₱' + change.toFixed(2);
        changeDisplay.className = change >= 0 ? 'text-success' : 'text-danger';
    }
}

// Update payment fields
function updatePaymentFields(method) {
    const cashFields = document.getElementById('cashFields');
    const gcashFields = document.getElementById('gcashFields');
    const cardFields = document.getElementById('cardFields');
    
    if (cashFields) cashFields.style.display = method === 'cash' ? 'block' : 'none';
    if (gcashFields) gcashFields.style.display = method === 'gcash' ? 'block' : 'none';
    if (cardFields) cardFields.style.display = method === 'card' ? 'block' : 'none';
}

// Process checkout
function processCheckout() {
    if (cart.length === 0) {
        showError('Cart is empty');
        return;
    }
    
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        showError('Please select payment method');
        return;
    }
    
    // Show checkout modal
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
    
    // Populate modal with cart details
    document.getElementById('modalCartItems').innerHTML = cart.map(item => `
        <tr>
            <td>${item.product_name}</td>
            <td>${item.quantity} ${item.unit}</td>
            <td>₱${item.price.toFixed(2)}</td>
            <td>₱${item.subtotal.toFixed(2)}</td>
        </tr>
    `).join('');
    
    document.getElementById('modalTotal').textContent = '₱' + cartTotal.toFixed(2);
}

// Complete sale
function completeSale() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const customerId = document.getElementById('customer_id')?.value || null;
    
    // Validate payment
    if (paymentMethod === 'cash') {
        const received = parseFloat(document.getElementById('amount_received').value) || 0;
        if (received < cartTotal) {
            showError('Amount received is less than total');
            return;
        }
    }
    
    // Prepare sale data
    const saleData = {
        customer_id: customerId,
        payment_method: paymentMethod,
        total_amount: cartTotal,
        total_profit: cartProfit,
        total_items: cart.reduce((sum, item) => sum + item.quantity, 0),
        items: cart
    };
    
    // Submit sale
    showLoading();
    
    fetch('process-sale.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(saleData)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showSuccess('Sale completed successfully!');
            
            // Print receipt
            if (confirm('Print receipt?')) {
                printReceipt(data.sale_id);
            }
            
            // Clear cart and close modal
            clearCart();
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
            
        } else {
            showError(data.message || 'Error processing sale');
        }
    })
    .catch(error => {
        hideLoading();
        showError('Error: ' + error.message);
    });
}

// Print receipt
function printReceipt(saleId) {
    window.open('print-receipt.php?id=' + saleId, '_blank');
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

// Load cart from localStorage
function loadCart() {
    const saved = localStorage.getItem('pos_cart');
    if (saved) {
        cart = JSON.parse(saved);
    }
}

// Quick actions
function holdTransaction() {
    if (cart.length === 0) {
        showError('Cart is empty');
        return;
    }
    
    const transactions = JSON.parse(localStorage.getItem('held_transactions') || '[]');
    transactions.push({
        id: Date.now(),
        date: new Date().toISOString(),
        cart: [...cart],
        total: cartTotal
    });
    
    localStorage.setItem('held_transactions', JSON.stringify(transactions));
    clearCart();
    showSuccess('Transaction held');
}

function loadHeldTransaction() {
    const transactions = JSON.parse(localStorage.getItem('held_transactions') || '[]');
    
    if (transactions.length === 0) {
        showError('No held transactions');
        return;
    }
    
    // Show list of held transactions
    const list = transactions.map(t => `
        <button class="btn btn-outline-primary w-100 mb-2" onclick="retrieveTransaction(${t.id})">
            Transaction ${new Date(t.date).toLocaleString()} - ₱${t.total.toFixed(2)}
        </button>
    `).join('');
    
    // You can create a modal to show this list
    console.log('Held transactions:', transactions);
}

function retrieveTransaction(id) {
    const transactions = JSON.parse(localStorage.getItem('held_transactions') || '[]');
    const transaction = transactions.find(t => t.id === id);
    
    if (transaction) {
        cart = transaction.cart;
        updateCartDisplay();
        saveCart();
        
        // Remove from held transactions
        const updated = transactions.filter(t => t.id !== id);
        localStorage.setItem('held_transactions', JSON.stringify(updated));
        
        showSuccess('Transaction retrieved');
    }
}

// Barcode scanner support
document.addEventListener('keydown', function(e) {
    // If F2 is pressed, focus on search
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('productSearch')?.focus();
    }
    
    // If F9 is pressed, process checkout
    if (e.key === 'F9') {
        e.preventDefault();
        processCheckout();
    }
    
    // If F12 is pressed, clear cart
    if (e.key === 'F12') {
        e.preventDefault();
        clearCart();
    }
});