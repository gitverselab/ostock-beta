<?php
include 'db.php';
if (isset($_POST['client_id'])) {
    $client_id = intval($_POST['client_id']);
    $result = $conn->query("SELECT id, location FROM client_locations WHERE client_id = $client_id");
    
    echo "<option value=''>Select a Location</option>";
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['location']}</option>";
    }
}
?>
