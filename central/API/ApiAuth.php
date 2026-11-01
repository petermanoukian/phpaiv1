<?php

namespace Central\API;

class ApiAuth {
    
    public static function verify(): void {
        // 1. Grab all incoming headers from the HTTP request
        $headers = getallheaders();
        $apiKeyHeader = null;

        // 2. Loop through headers to find 'X-API-Key' case-insensitively
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-api-key') {
                $apiKeyHeader = trim($value);
                break;
            }
        }

        // 3. Load our centralized config registry (which automatically executed via Composer autoload)
        $config = require APP_ROOT . '/config/config.php';
        $expectedKey = $config['api']['secret_key'] ?? '';

        // 4. Securely compare headers using hash_equals to prevent timing attacks
        if (empty($apiKeyHeader) || empty($expectedKey) || !hash_equals($expectedKey, $apiKeyHeader)) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(401); // 401 Unauthorized status code
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized. Missing or invalid X-API-Key header.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit; // Terminate execution immediately
        }
    }
}