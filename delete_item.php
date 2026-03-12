<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
include 'db.php'; ?>
<?php
if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('You do not have permission to access this page.'); window.location='login.php';</script>";
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM items WHERE id='$id'";
    $conn->query($sql);
    header("Location: list_items.php");
}
?>