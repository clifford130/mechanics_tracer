<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (file_exists($root . '/mechanics_tracer/forms/config.php')) {
    require_once($root . '/mechanics_tracer/forms/config.php');
} else {
    require_once($root . '/forms/config.php');
}

session_start();                 // start the session
$_SESSION = array();             // clear all session variables

//  using session cookies, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();               // destroy the session

// Redirect to login page
header("Location: " . BASE_URL . "forms/auth/login.php");
exit;
?>