<?php
include 'db.php';

if (isset($_POST['item_id']) && isset($_POST['warehouse_id'])) {
    $item_id = intval($_POST['item_id']);
    $warehouse_id = intval($_POST['warehouse_id']);
    
    // Adjust the query to filter pallets by the specified warehouse.
    // For instance, if your pallets are stored in the inventory table:
    $query = "SELECT DISTINCT pallet_id FROM inventory 
              WHERE item_id = '$item_id' AND warehouse_id = '$warehouse_id'
              ORDER BY pallet_id ASC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<option value=''>-- Select Pallet --</option>";
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($row['pallet_id']) . "'>" . htmlspecialchars($row['pallet_id']) . "</option>";
        }
    } else {
        echo "<option value=''>No pallets available</option>";
    }
} else {
    echo "<option value=''>Invalid parameters</option>";
}
?>
