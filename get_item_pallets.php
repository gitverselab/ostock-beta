<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

if (isset($_POST['item_id']) && isset($_POST['warehouse_id'])) {
    $item_id = intval($_POST['item_id']);
    $warehouse_id = intval($_POST['warehouse_id']);
    
    // Query inventory to return only pallets from the specified warehouse for the given item.
    $query = "SELECT pallet_id, quantity, items_per_pc, production_date 
              FROM inventory 
              WHERE item_id = '$item_id' AND warehouse_id = '$warehouse_id'
              ORDER BY pallet_id ASC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>" . htmlspecialchars($row['pallet_id']) . "</td>
                    <td>" . htmlspecialchars($row['quantity']) . "</td>
                    <td>" . htmlspecialchars($row['items_per_pc']) . "</td>
                    <td>" . htmlspecialchars($row['production_date']) . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center'>No pallets found for this item in this warehouse</td></tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-center'>Invalid parameters</td></tr>";
}
?>
