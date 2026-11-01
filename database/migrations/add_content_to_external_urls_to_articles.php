<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // Safely add columns to store scraped content for each of the 3 external URLs
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_1_content TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_2_content TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_3_content TEXT DEFAULT NULL");

    echo "Migration successful: Added scraped content columns for external URLs to the 'articles' table!\n";

} catch (\Throwable $e) {
    echo "Migration Notice / Error: " . $e->getMessage() . "\n";
}