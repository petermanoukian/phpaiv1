<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Operations\AI\ConversationalSemanticSearchOperation;

$searchData = null;
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = trim($_POST['query'] ?? '');
    if (!empty($query)) {
        try {
            $searchData = ConversationalSemanticSearchOperation::handle($query, 5, 0.50);
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
    <title>Conversational RAG Search (Search 3)</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Conversational AI Search & RAG (Search 3)</h1>
        <p>Ask a question. Gemini will chat with you naturally based on your database records, returning matching files, web links, and articles!</p>
        
        <form method="POST">
            <label for="query">What would you like to know?</label>
            <input type="text" name="query" id="query" required value="<?= htmlspecialchars($query) ?>" placeholder="e.g., Tell me about Lebanese culture, football, and agriculture...">
            <button type="submit" class="btn-search3">Ask AI & Search Database</button>
        </form>

        <?php if (isset($errorMessage)): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($searchData !== null): ?>
            <div class="ai-answer">
                <strong>🤖 AI Response:</strong><br><br>
                <?= nl2br(htmlspecialchars($searchData['answer'])) ?>
            </div>

            <div class="results-container">
                <h3>Retrieved Source Documents (<?= count($searchData['results']) ?> matching records):</h3>
                
                <?php if (empty($searchData['results'])): ?>
                    <p>No records met the relevance threshold.</p>
                <?php else: ?>
                    <?php foreach ($searchData['results'] as $result): ?>
                        <div class="card">
                            <h3>#<?= $result['id'] ?>: <?= htmlspecialchars($result['title']) ?></h3>
                            <div class="meta">
                                Language: <strong><?= htmlspecialchars($result['language']) ?></strong> | 
                                Match Score: <span class="score"><?= number_format($result['score'] * 100, 2) ?>%</span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($result['content'])) ?></p>
                            
                            <!-- Display Attached File Link if present -->
                            <?php if (!empty($result['file_path'])): ?>
                                <div><a href="<?= htmlspecialchars($result['file_path']) ?>" class="file-link" target="_blank">📥 View Attached File</a></div>
                            <?php endif; ?>

                            <!-- Display External Reference URLs if present -->
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <?php $urlKey = "external_url_{$i}"; ?>
                                <?php if (!empty($result[$urlKey])): ?>
                                    <div><a href="<?= htmlspecialchars($result[$urlKey]) ?>" class="file-link" style="background:#e8f8f5; color:#117a65;" target="_blank">🔗 External Link <?= $i ?>: <?= htmlspecialchars($result[$urlKey]) ?></a></div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>