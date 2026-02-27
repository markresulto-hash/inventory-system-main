<!-- Add Font Awesome CDN at the top of sidebar -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="sidebar">
    <h2>📦 Inventory</h2>

    <!-- CLOSE BUTTON (mobile only) -->
    <button class="close-btn" onclick="toggleSidebar()">✕</button>

    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>📊 Dashboard</span>
    </a>

    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
        <i class="fas fa-box"></i>
        <span>🏷️ Products</span>
    </a>

    <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i>
        <span>📈 Reports</span>
    </a>

    <a href="audit_trail.php" class="<?= basename($_SERVER['PHP_SELF']) == 'audit_trail.php' ? 'active' : '' ?>">
        <i class="fas fa-history"></i>
        <span>📋 Audit Trail</span>
    </a>
    
    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>🚪 Logout</span>
    </a>
</div>

<style>
/* ================= SIDEBAR ================= */

.sidebar {
    width: 220px;
    background: linear-gradient(165deg, #667eea 0%, #764ba2 50%, #9f7aea 100%);
    color: white;
    padding: 20px 0;
    box-shadow: 2px 0 20px rgba(102, 126, 234, 0.3);
    position: relative;
    transition: all 0.3s ease;
    z-index: 1000;
}

/* Alternative gradient options - uncomment to try different styles */
/*
.sidebar {
    background: linear-gradient(145deg, #2b32b2 0%, #1488cc 50%, #2b32b2 100%);
}

.sidebar {
    background: linear-gradient(160deg, #6a11cb 0%, #2575fc 100%);
}

.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.sidebar {
    background: linear-gradient(145deg, #4568DC 0%, #B06AB3 100%);
}
*/

/* ================= DESKTOP ================= */
@media (min-width: 768px) {
    .sidebar {
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }
}

/* ================= MOBILE SIDEBAR ================= */
@media (max-width: 767px) {
    .sidebar {
        position: fixed;
        left: -240px;
        top: 0;
        height: 100vh;
        z-index: 1000;
        transition: left 0.3s ease;
    }

    .sidebar.active {
        left: 0;
    }
}

/* ================= TITLE ================= */

.sidebar h2 {
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
    letter-spacing: 1px;
    font-size: 20px;
    opacity: 0.95;
    padding: 0 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

/* ================= LINKS ================= */

.sidebar a {
    display: flex;
    align-items: center;
    padding: 12px 15px 12px 15px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.25s ease;
    position: relative;
    gap: 10px;
    margin: 2px 10px;
    border-radius: 6px;
}

.sidebar a i {
    width: 20px;
    font-size: 16px;
    text-align: center;
    color: white;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.2);
    padding-left: 20px;
    backdrop-filter: blur(10px);
}

/* ACTIVE PAGE */
.sidebar a.active {
    background: rgba(255,255,255,0.25);
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
}

.sidebar a.active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: white;
    border-radius: 0 4px 4px 0;
    box-shadow: 0 2px 8px rgba(255,255,255,0.5);
}

/* LOGOUT BUTTON */
.sidebar a.logout-btn {
    margin-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.2);
    border-radius: 0;
    margin-left: 0;
    margin-right: 0;
    padding-left: 25px;
}

.sidebar a.logout-btn:hover {
    background: rgba(255, 68, 68, 0.3);
    padding-left: 30px;
    backdrop-filter: blur(10px);
}

.sidebar a.logout-btn i {
    color: white;
}

/* ================= CLOSE BUTTON ================= */

.close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    font-size: 20px;
    font-weight: bold;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s ease;
    z-index: 1001;
}

.close-btn:hover {
    background: rgba(255,255,255,0.4);
    transform: scale(1.08) rotate(90deg);
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

/* DESKTOP → hide button */
@media (min-width: 768px) {
    .close-btn {
        display: none;
    }
}

/* ================= OVERLAY ================= */
.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.6) 100%);
    z-index: 999;
    backdrop-filter: blur(4px);
    transition: all 0.3s ease;
}

.overlay.active {
    display: block;
}

/* ================= SCROLLBAR ================= */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* ================= ANIMATIONS ================= */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Apply animations only to main nav links, not logout */
.sidebar a:not(.logout-btn) {
    animation: slideIn 0.3s ease forwards;
    animation-delay: calc(var(--item-index) * 0.05s);
    opacity: 0;
}

.sidebar a:nth-child(3) { --item-index: 1; }
.sidebar a:nth-child(4) { --item-index: 2; }
.sidebar a:nth-child(5) { --item-index: 3; }
.sidebar a:nth-child(6) { --item-index: 4; }
</style>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.overlay').classList.toggle('active');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 767) {
        if (!sidebar.contains(e.target) && !menuToggle?.contains(e.target)) {
            sidebar.classList.remove('active');
            document.querySelector('.overlay')?.classList.remove('active');
        }
    }
});

// Close sidebar on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelector('.sidebar')?.classList.remove('active');
        document.querySelector('.overlay')?.classList.remove('active');
    }
});

// Ensure overlay is hidden on page load
window.addEventListener('load', function() {
    document.querySelector('.overlay')?.classList.remove('active');
});
</script>