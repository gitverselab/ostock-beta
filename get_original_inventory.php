<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // not logged in or missing session
    exit;
}

include 'db.php';
// show any PHP errors to the browser
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_POST['warehouse_id'], $_POST['item_id'])) {
    echo "<p class='text-danger'>Missing parameters. Got: "
       . var_export($_POST, true) ."</p>";
    exit;
}

$warehouse_id = intval($_POST['warehouse_id']);
$item_id      = intval($_POST['item_id']);

// prepare & execute
$stmt = $conn->prepare("
    SELECT id, pallet_id, quantity, items_per_pc, production_date, expiry_date
      FROM inventory
     WHERE warehouse_id = ?
       AND item_id      = ?
     ORDER BY production_date ASC
");
if (!$stmt) {
    echo "<p class='text-danger'>Prepare failed: {$conn->error}</p>";
    exit;
}
$stmt->bind_param("ii", $warehouse_id, $item_id);

if (!$stmt->execute()) {
    echo "<p class='text-danger'>Execute failed: {$stmt->error}</p>";
    exit;
}

$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo "<p class='text-muted'>No pallets found for warehouse_id={$warehouse_id}, item_id={$item_id}</p>";
    exit;
}

// render checkboxes
while ($row = $res->fetch_assoc()) {
    echo "<div class='form-check mb-1'>";
    echo "  <input class='form-check-input' type='checkbox' name='inventory_ids[]' value='{$row['id']}' id='inv{$row['id']}'>";
    echo "  <label class='form-check-label' for='inv{$row['id']}'>";
    echo      "Pallet {$row['pallet_id']} — Qty: {$row['quantity']} ×{$row['items_per_pc']} — Prod: {$row['production_date']} — Exp: {$row['expiry_date']}";
    echo "  </label>";
    echo "</div>";
}

$stmt->close();
