<?php

namespace Operations\AI;

use Central\Database;
use Central\GeminiAccess;

class ConversationalSemanticSearchOperation {

    public static function handle(string $queryText, int $maxLimit = 5, float $minThreshold = 0.45, float $relativeMargin = 0.08): array {
        $trimmedQuery = trim($queryText);
        if ($trimmedQuery === '') {
            return ['answer' => 'Please enter a search query.', 'results' => []];
        }

        $db = Database::getConnection();
        $client = GeminiAccess::getClient();

        // 1. Query embedding
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($trimmedQuery);
        $queryVector = $response->embedding->values;

        // 2. Load articles with all embeddings
        $articles = $db->query("
            SELECT id, title, content, language, file_path,
                   external_url_1, external_url_2, external_url_3,
                   external_url_1_content, external_url_2_content, external_url_3_content,
                   embedding,
                   external_url_1_embedding, external_url_2_embedding, external_url_3_embedding
            FROM articles
            WHERE embedding IS NOT NULL
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $scoredArticles = [];
        foreach ($articles as $article) {
            $best = 0.0;
            foreach (['embedding', 'external_url_1_embedding', 'external_url_2_embedding', 'external_url_3_embedding'] as $col) {
                if (empty($article[$col])) continue;
                $vec = json_decode($article[$col], true);
                if (is_array($vec) && count($vec) === count($queryVector)) {
                    $best = max($best, self::cosineSimilarity($queryVector, $vec));
                }
            }
            if ($best >= $minThreshold) {
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
                    'score' => $best
                ];
            }
        }

        // 3. Sort, then drop records far below the best match
        usort($scoredArticles, fn($a, $b) => $b['score'] <=> $a['score']);

        if (!empty($scoredArticles)) {
            $top = $scoredArticles[0]['score'];
            $scoredArticles = array_values(array_filter($scoredArticles, fn($a) => $a['score'] >= $top - $relativeMargin));
        }

        $topResults = array_slice($scoredArticles, 0, $maxLimit);

        if (empty($topResults)) {
            return ['answer' => "I couldn't find any matching records in your database for that query.", 'results' => []];
        }

        // 4. Conversational answer
        $contextText = "";
        foreach ($topResults as $res) {
            $contextText .= "Title: {$res['title']}\nContent: {$res['content']}\n";
            foreach ([1, 2, 3] as $i) {
                if (!empty($res["external_url_{$i}_content"])) {
                    $contextText .= "URL {$i} Scraped Text: {$res["external_url_{$i}_content"]}\n";
                }
            }
            $contextText .= "\n";
        }

        $prompt = "You are a helpful AI assistant. " .
                  "The user asked this question: \"{$trimmedQuery}\"\n\n" .
                  "Based strictly on the following retrieved database records and their scraped web contents, write a natural, friendly conversational response answering the user's query.\n\n" .
                  "Retrieved Documents:\n" . $contextText;

        try {
            $conversationalAnswer = $client->generativeModel('gemini-2.5-flash')->generateContent($prompt)->text();
        } catch (\Throwable $e) {
            $conversationalAnswer = "Here are the most relevant records found in your database:";
        }

        return ['answer' => $conversationalAnswer, 'results' => $topResults];
    }

    private static function cosineSimilarity(array $vecA, array $vecB): float {
        $dot = 0.0; $normA = 0.0; $normB = 0.0;
        $count = count($vecA);
        for ($i = 0; $i < $count; $i++) {
            $dot += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] ** 2;
            $normB += $vecB[$i] ** 2;
        }
        if ($normA == 0.0 || $normB == 0.0) return 0.0;
        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
