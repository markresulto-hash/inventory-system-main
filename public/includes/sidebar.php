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

    <a href="stock_in.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stock_in.php' ? 'active' : '' ?>">
        <i class="fas fa-arrow-down"></i>
        <span>📥 Stock In</span>
    </a>

    <a href="stock_out.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stock_out.php' ? 'active' : '' ?>">
        <i class="fas fa-arrow-up"></i>
        <span>📤 Stock Out</span>
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
    background: linear-gradient(180deg, #d49f7e, #c88c68);
    color: white;
    padding: 20px 0;
    box-shadow: 2px 0 12px rgba(0,0,0,0.08);
    position: relative;
}

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
}

/* ================= LINKS ================= */

.sidebar a {
    display: flex;
    align-items: center;
    padding: 13px 15px 13px 12px; /* REDUCED left padding from 15px to 12px */
    color: white;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.25s ease;
    position: relative;
    gap: 8px; /* REDUCED gap from 10px to 8px */
}

.sidebar a i {
    width: 18px;
    font-size: 16px;
    text-align: center;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.15);
    padding-left: 17px; /* ADJUSTED hover padding (was 20px, now 17px) */
}

/* ACTIVE PAGE */
.sidebar a.active {
    background: rgba(255,255,255,0.22);
    font-weight: 600;
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
}

/* ================= CLOSE BUTTON ================= */

/* MOBILE FIRST → visible */
.close-btn {
    position: absolute;
    top: 12px;
    right: 12px;

    width: 36px;
    height: 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.25);

    color: white;
    font-size: 20px;
    font-weight: bold;

    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s ease;
}

.close-btn:hover {
    background: rgba(255,255,255,0.35);
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* DESKTOP → hide button */
@media (min-width: 768px) {
    .close-btn {
        display: none;
    }
}

</style>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
</script>