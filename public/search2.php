<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Operations\AI\FilteredSemanticSearchOperation;

$searchResults = null;
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = trim($_POST['query'] ?? '');
    if (!empty($query)) {
        try {
            $searchResults = FilteredSemanticSearchOperation::handle($query, 3, 0.55);
        } catch (\Throwable $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Semantic Vector Search (Search 2)</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Semantic Vector Search (Search 2)</h1>
        <p>Type any natural language query. Gemini will vectorize your question and retrieve the most semantically relevant records and attached files from SQLite.</p>
        
        <form method="POST">
            <label for="query">Search Query:</label>
            <input type="text" name="query" id="query" required value="<?= htmlspecialchars($query) ?>" placeholder="e.g., What are the architectural guidelines for microservices?">
            <button type="submit">Execute Semantic Search</button>
        </form>

        <?php if (isset($errorMessage)): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($searchResults !== null): ?>
            <div class="results-container">
                <h3>Top Semantic Matches (<?= count($searchResults) ?> found):</h3>
                
                <?php if (empty($searchResults)): ?>
                    <p>No matching records found in the database.</p>
                <?php else: ?>
                    <?php foreach ($searchResults as $result): ?>
                        <div class="card">
                            <h3>#<?= $result['id'] ?>: <?= htmlspecialchars($result['title']) ?></h3>
                            <div class="meta">
                                Language: <strong><?= htmlspecialchars($result['language']) ?></strong> | 
                                Cosine Similarity Score: <span class="score"><?= number_format($result['score'] * 100, 2) ?>%</span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($result['content'])) ?></p>
                            
                            <?php if (!empty($result['file_path'])): ?>
                                <a href="<?= htmlspecialchars($result['file_path']) ?>" class="file-link" target="_blank">📥 View Attached File</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>