<?php
namespace Central;

class AI {
    private \Gemini\Client $client;

    public function __construct() {
        //$config = require_once __DIR__ . '/../config/config.php';
        $config = require APP_ROOT . '/config/config.php';
        $apiKey = $config['ai']['api_key'] ?? '';

        if (empty($apiKey)) {
            throw new \RuntimeException("Gemini API key missing in .env");
        }

        $this->client = \Gemini::client($apiKey);
    }

    public function getClient(): \Gemini\Client {
        return $this->client;
    }
}