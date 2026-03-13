<?php
/**
 * CSRF protection for admin forms.
 * Call csrf_token() to get/regenerate token, csrf_field() for hidden input, csrf_verify() to validate.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? '';
    return $token !== '' && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}
