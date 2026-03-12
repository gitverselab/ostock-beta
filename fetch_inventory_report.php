<?php
include 'db.php';

// --- Securely Build the Query ---

// Start with the base SQL query
$sql = "SELECT items.name, inventory.*, warehouses.name AS warehouse_name 
        FROM inventory 
        JOIN items ON inventory.item_id = items.id
        JOIN warehouses ON inventory.warehouse_id = warehouses.id";

// Initialize arrays for conditions and parameters
$conditions = [];
$params = [];
$types = ""; // This string will hold the data types for bind_param (e.g., "iis")

// Dynamically add conditions and parameters based on GET input
if (!empty($_GET['item_id'])) {
    $conditions[] = "inventory.item_id = ?";
    $params[] = $_GET['item_id'];
    $types .= "i"; // 'i' for integer
}
if (!empty($_GET['warehouse_id'])) {
    $conditions[] = "inventory.warehouse_id = ?";
    $params[] = $_GET['warehouse_id'];
    $types .= "i"; // 'i' for integer
}
if (!empty($_GET['start_date'])) {
    $conditions[] = "DATE(inventory.date_received) >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s"; // 's' for string
}
if (!empty($_GET['end_date'])) {
    $conditions[] = "DATE(inventory.date_received) <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s"; // 's' for string
}

// If there are conditions, append them to the SQL query
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Always order the results
$sql .= " ORDER BY inventory.date_received DESC";

// Handle pagination limit
$limit = $_GET['limit'] ?? 20;
if (strtoupper($limit) !== 'ALL') {
    $sql .= " LIMIT ?";
    $params[] = (int)$limit;
    $types .= "i";
}

// --- Prepare and Execute the Query ---
$stmt = $conn->prepare($sql);

// Bind the parameters if any exist
if (!empty($params)) {
    // The "..." is the spread operator, which passes array elements as arguments
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// --- Output the HTML ---
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['quantity']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['uom']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['items_per_pc']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['production_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['expiry_date']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['pallet_id']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['warehouse_name']) . "</td>
            <td class='text-center'>" . htmlspecialchars($row['date_received']) . "</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='9' class='text-center'>No records found.</td></tr>";
}

$stmt->close();
$conn->close();
?>