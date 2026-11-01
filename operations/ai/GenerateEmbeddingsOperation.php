<?php

namespace Operations\AI;

use Central\Database;
use Gemini\Client;

class GenerateEmbeddingsOperation {
    public static function handle(): void {
        $db = Database::getConnection();
        
        // Initialize Gemini client using the API key from config/env
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \Exception("Gemini API key is missing. Check your configuration.");
        }
        
        $client = \Gemini::client($apiKey);

        // Fetch all articles that need embeddings
        $stmt = $db->query("SELECT id, content FROM articles");
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $updateStmt = $db->prepare("UPDATE articles SET embedding = :embedding WHERE id = :id");

        foreach ($articles as $article) {
            echo "Generating embedding for Article ID #{$article['id']}...\n";

            try {
                // Call Gemini Embedding API (using text-embedding-004 model)
                $response = $client->embedding(
                    model: 'text-embedding-004',
                    text: $article['content']
                );

                // The response contains the vector values (array of floats)
                $vectorValues = $response->embedding->values;

                // Save as a JSON string in SQLite
                $updateStmt->execute([
                    'embedding' => json_encode($vectorValues),
                    'id' => $article['id']
                ]);

                echo "Successfully saved embedding for Article ID #{$article['id']}\n";
            } catch (\Throwable $e) {
                echo "Failed for Article ID #{$article['id']}: " . $e->getMessage() . "\n";
            }
        }

        echo "All article embeddings generated successfully!\n";
    }
}