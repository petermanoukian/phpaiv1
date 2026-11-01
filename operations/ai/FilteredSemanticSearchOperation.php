<?php

namespace Operations\AI;

use Central\Database;

class FilteredSemanticSearchOperation {
    
    public static function handle(string $queryText, int $limit = 3, float $minThreshold = 0.55): array {
        if (empty(trim($queryText))) {
            return [];
        }

        $db = Database::getConnection();
        
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \Exception("Gemini API key is missing.");
        }

        $client = \Gemini::client($apiKey);

        // 1. Generate query embedding
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($queryText);
        $queryVector = $response->embedding->values;

        // 2. Fetch records
        $stmt = $db->query("SELECT id, title, content, language, file_path, embedding FROM articles WHERE embedding IS NOT NULL");
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoredArticles = [];

        foreach ($articles as $article) {
            $articleVector = json_decode($article['embedding'], true);
            if (!is_array($articleVector)) {
                continue;
            }

            $similarity = self::cosineSimilarity($queryVector, $articleVector);

            // Filter out results below the specified similarity cutoff
            if ($similarity >= $minThreshold) {
                $scoredArticles[] = [
                    'id' => $article['id'],
                    'title' => $article['title'],
                    'content' => $article['content'],
                    'language' => $article['language'],
                    'file_path' => $article['file_path'],
                    'score' => $similarity
                ];
            }
        }

        // 3. Sort descending by score
        usort($scoredArticles, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scoredArticles, 0, $limit);
    }

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