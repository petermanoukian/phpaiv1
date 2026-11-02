<?php

namespace Operations\API;

use Central\API\ApiAuth;
use Central\API\ApiResponse;
use Operations\AI\ConversationalHybridSearchOperation;

class SearchApiOperationHybrid {

    public static function handle(): void {
        // 1. Verify API Key Header (X-API-Key)
        ApiAuth::verify();

        // 2. Enforce POST HTTP Method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ApiResponse::error('Method Not Allowed. Use POST.', 405);
        }

        // 3. Read and Decode Incoming Raw JSON Body
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data) || empty($data['query'])) {
            ApiResponse::error('Bad Request. Missing required parameter: "query".', 400);
        }

        $query = trim($data['query']);
        $limit = isset($data['limit']) ? (int)$data['limit'] : 5;
        $minThreshold = isset($data['min_threshold']) ? (float)$data['min_threshold'] : 0.45;

        try {
            // 4. Delegate to Hybrid Search Operation (FTS5 + Vector RRF)
            $searchResult = ConversationalHybridSearchOperation::handle($query, $limit, $minThreshold);

            // 5. Send Standardized JSON Response
            ApiResponse::send([
                'status' => 'success',
                'engine' => 'hybrid_fts5_vector_rrf',
                'query' => $query,
                'answer' => $searchResult['answer'],
                'results_count' => count($searchResult['results']),
                'results' => $searchResult['results']
            ], 200);

        } catch (\Throwable $e) {
            ApiResponse::error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}