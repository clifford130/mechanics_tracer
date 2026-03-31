<?php
// Secure configuration for AI API
require_once(__DIR__ . "/../../forms/config.php");

// Function to read .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        
        // Remove quotes if present
        $value = trim($value, "\"' ");
        
        if (!array_key_exists(trim($name), $_SERVER) && !array_key_exists(trim($name), $_ENV)) {
            putenv(sprintf('%s=%s', trim($name), $value));
            $_ENV[trim($name)] = $value;
            $_SERVER[trim($name)] = $value;
        }
    }
    return true;
}

// Ensure .env exists in ai_apis directory
$envPath = __DIR__ . '/ai_apis/.env';

// For this specific setup, the .env might just be the raw key on line 1, 
// so let's handle both standard .env format and raw string formats.
if (file_exists($envPath)) {
    $content = trim(file_get_contents($envPath));
    if (strpos($content, '=') !== false) {
        loadEnv($envPath);
        $api_key = getenv('OPENAI_API_KEY') ?: $_ENV['OPENAI_API_KEY'];
    } else {
        // If it's just the raw key
        $api_key = $content;
    }
} else {
    $api_key = ''; // Fallback
}

define('AI_API_KEY', $api_key);
define('AI_MODEL', 'gemini-1.5-flash'); // Using Google Gemini API
?>
