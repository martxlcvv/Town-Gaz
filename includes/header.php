<?php
if (!isset($_SESSION)) {
    session_start();
}

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard') . ' - Town Gas POS'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="<?php echo (isset($_SESSION['role_name']) && $_SESSION['role_name'] == 'Admin') ? '../' : '../'; ?>assets/js/sweetalert-helper.js"></script>
    <?php if (function_exists('get_session_token')): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_session_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    
    <!-- Preload collapsed state BEFORE rendering -->
    <script>
    (function() {
        if (sessionStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 1200) {
            const style = document.createElement('style');
            style.id = 'sidebar-preload';
            style.textContent = `
                .sidebar { width: 60px !important; }
                .main-content { margin-left: 60px !important; width: calc(100% - 60px) !important; }
                .top-header { margin-left: 60px !important; width: calc(100% - 60px) !important; }
                .sidebar .brand-text { opacity: 0 !important; width: 0 !important; }
                .sidebar .nav-group-header { display: none !important; }
                .sidebar .nav-link span { display: none !important; }
                .sidebar .nav-link { padding: 0.5rem 0 !important; justify-content: center !important; width: 42px !important; }
            `;
            document.head.appendChild(style);
        }
    })();
    </script>
    
    <style>
    :root {
        --primary-teal: #1a4d5c;
        --primary-teal-light: #216b7d;
        --primary-teal-dark: #0f3543;
        --accent-cyan: #22d3ee;
        --accent-green: #4ade80;
        --accent-red: #ef4444;
        --white: #ffffff;
        --light-bg: #f8fafc;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --border-color: #e2e8f0;
    }

    .top-header {
        background: var(--white);
        padding: 0.75rem 1.5rem;
        box-shadow: 0 2px 8px rgba(26, 77, 92, 0.1);
        position: sticky;
        top: 0;
        z-index: 1040;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: auto;
        margin-left: 280px;
        transition: margin-left 0.3s ease, padding 0.3s ease, width 0.3s ease;
        height: 65px;
        border-bottom: 2px solid var(--border-color);
        width: calc(100% - 280px);
    }
    
    .top-header.expanded {
        margin-left: 60px;
        width: calc(100% - 60px);
    }
    
    @media (max-width: 1200px) {
        .top-header {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 0.75rem 1rem;
        }
    }
    
    @media (max-width: 768px) {
        .top-header {
            padding: 0.75rem 0.75rem;
            height: 60px;
        }
    }
    
    @media (max-width: 480px) {
        .top-header {
            padding: 0.5rem 0.5rem;
        }
    }
    
    .header-menu-toggle {
        background: linear-gradient(135deg, var(--primary-teal), var(--primary-teal-dark));
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(26, 77, 92, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
    }
    
    .header-menu-toggle:hover {
        background: linear-gradient(135deg, var(--primary-teal-light), var(--primary-teal));
        transform: scale(1.08);
        box-shadow: 0 6px 16px rgba(26, 77, 92, 0.35);
    }
    
    .header-menu-toggle:active {
        transform: scale(0.95);
    }

    @media (max-width: 480px) {
        .header-menu-toggle {
            width: 38px;
            height: 38px;
            font-size: 1rem;
            padding: 6px 10px;
        }
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-left: auto;
    }
    
    .profile-dropdown {
        position: relative;
    }

    .profile-trigger {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0.8rem;
        background: transparent;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        min-height: auto;
    }

    .profile-trigger:hover {
        background: var(--light-bg);
        transform: none;
        box-shadow: none;
    }

    .profile-pic {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary-blue);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        border: none;
        box-shadow: none;
        overflow: hidden;
        flex-shrink: 0;
    }

    .profile-pic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
        max-width: 150px;
    }

    .profile-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-dark);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-role {
        font-size: 0.7rem;
        color: var(--text-light);
        white-space: nowrap;
    }

    @media (max-width: 576px) {
        .profile-info {
            max-width: 100px;
        }
        
        .profile-name {
            font-size: 0.75rem;
        }
        
        .profile-role {
            font-size: 0.65rem;
        }
    }

    @media (max-width: 480px) {
        .profile-info {
            display: none;
        }
    }

    .dropdown-arrow {
        color: var(--text-light);
        transition: transform 0.2s ease;
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .profile-trigger:hover .dropdown-arrow {
        transform: rotate(180deg);
    }

    .profile-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background: var(--white);
        border-radius: 10px;
        box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        min-width: 240px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.25s ease;
        z-index: 1050;
        overflow: hidden;
    }

    .profile-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .profile-menu-header {
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary-blue), #00547a);
        color: var(--white);
        text-align: center;
    }

    .profile-menu-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.4rem;
        margin: 0 auto 0.5rem;
        border: none;
        overflow: hidden;
    }

    .profile-menu-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-menu-name {
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 0.1rem;
    }

    .profile-menu-email {
        font-size: 0.75rem;
        opacity: 0.9;
    }

    .profile-menu-time {
        font-size: 0.9rem;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-family: 'Courier New', monospace;
    }

    .profile-menu-body {
        padding: 0.5rem 0;
    }

    .profile-menu-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.6rem 1rem;
        color: var(--text-dark);
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
        font-size: 0.85rem;
    }

    .profile-menu-item:hover {
        background: var(--light-bg);
        color: var(--primary-blue);
        padding-left: 1.3rem;
    }

    .profile-menu-item i {
        font-size: 0.95rem;
        width: 18px;
        text-align: center;
    }

    .profile-menu-divider {
        height: 1px;
        background: var(--border-color);
        margin: 0.3rem 0;
    }

    .profile-menu-item.logout {
        color: var(--primary-red);
    }

    .profile-menu-item.logout:hover {
        background: rgba(231, 76, 60, 0.1);
        color: var(--primary-red);
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .top-header {
            margin-left: 0 !important;
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }

    @media (max-width: 768px) {
        .top-header {
            padding: 0.5rem;
            height: 60px;
        }

        .header-left {
            gap: 0.75rem;
        }

        .profile-info {
            display: none;
        }

        .profile-menu {
            min-width: 250px;
        }

        .profile-trigger {
            padding: 0.4rem 0.6rem;
        }
        
        .header-right {
            gap: 0.5rem;
        }
        
        .header-menu-toggle {
            padding: 8px 12px;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
        }
    }

    @media (max-width: 576px) {
        .top-header {
            padding: 0.5rem 0.75rem;
            min-height: 60px;
        }
        
        .profile-pic {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }
        
        .profile-trigger {
            padding: 0.3rem 0.5rem;
            min-height: 40px;
        }
        
        .header-menu-toggle {
            width: 38px;
            height: 38px;
            font-size: 1.1rem;
        }
    }
    </style>
</head>
<body>
    <div class="top-header" id="topHeader">
        <div class="header-left">
            <button class="header-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="header-right">
            <div class="profile-dropdown">
                <button class="profile-trigger" onclick="toggleProfileMenu()" type="button">
                    <div class="profile-pic">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?php 
                            $name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
                            echo htmlspecialchars(substr($name, 0, 20)); 
                        ?></div>
                        <div class="profile-role"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?></div>
                    </div>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </button>
                
                <div class="profile-menu" id="profileMenu">
                    <div class="profile-menu-header">
                        <div class="profile-menu-avatar">
                            <?php if (!empty($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-fill"></i>
                            <?php endif; ?>
                        </div>
                        <div class="profile-menu-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="profile-menu-email"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?></div>
                    </div>
                    
                    <div class="profile-menu-body">
                        <a href="<?php 
                            if (isset($_SESSION['role_name']) && $_SESSION['role_name'] == 'Admin') {
                                echo 'settings.php';
                            } else {
                                echo '../staff/settings.php';
                            }
                        ?>" class="profile-menu-item">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                        <div class="profile-menu-divider"></div>
                        <a href="#" onclick="showLogoutModal(event)" class="profile-menu-item logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                        <div class="profile-menu-divider"></div>
                        <div style="padding: 0.8rem 1rem; text-align: center; color: #7f8c8d; font-size: 0.75rem;">
                            <div id="menuTime" style="font-size: 0.95rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.3rem; font-family: 'Courier New', monospace;">00:00:00</div>
                            <div id="menuDate" style="font-size: 0.7rem;">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: '2-digit'
        });
        
        const menuTime = document.getElementById('menuTime');
        if (menuTime) {
            menuTime.textContent = timeString;
        }
        
        const menuDate = document.getElementById('menuDate');
        if (menuDate) {
            menuDate.textContent = dateString;
        }
    }
    
    setInterval(updateClock, 1000);
    updateClock();

    // Toggle profile menu
    function toggleProfileMenu() {
        const menu = document.getElementById('profileMenu');
        menu.classList.toggle('show');
    }

    // Close profile menu when clicking outside
    document.addEventListener('click', function(e) {
        const profileDropdown = document.querySelector('.profile-dropdown');
        const profileMenu = document.getElementById('profileMenu');
        
        if (!profileDropdown.contains(e.target)) {
            profileMenu.classList.remove('show');
        }
    });

    // Logout modal
    function showLogoutModal(event) {
        event.preventDefault();
        document.getElementById('profileMenu').classList.remove('show');
        Swal.fire({
            title: 'Confirm Logout',
            text: 'Are you sure you want to logout? You will need to login again to access the system.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-box-arrow-right me-2"></i>Logout',
            cancelButtonText: '<i class="bi bi-x-circle me-2"></i>Cancel',
            html: '<p style="color: #666;">Are you sure you want to logout?</p><p style="color: #999; font-size: 0.9rem;">You will need to login again to access the system.</p>'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../auth/logout.php';
            }
        });
    }
    
    // Apply preloaded collapsed state immediately
    (function() {
        if (sessionStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 1200) {
            const topHeader = document.getElementById('topHeader');
            if (topHeader) {
                topHeader.classList.add('expanded');
            }
        }
    })();
    
    // Sync header margin with sidebar state
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const topHeader = document.getElementById('topHeader');
        
        if (sidebar && topHeader) {
            // Initial sync
            if (sidebar.classList.contains('collapsed')) {
                topHeader.classList.add('expanded');
            }
            
            // Watch for sidebar changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        const isCollapsed = sidebar.classList.contains('collapsed');
                        if (window.innerWidth > 1200) {
                            if (isCollapsed) {
                                topHeader.classList.add('expanded');
                            } else {
                                topHeader.classList.remove('expanded');
                            }
                        }
                    }
                });
            });
            
            observer.observe(sidebar, { attributes: true });
            
            // Remove preload style after everything is loaded
            setTimeout(() => {
                const preloadStyle = document.getElementById('sidebar-preload');
                if (preloadStyle) {
                    preloadStyle.remove();
                }
            }, 100);
        }
    });
    </script>
    
    <!-- Inactivity Timeout & Auto-Logout -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="<?php echo (isset($_SESSION['role_name']) && $_SESSION['role_name'] == 'Admin') ? '../' : '../'; ?>assets/js/inactivity-logout.js"></script>
    <?php endif; ?>
</body>
</html>