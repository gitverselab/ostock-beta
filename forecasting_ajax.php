<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
include 'db.php';
header('Content-Type: application/json');

$forecast_data = json_decode(file_get_contents('php://input'), true);

if (empty($forecast_data)) {
    echo json_encode(['success' => false, 'message' => 'No forecast data provided.']);
    exit;
}

$production_summary = [];
$raw_material_totals = [];

try {
    foreach ($forecast_data as $forecast_item) {
        $finished_good_id = intval($forecast_item['item_id']);
        $forecasted_qty = intval($forecast_item['quantity']);
        $buffer_qty = intval($forecast_item['buffer']); // NEW: Get buffer quantity

        // NEW: Calculate total demand
        $total_demand = $forecasted_qty + $buffer_qty;

        if ($total_demand <= 0) continue;

        $bom_stmt = $conn->prepare("SELECT id, bom_name, yield_quantity FROM boms WHERE finished_good_item_id = ? LIMIT 1");
        $bom_stmt->bind_param("i", $finished_good_id);
        $bom_stmt->execute();
        $bom = $bom_stmt->get_result()->fetch_assoc();
        $bom_stmt->close();

        if (!$bom) continue;

        // NEW: Calculate batches based on total demand
        $batches_needed = ceil($total_demand / $bom['yield_quantity']);
        $total_yield = $batches_needed * $bom['yield_quantity'];

        $item_name_stmt = $conn->prepare("SELECT name FROM items WHERE id = ?");
        $item_name_stmt->bind_param("i", $finished_good_id);
        $item_name_stmt->execute();
        $finished_good_name = $item_name_stmt->get_result()->fetch_assoc()['name'];
        $item_name_stmt->close();

        // Add new fields to the summary
        $production_summary[] = [
            'finished_good_name' => $finished_good_name,
            'forecasted_qty' => $forecasted_qty,
            'buffer_qty' => $buffer_qty,
            'total_demand' => $total_demand,
            'bom_yield' => $bom['yield_quantity'],
            'batches_to_run' => $batches_needed,
            'total_yield' => $total_yield
        ];

        $bom_items_stmt = $conn->prepare("SELECT bi.raw_material_item_id, bi.quantity_required, i.name, i.uom FROM bom_items bi JOIN items i ON bi.raw_material_item_id = i.id WHERE bi.bom_id = ?");
        $bom_items_stmt->bind_param("i", $bom['id']);
        $bom_items_stmt->execute();
        $bom_items_result = $bom_items_stmt->get_result();
        
        while ($item = $bom_items_result->fetch_assoc()) {
            $raw_material_id = $item['raw_material_item_id'];
            $total_needed_for_this_bom = $item['quantity_required'] * $batches_needed;

            if (!isset($raw_material_totals[$raw_material_id])) {
                $raw_material_totals[$raw_material_id] = ['name' => $item['name'], 'uom' => $item['uom'], 'total_required' => 0];
            }
            $raw_material_totals[$raw_material_id]['total_required'] += $total_needed_for_this_bom;
        }
        $bom_items_stmt->close();
    }

    echo json_encode([
        'success' => true,
        'production_summary' => $production_summary,
        'raw_materials' => array_values($raw_material_totals)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>