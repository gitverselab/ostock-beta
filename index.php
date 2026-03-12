<?php
// Start the session at the single entry point of the application.
session_start();

// Include the database connection once for all pages.
include 'db.php';

// --- 1. Define Routing and Public Pages ---

// Define which pages do NOT require a login.
$public_pages = ['login'];

// Determine the requested page. Default to 'dashboard' if logged in, 'login' if not.
$default_page = isset($_SESSION['user_id']) ? 'dashboard' : 'login';
$page = $_GET['page'] ?? $default_page;

// --- 2. Sanitize Page Input for Security ---

// This is a crucial security step. 
// basename() strips out any directory information (like ../ or /),
// preventing attempts to include files outside the current directory.
$page = basename($page);

// --- 3. Centralized Authentication Check ---

// Check if the user is logged in.
$is_logged_in = isset($_SESSION['user_id']);

// If the user is NOT logged in and the requested page is NOT a public page,
// redirect them to the login page.
if (!$is_logged_in && !in_array($page, $public_pages)) {
    header("Location: index.php?page=login");
    exit;
}

// If the user IS logged in and tries to access the login page,
// redirect them to the dashboard instead.
if ($is_logged_in && $page === 'login') {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- 4. Include the Page File ---

// Construct the full filename.
$file_to_include = $page . '.php';

// Check if the file actually exists before trying to include it.
if (file_exists($file_to_include)) {
    // The navbar should be included by the individual page files that need it,
    // not by the router. This gives you more control.
    include $file_to_include;
} else {
    // If the file doesn't exist, show a 404 error page.
    http_response_code(404);
    include '404.php'; // You should create a simple 404.php file.
}
?>