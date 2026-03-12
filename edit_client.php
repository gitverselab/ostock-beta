<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
// We will include functions.php in case we add permission checks later
// include 'functions.php'; 

// --- Handle Form Submission FIRST, before any HTML is output ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['client_id'])) {
    $client_id = intval($_POST['client_id']);
    $error_message = '';

    $conn->begin_transaction();
    try {
        // --- STEP 1: Get the list of new and old locations to compare ---
        $new_locations_submitted = $_POST['locations'] ?? [];
        
        $old_locations_stmt = $conn->prepare("SELECT id, location FROM client_locations WHERE client_id = ?");
        $old_locations_stmt->bind_param("i", $client_id);
        $old_locations_stmt->execute();
        $result = $old_locations_stmt->get_result();
        $old_locations_from_db = [];
        while($row = $result->fetch_assoc()) {
            $old_locations_from_db[$row['id']] = $row['location'];
        }
        $old_locations_stmt->close();

        // --- STEP 2: Determine which locations to remove ---
        $locations_to_remove_ids = [];
        foreach ($old_locations_from_db as $id => $location) {
            if (!in_array($location, $new_locations_submitted)) {
                $locations_to_remove_ids[] = $id;
            }
        }

        // --- STEP 3 (CRUCIAL): Check if any locations to be removed are in use ---
        if (!empty($locations_to_remove_ids)) {
            $placeholders = implode(',', array_fill(0, count($locations_to_remove_ids), '?'));
            $types = str_repeat('i', count($locations_to_remove_ids));
            
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count, l.location FROM delivery_item_schedule dis JOIN client_locations l ON dis.delivery_location_id = l.id WHERE dis.delivery_location_id IN ($placeholders) GROUP BY l.location LIMIT 1");
            $check_stmt->bind_param($types, ...$locations_to_remove_ids);
            $check_stmt->execute();
            $usage = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($usage && $usage['count'] > 0) {
                throw new Exception("Cannot remove location '" . htmlspecialchars($usage['location']) . "' because it is currently used in a delivery schedule. Please update or delete the schedule first.");
            }
            
            // If check passes, proceed with deletion
            $stmt_del_loc = $conn->prepare("DELETE FROM client_locations WHERE id IN ($placeholders)");
            $stmt_del_loc->bind_param($types, ...$locations_to_remove_ids);
            if (!$stmt_del_loc->execute()) throw new Exception("Failed to remove old locations.");
            $stmt_del_loc->close();
        }

        // --- STEP 4: Add any new locations ---
        $existing_locations_lower = array_map('strtolower', $old_locations_from_db);
        $stmt_add_loc = $conn->prepare("INSERT INTO client_locations (client_id, location) VALUES (?, ?)");
        foreach ($new_locations_submitted as $location) {
            if (!empty($location) && !in_array(strtolower($location), $existing_locations_lower)) {
                $stmt_add_loc->bind_param("is", $client_id, $location);
                if (!$stmt_add_loc->execute()) throw new Exception("Failed to add new location.");
            }
        }
        $stmt_add_loc->close();

        // --- STEP 5: Update other client details ---
        // Update business name
        $business_name = trim($_POST['business_name']);
        $stmt_client = $conn->prepare("UPDATE clients SET business_name = ? WHERE id = ?");
        $stmt_client->bind_param("si", $business_name, $client_id);
        if (!$stmt_client->execute()) throw new Exception("Failed to update client name.");
        $stmt_client->close();
        
        // Update contacts (using delete all and re-insert is safe for these)
        $stmt_del_contacts = $conn->prepare("DELETE FROM client_contacts WHERE client_id = ?");
        $stmt_del_contacts->bind_param("i", $client_id);
        $stmt_del_contacts->execute();
        $stmt_del_contacts->close();

        if (!empty($_POST['contacts'])) {
            $stmt_ins_contact = $conn->prepare("INSERT INTO client_contacts (client_id, contact_person, contact_number) VALUES (?, ?, ?)");
            foreach ($_POST['contacts'] as $contact) {
                if (!empty($contact['person']) || !empty($contact['number'])) {
                    $stmt_ins_contact->bind_param("iss", $client_id, $contact['person'], $contact['number']);
                    if (!$stmt_ins_contact->execute()) throw new Exception("Failed to insert new contact.");
                }
            }
            $stmt_ins_contact->close();
        }

        $conn->commit();
        header("Location: list_clients.php?success=edit");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        // Use session to pass the error message back to the page after redirecting
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: edit_client.php?id=" . $client_id);
        exit;
    }
}

// --- Prepare Data for Page Display ---
include 'navbar.php';

// Check for and display any error message from a failed POST
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (!isset($_GET['id'])) {
    die("Client ID not specified.");
}
$client_id = intval($_GET['id']);

// Securely fetch data for the form
$stmt_client = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt_client->bind_param("i", $client_id);
$stmt_client->execute();
$client = $stmt_client->get_result()->fetch_assoc();
$stmt_client->close();

$contacts = [];
if ($client) {
    $stmt_contacts = $conn->prepare("SELECT * FROM client_contacts WHERE client_id = ?");
    $stmt_contacts->bind_param("i", $client_id);
    $stmt_contacts->execute();
    $contacts_result = $stmt_contacts->get_result();
    while($row = $contacts_result->fetch_assoc()) { $contacts[] = $row; }
    $stmt_contacts->close();
}

$locations = [];
if ($client) {
    $stmt_locations = $conn->prepare("SELECT * FROM client_locations WHERE client_id = ?");
    $stmt_locations->bind_param("i", $client_id);
    $stmt_locations->execute();
    $locations_result = $stmt_locations->get_result();
    while($row = $locations_result->fetch_assoc()) { $locations[] = $row; }
    $stmt_locations->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Client</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Edit Client</h2>

    <?php if (!$client): ?>
        <div class="alert alert-danger mt-3">Client not found or ID is invalid. Please return to the <a href="list_clients.php" class="alert-link">client list</a>.</div>
    <?php else: ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger mt-3"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <form method="POST" action="edit_client.php?id=<?= $client_id ?>">
            <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['id']); ?>">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Business Name:</label>
                        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($client['business_name']); ?>" required>
                    </div>
                    <hr>
                    <h4>Contacts</h4>
                    <div id="contacts-container">
                        <?php $contactIndex = 0; foreach ($contacts as $contact): ?>
                            <div class="contact-entry row mb-2 gx-2">
                                <div class="col"><input type="text" name="contacts[<?= $contactIndex ?>][person]" class="form-control" value="<?= htmlspecialchars($contact['contact_person']) ?>" placeholder="Contact Person"></div>
                                <div class="col"><input type="text" name="contacts[<?= $contactIndex ?>][number]" class="form-control" value="<?= htmlspecialchars($contact['contact_number']) ?>" placeholder="Contact Number"></div>
                                <div class="col-auto"><button type="button" class="btn btn-danger btn-sm remove-contact">Remove</button></div>
                            </div>
                        <?php $contactIndex++; endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-contact">Add Contact</button>
                    <hr>
                    <h4 class="mt-4">Locations</h4>
                    <div id="locations-container">
                        <?php $locationIndex = 0; foreach ($locations as $location): ?>
                            <div class="location-entry row mb-2 gx-2">
                                <div class="col"><input type="text" name="locations[]" class="form-control" value="<?= htmlspecialchars($location['location']) ?>" placeholder="Business Location" required></div>
                                <div class="col-auto"><button type="button" class="btn btn-danger btn-sm remove-location">Remove</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-location">Add Location</button>
                </div>
                <div class="card-footer text-end">
                    <a href="list_clients.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Client</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    let contactIndex = <?= $contactIndex ?>;
    $("#add-contact").click(function () {
        $("#contacts-container").append(`
            <div class="contact-entry row mb-2 gx-2">
                <div class="col"><input type="text" name="contacts[${contactIndex}][person]" class="form-control" placeholder="Contact Person"></div>
                <div class="col"><input type="text" name="contacts[${contactIndex}][number]" class="form-control" placeholder="Contact Number"></div>
                <div class="col-auto"><button type="button" class="btn btn-danger btn-sm remove-contact">Remove</button></div>
            </div>
        `);
        contactIndex++;
    });

    $("#add-location").click(function () {
        $("#locations-container").append(`
            <div class="location-entry row mb-2 gx-2">
                <div class="col"><input type="text" name="locations[]" class="form-control" placeholder="Business Location" required></div>
                <div class="col-auto"><button type="button" class="btn btn-danger btn-sm remove-location">Remove</button></div>
            </div>
        `);
    });

    $(document).on("click", ".remove-contact", function () { $(this).closest('.contact-entry').remove(); });
    $(document).on("click", ".remove-location", function () { $(this).closest('.location-entry').remove(); });
});
</script>
</body>
</html>