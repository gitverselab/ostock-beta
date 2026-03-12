<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';
header('Content-Type: application/json');

$response = [
    "error" => false,
    "message" => "",
    "table_rows" => "",
    "pagination" => ""
];

try {
    $item_id      = $_GET['item_id'] ?? '';
    $warehouse_id = $_GET['warehouse_id'] ?? '';
    $start_date   = $_GET['start_date'] ?? '';
    $end_date     = $_GET['end_date'] ?? '';
    $limit_param  = $_GET['limit'] ?? '20';
    $page         = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) { $page = 1; }

    $conditions = [];
    $params = [];
    $types = "";

    if ($item_id !== '') {
        $conditions[] = "h.item_id = ?";
        $params[] = $item_id;
        $types .= "i";
    }
    if ($warehouse_id !== '') {
        $conditions[] = "h.warehouse_id = ?";
        $params[] = $warehouse_id;
        $types .= "i";
    }
    if ($start_date !== '') {
        $conditions[] = "h.transaction_date >= ?";
        $params[] = $start_date . " 00:00:00";
        $types .= "s";
    }
    if ($end_date !== '') {
        $conditions[] = "h.transaction_date <= ?";
        $params[] = $end_date . " 23:59:59";
        $types .= "s";
    }

    $where = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

    // Count total rows for pagination
    $count_sql = "SELECT COUNT(*) AS total FROM inventory_history h $where";
    $stmt_count = $conn->prepare($count_sql);
    if (!$stmt_count) throw new Exception("Count prepare failed: " . $conn->error);
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

    $limit_val = (strtoupper($limit_param) === "ALL") ? $total_rows : intval($limit_param);
    if ($limit_val <= 0) $limit_val = 20;
    $offset = ($page - 1) * $limit_val;

    // Build query for data retrieval
    $data_sql = "SELECT h.*, i.name as item_name, w.name as warehouse_name 
                 FROM inventory_history h
                 JOIN items i ON h.item_id = i.id
                 LEFT JOIN warehouses w ON h.warehouse_id = w.id
                 $where ORDER BY h.transaction_date DESC";
    if (strtoupper($limit_param) !== "ALL") {
        $data_sql .= " LIMIT ? OFFSET ?";
    }
    
    $stmt = $conn->prepare($data_sql);
    if (!$stmt) throw new Exception("Data query prepare failed: " . $conn->error);
    
    if (strtoupper($limit_param) === "ALL") {
        if (!empty($params)) $stmt->bind_param($types, ...$params);
    } else {
        $types .= "ii";
        $params[] = $limit_val;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $table_rows = "";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $table_rows .= "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['transaction_type']) . "</td>
                <td>" . htmlspecialchars($row['reference_id']) . "</td>
                <td>" . htmlspecialchars($row['item_name']) . "</td>
                <td>" . htmlspecialchars($row['pallet_id']) . "</td>
                <td>" . htmlspecialchars($row['warehouse_name']) . "</td>
                <td>" . htmlspecialchars($row['quantity']) . "</td>
                <td>" . htmlspecialchars($row['items_per_pc']) . "</td>
                <td>" . htmlspecialchars($row['transaction_date']) . "</td>
                <td>" . htmlspecialchars($row['processed_by']) . "</td>
                <td style='word-wrap:break-word; max-width: 250px;'>" . htmlspecialchars($row['details']) . "</td>
            </tr>";
        }
    } else {
         $table_rows = "<tr><td colspan='11' class='text-center'>No records found.</td></tr>";
    }
    $response["table_rows"] = $table_rows;

    // Generate pagination HTML if not ALL
    if (strtoupper($limit_param) !== "ALL" && $limit_val > 0) {
        // Pagination logic here (can be added if needed)
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = true;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>