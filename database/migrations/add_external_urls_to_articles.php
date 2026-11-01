<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // Safely add missing columns one by one
    @$db->exec("ALTER TABLE articles ADD COLUMN file_path TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_1 TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_2 TEXT DEFAULT NULL");
    @$db->exec("ALTER TABLE articles ADD COLUMN external_url_3 TEXT DEFAULT NULL");

    echo "Migration successful: Added file_path and external URL columns to the 'articles' table!\n";

} catch (\Throwable $e) {
    echo "Migration Notice / Error: " . $e->getMessage() . "\n";
}