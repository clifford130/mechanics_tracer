<?php
/**
 * Admin auth guard. Include at top of every admin page.
 * Redirects to login if not authenticated as admin.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . (defined('FORMS_URL') ? FORMS_URL : 'http://localhost/<?php echo BASE_URL; ?>forms/') . 'auth/login.php');
    exit;
}