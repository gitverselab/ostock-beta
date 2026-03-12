<?php
include 'db.php';

// --- Securely Build the Query ---

$sql = "SELECT oi.id, i.name, oi.pallet_id, oi.quantity_removed, oi.items_per_pc, 
               oi.production_date, oi.expiry_date, oi.outbound_type, oi.date_removed, oi.processed_by,
               w.name as warehouse_name
        FROM outbound_inventory oi
        JOIN items i ON oi.item_id = i.id
        JOIN warehouses w ON oi.warehouse_id = w.id";

$conditions = [];
$params = [];
$types = "";

if (!empty($_GET['item_id'])) {
    $conditions[] = "oi.item_id = ?";
    $params[] = $_GET['item_id'];
    $types .= "i";
}
if (!empty($_GET['warehouse_id'])) {
    $conditions[] = "oi.warehouse_id = ?";
    $params[] = $_GET['warehouse_id'];
    $types .= "i";
}
if (!empty($_GET['start_date'])) {
    $conditions[] = "DATE(oi.date_removed) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (!empty($_GET['end_date'])) {
    $conditions[] = "DATE(oi.date_removed) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY oi.date_removed DESC";

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
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['warehouse_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['pallet_id']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['quantity_removed']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['items_per_pc']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['production_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['expiry_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['outbound_type']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['date_removed']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['processed_by']) . "</td>
            <td>
                <a href='edit_outbound.php?id=" . $row['id'] . "' class='btn btn-warning btn-sm'>Edit</a>
                <a href='delete_outbound.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='11' class='text-center'>No records found.</td></tr>";
}

$stmt->close();
$conn->close();
?>