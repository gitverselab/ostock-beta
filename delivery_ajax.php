<?php
// Start session at the very beginning
session_start();

// Set timezone and include database
date_default_timezone_set('Asia/Manila');
include 'db.php';
header('Content-Type: application/json');

// --- Helper function for sending consistent JSON responses ---
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Authentication Check: This must happen after session_start()
if (!isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
}

// This list will be used by both the calendar and inventory panel for consistency.
$calendar_item_list = [
    'LECHE FLAN - MI - CHILLED', 'LECHE FLAN - RETAIL - CHILLED', 'UBE HALAYA - MI - CHILLED', 
    'BANANA CHILLED', 'LANGKA CHILLED', 'BEANS CHILLED', 'MONGGO CHILLED', 
    'UBE HALAYA - MI - FROZEN', 'MACAPUNO FROZEN', 'MONGGO FROZEN', 'BEANS FROZEN'
];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_calendar_schedule':
        $defaultStart = new DateTime();
        $earliest_res = $conn->query("SELECT MIN(ds.delivery_date) as earliest FROM delivery_schedule ds");
        if ($earliest_res && $row = $earliest_res->fetch_assoc()) {
            if (!empty($row['earliest'])) { $defaultStart = new DateTime($row['earliest']); }
        }
        $endDate = new DateTime('+30 days');
        
        $dates = [];
        for ($i = clone $defaultStart; $i <= $endDate; $i->modify('+1 day')) { $dates[] = $i->format('Y-m-d'); }

        $in_clause = implode(',', array_fill(0, count($calendar_item_list), '?'));
        $sql = "SELECT i.id as item_id, i.name as item_name, ds.delivery_date, ds.is_additional, dis.status, dis.quantity, cl.location as delivery_location
                FROM items i
                LEFT JOIN delivery_item_schedule dis ON i.id = dis.item_id
                LEFT JOIN delivery_schedule ds ON ds.id = dis.delivery_schedule_id
                LEFT JOIN client_locations cl ON dis.delivery_location_id = cl.id
                WHERE i.name IN ($in_clause) ORDER BY FIELD(i.name, $in_clause)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) { json_response(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error], 500); }
        
        $params = array_merge($calendar_item_list, $calendar_item_list);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $temp = [];
        while ($row = $res->fetch_assoc()) {
            $item_id = $row['item_id'];
            if (!isset($temp[$item_id])) $temp[$item_id] = ['item_id' => $item_id, 'name' => $row['item_name'], 'schedules' => []];
            if ($row['delivery_date'] && in_array($row['delivery_date'], $dates)) {
                $temp[$item_id]['schedules'][$row['delivery_date']][] = ['quantity' => (int)$row['quantity'], 'status' => $row['status'], 'is_additional' => $row['is_additional'], 'delivery_location' => $row['delivery_location'] ? trim(explode(',', $row['delivery_location'])[0]) : ''];
            }
        }
        json_response(['success' => true, 'calendar' => array_values($temp), 'dates' => $dates]);
        break;

    case 'get_inventory':
        $inventory = [];
        $in_clause = implode(',', array_fill(0, count($calendar_item_list), '?'));
        
        $sql = "SELECT i.name as item, w.name as location, SUM(inv.items_per_pc) as stock 
                FROM inventory inv 
                JOIN items i ON inv.item_id = i.id 
                JOIN warehouses w ON inv.warehouse_id = w.id 
                WHERE i.name IN ($in_clause)
                GROUP BY inv.item_id, inv.warehouse_id";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { json_response(['success' => false, 'message' => 'SQL prepare failed'], 500); }
        $types = str_repeat('s', count($calendar_item_list));
        $stmt->bind_param($types, ...$calendar_item_list);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) { while($row = $result->fetch_assoc()) { $inventory[] = $row; } }
        json_response(['success' => true, 'inventory' => $inventory]);
        break;

    case 'schedule_delivery':
        $conn->begin_transaction();
        try {
            $delivery_date = $_POST['delivery_date'];
            $is_additional = (isset($_POST['is_additional']) && $_POST['is_additional'] == '1') ? 1 : 0;
            $delivery_id = !empty($_POST['delivery_id']) ? intval($_POST['delivery_id']) : null;
            $item_ids = $_POST['item_id'] ?? [];

            if ($delivery_id) {
                $stmt = $conn->prepare("UPDATE delivery_schedule SET delivery_date = ?, is_additional = ? WHERE id = ?");
                $stmt->bind_param("sii", $delivery_date, $is_additional, $delivery_id);
                if (!$stmt->execute()) throw new Exception("Failed to update schedule header.");
                $stmt->close();
                
                $stmt_del = $conn->prepare("DELETE FROM delivery_item_schedule WHERE delivery_schedule_id = ?");
                $stmt_del->bind_param("i", $delivery_id);
                if (!$stmt_del->execute()) throw new Exception("Failed to delete old schedule items.");
                $stmt_del->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO delivery_schedule (delivery_date, created_by, is_additional) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $delivery_date, $_SESSION['username'], $is_additional);
                if (!$stmt->execute()) throw new Exception("Failed to create new schedule header.");
                $delivery_id = $conn->insert_id;
                $stmt->close();
            }

            $stmt_item = $conn->prepare("INSERT INTO delivery_item_schedule (delivery_schedule_id, item_id, quantity, status, delivery_location_id) VALUES (?, ?, ?, ?, ?)");
            foreach ($item_ids as $index => $item_id) {
                $stmt_item->bind_param("iiisi", $delivery_id, $item_id, $_POST['quantity'][$index], $_POST['item_status'][$index], $_POST['item_location'][$index]);
                if (!$stmt_item->execute()) throw new Exception("Failed to insert schedule item.");
            }
            $stmt_item->close();
            
            $conn->commit();
            json_response(['success' => true, 'message' => 'Delivery scheduled successfully.']);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'get_scheduled':
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $base_sql = "FROM delivery_schedule ds JOIN delivery_item_schedule dis ON ds.id = dis.delivery_schedule_id JOIN items i ON dis.item_id = i.id LEFT JOIN client_locations cl ON dis.delivery_location_id = cl.id";
        $conditions = ["1=1"]; $params = []; $types = "";

        if (!empty($_POST['start_date'])) {
            $conditions[] = "ds.delivery_date >= ?";
            $params[] = $_POST['start_date']; $types .= "s";
        }
        if (!empty($_POST['end_date'])) {
            $conditions[] = "ds.delivery_date <= ?";
            $params[] = $_POST['end_date']; $types .= "s";
        }
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $data_sql = "SELECT ds.id, ds.delivery_date, ds.is_additional, dis.quantity, dis.status, i.name as item, cl.location as delivery_location 
                     $base_sql $where_clause 
                     ORDER BY ds.delivery_date DESC, ds.id DESC";
        
        $stmt = $conn->prepare($data_sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $grouped = [];
        while($row = $result->fetch_assoc()){
            $id = $row['id'];
            if(!isset($grouped[$id])) $grouped[$id] = ['id' => $id, 'delivery_date' => $row['delivery_date'], 'is_additional' => $row['is_additional'], 'items' => []];
            $grouped[$id]['items'][] = htmlspecialchars($row['item']) . " (Qty: " . htmlspecialchars($row['quantity']) . ", Status: " . htmlspecialchars($row['status']) . ", Loc: " . htmlspecialchars($row['delivery_location']) . ")";
        }
        
        $scheduled = [];
        foreach($grouped as $group) {
            $group['items'] = implode(", ", $group['items']);
            $scheduled[] = $group;
        }

        $totalRecords = count($scheduled);
        $paginated_scheduled = array_slice($scheduled, $offset, $limit);
        
        json_response(['success' => true, 'scheduled' => $paginated_scheduled, 'total' => $totalRecords, 'page' => $page, 'limit' => $limit]);
        break;

    case 'get_delivery':
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT ds.delivery_date, ds.is_additional, dis.item_id, dis.quantity, dis.status, dis.delivery_location_id as item_location FROM delivery_schedule ds JOIN delivery_item_schedule dis ON ds.id = dis.delivery_schedule_id WHERE ds.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = []; $delivery = [];
        while($row = $result->fetch_assoc()) {
            if (empty($delivery)) $delivery = ['id' => $id, 'delivery_date' => $row['delivery_date'], 'is_additional' => $row['is_additional']];
            $items[] = ['item_id' => $row['item_id'], 'quantity' => $row['quantity'], 'status' => $row['status'], 'location' => $row['item_location']];
        }
        if(!empty($items)){
            json_response(['success' => true, 'delivery' => array_merge($delivery, ['items' => $items])]);
        } else {
            json_response(['success' => false, 'message' => 'Delivery not found']);
        }
        break;

    case 'delete_delivery':
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM delivery_schedule WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            json_response(['success' => true, 'message' => 'Schedule deleted.']);
        } else {
            json_response(['success' => false, 'message' => 'Failed to delete delivery'], 500);
        }
        break;
        
    default:
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}
?>