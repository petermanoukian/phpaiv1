<?php

namespace Operations\AI;

use Central\Database;

class ConversationalSemanticSearchOperation {
    
    public static function handle(string $queryText, int $maxLimit = 5, float $minThreshold = 0.45): array {
        $trimmedQuery = trim($queryText);
        if (empty($trimmedQuery)) {
            return ['answer' => 'Please enter a search query.', 'results' => []];
        }

        $db = Database::getConnection();
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \Exception("Gemini API key is missing.");
        }

        $client = \Gemini::client($apiKey);

        // 1. Generate query vector embedding from user query
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($trimmedQuery);
        $queryVector = $response->embedding->values;

        // 2. Fetch stored articles along with URLs, text contents, and all individual embeddings
        $stmt = $db->query("
            SELECT id, title, content, language, file_path, 
                   external_url_1, external_url_2, external_url_3, 
                   external_url_1_content, external_url_2_content, external_url_3_content, 
                   embedding, 
                   external_url_1_embedding, external_url_2_embedding, external_url_3_embedding 
            FROM articles 
            WHERE embedding IS NOT NULL
        ");
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoredArticles = [];

        foreach ($articles as $article) {
            $bestSimilarity = 0.0;

            // Check main article embedding
            $articleVector = json_decode($article['embedding'], true);
            if (is_array($articleVector)) {
                $sim = self::cosineSimilarity($queryVector, $articleVector);
                if ($sim > $bestSimilarity) {
                    $bestSimilarity = $sim;
                }
            }

            // Check URL 1 individual embedding
            if (!empty($article['external_url_1_embedding'])) {
                $vec1 = json_decode($article['external_url_1_embedding'], true);
                if (is_array($vec1)) {
                    $sim = self::cosineSimilarity($queryVector, $vec1);
                    if ($sim > $bestSimilarity) {
                        $bestSimilarity = $sim;
                    }
                }
            }

            // Check URL 2 individual embedding
            if (!empty($article['external_url_2_embedding'])) {
                $vec2 = json_decode($article['external_url_2_embedding'], true);
                if (is_array($vec2)) {
                    $sim = self::cosineSimilarity($queryVector, $vec2);
                    if ($sim > $bestSimilarity) {
                        $bestSimilarity = $sim;
                    }
                }
            }

            // Check URL 3 individual embedding
            if (!empty($article['external_url_3_embedding'])) {
                $vec3 = json_decode($article['external_url_3_embedding'], true);
                if (is_array($vec3)) {
                    $sim = self::cosineSimilarity($queryVector, $vec3);
                    if ($sim > $bestSimilarity) {
                        $bestSimilarity = $sim;
                    }
                }
            }

            // Filter out results below threshold based on the best similarity found across all vectors
            if ($bestSimilarity >= $minThreshold) {
                $scoredArticles[] = [
                    'id' => $article['id'],
                    'title' => $article['title'],
                    'content' => $article['content'],
                    'language' => $article['language'],
                    'file_path' => $article['file_path'],
                    'external_url_1' => $article['external_url_1'],
                    'external_url_2' => $article['external_url_2'],
                    'external_url_3' => $article['external_url_3'],
                    'external_url_1_content' => $article['external_url_1_content'],
                    'external_url_2_content' => $article['external_url_2_content'],
                    'external_url_3_content' => $article['external_url_3_content'],
                    'score' => $bestSimilarity
                ];
            }
        }

        // 3. Sort by highest similarity score
        usort($scoredArticles, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Dynamic slice (max $maxLimit records)
        $topResults = array_slice($scoredArticles, 0, $maxLimit);

        if (empty($topResults)) {
            return [
                'answer' => "I couldn't find any matching records in your database for that query.",
                'results' => []
            ];
        }

        // 4. Build complete context including stored scraped website contents for Gemini synthesis
        $contextText = "";
        foreach ($topResults as $res) {
            $contextText .= "Title: {$res['title']}\nContent: {$res['content']}\n";
            if (!empty($res['external_url_1_content'])) {
                $contextText .= "URL 1 Scraped Text: {$res['external_url_1_content']}\n";
            }
            if (!empty($res['external_url_2_content'])) {
                $contextText .= "URL 2 Scraped Text: {$res['external_url_2_content']}\n";
            }
            if (!empty($res['external_url_3_content'])) {
                $contextText .= "URL 3 Scraped Text: {$res['external_url_3_content']}\n";
            }
            $contextText .= "\n";
        }

        $prompt = "You are a helpful AI assistant. " .
                  "The user asked this question: \"{$trimmedQuery}\"\n\n" .
                  "Based strictly on the following retrieved database records and their scraped web contents, write a natural, friendly conversational response answering the user's query.\n\n" .
                  "Retrieved Documents:\n" . $contextText;

        try {
            $aiResponse = $client->generativeModel('gemini-2.5-flash')->generateContent($prompt);
            $conversationalAnswer = $aiResponse->text();
        } catch (\Throwable $e) {
            $conversationalAnswer = "Here are the most relevant records found in your database:";
        }

        return [
            'answer' => $conversationalAnswer,
            'results' => $topResults
        ];
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