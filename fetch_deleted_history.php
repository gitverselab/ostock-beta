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
    "table_rows" => ""
];

try {
    // Retrieve and sanitize filters
    $transaction_type = $_GET['transaction_type'] ?? '';
    $deleted_by = $_GET['deleted_by'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    $conditions = [];
    $params = [];
    $types = "";

    if ($transaction_type !== '') {
        $conditions[] = "transaction_type = ?";
        $params[] = $transaction_type;
        $types .= "s";
    }
    if ($deleted_by !== '') {
        $conditions[] = "deleted_by LIKE ?";
        $params[] = "%" . $deleted_by . "%";
        $types .= "s";
    }
    if ($start_date !== '') {
        $conditions[] = "deleted_date >= ?";
        $params[] = $start_date . " 00:00:00";
        $types .= "s";
    }
    if ($end_date !== '') {
        $conditions[] = "deleted_date <= ?";
        $params[] = $end_date . " 23:59:59";
        $types .= "s";
    }

    $where = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

    $sql = "SELECT * FROM deleted_transactions $where ORDER BY deleted_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("SQL prepare failed: " . $conn->error);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $table_rows = "";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $details = json_decode($row['details'], true);
            $formatted_details = json_encode($details, JSON_PRETTY_PRINT);

            $table_rows .= "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['transaction_type']) . "</td>
                <td>" . htmlspecialchars($row['original_id']) . "</td>
                <td>" . htmlspecialchars($row['deleted_by']) . "</td>
                <td>" . htmlspecialchars($row['deleted_date']) . "</td>
                <td><pre><code>" . htmlspecialchars($formatted_details) . "</code></pre></td>
            </tr>";
        }
    } else {
         $table_rows = "<tr><td colspan='6' class='text-center'>No records found matching your criteria.</td></tr>";
    }
    $response["table_rows"] = $table_rows;

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = true;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>