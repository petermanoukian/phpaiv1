<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Simple manual .env loader if not using phpdotenv
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2) + ['', ''];
        if ($key = trim($key)) {
            $value = trim($value, "\"' ");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

return [
    'ai' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
    ],
];