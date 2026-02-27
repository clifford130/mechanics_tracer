<?php
// /new/forms/auth/logout.php

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
header("Location: /mechanics_tracer/forms/auth/login.php");
exit;
?>