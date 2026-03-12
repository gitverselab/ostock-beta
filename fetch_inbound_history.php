<?php
include 'db.php';

// Start SQL query
$sql = "SELECT inventory.id, items.name, inventory.pallet_id, inventory.quantity, inventory.uom, 
               inventory.items_per_pc, inventory.expiry_date, inventory.production_date, inventory.date_received, inventory.processed_by,
               warehouses.name AS warehouse_name
        FROM inventory 
        JOIN items ON inventory.item_id = items.id 
        JOIN warehouses ON inventory.warehouse_id = warehouses.id
        WHERE 1=1";

// Apply filters
if (!empty($_GET['item_id'])) {
    $item_id = $conn->real_escape_string($_GET['item_id']);
    $sql .= " AND inventory.item_id = '$item_id'";
}
if (!empty($_GET['warehouse_id'])) {
    $warehouse_id = $conn->real_escape_string($_GET['warehouse_id']);
    $sql .= " AND inventory.warehouse_id = '$warehouse_id'";
}
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $sql .= " AND DATE(inventory.date_received) BETWEEN '$start_date' AND '$end_date'";
}

// Pagination logic
$limit = isset($_GET['limit']) ? $_GET['limit'] : 20;
if ($limit !== 'ALL') {
    $sql .= " ORDER BY inventory.date_received DESC LIMIT $limit";
}

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['warehouse_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['pallet_id']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['quantity']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['uom']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['items_per_pc']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['production_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['expiry_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['date_received']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['processed_by']) . "</td>
            <td>
                <a href='edit_inbound.php?id=" . $row['id'] . "' class='btn btn-warning btn-sm'>Edit</a>
                <a href='delete_inbound.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='11' class='text-center'>No records found.</td></tr>";
}
?>
