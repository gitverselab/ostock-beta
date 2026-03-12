<?php
include 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    
    // Retrieve item_code for the given item_id
    $sql = "SELECT item_code FROM items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->bind_result($item_code);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Item not found.']);
        exit;
    }
    $stmt->close();
    
    // Get current date in YYYYMMDD format
    $date = date('Ymd');
    $unique = 1;
    $pallet_id = "";
    
    // Loop until a unique pallet_id is found
    while (true) {
        $unique_str = str_pad($unique, 4, '0', STR_PAD_LEFT);
        $pallet_id = $item_code . "-" . $date . "-" . $unique_str;
        
        // Check if this pallet_id exists in inventory
        $sql = "SELECT COUNT(*) FROM inventory WHERE pallet_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $pallet_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        if ($count == 0) {
            break;
        } else {
            $unique++;
            if ($unique > 9999) {
                echo json_encode(['error' => 'Unable to generate unique pallet ID.']);
                exit;
            }
        }
    }
    
    echo json_encode(['pallet_id' => $pallet_id]);
    exit;
} else {
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
?>
