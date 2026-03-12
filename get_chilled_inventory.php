<?php
// get_chilled_inventory.php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit;
}
include 'db.php';

$warehouse_id = $_POST['warehouse_id'] ?? '';
$item_id = $_POST['item_id'] ?? '';

if (!$warehouse_id || !$item_id) {
    echo "<p>Please select a valid warehouse and item.</p>";
    exit;
}

$sql = "SELECT * FROM inventory WHERE item_id = ? AND warehouse_id = ? ORDER BY pallet_id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $item_id, $warehouse_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo "<div class='form-check'>";
    while ($row = $result->fetch_assoc()) {
        echo "<div>
                <input class='form-check-input' type='checkbox' name='inventory_ids[]' value='" . $row['id'] . "' id='inv_" . $row['id'] . "'>
                <label class='form-check-label' for='inv_" . $row['id'] . "'>
                    Pallet: " . htmlspecialchars($row['pallet_id']) . " | Qty: " . htmlspecialchars($row['quantity']) . " | Items/PC: " . htmlspecialchars($row['items_per_pc']) . " | Prod: " . htmlspecialchars($row['production_date']) . " | Exp: " . htmlspecialchars($row['expiry_date']) . "
                </label>
              </div>";
    }
    echo "</div>";
} else {
    echo "<p>No inventory records found for this item in the selected warehouse.</p>";
}
$stmt->close();
?>
