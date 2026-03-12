<?php
$host = "localhost";
$user = "u539825091_inventory_t";
$password = "B@dw0lfz";
$database = "u539825091_inventory_t";

// Set the default timezone for PHP
date_default_timezone_set('Asia/Manila'); // This is for PHP only

$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the MySQL connection timezone to UTC
$conn->query("SET time_zone = '+08:00'");

?>