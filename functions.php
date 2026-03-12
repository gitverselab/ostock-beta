<?php
// functions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current logged-in user has a specific permission.
 *
 * @param string $permission The name of the permission to check (e.g., 'items-edit').
 * @return bool True if the user has the permission, false otherwise.
 */
function can(string $permission): bool {
    // Admins (role_id 1) can do everything, bypassing the check.
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 1) {
        return true;
    }
    // For other roles, check if the permission exists in their session array.
    return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}
?>