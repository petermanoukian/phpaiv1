<?php

namespace Central\API;

class ApiResponse {
    
    public static function send(mixed $data, int $statusCode = 200): void {
        header('Content-Type: application/json; charset=UTF-8');
        // Enable CORS if needed for external client apps
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

        http_response_code($statusCode);
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $statusCode = 400): void {
        self::send([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }
}