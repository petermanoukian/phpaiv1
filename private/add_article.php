<?php
require_once dirname(__DIR__, 1) . '/vendor/autoload.php';

use Central\Database;
use Operations\AI\CreateArticleWithEmbeddingOperation;

$message = '';
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = CreateArticleWithEmbeddingOperation::handleForm($_POST, $_FILES['document'] ?? null);
        $message = $result['message'];
    } catch (\Throwable $e) {
        $message = "Error: " . $e->getMessage();
    }
}

$stmt = $db->query("SELECT COUNT(*) as count FROM articles");
$totalCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Article & Document RAG Node</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Add Article & Attach Document / URLs</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label for="title">Article / Document Title:</label>
            <input type="text" name="title" id="title" required placeholder="e.g., Q3 Financial Report or Technical Guide">

            <label for="language">Language:</label>
            <select name="language" id="language">
                <option value="English">English</option>
                <option value="Eastern Armenian">Eastern Armenian</option>
                <option value="Western Armenian">Western Armenian</option>
                <option value="Arabic">Arabic</option>
            </select>

            <label for="document">Attach File (PDF, DOCX, TXT, JSON, Image):</label>
            <input type="file" name="document" id="document" accept=".pdf,.doc,.docx,.rtf,.txt,.json,.jpg,.jpeg,.png">

            <!-- External URLs Section -->
            <div class="url-group">
                <h4>🔗 External Reference Websites (Optional - Auto-Scraped for AI Embedding)</h4>
                
                <label for="external_url_1" style="margin-top: 5px;">External URL 1:</label>
                <input type="url" name="external_url_1" id="external_url_1" placeholder="https://example.com/docs">

                <label for="external_url_2">External URL 2:</label>
                <input type="url" name="external_url_2" id="external_url_2" placeholder="https://example.com/article">

                <label for="external_url_3">External URL 3:</label>
                <input type="url" name="external_url_3" id="external_url_3" placeholder="https://example.com/reference">
            </div>

            <label for="content">Description / Content Summary:</label>
            <textarea name="content" id="content" required placeholder="Provide a summary or raw text. This text, attached files, and scraped webpage contents will be vectorized together..."></textarea>

            <button type="submit">Save Record & Generate AI Embedding</button>
        </form>

        <div class="stats">Total records currently in database: <strong><?= $totalCount ?></strong></div>
    </div>
</body>
</html>