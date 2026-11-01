<?php

namespace Operations\AI;

use Central\Database;

class SemanticSearchOperation {
    
    public static function handle(string $queryText, int $limit = 3): array {
        if (empty(trim($queryText))) {
            return [];
        }

        $db = Database::getConnection();
        
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \Exception("Gemini API key is missing.");
        }

        $client = \Gemini::client($apiKey);

        // 1. Generate an embedding vector for the user's search query
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($queryText);
        $queryVector = $response->embedding->values;

        // 2. Fetch all articles that have valid embeddings from SQLite
        $stmt = $db->query("SELECT id, title, content, language, file_path, embedding FROM articles WHERE embedding IS NOT NULL");
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoredArticles = [];

        foreach ($articles as $article) {
            $articleVector = json_decode($article['embedding'], true);
            if (!is_array($articleVector)) {
                continue;
            }

            // 3. Calculate Cosine Similarity between query vector and article vector
            $similarity = self::cosineSimilarity($queryVector, $articleVector);

            $scoredArticles[] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'content' => $article['content'],
                'language' => $article['language'],
                'file_path' => $article['file_path'],
                'score' => $similarity // Score between -1 and 1 (closer to 1 = higher semantic match)
            ];
        }

        // 4. Sort results by highest similarity score
        usort($scoredArticles, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Return top matches up to the limit
        return array_slice($scoredArticles, 0, $limit);
    }

    /**
     * Mathematical Cosine Similarity calculation between two float arrays
     */
    private static function cosineSimilarity(array $vecA, array $vecB): float {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = count($vecA);
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] ** 2;
            $normB += $vecB[$i] ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}