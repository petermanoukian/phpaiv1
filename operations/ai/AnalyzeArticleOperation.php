<?php
namespace Operations\AI;

use Central\AI;
use Central\Database;

class AnalyzeArticleOperation {
    public static function handle(int $articleId): string {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT title, content, language FROM articles WHERE id = :id");
        $stmt->execute(['id' => $articleId]);
        $article = $stmt->fetch();

        if (!$article) {
            throw new \RuntimeException("Article not found.");
        }

        $prompt = "You are a veteran editor. Analyze this article titled '{$article['title']}' ({$article['language']}):\n" .
                  "Provide a 3-bullet structural critique:\n\n" . $article['content'];

        $aiService = new AI();
        return $aiService->getClient()->generativeModel('gemini-2.5-flash')->generateContent($prompt)->text();
    }
}