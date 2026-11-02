<?php
// routes/api.php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Autoload Composer Dependencies & Classes
require_once APP_ROOT . '/vendor/autoload.php';

use Central\API\ApiResponse;
use Operations\API\SearchApiOperation;
use Operations\API\SearchApiOperationHybrid;
use Operations\API\IngestArticleApiOperation;

// Handle Preflight OPTIONS Requests (for CORS compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search':
        SearchApiOperation::handle();
        break;

    case 'search4':
        SearchApiOperation4::handle();
        break;

    case 'search_hybrid':
        SearchApiOperationHybrid::handle();
        break;

    case 'ingest':
        IngestArticleApiOperation::handle();
        break;

    default:
        ApiResponse::error('Invalid or missing API action. Use ?action=search, ?action=search4, ?action=search_hybrid, or ?action=ingest.', 404);
        break;
}