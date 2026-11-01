<?php

namespace Operations\API;

use Central\API\ApiAuth;
use Central\API\ApiResponse;
use Operations\AI\CreateArticleWithEmbeddingOperation;

class IngestArticleApiOperation {

    public static function handle(): void {
        // 1. Verify API Key Header (X-API-Key)
        ApiAuth::verify();

        // 2. Enforce POST HTTP Method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ApiResponse::error('Method Not Allowed. Use POST.', 405);
        }

        // 3. Detect Content-Type to support both JSON payloads and Multipart Form-Data (file uploads)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $postData = [];
        $fileData = $_FILES['file'] ?? null;

        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            $postData = json_decode($rawInput, true) ?? [];
        } else {
            $postData = $_POST;
        }

        // 4. Validate required fields
        if (empty($postData['title']) || empty($postData['content'])) {
            ApiResponse::error('Bad Request. Missing required parameters: "title" and "content".', 400);
        }

        try {
            // 5. Delegate execution to core database and embedding operation
            $result = CreateArticleWithEmbeddingOperation::handleForm($postData, $fileData);

            // 6. Return HTTP 201 Created with the new record ID
            ApiResponse::send([
                'status' => 'success',
                'message' => $result['message'],
                'article_id' => $result['id']
            ], 201);

        } catch (\Throwable $e) {
            ApiResponse::error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}