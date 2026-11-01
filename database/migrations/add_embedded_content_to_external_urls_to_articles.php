<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // 1. Columns for raw scraped text content


    // 2. Columns for individual URL vector embeddings (JSON array strings)
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_1_embedding TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_2_embedding TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_3_embedding TEXT DEFAULT NULL");

    echo "Migration successful: Added text content and individual vector embedding columns for all 3 external URLs!\n";

} catch (\Throwable $e) {
    echo "Migration Notice / Error: " . $e->getMessage() . "\n";
}