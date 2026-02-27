<?php
require_once '../app/config/database.php';
$db = Database::connect();

/* =========================
   INVENTORY SUMMARY
========================= */

$summary = $db->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock <= min_stock AND stock > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_stock,
        SUM(stock) as total_items
    FROM (
        SELECT 
            p.id,
            p.min_stock,
            COALESCE(SUM(
                CASE 
                    WHEN sm.type = 'IN' THEN sm.quantity
                    WHEN sm.type = 'OUT' THEN -sm.quantity
                END
            ),0) as stock
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        GROUP BY p.id
    ) as inventory
")->fetch(PDO::FETCH_ASSOC);


/* Expiring Soon - 60 DAYS NOTICE */
$expiringSoon = $db->query("
    SELECT COUNT(DISTINCT product_id) 
    FROM stock_movements
    WHERE expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    AND expiry_date >= CURDATE()
    AND (
        SELECT SUM(
            CASE 
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
            END
        )
        FROM stock_movements sm2
        WHERE sm2.product_id = stock_movements.product_id
        AND sm2.expiry_date = stock_movements.expiry_date
    ) > 0
")->fetchColumn();

/* Get expiring products data for the link */
$expiringProducts = $db->query("
    SELECT DISTINCT 
        p.id,
        p.name,
        c.name as category_name,
        sm.expiry_date,
        (
            SELECT SUM(
                CASE 
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                END
            )
            FROM stock_movements sm2
            WHERE sm2.product_id = p.id
            AND sm2.expiry_date = sm.expiry_date
        ) as batch_stock
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN stock_movements sm ON sm.product_id = p.id
    WHERE sm.expiry_date IS NOT NULL
    AND sm.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    AND sm.expiry_date >= CURDATE()
    AND p.is_active = 1
    GROUP BY p.id, sm.expiry_date
    HAVING batch_stock > 0
    ORDER BY sm.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Encode expiring products for JavaScript */
$expiringProductsJson = json_encode($expiringProducts);

/* CATEGORY DATA (REAL DATA NOW) */
$categoryData = $db->query("
SELECT 
    c.name,
    COALESCE(SUM(
        CASE 
            WHEN sm.type='IN' THEN sm.quantity
            WHEN sm.type='OUT' THEN -sm.quantity
        END
    ),0) as total
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
LEFT JOIN stock_movements sm ON sm.product_id = p.id
GROUP BY c.id
")->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [];
$categoryTotals = [];

foreach($categoryData as $c){
    $categoryLabels[] = $c['name'];
    $categoryTotals[] = $c['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Inventory Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ================= MAIN CONTENT STYLES ONLY ================= */
.main {
    flex: 1;
    padding: 25px 30px;
    background: #f5f7fb;
    overflow-y: auto;
    height: 100vh;
}

/* Menu Toggle - Only for mobile */
.menu-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 100;
    background: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 10px;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    color: #333;
}

/* Dashboard Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.dashboard-header h1 {
    font-size: 24px;
    color: #1e293b;
    font-weight: 600;
}

.date-badge {
    background: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
    color: #4b5563;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

/* Stats Cards */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    text-decoration: none;
    color: inherit;
    display: block;
    transition: all 0.2s;
    border: 1px solid #edf2f7;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.card h3 {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
    font-weight: 500;
}

.card .value {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 5px;
}

.card .label {
    font-size: 12px;
    color: #94a3b8;
}

/* Card Colors */
.card.total { border-left: 3px solid #3b82f6; }
.card.low { border-left: 3px solid #f59e0b; }
.card.out { border-left: 3px solid #ef4444; }
.card.items { border-left: 3px solid #10b981; }
.card.expire { border-left: 3px solid #8b5cf6; }

/* Charts Section */
.section {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    margin-top: 20px;
    border: 1px solid #edf2f7;
}

.section h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    font-weight: 600;
}

.charts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.chart-box {
    height: 250px;
    padding: 10px;
    position: relative;
}

/* Cool hover effect for pie chart */
.chart-box:first-child canvas {
    transition: filter 0.3s ease;
    cursor: pointer;
}

.chart-box:first-child canvas:hover {
    filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        filter: drop-shadow(0 0 5px rgba(59, 130, 246, 0.3));
    }
    50% {
        filter: drop-shadow(0 0 15px rgba(59, 130, 246, 0.7));
    }
    100% {
        filter: drop-shadow(0 0 5px rgba(59, 130, 246, 0.3));
    }
}

/* Segment hover effect will be handled by Chart.js options */

/* Table */
.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 12px;
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eef2f6;
    color: #334155;
    font-size: 13px;
}

.type-badge {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.type-badge.in {
    background: #dcfce7;
    color: #166534;
}

.type-badge.out {
    background: #fee2e2;
    color: #991b1b;
}

/* Modal */
.expiring-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.expiring-modal-content {
    background: white;
    margin: 5% auto;
    padding: 25px;
    border-radius: 16px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.expiring-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.expiring-modal-header h2 {
    font-size: 20px;
    color: #1e293b;
}

.expiring-modal-close {
    font-size: 28px;
    color: #94a3b8;
    cursor: pointer;
}

.expiring-badge {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.expiring-badge.warning-60 {
    background: #fef3c7;
    color: #92400e;
}

.expiring-badge.warning-30 {
    background: #fee2e2;
    color: #b91c1c;
}

/* Mobile */
@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }
    
    .main {
        padding: 70px 15px 20px;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: start;
        gap: 10px;
    }
    
    .charts {
        grid-template-columns: 1fr;
    }
    
    .chart-box {
        height: 220px;
    }
}

@media (max-width: 480px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="overlay" onclick="toggleSidebar()"></div>

<div class="container">

<?php include 'includes/sidebar.php'; ?>

<div class="main">

<button class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="dashboard-header">
    <h1>Dashboard</h1>
    <div class="date-badge">
        <i class="far fa-calendar-alt" style="margin-right: 8px;"></i>
        <?= date('F j, Y') ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="dashboard-grid">
    <a href="products.php" class="card total">
        <h3>Total Products</h3>
        <div class="value"><?= number_format($summary['total_products'] ?? 0) ?></div>
        <div class="label">Active products</div>
    </a>

    <a href="products.php?filter=low_stock" class="card low">
        <h3>Low Stock</h3>
        <div class="value"><?= $summary['low_stock'] ?? 0 ?></div>
        <div class="label">Need attention</div>
    </a>

    <a href="products.php?filter=out_stock" class="card out">
        <h3>Out of Stock</h3>
        <div class="value"><?= $summary['out_stock'] ?? 0 ?></div>
        <div class="label">Require restock</div>
    </a>

    <a href="products.php" class="card items">
        <h3>Total Items</h3>
        <div class="value"><?= number_format($summary['total_items'] ?? 0) ?></div>
        <div class="label">In inventory</div>
    </a>

    <div class="card expire" onclick="showExpiringProducts(<?= htmlspecialchars($expiringProductsJson) ?>)">
        <h3>Expiring Soon</h3>
        <div class="value"><?= $expiringSoon ?? 0 ?></div>
        <div class="label">Within 60 days</div>
    </div>
</div>

<!-- Charts -->
<div class="section">
    <h3>Inventory Analytics</h3>
    <div class="charts">
        <div class="chart-box">
            <canvas id="stockChart"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Movements -->
<div class="section">
    <h3>Recent Stock Movements</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent = $db->query("
                    SELECT p.name as product_name, sm.type, sm.quantity, sm.created_at
                    FROM stock_movements sm
                    JOIN products p ON sm.product_id = p.id
                    ORDER BY sm.created_at DESC
                    LIMIT 5
                ")->fetchAll(PDO::FETCH_ASSOC);

                if(count($recent) > 0):
                    foreach($recent as $r):
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['product_name']) ?></td>
                    <td>
                        <span class="type-badge <?= strtolower($r['type']) ?>">
                            <?= $r['type'] ?>
                        </span>
                    </td>
                    <td><?= $r['quantity'] ?></td>
                    <td><?= date('M d, H:i', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">
                        No recent activity
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>

<!-- Modal -->
<div id="expiringModal" class="expiring-modal">
    <div class="expiring-modal-content">
        <div class="expiring-modal-header">
            <h2>Products Expiring Within 60 Days</h2>
            <span class="expiring-modal-close" onclick="closeExpiringModal()">&times;</span>
        </div>
        <div id="expiringProductsList"></div>
    </div>
</div>

<script>
// Sidebar Toggle
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.overlay').classList.toggle('active');
}

// Close sidebar when clicking outside
document.addEventListener("click", function (e) {
    const sidebar = document.querySelector(".sidebar");
    const btn = document.querySelector(".menu-toggle");
    if (!sidebar.contains(e.target) && !btn?.contains(e.target)) {
        sidebar.classList.remove("active");
        document.querySelector('.overlay')?.classList.remove('active');
    }
});

// Expiring Products Modal
function showExpiringProducts(products) {
    const modal = document.getElementById('expiringModal');
    const container = document.getElementById('expiringProductsList');
    
    if (!products || products.length === 0) {
        container.innerHTML = '<p style="text-align:center; padding:20px;">No products expiring within 60 days.</p>';
    } else {
        let html = '<table style="width:100%"><thead><tr><th>Product</th><th>Category</th><th>Batch</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody>';
        
        products.forEach(product => {
            const daysLeft = Math.ceil((new Date(product.expiry_date) - new Date()) / (1000 * 60 * 60 * 24));
            const statusClass = daysLeft <= 30 ? 'warning-30' : 'warning-60';
            const statusText = daysLeft <= 30 ? `${daysLeft} days (Urgent)` : `${daysLeft} days`;
            
            html += `
                <tr>
                    <td><strong>${product.name}</strong></td>
                    <td>${product.category_name || 'N/A'}</td>
                    <td>${product.expiry_date}</td>
                    <td>${product.batch_stock}</td>
                    <td><span class="expiring-badge ${statusClass}">${statusText}</span></td>
                    <td><a href="products.php?batch=${product.expiry_date}&id=${product.id}" style="color:#3b82f6;">View</a></td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    modal.style.display = 'block';
}

function closeExpiringModal() {
    document.getElementById('expiringModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('expiringModal');
    if (event.target === modal) closeExpiringModal();
});

// Charts
const colors = { green: '#10b981', orange: '#f59e0b', red: '#ef4444', blue: '#3b82f6' };

// Stock Status Chart with cool hover animation
const stockChart = new Chart(document.getElementById('stockChart'), {
    type: 'doughnut',
    data: {
        labels: ['Healthy Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
            data: [
                <?= max(0, ($summary['total_products'] ?? 0) - ($summary['low_stock'] ?? 0) - ($summary['out_stock'] ?? 0)) ?>,
                <?= $summary['low_stock'] ?? 0 ?>,
                <?= $summary['out_stock'] ?? 0 ?>
            ],
            backgroundColor: [colors.green, colors.orange, colors.red],
            borderWidth: 0,
            hoverOffset: 25,
            hoverBorderColor: 'white',
            hoverBorderWidth: 3,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        animation: {
            animateScale: true,
            animateRotate: true,
            duration: 1000,
            easing: 'easeInOutQuart'
        },
        plugins: {
            legend: { 
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    font: { size: 11, weight: '500' }
                }
            },
            tooltip: {
                backgroundColor: '#1e293b',
                titleColor: '#fff',
                bodyColor: '#cbd5e1',
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                borderColor: '#3b82f6',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value} units (${percentage}%)`;
                    }
                }
            }
        },
        hover: {
            mode: 'nearest',
            intersect: true,
            animationDuration: 400
        },
        elements: {
            arc: {
                borderWidth: 0,
                hoverBorderWidth: 3,
                hoverBorderColor: '#ffffff',
                hoverOffset: 15
            }
        }
    }
});

// Category Bar Chart
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
            label: 'Stock Quantity',
            data: <?= json_encode($categoryTotals) ?>,
            backgroundColor: colors.blue,
            borderRadius: 6,
            hoverBackgroundColor: '#2563eb',
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleColor: '#fff',
                bodyColor: '#cbd5e1',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return `Quantity: ${context.raw.toLocaleString()} units`;
                    }
                }
            }
        },
        scales: { 
            y: { 
                beginAtZero: true, 
                grid: { color: '#eef2f6' },
                ticks: { font: { size: 10 } }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { size: 10 } }
            }
        },
        hover: {
            mode: 'index',
            intersect: false,
            animationDuration: 300
        }
    }
});

// Add cool glow effect when hovering over the pie chart container
document.querySelector('.chart-box:first-child').addEventListener('mouseenter', function() {
    this.style.transition = 'all 0.3s ease';
});

document.querySelector('.chart-box:first-child').addEventListener('mouseleave', function() {
    this.style.transition = 'all 0.3s ease';
});
</script>

</body>
</html>