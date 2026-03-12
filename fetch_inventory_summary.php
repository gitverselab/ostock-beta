<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}
include 'db.php';

// Retrieve filters from GET parameters
$item_id      = isset($_GET['item_id']) ? trim($_GET['item_id']) : "";
$warehouse_id = isset($_GET['warehouse_id']) ? trim($_GET['warehouse_id']) : "";
$start_date   = isset($_GET['start_date']) ? trim($_GET['start_date']) : "";
$end_date     = isset($_GET['end_date']) ? trim($_GET['end_date']) : "";

// Build WHERE clause for filtering (using inventory.date_received)
$where = "WHERE 1 ";
$params = [];
$types = "";
if ($item_id !== "") {
    $where .= " AND inventory.item_id = ? ";
    $params[] = $item_id;
    $types .= "i";
}
if ($warehouse_id !== "") {
    $where .= " AND inventory.warehouse_id = ? ";
    $params[] = $warehouse_id;
    $types .= "i";
}
if ($start_date !== "") {
    $where .= " AND inventory.date_received >= ? ";
    $params[] = $start_date . " 00:00:00";
    $types .= "s";
}
if ($end_date !== "") {
    $where .= " AND inventory.date_received <= ? ";
    $params[] = $end_date . " 23:59:59";
    $types .= "s";
}

// Query to aggregate inventory data (grouping by item and warehouse)
$query = "SELECT items.name AS item_name, warehouses.name AS warehouse_name, 
          SUM(inventory.quantity) AS total_crates, 
          SUM(inventory.items_per_pc) AS total_items_per_pc
          FROM inventory
          JOIN items ON inventory.item_id = items.id
          JOIN warehouses ON inventory.warehouse_id = warehouses.id
          $where
          GROUP BY inventory.item_id, inventory.warehouse_id
          ORDER BY items.name";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "Error: " . $conn->error;
    exit;
}
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$output = "";
if ($result && $result->num_rows > 0) {
    $output .= "<table class='table table-bordered'>";
    $output .= "<thead class='table-dark'><tr>
                <th>Item Name</th>
                <th>Warehouse</th>
                <th>Total Crates</th>
                <th>Total Items Per PC</th>
                </tr></thead><tbody>";
    while ($row = $result->fetch_assoc()) {
        $output .= "<tr>";
        $output .= "<td>" . htmlspecialchars($row['item_name'] ?? '') . "</td>";
        $output .= "<td>" . htmlspecialchars($row['warehouse_name'] ?? '') . "</td>";
        $output .= "<td>" . htmlspecialchars($row['total_crates'] ?? '') . "</td>";
        $output .= "<td>" . htmlspecialchars($row['total_items_per_pc'] ?? '') . "</td>";
        $output .= "</tr>";
    }
    $output .= "</tbody></table>";
} else {
    $output .= "<p class='text-center'>No inventory summary data available.</p>";
}

$stmt->close();
echo $output;
?>
