<?php

namespace Operations\AI;

use Central\Database;
use Central\GeminiAccess;

class ConversationalHybridSearchOperation {

    private const STOPWORDS = ['the','and','about','tell','me','what','is','are','of','in','a','an','to','for','on','how','can','you','do','does','i','my','with','it'];

    public static function handle(string $queryText, int $maxLimit = 5, float $minThreshold = 0.45, float $relativeMargin = 0.08): array {
        $trimmedQuery = trim($queryText);
        if ($trimmedQuery === '') {
            return ['answer' => 'Please enter a search query.', 'results' => []];
        }

        $db = Database::getConnection();
        $client = GeminiAccess::getClient();

        // 1. Keyword search (FTS5 / BM25)
        $tokens = [];
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $trimmedQuery); // hyphens etc. become spaces, all languages kept
        foreach (preg_split('/\s+/u', trim($normalized)) as $word) {
            if ($word === '') continue;
            if (mb_strlen($word) < 2) continue;
            if (in_array(mb_strtolower($word), self::STOPWORDS, true)) continue;
            $tokens[] = '"' . $word . '"';
        }
        $ftsQuery = !empty($tokens) ? implode(' OR ', $tokens) : '"' . str_replace('"', '""', $trimmedQuery) . '"';

        $keywordResults = [];
        $ftsError = null;
        try {
            $ftsStmt = $db->prepare("
                SELECT a.id, a.title, a.content, a.language, a.file_path,
                       a.external_url_1, a.external_url_2, a.external_url_3,
                       a.external_url_1_content, a.external_url_2_content, a.external_url_3_content,
                       rank AS fts_rank
                FROM articles_fts
                JOIN articles a ON articles_fts.rowid = a.id
                WHERE articles_fts MATCH ?
                ORDER BY rank
                LIMIT ?
            ");
            $ftsStmt->execute([$ftsQuery, $maxLimit * 3]);
            $keywordResults = $ftsStmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $ftsError = $e->getMessage();
        }

        // 2. Vector search
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($trimmedQuery);
        $queryVector = $response->embedding->values;

        $articles = $db->query("
            SELECT id, title, content, language, file_path,
                   external_url_1, external_url_2, external_url_3,
                   external_url_1_content, external_url_2_content, external_url_3_content,
                   embedding,
                   external_url_1_embedding, external_url_2_embedding, external_url_3_embedding
            FROM articles
            WHERE embedding IS NOT NULL
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $vectorResults = [];
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
                $article['score'] = $best;
                $vectorResults[] = $article;
            }
        }
        usort($vectorResults, fn($a, $b) => $b['score'] <=> $a['score']);

        // Keep only records close to the best match, then cap the list
        if (!empty($vectorResults)) {
            $top = $vectorResults[0]['score'];
            $vectorResults = array_values(array_filter($vectorResults, fn($r) => $r['score'] >= $top - $relativeMargin));
            $vectorResults = array_slice($vectorResults, 0, $maxLimit * 3);
        }

        // 3. Fusion
        $topResults = self::reciprocalRankFusion($keywordResults, $vectorResults, 60, $maxLimit);

        $debug = [
            'fts_query'  => $ftsQuery,
            'fts_error'  => $ftsError,
            'fts_ids'    => array_column($keywordResults, 'id'),
            'vector_ids' => array_column(array_slice($vectorResults, 0, 10), 'id'),
            'vector_scores' => array_map(fn($r) => round($r['score'], 3), array_slice($vectorResults, 0, 10)),
        ];

        if (empty($topResults)) {
            return ['answer' => "I couldn't find any matching records in your database for that query.", 'results' => [], 'debug' => $debug];
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

        return ['answer' => $conversationalAnswer, 'results' => $topResults, 'debug' => $debug];
    }

    private static function reciprocalRankFusion(array $keywordList, array $vectorList, int $k, int $maxLimit): array {
        $scores = [];
        $registry = [];

        foreach ($keywordList as $rank => $item) {
            $id = $item['id'];
            $registry[$id] = ($registry[$id] ?? []) + $item;
            $registry[$id]['fts_rank'] = $item['fts_rank'];
            $registry[$id]['fts_position'] = $rank + 1;
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
        }

        foreach ($vectorList as $rank => $item) {
            $id = $item['id'];
            $registry[$id] = ($registry[$id] ?? []) + $item;
            $registry[$id]['vector_score'] = $item['score'];
            $registry[$id]['vector_position'] = $rank + 1;
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
        }

        arsort($scores);

        $fused = [];
        foreach ($scores as $id => $rrfScore) {
            $item = $registry[$id];
            $item['rrf_score'] = $rrfScore;
            unset($item['embedding'], $item['external_url_1_embedding'], $item['external_url_2_embedding'], $item['external_url_3_embedding']);
            $fused[] = $item;
            if (count($fused) >= $maxLimit) break;
        }
        return $fused;
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
