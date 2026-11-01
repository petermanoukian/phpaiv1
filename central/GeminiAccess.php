<?php

namespace Central;

use Gemini\Client;

class GeminiAccess {
    
    public static function getClient(): Client {
        $config = require APP_ROOT . '/config/config.php';
        $apiKey = $config['ai']['api_key'] ?? '';
        
        if (empty($apiKey)) {
            throw new \Exception("Gemini API key is missing in configuration.");
        }

        return \Gemini::client($apiKey);
    }
}