<?php
/**
 * config_loader.php
 * Environment-aware path and URL configuration.
 * Handles both localhost (with subfolder) and production (htdocs root).
 */

if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    // Localhost Development (assuming /mechanics_tracer/ subfolder)
    define('BASE_URL', '/mechanics_tracer/');
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mechanics_tracer/');
} else {
    // Production Deployment (InfinityFree - directly in htdocs)
    define('BASE_URL', '/');
    define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
}

// URL Constants for frontend assets and navigation
if (!defined('FORMS_URL'))    define('FORMS_URL', BASE_URL . 'forms/');
if (!defined('DASHBOARD_URL')) define('DASHBOARD_URL', BASE_URL . 'dashboard/');
if (!defined('ADMIN_URL'))     define('ADMIN_URL', BASE_URL . 'admin/');
if (!defined('ASSETS_URL'))    define('ASSETS_URL', BASE_URL . 'assets/');

// Optional: Path constants for server-side includes
if (!defined('FORMS_PATH'))    define('FORMS_PATH', ROOT_PATH . 'forms/');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', ROOT_PATH . 'includes/');
?>