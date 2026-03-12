<?php
include 'db.php';

// --- Securely Build the Query ---
$sql = "SELECT t.id, i.name AS item_name, 
               sw.name AS source_warehouse_name, t.source_pallet,
               dw.name AS destination_warehouse_name, t.dest_pallet,
               t.quantity_transferred, t.pieces_transferred, t.date_transferred,
               t.processed_by
        FROM transfers t
        JOIN items i ON t.item_id = i.id
        JOIN warehouses sw ON t.source_warehouse = sw.id
        JOIN warehouses dw ON t.destination_warehouse = dw.id";

$conditions = [];
$params = [];
$types = "";

if (!empty($_GET['item_id'])) {
    $conditions[] = "t.item_id = ?";
    $params[] = $_GET['item_id'];
    $types .= "i";
}
if (!empty($_GET['source_warehouse'])) {
    $conditions[] = "t.source_warehouse = ?";
    $params[] = $_GET['source_warehouse'];
    $types .= "i";
}
if (!empty($_GET['destination_warehouse'])) {
    $conditions[] = "t.destination_warehouse = ?";
    $params[] = $_GET['destination_warehouse'];
    $types .= "i";
}
if (!empty($_GET['start_date'])) {
    $conditions[] = "DATE(t.date_transferred) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (!empty($_GET['end_date'])) {
    $conditions[] = "DATE(t.date_transferred) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY t.date_transferred DESC";

$limit = $_GET['limit'] ?? 20;
if (strtoupper($limit) !== 'ALL') {
    $sql .= " LIMIT ?";
    $params[] = (int)$limit;
    $types .= "i";
}

// --- Prepare and Execute the Query ---
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Output the HTML ---
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td class='text-center'>" . htmlspecialchars($row['id']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['item_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['source_warehouse_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['source_pallet']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['destination_warehouse_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['dest_pallet']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['quantity_transferred']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['pieces_transferred']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['date_transferred']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['processed_by']) . "</td>
            <td class='text-center'>
                <a href='edit_transfer.php?transfer_id=" . htmlspecialchars($row['id']) . "' class='btn btn-warning btn-sm'>Edit</a>
                <a href='delete_transfer.php?transfer_id=" . htmlspecialchars($row['id']) . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this transfer?\")'>Delete</a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='11' class='text-center'>No records found.</td></tr>";
}

$stmt->close();
$conn->close();
?>