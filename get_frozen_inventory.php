<?php
// get_frozen_inventory.php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $warehouse_id  = $_POST['warehouse_id'];
    $frozen_item_id = $_POST['item_id'];

    $stmt = $conn->prepare("
      SELECT id, pallet_id, quantity, items_per_pc, production_date, expiry_date, uom
        FROM inventory
       WHERE warehouse_id = ?
         AND item_id = ?
    ");
    $stmt->bind_param("ii", $warehouse_id, $frozen_item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<div class="row">';
        while ($row = $result->fetch_assoc()) {
            $invId       = htmlspecialchars($row['id']);
            $palletId    = htmlspecialchars($row['pallet_id']);
            $qty         = htmlspecialchars($row['quantity']);
            $itemsPerPC  = htmlspecialchars($row['items_per_pc']);
            $prodDate    = htmlspecialchars($row['production_date']);
            $expDate     = htmlspecialchars($row['expiry_date']);
            $uom         = htmlspecialchars($row['uom']);

            echo '
            <div class="col-md-4 mb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="inventory_ids[]" 
                       value="'. $invId .'" 
                       id="inv_'.$invId.'">
                <label class="form-check-label" for="inv_'.$invId.'">
                  Pallet: '. $palletId .'<br>
                  Qty: '. $qty .' '. $uom .'<br>
                  Production: '. $prodDate .'<br>
                  Expiry: '. $expDate .'<br>
                  Per PC: '. $itemsPerPC .'
                </label>
              </div>
            </div>';
        }
        echo '</div>';
    } else {
        echo "<p class='text-muted'>No frozen inventory found for that warehouse/item.</p>";
    }
    $stmt->close();
}
?>
