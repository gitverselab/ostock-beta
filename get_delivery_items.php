<?php
session_start();
include 'db.php';

if (!isset($_POST['delivery_id'])) {
    echo "No delivery ID provided.";
    exit;
}

$delivery_id = intval($_POST['delivery_id']);

// Retrieve delivery detail records for this delivery
$sql = "SELECT di.*, i.name AS item_name 
        FROM delivery_items di 
        JOIN items i ON di.item_id = i.id 
        WHERE di.delivery_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table class='table table-bordered'>
            <thead class='table-dark'>
              <tr>
                <th>Item Name</th>
                <th>Pallet ID</th>
                <th>Crates Delivered</th>
                <th>Pieces Delivered</th>
              </tr>
            </thead>
            <tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='text-center'>
                <td>" . htmlspecialchars($row['item_name']) . "</td>
                <td>" . htmlspecialchars($row['pallet_id']) . "</td>
                <td>" . htmlspecialchars($row['quantity_removed']) . "</td>
                <td>" . htmlspecialchars($row['items_per_pc']) . "</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No delivery items found.</p>";
}
$stmt->close();
$conn->close();
?>
