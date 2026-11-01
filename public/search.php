<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Central\Database;
use Operations\AI\AnalyzeArticleOperation;

$db = Database::getConnection();
$analysisResult = null;
$selectedId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedId = (int)($_POST['article_id'] ?? 0);
    if ($selectedId > 0) {
        try {
            $analysisResult = AnalyzeArticleOperation::handle($selectedId);
        } catch (\Throwable $e) {
            $analysisResult = "Error: " . $e->getMessage();
        }
    }
}

$stmt = $db->query("SELECT id, title, language FROM articles");
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP & Gemini AI Article Analyzer</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>PHP & Gemini Article Analyzer</h1>
        <form method="POST">
            <label for="article_id">Select an Article from Database:</label>
            <select name="article_id" id="article_id" required>
                <option value="">-- Choose an article --</option>
                <?php foreach ($articles as $article): ?>
                    <option value="<?= $article['id'] ?>" <?= $selectedId === $article['id'] ? 'selected' : '' ?>>
                        #<?= $article['id'] ?>: <?= htmlspecialchars($article['title']) ?> (<?= htmlspecialchars($article['language']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-analyzer">Analyze with Gemini AI</button>
        </form>

        <?php if ($analysisResult !== null): ?>
            <div class="result">
                <h3>AI Analysis Result:</h3>
                <?= htmlspecialchars($analysisResult) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>