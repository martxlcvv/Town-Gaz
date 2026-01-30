<?php
// PIN Modal Component
// This modal is used for sensitive operations requiring PIN verification
?>

<!-- PIN Verification Modal -->
<div class="modal fade" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock me-2"></i>Security Verification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                    <h6 class="mt-3 mb-2">Admin PIN Required</h6>
                    <p class="text-muted">Please enter your 6-digit PIN to continue</p>
                </div>
                
                <div class="pin-input-container">
                    <input type="password" 
                           id="pinInput" 
                           maxlength="6" 
                           inputmode="numeric"
                           pattern="[0-9]*"
                           class="form-control text-center fs-4 py-3"
                           placeholder="Enter 6-digit PIN"
                           autocomplete="off"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                
                <div class="alert alert-danger mt-3" id="pinError" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="pinErrorMessage"></span>
                </div>
                
                <div class="attempts-counter mt-3 text-center small text-muted">
                    Attempts: <span id="attemptsCount">0</span>/3
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmPinBtn" onclick="verifyPin()">
                    <i class="bi bi-check-circle me-2"></i>Verify & Continue
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PIN Verification JS -->
<script>
// PIN Verification System
let pinVerificationCallback = null;
let pinActionData = null;
let pinAttempts = 0;
const MAX_PIN_ATTEMPTS = 3;

function showPinVerification(action, data, callback) {
    pinVerificationCallback = callback;
    pinActionData = data;
    pinAttempts = 0;
    
    // Reset PIN input
    const pinInput = document.getElementById('pinInput');
    const pinError = document.getElementById('pinError');
    
    pinInput.value = '';
    pinError.style.display = 'none';
    document.getElementById('attemptsCount').textContent = pinAttempts;
    document.getElementById('confirmPinBtn').disabled = false;
    document.getElementById('confirmPinBtn').innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify & Continue';
    
    // Show modal
    const pinModal = new bootstrap.Modal(document.getElementById('pinModal'));
    pinModal.show();
    
    // Focus PIN input
    setTimeout(() => {
        pinInput.focus();
    }, 300);
}

async function verifyPin() {
    const pin = document.getElementById('pinInput').value;
    const errorDiv = document.getElementById('pinError');
    const errorMessage = document.getElementById('pinErrorMessage');
    const confirmBtn = document.getElementById('confirmPinBtn');
    const attemptsCount = document.getElementById('attemptsCount');
    
    if (pin.length !== 6) {
        errorMessage.textContent = 'Please enter a valid 6-digit PIN';
        errorDiv.style.display = 'block';
        return;
    }
    
    // Disable button during verification
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Verifying...';
    
    try {
        // Verify PIN via AJAX
        const response = await fetch('../auth/verify-pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `pin=${encodeURIComponent(pin)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // PIN verified successfully
            const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinModal'));
            pinModal.hide();
            
            // Show success toast
            showToast('PIN verified successfully!', 'success');
            
            // Call the callback function with action data
            if (pinVerificationCallback) {
                setTimeout(() => {
                    pinVerificationCallback(pinActionData);
                }, 500);
            }
            
            // Reset
            pinVerificationCallback = null;
            pinActionData = null;
            pinAttempts = 0;
        } else {
            // PIN verification failed
            pinAttempts++;
            attemptsCount.textContent = pinAttempts;
            
            if (pinAttempts >= MAX_PIN_ATTEMPTS) {
                errorMessage.textContent = 'Maximum attempts exceeded. Please contact administrator.';
                errorDiv.style.display = 'block';
                
                setTimeout(() => {
                    const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinModal'));
                    pinModal.hide();
                    showToast('Too many failed attempts. Please login again.', 'danger');
                    setTimeout(() => {
                        window.location.href = '../auth/logout.php';
                    }, 2000);
                }, 3000);
            } else {
                errorMessage.textContent = `Invalid PIN. ${MAX_PIN_ATTEMPTS - pinAttempts} attempt(s) remaining.`;
                errorDiv.style.display = 'block';
                
                // Clear PIN input
                document.getElementById('pinInput').value = '';
                
                // Re-enable button
                setTimeout(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify & Continue';
                    document.getElementById('pinInput').focus();
                }, 1000);
            }
        }
    } catch (error) {
        console.error('Error verifying PIN:', error);
        errorMessage.textContent = 'Error verifying PIN. Please try again.';
        errorDiv.style.display = 'block';
        
        // Re-enable button
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify & Continue';
    }
}

// PIN input handling
document.addEventListener('DOMContentLoaded', function() {
    const pinInput = document.getElementById('pinInput');
    const pinError = document.getElementById('pinError');
    const confirmBtn = document.getElementById('confirmPinBtn');
    
    if (pinInput) {
        pinInput.addEventListener('input', function(e) {
            // Allow only numbers
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
            
            // Hide error when user starts typing
            pinError.style.display = 'none';
            
            // Enable verify button when PIN is 6 digits
            confirmBtn.disabled = this.value.length !== 6;
        });
        
        // Enter key submits PIN
        pinInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && this.value.length === 6) {
                verifyPin();
            }
        });
    }
    
    // Auto-focus PIN input when modal opens
    const pinModal = document.getElementById('pinModal');
    if (pinModal) {
        pinModal.addEventListener('shown.bs.modal', function() {
            setTimeout(() => {
                const pinInput = document.getElementById('pinInput');
                if (pinInput) {
                    pinInput.focus();
                }
            }, 100);
        });
    }
});

// Helper function for toast notifications
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toastHTML = `
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', toastHTML);
    
    // Show toast
    const toastEl = document.querySelector('.toast');
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
    
    // Remove toast after hiding
    toastEl.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>

<style>
.pin-input-container {
    max-width: 300px;
    margin: 0 auto;
}

#pinInput {
    letter-spacing: 8px;
    font-weight: 600;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    transition: all 0.3s ease;
}

#pinInput:focus {
    border-color: #4fc3f7;
    box-shadow: 0 0 0 0.25rem rgba(79, 195, 247, 0.25);
}

.attempts-counter {
    font-size: 0.85rem;
}

#attemptsCount {
    font-weight: 600;
}

.toast {
    z-index: 9999;
}
</style>