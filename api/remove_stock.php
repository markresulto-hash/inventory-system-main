<?php
require_once '../app/config/database.php';
$db = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/stock_out.php");
    exit;
}

/* ================= INPUT ================= */

$product_id = $_POST['product_id'] ?? null;
$quantity   = $_POST['quantity'] ?? null;
$reason     = trim($_POST['reason'] ?? '') ?: null;

/* ================= VALIDATION ================= */

if (!$product_id || !$quantity) {
    header("Location: ../public/stock_out.php?error=missing");
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    header("Location: ../public/stock_out.php?error=invalid_quantity");
    exit;
}

$product_id = (int)$product_id;
$quantity   = (int)$quantity;

try {

    /* =====================================================
       START TRANSACTION (CRITICAL FOR INVENTORY SAFETY)
    ===================================================== */
    $db->beginTransaction();


    /* =====================================================
       LOCK PRODUCT STOCK (PREVENT CONCURRENT STOCK OUT)
    ===================================================== */

    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                END
            ),0) AS current_stock
        FROM stock_movements
        WHERE product_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$product_id]);
    $currentStock = (int)$stmt->fetchColumn();

    /* PREVENT NEGATIVE STOCK */
    if ($quantity > $currentStock) {
        throw new Exception('Insufficient stock');
    }


    /* =====================================================
       FIFO — GET AVAILABLE BATCHES (OLDEST FIRST)
    ===================================================== */

    $stmt = $db->prepare("
        SELECT 
            expiry_date,
            SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                END
            ) AS stock
        FROM stock_movements
        WHERE product_id = ?
        GROUP BY expiry_date
        HAVING stock > 0
        ORDER BY 
            expiry_date IS NULL,   -- NULL expiry goes last
            expiry_date ASC
        FOR UPDATE
    ");

    $stmt->execute([$product_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remaining = $quantity;


    /* =====================================================
       DEDUCT STOCK USING FIFO
    ===================================================== */

    $insert = $db->prepare("
        INSERT INTO stock_movements
        (product_id, type, quantity, reason, expiry_date, created_at)
        VALUES (?, 'OUT', ?, ?, ?, NOW())
    ");

    foreach ($batches as $batch) {

        if ($remaining <= 0) {
            break;
        }

        $availableStock = (int)$batch['stock'];

        if ($availableStock <= 0) {
            continue;
        }

        $deduct = min($availableStock, $remaining);

        $insert->execute([
            $product_id,
            $deduct,
            $reason,
            $batch['expiry_date']
        ]);

        $remaining -= $deduct;
    }


    /* =====================================================
       FINAL SAFETY CHECK
    ===================================================== */

    if ($remaining > 0) {
        throw new Exception('FIFO deduction failed');
    }


    /* =====================================================
       COMMIT TRANSACTION
    ===================================================== */
    $db->commit();

    header("Location: ../public/stock_out.php?success=1&product_id=" . $product_id);
    exit;

} catch (Exception $e) {

    /* =====================================================
       ROLLBACK EVERYTHING IF ANY ERROR OCCURS
    ===================================================== */
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    header("Location: ../public/stock_out.php?error=insufficient");
    exit;
}
