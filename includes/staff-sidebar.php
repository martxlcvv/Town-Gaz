<!-- Staff Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-shop me-2"></i>
        <span>Town Gas POS</span>
    </div>
    
    <nav class="sidebar-nav">
        <a href="pos.php" class="nav-link <?php echo ($page_title == 'POS') ? 'active' : ''; ?>">
            <i class="bi bi-cart3"></i>
            <span>Point of Sale</span>
        </a>
        
        <a href="mark-rider-attendance.php" class="nav-link <?php echo ($page_title == 'Mark Rider Attendance') ? 'active' : ''; ?>">
            <i class="bi bi-bicycle"></i>
            <span>Mark Rider Attendance</span>
        </a>
        
        <a href="sales-history.php" class="nav-link <?php echo ($page_title == 'Sales History') ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i>
            <span>Sales History</span>
        </a>
        
        <a href="customers.php" class="nav-link <?php echo ($page_title == 'Customers') ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            <span>Customers</span>
        </a>
        
        <a href="delivery.php" class="nav-link <?php echo ($page_title == 'Deliveries') ? 'active' : ''; ?>">
            <i class="bi bi-truck"></i>
            <span>Deliveries</span>
        </a>
        
        <a href="inventory-update.php" class="nav-link <?php echo ($page_title == 'Inventory Update') ? 'active' : ''; ?>">
            <i class="bi bi-box-seam"></i>
            <span>Inventory Update</span>
        </a>
        
        <hr class="sidebar-divider">
        
        <a href="../auth/logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="bi bi-person-circle me-2"></i>
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['role_name']); ?></small>
            </div>
        </div>
    </div>
</aside>

<style>
:root {
    --sidebar-blue: #065275;
    --sidebar-dark-blue: #00547a;
    --sidebar-light-blue: #b3e5fc;
    --sidebar-hover-blue: #0a6fa0;
    --sidebar-active-blue: #087bb5;
    --white: #ffffff;
    --text-dark: #333333;
    --text-light: #666666;
    --border-color: rgba(255, 255, 255, 0.2);
    --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --space-lg: 1.5rem;
    --space-md: 1rem;
    --radius-lg: 16px;
    --gradient-sidebar: linear-gradient(160deg, var(--sidebar-blue), var(--sidebar-dark-blue));
    --gradient-hover: linear-gradient(160deg, var(--sidebar-hover-blue), var(--sidebar-blue));
}

.sidebar {
    width: 280px;
    background: var(--gradient-sidebar);
    color: var(--white);
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    transition: all 0.3s;
    z-index: 1050;
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.2);
    pointer-events: auto;
}

.sidebar-header {
    padding: var(--space-lg);
    background: rgba(0,0,0,0.2);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}

.sidebar-nav {
    padding: var(--space-md) 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    transition: all var(--transition-normal);
    gap: 12px;
    pointer-events: auto;
    position: relative;
    z-index: 1051;
    cursor: pointer;
}

.nav-link:hover {
    background: rgba(255,255,255,0.12);
    color: var(--white);
    transform: translateX(4px);
}

.nav-link.active {
    background: rgba(255,255,255,0.18);
    color: var(--white);
    border-left: 4px solid var(--white);
    padding-left: 16px;
}

.nav-link i {
    font-size: 1.2rem;
    width: 30px;
    text-align: center;
}

.sidebar-divider {
    margin: 10px 20px;
    border-color: rgba(255,255,255,0.2);
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.15);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-info i {
    font-size: 2rem;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ensure sidebar links navigate properly
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Allow normal navigation - don't prevent default
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                // Small delay to ensure any pending operations complete
                setTimeout(() => {
                    window.location.href = href;
                }, 50);
            }
        });
    });
});
</script>