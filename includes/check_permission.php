<?php
// Farsi Comments: فایل برای بررسی دسترسی کاربران

// این تابع بررسی می‌کند که آیا کاربر لاگین کرده و نقش مورد نیاز را دارد یا خیر
// This function checks if a user is logged in and has the required role.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current user has the required role or permission.
 *
 * @param string|array $required_roles The role(s) required to access the resource.
 * @return bool True if the user has permission, false otherwise.
 */
function check_permission($required_roles): bool {
    // 1. Check if user is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    // 2. Get user's role from session
    $user_role = $_SESSION['role'] ?? '';

    // 3. Ensure $required_roles is an array
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }

    // 4. Check if user's role is in the required roles array
    // An admin has access to everything
    if ($user_role === 'super_admin' || in_array($user_role, $required_roles)) {
        return true;
    }

    // You could also add more granular permission checks here based on the `permissions` JSON field
    // For example: checking $_SESSION['permissions']

    return false;
}

/**
 * A helper function to secure an API endpoint.
 * It checks permission and exits if the user is not authorized.
 *
 * @param string|array $required_roles
 */
function secure_api_endpoint($required_roles) {
    if (!check_permission($required_roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'شما دسترسی لازم برای انجام این عملیات را ندارید.']);
        exit;
    }
}
?>
