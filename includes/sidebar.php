<?php
if (!isset($_SESSION)) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_mobile = isset($_GET['mobile']) || (isset($_SERVER['HTTP_USER_AGENT']) && 
    preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT']));
?>

<style>
:root {
    /* Dark Teal Theme */
    --sidebar-primary: #1a4d5c;
    --sidebar-secondary: #0f3543;
    --sidebar-dark: #082a33;
    --sidebar-hover: #216b7d;
    --sidebar-active: #2a8fa0;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-red: #ef4444;
    --text-primary: #ffffff;
    --text-secondary: #e2e8f0;
    --text-tertiary: #cbd5e1;
    --border-light: rgba(255, 255, 255, 0.1);
    --border-medium: rgba(255, 255, 255, 0.15);
    --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-smooth: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    --space-sm: 0.5rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --shadow-light: 0 2px 12px rgba(0, 0, 0, 0.3);
    --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.4);
    --shadow-heavy: 0 12px 35px rgba(0, 0, 0, 0.5);
    --gradient-sidebar: linear-gradient(160deg, var(--sidebar-primary), var(--sidebar-secondary));
    --gradient-hover: linear-gradient(160deg, var(--sidebar-hover), var(--sidebar-primary));
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    overflow-x: hidden;
}

/* Sidebar */
.sidebar {
    width: 280px;
    background: var(--gradient-sidebar);
    color: var(--white);
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: hidden;
    overflow-x: hidden;
    transition: width 0.3s ease;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="1" fill="white" opacity="0.05"/></svg>');
    background-size: 20px 20px;
    opacity: 0.3;
    pointer-events: none;
}

.sidebar-header {
    padding: var(--space-lg);
    border-bottom: 1px solid var(--border-medium);
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: var(--space-md);
    background: linear-gradient(135deg, var(--sidebar-primary), var(--sidebar-secondary));
    backdrop-filter: blur(15px);
    flex-shrink: 0;
    position: relative;
    min-height: 90px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    transition: all 0.3s ease;
}

.brand-logo {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, #ffffff, #f0f9fa);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: all 0.3s ease;
    flex-shrink: 0;
    padding: 6px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    transition: transform 0.3s ease;
}

.brand-logo i {
    font-size: 1.6rem;
    color: var(--sidebar-primary);
    display: none;
}

.brand-logo img.error {
    display: none;
}

.brand-logo img.error + i {
    display: block;
}

.brand-logo:hover {
    transform: rotate(5deg) scale(1.05);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.brand-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    transition: all 0.3s ease;
    opacity: 1;
    overflow: hidden;
}

.brand-name {
    font-weight: 800;
    font-size: 1.3rem;
    color: var(--text-primary);
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5);
    white-space: nowrap;
    letter-spacing: 0.5px;
    background: linear-gradient(135deg, #ffffff, var(--accent-cyan));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.brand-subtitle {
    font-size: 0.8rem;
    color: var(--accent-cyan);
    margin-top: 4px;
    white-space: nowrap;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.4);
}

.sidebar-nav-wrapper {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: var(--space-md) 0;
    position: relative;
}

.sidebar-nav-wrapper::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav-wrapper::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
}

.sidebar-nav-wrapper::-webkit-scrollbar-thumb {
    background: rgba(34, 211, 238, 0.3);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.sidebar-nav-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(34, 211, 238, 0.5);
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0 var(--space-md);
}

/* Nav Group */
.nav-group {
    margin-bottom: 8px;
}

.nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 1rem;
    color: var(--text-tertiary);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s ease;
    border-radius: var(--radius-sm);
    position: relative;
    overflow: hidden;
}

.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--accent-cyan);
}

.nav-group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
}

.nav-group-icon {
    font-size: 0.9rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.nav-group-arrow {
    font-size: 0.8rem;
    transition: transform 0.3s ease;
    flex-shrink: 0;
}

.nav-group.expanded .nav-group-arrow {
    transform: rotate(180deg);
}

.nav-group-items {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    padding-left: 0;
}

.nav-group.expanded .nav-group-items {
    max-height: 1000px;
}

/* Show all items in collapsed sidebar */
.sidebar.collapsed .nav-group-items {
    max-height: none !important;
    overflow: visible !important;
    display: flex !important;
    flex-direction: column;
    gap: 2px;
}

/* Hide group headers completely in collapsed state */
.sidebar.collapsed .nav-group-header {
    display: none !important;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all var(--transition-normal);
    border-left: 3px solid transparent;
    margin: 2px 0;
    border-radius: var(--radius-sm);
    font-weight: 500;
    position: relative;
    overflow: hidden;
    font-size: 0.9rem;
}

.nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 0;
}

.nav-link:hover::before {
    opacity: 1;
}

.nav-link:hover {
    background: var(--gradient-hover);
    color: var(--text-primary);
    border-left-color: var(--accent-cyan);
    transform: translateX(4px);
    box-shadow: 0 3px 12px rgba(34, 211, 238, 0.25);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-hover));
    color: var(--text-primary);
    border-left-color: var(--accent-cyan);
    font-weight: 600;
    box-shadow: 0 6px 20px rgba(34, 211, 238, 0.35);
    transform: translateX(4px);
}

.nav-link.active::after {
    content: '';
    position: absolute;
    right: 10px;
    width: 6px;
    height: 6px;
    background: var(--accent-cyan);
    border-radius: 50%;
    animation: pulse 2s infinite;
    z-index: 1;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(34, 211, 238, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 8px rgba(34, 211, 238, 0);
    }
}

.nav-link i {
    font-size: 0.95rem;
    width: 18px;
    margin-right: 0.75rem;
    text-align: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.nav-link:hover i {
    transform: scale(1.1);
    color: var(--accent-cyan);
}

.nav-link span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    position: relative;
    z-index: 1;
    letter-spacing: 0.2px;
}

.sidebar-divider {
    margin: var(--space-md) var(--space-md);
    border: none;
    border-top: 1px solid var(--border-medium);
}

/* Collapsed State */
.sidebar.collapsed {
    width: 60px;
}

.sidebar.collapsed .brand-text {
    opacity: 0;
    width: 0;
    overflow: hidden;
}

.sidebar.collapsed .sidebar-header {
    justify-content: center;
    padding: var(--space-md);
    width: 60px;
}

.sidebar.collapsed .brand-logo {
    width: 38px;
    height: 38px;
    margin: 0;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar.collapsed .brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.sidebar.collapsed .brand-logo i {
    font-size: 1.3rem;
    margin: 0;
}

.sidebar.collapsed .nav-group {
    margin-bottom: 4px;
}

/* Show all nav links in collapsed state */
.sidebar.collapsed .nav-group-items {
    max-height: none !important;
    overflow: visible !important;
    display: flex !important;
    flex-direction: column;
    gap: 2px;
    padding: 0;
}

.sidebar.collapsed .nav-link {
    padding: 0.5rem 0;
    justify-content: center;
    margin: 2px auto;
    width: 42px;
    transform: none !important;
    border-left: none;
    position: relative;
    transition: all 0.3s ease;
}

.sidebar.collapsed .nav-link:hover {
    transform: scale(1.1) !important;
    background: var(--gradient-hover);
    box-shadow: 0 4px 15px rgba(34, 211, 238, 0.3);
}

.sidebar.collapsed .nav-link:active {
    transform: scale(0.95) !important;
}

.sidebar.collapsed .nav-link.active {
    transform: none !important;
    border-left: 3px solid var(--accent-cyan);
    padding-left: 0;
    background: linear-gradient(135deg, var(--sidebar-active), var(--sidebar-hover));
}

.sidebar.collapsed .nav-link.active:hover {
    transform: scale(1.1) !important;
}

/* Ripple effect on click */
@keyframes ripple {
    0% {
        transform: scale(0);
        opacity: 0.6;
    }
    100% {
        transform: scale(2.5);
        opacity: 0;
    }
}

.sidebar.collapsed .nav-link::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: var(--accent-cyan);
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    pointer-events: none;
}

.sidebar.collapsed .nav-link:active::after {
    animation: ripple 0.6s ease-out;
}

.sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: 0.95rem;
}

.sidebar.collapsed .nav-link span {
    display: none;
}

.sidebar.collapsed .nav-link.active::after {
    right: 2px;
    width: 4px;
    height: 4px;
}

.sidebar.collapsed .sidebar-nav {
    padding: 0 9px;
    align-items: center;
}

.sidebar.collapsed .sidebar-divider {
    margin: var(--space-sm) auto;
    width: 30px;
}

/* Tooltip for collapsed state */
.sidebar.collapsed .nav-link,
.sidebar.collapsed .nav-group-header {
    position: relative;
}

.sidebar.collapsed .nav-link:hover::after,
.sidebar.collapsed .nav-group-header:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: var(--sidebar-dark);
    color: var(--text-primary);
    padding: 0.4rem 0.8rem;
    border-radius: var(--radius-sm);
    white-space: nowrap;
    margin-left: 10px;
    font-size: 0.85rem;
    z-index: 1100;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    pointer-events: none;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1045;
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.show {
    display: block;
    opacity: 1;
}

.main-content {
    margin-left: 280px;
    padding: 1.75rem 2rem;
    min-height: 100vh;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    transition: margin-left 0.3s ease, padding 0.3s ease, opacity 0.3s ease, transform 0.3s ease;
    width: calc(100% - 280px);
    max-width: calc(100% - 280px);
    box-sizing: border-box;
    opacity: 1;
    transform: translateX(0);
}

.main-content.expanded {
    margin-left: 60px;
    width: calc(100% - 60px);
    max-width: calc(100% - 60px);
}

/* Page load animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.main-content {
    animation: fadeInUp 0.5s ease-out;
}

/* Smooth transitions for content sections */
.main-content > * {
    animation: fadeInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.main-content > *:nth-child(1) { animation-delay: 0.1s; }
.main-content > *:nth-child(2) { animation-delay: 0.2s; }
.main-content > *:nth-child(3) { animation-delay: 0.3s; }
.main-content > *:nth-child(4) { animation-delay: 0.4s; }
.main-content > *:nth-child(5) { animation-delay: 0.5s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 1.5rem 2rem;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 280px;
    }
    
    .main-content {
        padding: 1.25rem 1.5rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 1rem 1rem;
    }
    
    .sidebar {
        width: 260px;
    }
}
</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-logo">
            <img src="../assets/images/main lg.png" alt="Town Gas Logo" id="brandLogo">
            <i class="bi bi-fuel-pump"></i>
        </div>
        <div class="brand-text">
            <div class="brand-name">TOWN GAZ</div>
            <div class="brand-subtitle">LPG ng Bayan</div>
        </div>
    </div>
    
    <div class="sidebar-nav-wrapper" id="sidebarNavWrapper">
        <nav class="sidebar-nav">
            <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] == 'Admin'): ?>
                <!-- Main Operations -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Main Operations">
                        <div class="nav-group-title">
                            <i class="bi bi-grid nav-group-icon"></i>
                            <span>Main Operations</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                           href="../admin/dashboard.php" data-tooltip="Dashboard">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>" 
                           href="../admin/pos.php" data-tooltip="Point of Sale">
                            <i class="bi bi-cart-check"></i>
                            <span>Point of Sale</span>
                        </a>
                    </div>
                </div>
                
                <!-- Inventory & Products -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Inventory & Products">
                        <div class="nav-group-title">
                            <i class="bi bi-box-seam nav-group-icon"></i>
                            <span>Inventory & Products</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>" 
                           href="../admin/inventory.php" data-tooltip="Inventory">
                            <i class="bi bi-boxes"></i>
                            <span>Inventory</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>" 
                           href="../admin/products.php" data-tooltip="Products">
                            <i class="bi bi-droplet"></i>
                            <span>Products</span>
                        </a>
                    </div>
                </div>
                
                <!-- Sales & Customers -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Sales & Customers">
                        <div class="nav-group-title">
                            <i class="bi bi-people nav-group-icon"></i>
                            <span>Sales & Customers</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>" 
                           href="../admin/customers.php" data-tooltip="Customers">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Customers</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'deliveries.php') ? 'active' : ''; ?>" 
                           href="../admin/deliveries.php" data-tooltip="Deliveries">
                            <i class="bi bi-truck"></i>
                            <span>Deliveries</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'sales.php') ? 'active' : ''; ?>" 
                           href="../admin/sales.php" data-tooltip="Sales">
                            <i class="bi bi-receipt"></i>
                            <span>Sales</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" 
                           href="../admin/reports.php" data-tooltip="Reports">
                            <i class="bi bi-bar-chart"></i>
                            <span>Reports</span>
                        </a>
                    </div>
                </div>
                
                <hr class="sidebar-divider">
                
                <!-- User Management -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="User Management">
                        <div class="nav-group-title">
                            <i class="bi bi-person-gear nav-group-icon"></i>
                            <span>User Management</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" 
                           href="../admin/users.php" data-tooltip="Users">
                            <i class="bi bi-people-fill"></i>
                            <span>Users</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'riders.php') ? 'active' : ''; ?>" 
                           href="../admin/riders.php" data-tooltip="Riders">
                            <i class="bi bi-person-badge"></i>
                            <span>Riders</span>
                        </a>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="System Settings">
                        <div class="nav-group-title">
                            <i class="bi bi-gear nav-group-icon"></i>
                            <span>System Settings</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'audit-logs.php') ? 'active' : ''; ?>" 
                           href="../admin/audit-logs.php" data-tooltip="Audit Logs">
                            <i class="bi bi-file-text"></i>
                            <span>Audit Logs</span>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Staff Menu -->
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Main Tasks">
                        <div class="nav-group-title">
                            <i class="bi bi-briefcase nav-group-icon"></i>
                            <span>Main Tasks</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>" 
                           href="pos.php" data-tooltip="Point of Sale">
                            <i class="bi bi-cart-check"></i>
                            <span>Point of Sale</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'sales-history.php') ? 'active' : ''; ?>" 
                           href="sales-history.php" data-tooltip="Sales History">
                            <i class="bi bi-clock-history"></i>
                            <span>Sales History</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Customer Service">
                        <div class="nav-group-title">
                            <i class="bi bi-people nav-group-icon"></i>
                            <span>Customer Service</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>" 
                           href="customers.php" data-tooltip="Customers">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Customers</span>
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'delivery.php') ? 'active' : ''; ?>" 
                           href="delivery.php" data-tooltip="Deliveries">
                            <i class="bi bi-truck"></i>
                            <span>Deliveries</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-header" data-tooltip="Operations">
                        <div class="nav-group-title">
                            <i class="bi bi-tools nav-group-icon"></i>
                            <span>Operations</span>
                        </div>
                        <i class="bi bi-chevron-down nav-group-arrow"></i>
                    </div>
                    <div class="nav-group-items">
                        <a class="nav-link <?php echo ($current_page == 'inventory-update.php') ? 'active' : ''; ?>" 
                           href="inventory-update.php" data-tooltip="Inventory Update">
                            <i class="bi bi-box-seam"></i>
                            <span>Inventory Update</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</div>

<script>
// Apply collapsed state IMMEDIATELY before page renders
(function() {
    if (sessionStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 1200) {
        // Add style directly to prevent flash
        const style = document.createElement('style');
        style.id = 'sidebar-preload';
        style.textContent = `
            .sidebar { width: 60px !important; }
            .main-content { margin-left: 60px !important; width: calc(100% - 60px) !important; }
            .top-header { margin-left: 60px !important; width: calc(100% - 60px) !important; }
        `;
        document.head.appendChild(style);
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const brandLogo = document.getElementById('brandLogo');
    
    // Handle logo error
    if (brandLogo) {
        brandLogo.onerror = function() {
            this.classList.add('error');
        };
    }
    
    // Apply collapsed state immediately
    if (sessionStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 1200) {
        sidebar.classList.add('collapsed');
        const mainContent = document.querySelector('.main-content');
        const topHeader = document.querySelector('.top-header');
        if (mainContent) {
            mainContent.classList.add('expanded');
        }
        if (topHeader) {
            topHeader.classList.add('expanded');
        }
        
        // Remove preload style after classes are applied
        setTimeout(() => {
            const preloadStyle = document.getElementById('sidebar-preload');
            if (preloadStyle) {
                preloadStyle.remove();
            }
        }, 50);
    }
    
    // Auto-expand group containing active link (only if sidebar is not collapsed)
    if (!sidebar.classList.contains('collapsed')) {
        const activeLink = document.querySelector('.nav-link.active');
        if (activeLink) {
            const parentGroup = activeLink.closest('.nav-group');
            if (parentGroup) {
                parentGroup.classList.add('expanded');
            }
        }
    }
    
    // Toggle sidebar function (called from header)
    window.toggleSidebar = function() {
        const isMobile = window.innerWidth <= 1200;
        
        if (isMobile) {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            const mainContent = document.querySelector('.main-content');
            const topHeader = document.querySelector('.top-header');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
            if (topHeader) {
                topHeader.classList.toggle('expanded');
            }
            
            // Update sessionStorage based on current state
            if (sidebar.classList.contains('collapsed')) {
                sessionStorage.setItem('sidebarCollapsed', 'true');
            } else {
                sessionStorage.removeItem('sidebarCollapsed');
            }
        }
    };
    
    // Toggle nav groups
    const navGroupHeaders = document.querySelectorAll('.nav-group-header');
    navGroupHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            // Don't toggle if sidebar is collapsed
            if (sidebar.classList.contains('collapsed')) {
                return;
            }
            
            const navGroup = this.parentElement;
            
            // Only toggle this specific group
            navGroup.classList.toggle('expanded');
        });
    });
    
    // Handle nav link clicks - prevent sidebar expansion in collapsed state
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const isMobile = window.innerWidth <= 1200;
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            // On mobile, close sidebar
            if (isMobile) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                return; // Let default navigation happen
            }
            
            // On desktop collapsed state, keep it collapsed and navigate directly
            if (isCollapsed && !isMobile) {
                // Store collapsed state before navigation
                sessionStorage.setItem('sidebarCollapsed', 'true');
                // Let default navigation happen - no animation needed
            }
        });
    });
    
    // Close sidebar when clicking overlay (mobile)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1200) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        } else {
            sidebar.classList.remove('collapsed');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.classList.remove('expanded');
            }
        }
    });
});

// Close sidebar with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    }
});
</script>