/**
 * Inactivity Timeout & Auto-Logout System
 * Logs out user after 5 minutes of inactivity
 * Shows warning before logout
 */

(function() {
    const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutes
    const WARNING_TIME = 30 * 1000; // Show warning 30 seconds before logout
    
    let inactivityTimer = null;
    let warningTimer = null;
    let warningShown = false;
    let lastActivityTime = Date.now();
    
    /**
     * Reset the inactivity timer
     */
    function resetInactivityTimer() {
        // Clear existing timers
        if (inactivityTimer) clearTimeout(inactivityTimer);
        if (warningTimer) clearTimeout(warningTimer);
        
        warningShown = false;
        lastActivityTime = Date.now();
        
        // Set warning timer (to show 30 seconds before logout)
        warningTimer = setTimeout(function() {
            if (!warningShown) {
                showWarningModal();
                warningShown = true;
            }
        }, INACTIVITY_TIMEOUT - WARNING_TIME);
        
        // Set logout timer
        inactivityTimer = setTimeout(function() {
            performLogout();
        }, INACTIVITY_TIMEOUT);
        
        console.log('[INACTIVITY] Timer reset. Logout in 5 minutes.');
    }
    
    /**
     * Show warning modal before logout
     */
    function showWarningModal() {
        let secondsRemaining = Math.ceil(WARNING_TIME / 1000);
        
        const modal = Swal.fire({
            title: 'Session Expiring',
            html: `<p>Your session will expire due to inactivity in <strong id="warningCountdown">${secondsRemaining}</strong> seconds.</p>
                   <p class="text-muted small">Click "Stay Logged In" to continue working.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Stay Logged In',
            cancelButtonText: '<i class="bi bi-box-arrow-right me-2"></i>Logout Now',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() {
                // Update countdown every second
                const countdownInterval = setInterval(function() {
                    secondsRemaining--;
                    const countdownEl = document.getElementById('warningCountdown');
                    if (countdownEl) {
                        countdownEl.textContent = secondsRemaining;
                    }
                    
                    if (secondsRemaining <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                // User clicked "Stay Logged In"
                resetInactivityTimer();
                Swal.fire({
                    icon: 'success',
                    title: 'Session Extended',
                    text: 'Your session has been extended for another 5 minutes.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else if (result.dismiss === Swal.DismissReason.cancelButton) {
                // User clicked "Logout Now"
                performLogout();
            }
        });
    }
    
    /**
     * Perform logout
     */
    function performLogout() {
        console.log('[INACTIVITY] Logging out due to inactivity');
        
        Swal.fire({
            icon: 'warning',
            title: 'Session Expired',
            text: 'Your session has expired due to inactivity. Please login again.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            confirmButtonText: 'Login Again'
        }).then(function() {
            window.location.href = '/town-gas/auth/logout.php';
        });
    }
    
    /**
     * Track user activity
     */
    function trackActivity() {
        const now = Date.now();
        const timeSinceLastActivity = now - lastActivityTime;
        
        // Only reset if more than 1 second has passed (to avoid duplicate resets)
        if (timeSinceLastActivity > 1000) {
            console.log('[INACTIVITY] Activity detected - resetting timer');
            resetInactivityTimer();
        }
    }
    
    /**
     * Setup activity listeners
     */
    function setupActivityListeners() {
        // Track mouse movement
        document.addEventListener('mousemove', trackActivity, true);
        
        // Track keyboard input
        document.addEventListener('keypress', trackActivity, true);
        
        // Track clicks
        document.addEventListener('click', trackActivity, true);
        
        // Track scrolling
        document.addEventListener('scroll', trackActivity, true);
        
        // Track form input
        document.addEventListener('change', trackActivity, true);
        document.addEventListener('input', trackActivity, true);
    }
    
    /**
     * Initialize inactivity timeout
     */
    function init() {
        console.log('[INACTIVITY] Inactivity timeout system initialized');
        setupActivityListeners();
        resetInactivityTimer();
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
