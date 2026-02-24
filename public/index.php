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


/* Expiring Soon */
$expiringSoon = $db->query("
    SELECT COUNT(*) 
    FROM stock_movements
    WHERE expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();


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

<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* ================= GLOBAL ================= */

body{
    margin:0;
    background:
        radial-gradient(circle at 20% 20%, #ffffff 0%, #f1f4f9 40%),
        linear-gradient(180deg,#eef2f8,#f7f9fc);
}


.container{
    display:flex;
    min-height:100vh;
}

/* ================= MAIN ================= */

.main{
    flex:1;
    padding:28px;
    background:#f1f4f9;
}

/* Prevent UI shifting */
.main,
.section,
.card{
    box-sizing:border-box;
}

/* HEADER */
.dashboard-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:28px;
}

.dashboard-header h1{
    font-weight:700;
    letter-spacing:.3px;
    color:#2c3e50;
}

/* ================= CARDS ================= */

.dashboard-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card{
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(8px);
    padding:24px;
    border-radius:16px;

    box-shadow:
        0 10px 25px rgba(0,0,0,.06),
        inset 0 1px 0 rgba(255,255,255,.6);

    transition: all .25s ease;
    position:relative;
    overflow:hidden;
}


.card:hover{
    transform: translateY(-6px);
    box-shadow:
        0 18px 35px rgba(0,0,0,.2);
}


.card h3{
    font-size:13px;
    color:#888;
    margin:0;
}
.card::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(
        120deg,
        transparent,
        rgba(255,255,255,.35),
        transparent
    );
    opacity:0;
    transition:.4s;
}

.card:hover::after{
    opacity:1;
}

.value{
    font-size:34px;
    font-weight:700;
    margin-top:10px;
    letter-spacing:.5px;
    color:#2c3e50;
}
.card:hover .value{
    transform:scale(1.03);
}

/* colored glow bars */
.card.total{border-left:5px solid #3498db;}
.card.low{border-left:5px solid #f39c12;}
.card.out{border-left:5px solid #e74c3c;}
.card.items{border-left:5px solid #2ecc71;}
.card.expire{border-left:5px solid #9b59b6;}

/* ================= SECTIONS ================= */

.section{
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(6px);
    padding:24px;
    border-radius:16px;

    box-shadow:
        0 8px 22px rgba(0,0,0,.05);

    margin-top:22px;
}

/* ================= CHARTS ================= */

.charts{
    display:grid;
    grid-template-columns:repeat(2,420px);
    gap:20px;
}

/* fixed size prevents layout jumping */
.chart-box{
    width:420px;
    height:260px;
    background:#fff;
    border-radius:14px;
    padding:12px;

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.7),
        0 4px 14px rgba(0,0,0,.04);
}


.chart-box canvas{
    width:100%!important;
    height:220px!important;
}

/* ================= TABLE ================= */

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:12px;
    border-bottom:1px solid #eee;
}

th{
    background:#f7f9fc;
    font-weight:600;
}

tr:hover{
    background:#fafafa;
}

/* MOBILE */
@media(max-width:900px){

    .charts{
        grid-template-columns:1fr;
    }

    .chart-box{
        width:100%;
    }
}
*{
    transition: background-color .2s, box-shadow .2s, transform .2s;
}


</style>
</head>

<body>

<div class="overlay" onclick="toggleSidebar()"></div>

<div class="container">

<?php include 'includes/sidebar.php'; ?>

<div class="main">

<button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>

<div class="dashboard-header">
    <h1>📊 Dashboard</h1>
</div>

<!-- ================= CARDS ================= -->

<div class="dashboard-grid">

<div class="card total">
<h3>Total Products</h3>
<div class="value"><?= $summary['total_products'] ?? 0 ?></div>
</div>

<div class="card low">
<h3>Low Stock Items</h3>
<div class="value"><?= $summary['low_stock'] ?? 0 ?></div>
</div>

<div class="card out">
<h3>Out of Stock</h3>
<div class="value"><?= $summary['out_stock'] ?? 0 ?></div>
</div>

<div class="card items">
<h3>Total Items</h3>
<div class="value"><?= $summary['total_items'] ?? 0 ?></div>
</div>

<div class="card expire">
<h3>Expiring Soon</h3>
<div class="value"><?= $expiringSoon ?? 0 ?></div>
</div>

</div>

<!-- ================= CHARTS ================= -->

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

<!-- ================= RECENT ================= -->

<div class="section">
<h3>Recent Stock Movements</h3>

<table>
<tr>
<th>Product ID</th>
<th>Type</th>
<th>Quantity</th>
<th>Date</th>
</tr>

<?php
$recent = $db->query("
SELECT product_id,type,quantity,created_at
FROM stock_movements
ORDER BY created_at DESC
LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if(count($recent)>0):
foreach($recent as $r):
?>

<tr>
<td><?= $r['product_id'] ?></td>
<td><?= $r['type'] ?></td>
<td><?= $r['quantity'] ?></td>
<td><?= $r['created_at'] ?></td>
</tr>

<?php endforeach; else: ?>

<tr>
<td colspan="4">No recent activity.</td>
</tr>

<?php endif; ?>

</table>
</div>

</div>
</div>

<!-- ================= SIDEBAR TOGGLE ================= -->
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

/* Close sidebar when clicking outside (mobile UX) */
document.addEventListener("click", function (e) {

    const sidebar = document.querySelector(".sidebar");
    const btn = document.querySelector(".menu-toggle");

    if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
        sidebar.classList.remove("active");
    }
});
</script>


<!-- ================= DASHBOARD COLORS ================= -->
<script>
const dashboardColors = {
    blue: '#3498db',
    orange: '#f39c12',
    red: '#e74c3c',
    green: '#2ecc71',
    purple: '#9b59b6'
};
</script>


<!-- ================= CHARTS ================= -->
<script>

/* ================= STOCK STATUS CHART ================= */
new Chart(document.getElementById('stockChart'), {
    type: 'doughnut',
    data: {
        labels: ['Healthy Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
            data: [
                <?= ($summary['total_products']
                    - $summary['low_stock']
                    - $summary['out_stock']) ?>,
                <?= $summary['low_stock'] ?? 0 ?>,
                <?= $summary['out_stock'] ?? 0 ?>
            ],
            backgroundColor: [
                dashboardColors.green,
                dashboardColors.orange,
                dashboardColors.red
            ],
            borderWidth: 0,
            hoverOffset: 12
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '65%',
        animation: {
            animateRotate: true,
            duration: 900
        },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 18,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            }
        }
    }
});


/* ================= CATEGORY BAR CHART ================= */
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
            label: 'Products',
            data: <?= json_encode($categoryTotals) ?>,
            borderRadius: 8,
            borderSkipped: false,
            backgroundColor: dashboardColors.blue
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 900
        },
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

</script>

</body>
</html>
