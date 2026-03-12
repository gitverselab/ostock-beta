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
    'error' => false,
    'table_rows' => '',
    'pagination_html' => '',
    'page' => 1,
    'total_pages' => 1
];

try {
    // Get parameters
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $gr_number = $_POST['gr_number'] ?? ''; // New
    $dr_number = $_POST['dr_number'] ?? ''; // New
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20; // New
    if ($limit <= 0) {
        $limit = 20;
    }
    $offset = ($page - 1) * $limit;

    // Base SQL
    $base_sql = "FROM deliveries d
                 JOIN warehouses w ON d.warehouse_id = w.id
                 LEFT JOIN clients c ON d.client_id = c.id
                 LEFT JOIN client_locations cl ON d.delivery_location_id = cl.id";
    
    $conditions = [];
    $params = [];
    $types = "";

    if ($start_date) {
        $conditions[] = "DATE(d.delivery_date) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    if ($end_date) {
        $conditions[] = "DATE(d.delivery_date) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    // New filter conditions
    if ($gr_number) {
        $conditions[] = "d.gr_number LIKE ?";
        $params[] = "%" . $gr_number . "%";
        $types .= "s";
    }
    if ($dr_number) {
        $conditions[] = "d.dr_number LIKE ?";
        $params[] = "%" . $dr_number . "%";
        $types .= "s";
    }

    $where_clause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

    // Count total records for pagination
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total $base_sql $where_clause");
    if(!empty($params)) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
    $count_stmt->close();
    
    // Fetch paginated data
    $sql = "SELECT d.id, d.gr_number, d.dr_number, d.delivery_date, d.processed_by, 
                   w.name AS warehouse_name, c.business_name AS client_name, cl.location AS delivery_location
            $base_sql $where_clause
            ORDER BY d.delivery_date DESC, d.id DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $types .= "i";
    $params[] = $offset;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $table_rows = '';
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // The HTML generation for the rows is unchanged
            $table_rows .= "
            <tr class='text-center main-row'>
              <td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['client_name']) . "</td>
              <td>" . htmlspecialchars($row['delivery_location']) . "</td><td>" . htmlspecialchars($row['warehouse_name']) . "</td>
              <td>" . htmlspecialchars($row['gr_number']) . "</td><td>" . htmlspecialchars($row['dr_number']) . "</td>
              <td>" . htmlspecialchars($row['delivery_date']) . "</td><td>" . htmlspecialchars($row['processed_by']) . "</td>
              <td>
                <button class='btn btn-success btn-sm toggleDetails' data-deliveryid='" . htmlspecialchars($row['id']) . "'>View Items</button>
                <a href='edit_delivery.php?id=" . htmlspecialchars($row['id']) . "' class='btn btn-warning btn-sm'>Edit</a>
              </td>
            </tr>
            <tr class='collapse-row' id='details-" . $row['id'] . "'><td colspan='9'><div class='p-3 text-center'>Loading item details...</div></td></tr>";
        }
    } else {
        $table_rows = "<tr><td colspan='9' class='text-center'>No delivery records found for the selected criteria.</td></tr>";
    }

    $response['table_rows'] = $table_rows;
    $response['page'] = $page;
    $response['total_pages'] = $total_pages;

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = true;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>