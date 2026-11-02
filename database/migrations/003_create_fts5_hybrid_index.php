<?php
// database/migrations/003_create_fts5_hybrid_index.php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // 1. Create FTS5 Virtual Table pointing to articles table
    $db->exec("
        CREATE VIRTUAL TABLE IF NOT EXISTS articles_fts USING fts5(
            title,
            content,
            content='articles',
            content_rowid='id'
        );
    ");

    // 2. Create Synchronization Triggers
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS articles_ai AFTER INSERT ON articles BEGIN
            INSERT INTO articles_fts(rowid, title, content) VALUES (new.id, new.title, new.content);
        END;
    ");

    $db->exec("
        CREATE TRIGGER IF NOT EXISTS articles_ad AFTER DELETE ON articles BEGIN
            INSERT INTO articles_fts(articles_fts, rowid, title, content) VALUES('delete', old.id, old.title, old.content);
        END;
    ");

    $db->exec("
        CREATE TRIGGER IF NOT EXISTS articles_au AFTER UPDATE ON articles BEGIN
            INSERT INTO articles_fts(articles_fts, rowid, title, content) VALUES('delete', old.id, old.title, old.content);
            INSERT INTO articles_fts(rowid, title, content) VALUES (new.id, new.title, new.content);
        END;
    ");

    // 3. Populate FTS5 table if empty
    $stmt = $db->query("SELECT COUNT(*) FROM articles_fts");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO articles_fts(rowid, title, content) SELECT id, title, content FROM articles;");
    }

    echo "Migration successful: Created FTS5 virtual table and sync triggers for hybrid search!\n";

} catch (\Throwable $e) {
    echo "Migration Notice / Error: " . $e->getMessage() . "\n";
}