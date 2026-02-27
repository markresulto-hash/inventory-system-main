<?php
// At the very top of each protected page (before any HTML)
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: log_in.php");
    exit();
}

// FORCE SESSION CLEANUP - This ensures no stuck states
if (isset($_SESSION['overlay_active'])) {
    unset($_SESSION['overlay_active']);
}

// Add cache busting headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../app/config/database.php';
$db = Database::connect();

// Get current page name for active link
$current_page = basename($_SERVER['PHP_SELF']);

// Get date range from request
$reportType = $_GET['type'] ?? 'monthly';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get view mode from request (default to 'grouped')
$viewMode = $_GET['view'] ?? 'grouped';

// Adjust dates based on report type
switch($reportType) {
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'annual':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
}

// Get consumption summary
$consumptionSummary = $db->prepare("
    SELECT 
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(DISTINCT product_id) as unique_products_used,
        SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as total_donations_received,
        SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as total_consumed,
        COUNT(CASE WHEN type = 'OUT' THEN 1 END) as consumption_events,
        AVG(CASE WHEN type = 'OUT' THEN quantity ELSE NULL END) as avg_consumption_per_event
    FROM stock_movements
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$consumptionSummary->execute([$startDate, $endDate]);
$summary = $consumptionSummary->fetch(PDO::FETCH_ASSOC);

// Ensure summary has values even if query returns null
if (!$summary) {
    $summary = [
        'active_days' => 0,
        'unique_products_used' => 0,
        'total_donations_received' => 0,
        'total_consumed' => 0,
        'consumption_events' => 0,
        'avg_consumption_per_event' => 0
    ];
} else {
    // Convert any null values to 0
    foreach ($summary as $key => $value) {
        if ($value === null) {
            $summary[$key] = 0;
        }
    }
}
// Calculate consumption rate based on available stock
try {
    // Get stock at the beginning of the period
    $startStockQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN created_at < ? AND type = 'IN' THEN quantity ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN created_at < ? AND type = 'OUT' THEN quantity ELSE 0 END), 0) as starting_stock
        FROM stock_movements
    ");
    $startStockQuery->execute([$startDate, $startDate]);
    $startingStock = $startStockQuery->fetch(PDO::FETCH_ASSOC)['starting_stock'];
    
    $totalAvailable = $startingStock + $summary['total_donations_received'];
    $consumptionRate = $totalAvailable > 0 
        ? round(($summary['total_consumed'] / $totalAvailable) * 100, 1)
        : 0;
        
} catch (Exception $e) {
    $consumptionRate = $summary['total_donations_received'] > 0 
        ? round(($summary['total_consumed'] / $summary['total_donations_received']) * 100, 1)
        : 0;
}

// Calculate daily total consumption
$daysInPeriod = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400 + 1);
$dailyTotalConsumption = $daysInPeriod > 0 ? round($summary['total_consumed'] / $daysInPeriod, 1) : 0;

// Get current stock (what's available)
try {
    $stockInQuery = $db->query("
        SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(20,0))), 0) as total_in 
        FROM stock_movements 
        WHERE type = 'IN'
    ");
    $stockIn = $stockInQuery->fetch(PDO::FETCH_ASSOC)['total_in'];
    
    $stockOutQuery = $db->query("
        SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(20,0))), 0) as total_out 
        FROM stock_movements 
        WHERE type = 'OUT'
    ");
    $stockOut = $stockOutQuery->fetch(PDO::FETCH_ASSOC)['total_out'];
    
    $currentStock = $stockIn - $stockOut;
    
    if ($currentStock < 0) {
        $currentStock = 0;
    }
    
} catch (Exception $e) {
    $currentStock = 0;
}

// Get most consumed items with capitalized names
$mostConsumed = $db->prepare("
    SELECT 
        CONCAT(UPPER(LEFT(p.name, 1)), LOWER(SUBSTRING(p.name, 2))) as name,
        p.unit,
        p.min_stock,
        SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END) as total_donated,
        SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END) as total_consumed,
        COUNT(CASE WHEN sm.type = 'OUT' THEN 1 END) as times_used,
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) as current_stock
    FROM products p
    JOIN stock_movements sm ON p.id = sm.product_id
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
    GROUP BY p.id, p.name, p.unit, p.min_stock
    ORDER BY total_consumed DESC
    LIMIT 10
");
$mostConsumed->execute([$startDate, $endDate]);
$allConsumedItems = $mostConsumed->fetchAll(PDO::FETCH_ASSOC);

// Get daily consumption trend
$dailyTrend = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as donations,
        SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as consumption
    FROM stock_movements
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailyTrend->execute([$startDate, $endDate]);
$trendData = $dailyTrend->fetchAll(PDO::FETCH_ASSOC);

// Get category consumption
$categoryConsumption = $db->prepare("
    SELECT 
        c.name,
        SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END) as consumed
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN stock_movements sm ON p.id = sm.product_id 
        AND DATE(sm.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    HAVING consumed > 0
    ORDER BY consumed DESC
");
$categoryConsumption->execute([$startDate, $endDate]);
$categories = $categoryConsumption->fetchAll(PDO::FETCH_ASSOC);

// Get items running low with capitalized names
$lowItemsQuery = $db->query("
    SELECT 
        CONCAT(UPPER(LEFT(p.name, 1)), LOWER(SUBSTRING(p.name, 2))) as name,
        p.unit,
        p.min_stock,
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) as current_stock
    FROM products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    GROUP BY p.id, p.name, p.unit, p.min_stock
    HAVING current_stock <= p.min_stock
    ORDER BY current_stock ASC
    LIMIT 5
");
$allLowItems = $lowItemsQuery->fetchAll(PDO::FETCH_ASSOC);

// Function to format days into years, months, days
function formatTimeRemaining($days) {
    if ($days <= 0) {
        return '<span style="color: #e74c3c; font-weight: bold;">🚫 OUT OF STOCK</span>';
    }
    
    $years = floor($days / 365);
    $months = floor(($days % 365) / 30);
    $remainingDays = floor(($days % 365) % 30);
    
    $parts = [];
    if ($years > 0) $parts[] = $years . ' ' . ($years == 1 ? 'year' : 'years');
    if ($months > 0) $parts[] = $months . ' ' . ($months == 1 ? 'month' : 'months');
    if ($remainingDays > 0 || count($parts) == 0) 
        $parts[] = $remainingDays . ' ' . ($remainingDays == 1 ? 'day' : 'days');
    
    return implode(', ', $parts);
}

// Format large numbers for display
function formatLargeNumber($num) {
    if ($num >= 1000000000) {
        return number_format($num / 1000000000, 2) . 'B';
    } elseif ($num >= 1000000) {
        return number_format($num / 1000000, 2) . 'M';
    } elseif ($num >= 1000) {
        return number_format($num / 1000, 2) . 'K';
    } else {
        return number_format($num);
    }
}

// Capitalize first letter of unit
function formatUnit($unit) {
    return ucfirst(strtolower(trim($unit)));
}

// Calculate if scrolling is needed
$needsScrolling = count($trendData) > 5;

// Dashboard colors for charts
$dashboardColors = [
    'blue' => '#3498db',
    'orange' => '#f39c12',
    'red' => '#e74c3c',
    'green' => '#2ecc71',
    'purple' => '#9b59b6'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Consumption Reports - OCSR Inventory System</title>
    <link rel="icon" type="image/png" href="../img/sunset2.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
        /* Additional Reports-specific styles - preserving original */
        * {
            box-sizing: border-box;
        }
        
        /* UPDATED: Make only main content scrollable */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden; /* Prevent double scrollbars */
            width: 100%;
            position: relative;
        }
        
        .container {
            display: flex;
            height: 100vh; /* Full viewport height */
            overflow: hidden; /* Prevent container scrolling */
            width: 100%;
        }
        
        /* Sidebar - not fixed, use flex layout instead */
        .sidebar {
            width: 280px;
            height: 100vh;
            overflow-y: auto; /* Sidebar scrolls independently if content is long */
            background: white;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }
        
        /* Main content - scrollable area */
        .main {
            flex: 1; /* Take remaining width */
            height: 100vh;
            overflow-y: auto; /* THIS MAKES MAIN CONTENT SCROLLABLE */
            padding: 20px 25px 25px 25px;
            background: #f5f7fa;
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }
        
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        
        .reports-header h1 {
            font-weight: 700;
            letter-spacing: .3px;
            color: #2c3e50;
            margin: 0;
        }
        
        .report-tabs {
            display: flex;
            gap: 10px;
            background: white;
            padding: 15px 20px;
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            color: #666;
            background: #f8f9fa;
            transition: all 0.3s;
            font-size: 14px;
            border: 1px solid #eee;
        }
        
        .tab:hover,
        .tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* Print Button Styles */
        .print-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .print-btn {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: 1px solid #2c3e50;
            font-weight: 500;
        }
        
        .print-btn:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        
        .print-btn i {
            font-size: 18px;
        }
        
        /* ================= SUMMARY CARDS ================= */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .card {
            background: white;
            padding: 18px 15px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: 0.2s ease;
            position: relative;
            overflow: hidden;
            min-width: 0;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }
        
        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.3), transparent);
            opacity: 0;
            transition: .4s;
        }
        
        .card:hover::after {
            opacity: 1;
        }
        
        .card-icon {
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .card h3 {
            margin: 0;
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .card .value {
            font-size: 20px;
            font-weight: bold;
            margin-top: 6px;
            color: #2c3e50;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .card .value.positive { color: #27ae60; }
        .card .value.warning { color: #f39c12; }
        .card .value.neutral { color: #3498db; }
        
        .card small {
            display: block;
            font-size: 10px;
            color: #95a5a6;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Card color indicators */
        .card.donations { border-left: 4px solid #3498db; }
        .card.consumed { border-left: 4px solid #e74c3c; }
        .card.rate { border-left: 4px solid #f39c12; }
        .card.average { border-left: 4px solid #2ecc71; }
        .card.stock { border-left: 4px solid #9b59b6; }
        .card.days { border-left: 4px solid #f1c40f; }
        
        /* Menu toggle button - fixed at top */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #d49f7e;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            line-height: 1;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            min-width: 70px;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .menu-toggle:hover {
            background: #c48b67;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .menu-toggle:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* ULTIMATE OVERLAY KILLER - preserved from index.php */
        .overlay, 
        #overlay, 
        .sidebar-overlay, 
        .modal-backdrop,
        div[class*="overlay"],
        div[id*="overlay"],
        div[style*="background"][style*="rgba"]:empty {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            z-index: -9999 !important;
            width: 0 !important;
            height: 0 !important;
            position: fixed !important;
            left: -9999px !important;
            top: -9999px !important;
        }

        /* Ensure body is clickable */
        body {
            overflow: hidden !important; /* Changed from auto to hidden */
            position: relative !important;
            pointer-events: auto !important;
        }

        /* Ensure main content is clickable */
        .main, .container, .reports-header, .report-tabs, .summary-cards, .charts-grid, .data-table, .card {
            pointer-events: auto !important;
            position: relative !important;
            z-index: 1 !important;
        }

        /* Sidebar should be clickable but not block content */
        .sidebar {
            pointer-events: auto !important;
            z-index: 1000 !important;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
            height: 450px;
            display: flex;
            flex-direction: column;
            width: 100%;
            overflow: hidden;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
            height: 500px;
            width: 100%;
            overflow: hidden;
        }
        
        .chart-card h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
        }
        
        .chart-container {
            flex: 1;
            width: 100%;
            position: relative;
        }
        
        .chart-scroll-container {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            width: 100%;
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            background: #fafbfc;
        }
        
        .chart-wrapper {
            min-width: <?= max(700, count($trendData) * 60) ?>px;
            height: 300px;
            padding: 15px;
        }
        
        .scroll-hint {
            display: <?= $needsScrolling ? 'flex' : 'none' ?>;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 30px;
            font-size: 13px;
            color: #2c3e50;
            width: fit-content;
        }
        
        .view-selector-container {
            background: white;
            border-radius: 14px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
        }
        
        .view-selector {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .view-label {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 600;
            background: #ecf0f1;
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .view-option {
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-option:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        
        .view-option.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* ========== FIXED TABLE STYLES - NO EXCESS WHITE SPACE ========== */
        .data-table {
            background: white;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }
        
        .data-table h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .view-info {
            font-size: 12px;
            color: #7f8c8d;
            background: #ecf0f1;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        /* Main table (6 columns) */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
            table-layout: auto;
        }
        
        /* Items needing donations table (5 columns) - more compact */
        .data-table:last-child table {
            min-width: 500px;
        }
        
        th {
            background: #f4f6f9;
            padding: 12px 8px;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #bdc3c7;
            text-align: center;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 14px;
            text-align: center;
            white-space: nowrap;
        }
        
        td:first-child, th:first-child {
            text-align: left;
            font-weight: 600;
            padding-left: 12px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unit-badge {
            display: inline-block;
            background: #ecf0f1;
            padding: 4px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: bold;
            min-width: 70px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-transform: capitalize;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            min-width: 70px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .badge-critical {
            background: #e74c3c !important;
            color: white !important;
        }
        
        .badge-warning {
            background: #f39c12 !important;
            color: white !important;
        }
        
        .badge-good {
            background: #27ae60 !important;
            color: white !important;
        }
        
        .positive {
            color: #27ae60;
            font-weight: 600;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .color-box {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .quick-summary {
            background:black;
            padding: 20px;
            border-radius: 14px;
            margin-top: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
        }
        
        .quick-summary h3 {
            color: #2e7d32;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .summary-stats {
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        .action-box {
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 500;
        }

        /* ========== UPDATED MOBILE STYLES ========== */
        @media (max-width: 1200px) {
            .summary-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                height: auto !important;
                min-height: 380px;
                margin-bottom: 15px;
                padding: 15px;
            }
            
            .chart-card.full-width {
                height: auto !important;
                min-height: 420px;
                grid-column: 1 / -1;
                padding: 15px;
            }
            
            .chart-container {
                height: 280px;
                width: 100% !important;
                position: relative;
            }
            
            .chart-scroll-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
                min-height: 280px;
                border-radius: 8px;
            }
            
            .chart-wrapper {
                min-width: 700px;
                height: 250px;
                padding: 10px;
            }
            
            .chart-wrapper canvas {
                display: block;
                width: 100% !important;
                height: 100% !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                display: block;
                height: 100vh;
                overflow: hidden;
            }
            
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                width: 280px;
                height: 100vh;
                transition: left 0.3s ease;
                z-index: 1002;
                background: white;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                overflow-y: auto;
            }
            
            .sidebar.show {
                left: 0;
                box-shadow: 2px 0 20px rgba(0,0,0,0.3);
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: #d49f7e;
                color: white;
                border: none;
                padding: 12px 18px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 18px;
                font-weight: 500;
                box-shadow: 0 4px 15px rgba(0,0,0,0.25);
                line-height: 1;
                border: 1px solid rgba(255,255,255,0.2);
                transition: all 0.3s ease;
                min-width: 70px;
                text-align: center;
                letter-spacing: 0.5px;
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
            
            .main {
                margin-left: 0;
                height: 100vh;
                overflow-y: auto; /* STILL SCROLLABLE ON MOBILE */
                padding: 70px 15px 15px 15px;
                width: 100%;
                -webkit-overflow-scrolling: touch;
            }
            
            .reports-header {
                margin-top: 0;
                margin-bottom: 15px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .reports-header h1 {
                font-size: 22px;
                margin-left: 0;
                width: 100%;
                text-align: left;
            }
            
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 20px;
            }
            
            .card {
                padding: 12px 10px;
            }
            
            .card-icon {
                font-size: 16px;
                margin-bottom: 4px;
            }
            
            .card h3 {
                font-size: 10px;
                white-space: normal;
                line-height: 1.2;
                margin-bottom: 4px;
            }
            
            .card .value {
                font-size: 16px;
                margin-top: 2px;
            }
            
            .card small {
                font-size: 9px;
            }
            
            .report-tabs {
                padding: 12px;
                justify-content: center;
                gap: 8px;
            }
            
            .tab {
                padding: 6px 12px;
                font-size: 12px;
                flex: 0 1 auto;
                white-space: nowrap;
            }
            
            .view-selector-container {
                padding: 12px;
            }
            
            .view-selector {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .view-label {
                text-align: center;
                width: 100%;
                padding: 6px;
                font-size: 13px;
            }
            
            .view-option {
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .charts-grid {
                gap: 15px;
            }
            
            .chart-card {
                padding: 12px;
                min-height: 350px;
            }
            
            .chart-card.full-width {
                min-height: 380px;
            }
            
            .chart-card h3 {
                font-size: 15px;
                margin-bottom: 10px;
            }
            
            .chart-container {
                height: 250px !important;
                width: 100% !important;
            }
            
            .chart-scroll-container {
                min-height: 250px;
                overflow-x: auto;
            }
            
            .chart-wrapper {
                min-width: 600px;
                height: 220px;
                padding: 8px;
            }
            
            .scroll-hint {
                display: flex !important;
                justify-content: center;
                align-items: center;
                gap: 8px;
                margin-top: 10px;
                padding: 6px 12px;
                background: #f0f0f0;
                border-radius: 20px;
                font-size: 12px;
                color: #555;
                width: 100%;
            }
            
            .legend {
                flex-wrap: wrap;
                gap: 10px;
                padding: 8px;
                margin-bottom: 10px;
            }
            
            .legend-item {
                font-size: 11px;
                gap: 5px;
            }
            
            .color-box {
                width: 12px;
                height: 12px;
            }
            
            /* Table styles - optimized for mobile with minimal white space */
            .data-table {
                padding: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 15px;
            }
            
            .data-table h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 12px;
            }
            
            .view-info {
                font-size: 11px;
                padding: 4px 8px;
                width: 100%;
                text-align: left;
            }
            
            /* Main table */
            table {
                min-width: 500px;
                font-size: 12px;
            }
            
            /* Items needing donations table - more compact on mobile */
            .data-table:last-child table {
                min-width: 400px;
            }
            
            th {
                padding: 8px 4px;
                font-size: 11px;
                white-space: nowrap;
            }
            
            td {
                padding: 8px 4px;
                font-size: 11px;
            }
            
            td:first-child, th:first-child {
                text-align: left;
                padding-left: 6px;
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .unit-badge {
                min-width: 50px;
                padding: 3px 4px;
                font-size: 10px;
            }
            
            .badge {
                min-width: 55px;
                padding: 3px 4px;
                font-size: 10px;
            }
            
            .quick-summary {
                padding: 15px;
                margin-top: 15px;
            }
            
            .quick-summary h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }
            
            .summary-stats {
                font-size: 13px;
                line-height: 1.6;
            }
            
            .action-box {
                padding: 10px;
                font-size: 13px;
            }
            
            .print-actions {
                margin-top: 15px;
                margin-bottom: 15px;
            }
            
            .print-btn {
                width: 100%;
                padding: 12px 20px;
                font-size: 15px;
                justify-content: center;
            }
            
            /* Fix for iOS scrolling */
            .chart-scroll-container::-webkit-scrollbar,
            .data-table::-webkit-scrollbar {
                height: 4px;
            }
            
            .chart-scroll-container::-webkit-scrollbar-thumb,
            .data-table::-webkit-scrollbar-thumb {
                background: #ccc;
                border-radius: 4px;
            }
        }
        
        @media (max-width: 576px) {
            .menu-toggle {
                top: 12px;
                left: 12px;
                padding: 10px 16px;
                font-size: 16px;
                min-width: 65px;
            }
            
            .main {
                padding: 65px 12px 12px 12px;
            }
            
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .card {
                padding: 10px 8px;
            }
            
            .card .value {
                font-size: 14px;
            }
            
            .card small {
                font-size: 8px;
            }
            
            .reports-header h1 {
                font-size: 20px;
            }
            
            .report-tabs {
                padding: 10px;
            }
            
            .tab {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .chart-card {
                padding: 10px;
                min-height: 320px;
            }
            
            .chart-card.full-width {
                min-height: 350px;
            }
            
            .chart-container {
                height: 220px !important;
            }
            
            .chart-wrapper {
                min-width: 550px;
                height: 200px;
                padding: 5px;
            }
            
            .legend {
                gap: 8px;
            }
            
            .legend-item {
                font-size: 10px;
            }
            
            .data-table {
                padding: 10px;
            }
            
            /* Main table */
            table {
                min-width: 450px;
            }
            
            /* Items needing donations table */
            .data-table:last-child table {
                min-width: 350px;
            }
            
            th, td {
                padding: 6px 3px;
                font-size: 10px;
            }
            
            td:first-child, th:first-child {
                max-width: 100px;
            }
            
            .unit-badge {
                min-width: 45px;
                font-size: 9px;
                padding: 2px 3px;
            }
            
            .badge {
                min-width: 50px;
                font-size: 9px;
                padding: 2px 3px;
            }
            
            .quick-summary {
                padding: 12px;
            }
            
            .quick-summary h3 {
                font-size: 15px;
            }
            
            .summary-stats {
                font-size: 12px;
                line-height: 1.5;
            }
        }

        @media (max-width: 480px) {
            .menu-toggle {
                top: 10px;
                left: 10px;
                padding: 8px 14px;
                font-size: 15px;
                min-width: 60px;
            }
            
            .main {
                padding: 60px 10px 10px 10px;
            }
            
            /* Main table */
            table {
                min-width: 400px;
            }
            
            /* Items needing donations table */
            .data-table:last-child table {
                min-width: 320px;
            }
        }

        /* Landscape mode optimization */
        @media (max-width: 768px) and (orientation: landscape) {
            .menu-toggle {
                top: 10px;
                left: 10px;
                padding: 8px 14px;
            }
            
            .main {
                padding: 60px 15px 15px 15px;
            }
            
            .summary-cards {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .chart-card {
                min-height: 300px;
            }
            
            .chart-card.full-width {
                min-height: 330px;
            }
            
            .chart-container {
                height: 200px !important;
            }
            
            .chart-wrapper {
                min-width: 650px;
                height: 180px;
            }
        }

        /* Print-specific styles - optimized for no white space */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-section,
            .print-section * {
                visibility: visible;
            }
            
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 15px;
                background: white;
            }
            
            .no-print {
                display: none !important;
            }
            
            .data-table {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
                margin-bottom: 15px;
                overflow: visible !important;
                padding: 10px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
                table-layout: fixed;
            }
            
            /* Compact column widths for main table (6 columns) */
            table th:nth-child(1), table td:nth-child(1) { width: 20%; }
            table th:nth-child(2), table td:nth-child(2) { width: 8%; }
            table th:nth-child(3), table td:nth-child(3) { width: 12%; }
            table th:nth-child(4), table td:nth-child(4) { width: 10%; }
            table th:nth-child(5), table td:nth-child(5) { width: 15%; }
            table th:nth-child(6), table td:nth-child(6) { width: 35%; }
            
            /* Items needing donations table (5 columns) - even more compact */
            .data-table:last-child table th:nth-child(1),
            .data-table:last-child table td:nth-child(1) { width: 25%; }
            .data-table:last-child table th:nth-child(2),
            .data-table:last-child table td:nth-child(2) { width: 12%; }
            .data-table:last-child table th:nth-child(3),
            .data-table:last-child table td:nth-child(3) { width: 18%; }
            .data-table:last-child table th:nth-child(4),
            .data-table:last-child table td:nth-child(4) { width: 18%; }
            .data-table:last-child table th:nth-child(5),
            .data-table:last-child table td:nth-child(5) { width: 27%; }
            
            th {
                background: #f0f0f0 !important;
                color: black !important;
                padding: 6px 3px;
                text-align: center;
                border: 1px solid #ddd;
                font-weight: bold;
                font-size: 10px;
                white-space: nowrap;
                text-transform: uppercase;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            td {
                padding: 6px 3px;
                border: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                word-break: break-word;
            }
            
            td:first-child, th:first-child {
                text-align: left;
                padding-left: 6px;
            }
            
            .unit-badge {
                display: inline-block;
                background: #f0f0f0;
                padding: 3px 6px;
                border-radius: 30px;
                font-size: 9px;
                font-weight: bold;
                min-width: 60px;
                text-align: center;
                border: 1px solid #ccc;
                text-transform: capitalize;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .badge {
                display: inline-block;
                padding: 3px 6px;
                border-radius: 30px;
                font-size: 9px;
                font-weight: bold;
                min-width: 60px;
                text-align: center;
                white-space: nowrap;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .badge-critical {
                background: #e74c3c !important;
                color: white !important;
            }
            
            .badge-warning {
                background: #f39c12 !important;
                color: white !important;
            }
            
            .badge-good {
                background: #27ae60 !important;
                color: white !important;
            }
            
            @page {
                size: landscape;
                margin: 0.8cm;
            }
        }
    </style>
</head>
<body>
<!-- HARDCORE OVERLAY REMOVER - Runs immediately -->
<script>
(function() {
    // Function to kill all overlays
    function killAllOverlays() {
        // 1. Remove by common IDs
        const ids = ['overlay', 'sidebar-overlay', 'menu-overlay', 'screen-overlay', 'modal-backdrop'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.remove();
        });
        
        // 2. Remove by common classes
        const classes = ['overlay', 'sidebar-overlay', 'menu-overlay', 'screen-overlay', 'modal-backdrop', 'modal', 'backdrop'];
        classes.forEach(className => {
            document.querySelectorAll('.' + className).forEach(el => el.remove());
        });
        
        // 3. Remove any element with "overlay" in class or id
        document.querySelectorAll('[class*="overlay"], [id*="overlay"]').forEach(el => el.remove());
        
        // 4. Remove any empty divs that might be overlays
        document.querySelectorAll('div:empty, div[style*="background"], div[style*="rgba"]').forEach(el => {
            if (el.children.length === 0 && el.innerHTML.trim() === '') {
                el.remove();
            }
        });
        
        // 5. Reset body styles
        document.body.style.overflow = 'auto';
        document.body.style.position = 'relative';
        document.body.style.pointerEvents = 'auto';
        
        // 6. Ensure main content is clickable
        const mainContent = document.querySelector('.main, main, #main, .content');
        if (mainContent) {
            mainContent.style.pointerEvents = 'auto';
            mainContent.style.position = 'relative';
            mainContent.style.zIndex = '1';
        }
        
        // 7. Fix sidebar on desktop
        if (window.innerWidth > 767) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.style.left = '0';
                sidebar.style.display = 'block';
                sidebar.style.visibility = 'visible';
                sidebar.style.pointerEvents = 'auto';
                sidebar.classList.remove('show', 'active');
            }
        }
    }
    
    // Run immediately
    killAllOverlays();
    
    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', killAllOverlays);
    } else {
        killAllOverlays();
    }
    
    // Run after everything loads
    window.addEventListener('load', killAllOverlays);
    
    // Run repeatedly for the first second
    let count = 0;
    const interval = setInterval(function() {
        killAllOverlays();
        count++;
        if (count > 20) clearInterval(interval);
    }, 100);
})();
</script>

<div class="container">
    <!-- Include the same sidebar as products.php -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main">
        <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>

        <div class="reports-header">
            <h1>📈 Consumption Reports</h1>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs no-print">
            <a href="?type=weekly&view=<?= $viewMode ?>" class="tab <?= $reportType == 'weekly' ? 'active' : '' ?>">This Week</a>
            <a href="?type=monthly&view=<?= $viewMode ?>" class="tab <?= $reportType == 'monthly' ? 'active' : '' ?>">This Month</a>
            <a href="?type=annual&view=<?= $viewMode ?>" class="tab <?= $reportType == 'annual' ? 'active' : '' ?>">This Year</a>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards no-print">
            <div class="card donations">
                <div class="card-icon">📦</div>
                <h3>Donations Received</h3>
                <div class="value positive">+<?= formatLargeNumber($summary['total_donations_received']) ?></div>
                <small><?= $summary['unique_products_used'] ?> products</small>
            </div>
            
            <div class="card consumed">
                <div class="card-icon">🍽️</div>
                <h3>Total Consumed</h3>
                <div class="value"><?= formatLargeNumber($summary['total_consumed']) ?></div>
                <small><?= $summary['unique_products_used'] ?> products</small>
            </div>
            
            <div class="card rate">
                <div class="card-icon">📊</div>
                <h3>Consumption Rate</h3>
                <div class="value <?= $consumptionRate > 80 ? 'positive' : ($consumptionRate > 50 ? 'warning' : 'neutral') ?>">
                    <?= $consumptionRate ?>%
                </div>
                <small>of available stock</small>
            </div>
            
            <div class="card average">
                <div class="card-icon">📅</div>
                <h3>Daily Average</h3>
                <div class="value"><?= formatLargeNumber($dailyTotalConsumption) ?></div>
                <small>items per day</small>
            </div>
            
            <div class="card stock">
                <div class="card-icon">💝</div>
                <h3>Current Stock</h3>
                <div class="value"><?= formatLargeNumber($currentStock) ?></div>
                <small>items available</small>
            </div>
            
            <div class="card days">
                <div class="card-icon">⏰</div>
                <h3>Active Days</h3>
                <div class="value"><?= $summary['active_days'] ?></div>
                <small>days with activity</small>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid no-print">
            <!-- Daily Trend Chart -->
            <div class="chart-card full-width">
                <h3>📈 Daily Consumption vs Donations</h3>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="color-box" style="background: #e74c3c;"></div>
                        <span>Consumption (Items used)</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box" style="background: #3498db;"></div>
                        <span>Donations (Items received)</span>
                    </div>
                </div>
                
                <div class="chart-scroll-container">
                    <div class="chart-wrapper">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <?php if ($needsScrolling): ?>
                <div class="scroll-hint">
                    <span>⬅️ ➡️</span>
                    <span>Scroll horizontally to see all <?= count($trendData) ?> days</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Category Chart -->
            <div class="chart-card">
                <h3>🥗 Consumption by Category</h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Comparison Chart -->
            <div class="chart-card">
                <h3>📊 Donations vs Consumption</h3>
                <div class="chart-container">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>

        <!-- View Mode Selector -->
        <div class="view-selector-container no-print">
            <div class="view-selector">
                <span class="view-label">View Mode:</span>
                <a href="?type=<?= $reportType ?>&view=grouped" class="view-option <?= $viewMode == 'grouped' ? 'active' : '' ?>">
                    <span>📑</span> Grouped by Unit
                </a>
                <a href="?type=<?= $reportType ?>&view=table" class="view-option <?= $viewMode == 'table' ? 'active' : '' ?>">
                    <span>📋</span> Table View
                </a>
            </div>
        </div>

        <!-- Print Section -->
        <div class="print-section">
            <!-- Most Consumed Items -->
            <div class="data-table">
                <div class="table-header">
                    <h3>🍚 Most Consumed Items</h3>
                    <span class="view-info">Period: <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?></span>
                </div>
                
                <?php if (count($allConsumedItems) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>Total Consumed</th>
                            <th>Times Used</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allConsumedItems as $item): 
                            $statusClass = $item['current_stock'] <= 0 ? 'badge-critical' : 
                                          ($item['current_stock'] <= $item['min_stock'] ? 'badge-warning' : 'badge-good');
                            $statusText = $item['current_stock'] <= 0 ? '🚫 OUT OF STOCK' : 
                                         ($item['current_stock'] <= $item['min_stock'] ? '⚠️ LOW STOCK' : '✅ GOOD');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                            <td style="text-align: center;">
                                <span class="unit-badge"><?= htmlspecialchars(formatUnit($item['unit'])) ?></span>
                            </td>
                            <td><strong><?= formatLargeNumber($item['total_consumed']) ?></strong></td>
                            <td><?= $item['times_used'] ?> Times</td>
                            <td><?= formatLargeNumber($item['current_stock']) ?> <span style="color:#7f8c8d;"><?= htmlspecialchars(formatUnit($item['unit'])) ?></span></td>
                            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align:center; padding:40px; color:#666;">No consumption data available</p>
                <?php endif; ?>
            </div>

            <!-- Items Needing Donations -->
            <?php if(count($allLowItems) > 0): ?>
            <div class="data-table" style="border-left: 6px solid #e74c3c; background: #FFB8B8;">
                <div class="table-header">
                    <h3>⚠️ Items Needing Donations</h3>
                    <span class="view-info">Running low on stock</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Minimum Needed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allLowItems as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                            <td style="text-align: center;">
                                <span class="unit-badge"><?= htmlspecialchars(formatUnit($item['unit'])) ?></span>
                            </td>
                            <td><?= formatLargeNumber($item['current_stock']) ?> <span style="color:#7f8c8d;"><?= htmlspecialchars(formatUnit($item['unit'])) ?></span></td>
                            <td><?= formatLargeNumber($item['min_stock']) ?> <span style="color:#7f8c8d;"><?= htmlspecialchars(formatUnit($item['unit'])) ?></span></td>
                            <td><span class="badge badge-critical">🚨 URGENT</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Print Button -->
        <div class="print-actions no-print">
            <button onclick="printTables()" class="print-btn">
                <i>🖨️</i> Print Report
            </button>
        </div>

        <!-- Quick Summary -->
        <div class="quick-summary no-print" style="background:  #C1F5C1; border-left: 6px solid #2e7d32;">
            <h3 style="color: #1b5e20; margin-bottom: 15px; font-size: 18px;">📝 Quick Summary</h3>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="color-box" style="background: #e74c3c;"></div>
                    <span><strong>🔴 CRITICAL</strong> (0-7 days)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background: #f39c12;"></div>
                    <span><strong>⚠️ WARNING</strong> (8-30 days)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background: #f1c40f;"></div>
                    <span><strong>⚡ CAUTION</strong> (31-90 days)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box" style="background: #27ae60;"></div>
                    <span><strong>✅ GOOD</strong> (90+ days)</span>
                </div>
            </div>

            <?php
            $daysRemaining = 0;
            if ($dailyTotalConsumption > 0 && $currentStock > 0) {
                $daysRemaining = floor($currentStock / $dailyTotalConsumption);
            }
            
            $timeRemaining = formatTimeRemaining($daysRemaining);
            
            $urgencyColor = '#27ae60';
            $statusDisplay = '✅ GOOD';
            
            if ($currentStock <= 0) {
                $urgencyColor = '#e74c3c';
                $statusDisplay = '🚫 OUT OF STOCK';
            } elseif ($daysRemaining <= 7) {
                $urgencyColor = '#e74c3c';
                $statusDisplay = '🔴 CRITICAL';
            } elseif ($daysRemaining <= 30) {
                $urgencyColor = '#f39c12';
                $statusDisplay = '⚠️ WARNING';
            } elseif ($daysRemaining <= 90) {
                $urgencyColor = '#f1c40f';
                $statusDisplay = '⚡ CAUTION';
            }
            ?>
            
            <div class="summary-stats">
                • This <strong><?= ucfirst($reportType) ?></strong>: <strong><?= formatLargeNumber($summary['total_consumed']) ?></strong> items consumed<br>
                • Average: <strong><?= formatLargeNumber($dailyTotalConsumption) ?></strong> items per day<br>
                • <strong><?= $consumptionRate ?>%</strong> of available stock used<br>
                • <strong><?= count($allLowItems) ?></strong> items need donations<br>
                • Stock will last: <strong style="color: <?= $urgencyColor ?>;"><?= $timeRemaining ?></strong><br>
                • Status: <strong style="color: <?= $urgencyColor ?>;"><?= $statusDisplay ?></strong>
            </div>
            
            <?php if ($daysRemaining <= 7): ?>
                <div class="action-box" style="background: #fef2f2; border-left: 4px solid #e74c3c;">
                    <strong style="color: #e74c3c;">🔴 CRITICAL:</strong> Call donors NOW! Stock runs out in <?= $timeRemaining ?>.
                </div>
            <?php elseif ($daysRemaining <= 30): ?>
                <div class="action-box" style="background: #fff3e0; border-left: 4px solid #f39c12;">
                    <strong style="color: #f39c12;">⚠️ WARNING:</strong> Contact donors this week. Stock lasts <?= $timeRemaining ?>.
                </div>
            <?php elseif ($daysRemaining <= 90): ?>
                <div class="action-box" style="background: #fff9e6; border-left: 4px solid #f1c40f;">
                    <strong style="color: #b8860b;">⚡ CAUTION:</strong> Monitor usage. Stock lasts <?= $timeRemaining ?>.
                </div>
            <?php else: ?>
                <div class="action-box" style="background: #e8f5e9; border-left: 4px solid #27ae60;">
                    <strong style="color: #27ae60;">✅ GOOD:</strong> Stock levels healthy. Stock lasts <?= $timeRemaining ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
/* ================= SINGLE MENU TOGGLE FUNCTION ================= */
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (sidebar) {
        // Use 'show' class to match CSS
        sidebar.classList.toggle('show');
        
        // Change button text based on sidebar state
        if (sidebar.classList.contains('show')) {
            menuToggle.innerHTML = '✕ Close';
            document.body.style.overflow = 'hidden'; // Prevent scrolling when menu open
        } else {
            menuToggle.innerHTML = '☰ Menu';
            document.body.style.overflow = '';
        }
    }
}

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('show');
            menuToggle.innerHTML = '☰ Menu';
            document.body.style.overflow = '';
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth > 768) {
        if (sidebar) {
            sidebar.classList.remove('show');
        }
        if (menuToggle) {
            menuToggle.innerHTML = '☰ Menu';
        }
        document.body.style.overflow = '';
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide menu button based on screen size
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        menuToggle.style.display = window.innerWidth <= 768 ? 'block' : 'none';
    }
    
    // Remove any stuck overlays
    const overlays = document.querySelectorAll('.overlay, #overlay, .sidebar-overlay');
    overlays.forEach(el => el.remove());
});
</script>
<!-- ============ SIDEBAR TOGGLE FUNCTION ============ -->
<script>
// Store chart instances
let trendChart, categoryChart, comparisonChart;

// Format numbers for display
function formatNumber(num) {
    if (num >= 1000000000) return (num / 1000000000).toFixed(2) + 'B';
    if (num >= 1000000) return (num / 1000000).toFixed(2) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(2) + 'K';
    return num.toString();
}

// Function to resize charts
function resizeCharts() {
    setTimeout(function() {
        if (trendChart) {
            trendChart.resize();
            trendChart.update({
                duration: 0,
                lazy: false
            });
        }
        if (categoryChart) {
            categoryChart.resize();
            categoryChart.update({
                duration: 0,
                lazy: false
            });
        }
        if (comparisonChart) {
            comparisonChart.resize();
            comparisonChart.update({
                duration: 0,
                lazy: false
            });
        }
    }, 100);
}


// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const trendData = <?= json_encode($trendData) ?>;
    const categoryData = <?= json_encode($categories) ?>;
    
    // Trend Chart
    if (document.getElementById('trendChart') && trendData.length > 0) {
        const ctx = document.getElementById('trendChart').getContext('2d');
        const dates = trendData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        trendChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Consumption',
                        data: trendData.map(d => Number(d.consumption) || 0),
                        backgroundColor: '#e74c3c',
                        borderRadius: 6,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Donations',
                        data: trendData.map(d => Number(d.donations) || 0),
                        backgroundColor: '#3498db',
                        borderRadius: 6,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + formatNumber(context.parsed.y) + ' items'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 30,
                            font: { size: window.innerWidth < 768 ? 9 : 11 },
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 768 ? 6 : 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => formatNumber(value),
                            font: { size: window.innerWidth < 768 ? 9 : 11 }
                        }
                    }
                }
            }
        });
    }

    // Category Chart
    if (document.getElementById('categoryChart') && categoryData.length > 0) {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(c => c.name),
                datasets: [{
                    data: categoryData.map(c => Number(c.consumed) || 0),
                    backgroundColor: ['#e74c3c', '#3498db', '#f1c40f', '#2ecc71', '#9b59b6', '#e67e22'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: window.innerWidth < 768 ? 10 : 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.label + ': ' + formatNumber(context.raw) + ' items'
                        }
                    }
                }
            }
        });
    }

    // Comparison Chart
    if (document.getElementById('comparisonChart')) {
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        comparisonChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Donations', 'Consumption', 'Current Stock'],
                datasets: [{
                    data: [
                        <?= $summary['total_donations_received'] ?>,
                        <?= $summary['total_consumed'] ?>,
                        <?= $currentStock ?>
                    ],
                    backgroundColor: ['#3498db', '#e74c3c', '#2ecc71'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => formatNumber(context.raw) + ' items'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => formatNumber(value),
                            font: { size: window.innerWidth < 768 ? 9 : 11 }
                        }
                    }
                }
            }
        });
    }
    
    // Initial resize
    setTimeout(resizeCharts, 200);
});

// Print function for tables only
function printTables() {
    const printWindow = window.open('', '_blank');
    const printSection = document.querySelector('.print-section').cloneNode(true);
    printSection.querySelectorAll('.no-print').forEach(el => el.remove());
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Consumption Report - <?= date('M d, Y', strtotime($startDate)) ?> to <?= date('M d, Y', strtotime($endDate)) ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    margin: 0;
                    padding: 15px;
                    background: white;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #333;
                }
                .print-header h1 {
                    margin: 0 0 5px;
                    color: #2c3e50;
                    font-size: 20px;
                    font-weight: bold;
                }
                .print-header p {
                    margin: 3px 0;
                    color: #555;
                    font-size: 12px;
                }
                .data-table {
                    margin-bottom: 25px;
                    page-break-inside: avoid;
                    overflow-x: visible !important;
                }
                .data-table h3 {
                    margin: 0 0 10px 0;
                    color: #2c3e50;
                    font-size: 16px;
                    font-weight: bold;
                }
                .view-info {
                    color: #666;
                    font-size: 11px;
                    margin-bottom: 8px;
                    font-style: italic;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #999;
                    table-layout: fixed;
                }
                /* Main table (6 columns) */
                table th:nth-child(1), table td:nth-child(1) { width: 20%; }
                table th:nth-child(2), table td:nth-child(2) { width: 8%; }
                table th:nth-child(3), table td:nth-child(3) { width: 12%; }
                table th:nth-child(4), table td:nth-child(4) { width: 10%; }
                table th:nth-child(5), table td:nth-child(5) { width: 15%; }
                table th:nth-child(6), table td:nth-child(6) { width: 35%; }
                
                /* Items needing donations table (5 columns) */
                .data-table:last-child table th:nth-child(1),
                .data-table:last-child table td:nth-child(1) { width: 25%; }
                .data-table:last-child table th:nth-child(2),
                .data-table:last-child table td:nth-child(2) { width: 12%; }
                .data-table:last-child table th:nth-child(3),
                .data-table:last-child table td:nth-child(3) { width: 18%; }
                .data-table:last-child table th:nth-child(4),
                .data-table:last-child table td:nth-child(4) { width: 18%; }
                .data-table:last-child table th:nth-child(5),
                .data-table:last-child table td:nth-child(5) { width: 27%; }
                
                th {
                    background: #e6e6e6 !important;
                    color: #000 !important;
                    padding: 6px 3px;
                    text-align: center;
                    border: 1px solid #999;
                    font-weight: bold;
                    font-size: 10px;
                    text-transform: uppercase;
                }
                td {
                    padding: 6px 3px;
                    border: 1px solid #ccc;
                    text-align: center;
                    font-size: 10px;
                    vertical-align: middle;
                }
                td:first-child {
                    text-align: left;
                    padding-left: 6px;
                    font-weight: 500;
                }
                .unit-badge {
                    display: inline-block;
                    background: #f2f2f2;
                    padding: 3px 6px;
                    border-radius: 30px;
                    font-size: 9px;
                    font-weight: bold;
                    min-width: 60px;
                    text-align: center;
                    border: 1px solid #ccc;
                    text-transform: capitalize;
                }
                .badge {
                    display: inline-block;
                    padding: 3px 6px;
                    border-radius: 30px;
                    font-size: 9px;
                    font-weight: bold;
                    min-width: 60px;
                    text-align: center;
                }
                .badge-critical { background: #e74c3c !important; color: white !important; }
                .badge-warning { background: #f39c12 !important; color: white !important; }
                .badge-good { background: #27ae60 !important; color: white !important; }
                .print-footer {
                    margin-top: 20px;
                    text-align: right;
                    font-size: 9px;
                    color: #999;
                    border-top: 1px dashed #ccc;
                    padding-top: 5px;
                }
                @media print {
                    body { padding: 0; }
                    @page { size: landscape; margin: 0.8cm; }
                }
                @media (max-width: 768px) {
                    body { padding: 10px; }
                    .print-header h1 { font-size: 18px; }
                    .data-table { overflow-x: auto; }
                    table { min-width: 700px; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>CONSUMPTION REPORT</h1>
                <p><strong>Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> - <?= date('F d, Y', strtotime($endDate)) ?></p>
                <p><strong>Generated:</strong> <?= date('F d, Y H:i:s') ?></p>
            </div>
            ${printSection.outerHTML}
            <div class="print-footer">
                <p>Report generated by OCSR Inventory System</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.onload = function() { printWindow.print(); };
}
</script>

</body>
</html>