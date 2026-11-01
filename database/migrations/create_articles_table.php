<?php
// Go up two levels to reach project root, then load Composer autoloader
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // 1. Drop the table completely if it exists to ensure a clean slate
    $db->exec("DROP TABLE IF EXISTS articles");

    // 2. Create the updated table schema with the new 'embedding' column
    $db->exec("
        CREATE TABLE articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            language TEXT NOT NULL,
            embedding TEXT DEFAULT NULL, -- Stores vector embeddings as a JSON string for RAG
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "Migration successful: 'articles' table dropped and recreated with vector embedding support!\n";

} catch (\Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}