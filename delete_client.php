<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    die("Client ID not provided.");
}

$client_id = (int) $_GET['id'];

// Cascade deletes via foreign key
$conn->query("DELETE FROM clients WHERE id = $client_id");

header("Location: list_clients.php?deleted=1");
exit;
?>
