<?php
/**
 * Seeds test records (title prefix "[TEST]") with real Gemini embeddings.
 *
 * Put this next to search3.php / search4.php, then run:
 *   php seed_test_records.php          -> insert records
 *   php seed_test_records.php clean    -> delete them again
 *
 * If your articles table has other NOT NULL columns (created_at etc.),
 * add them to the INSERT below.
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';


use Central\Database;
use Central\GeminiAccess;

$db = Database::getConnection();

if (($argv[1] ?? '') === 'clean') {
    $n = $db->exec("DELETE FROM articles WHERE title LIKE '[TEST]%'");
    echo "Deleted $n test records.\n";
    rebuildFts($db);
    exit;
}

$client = GeminiAccess::getClient();

$records = [
    // ---- KEYWORD-ONLY targets (exact rare words/codes; vectors are weak here) ----
    ['[TEST] Zorbex Industrial Pump', 'The Zorbex pump, unit code QX8821, must be serviced every 400 hours. Replace the gasket kit ZK77 and check valve seals.'],
    ['[TEST] Warranty Ticket 55302', 'Customer ticket 55302 was opened by Mr. Hagopian regarding invoice INV20931. Refund approved on the third attempt.'],

    // ---- VECTOR-ONLY targets (no shared words with the test query) ----
    ['[TEST] Keeping Your Home Comfortable in Summer', 'Close shutters during the day, open windows at night for cross ventilation, use ceiling fans, and add reflective film to glass. These steps lower indoor temperatures without air conditioning.'],
    ['[TEST] Growing Olives in Mountain Terrain', 'Olive trees thrive on rocky slopes with little rain. Farmers prune in late winter and harvest fruit in autumn to press into oil.'],

    // ---- BOTH should find these (shared words AND meaning) ----
    ['[TEST] Lebanese Cuisine Basics', 'Lebanese food features hummus, tabbouleh, grilled meats, and fresh bread. Meals are shared mezze style among family and friends.'],
    ['[TEST] History of Lebanese Football', 'Football is hugely popular in Lebanon. The national team and local clubs draw large crowds, and the league has a long history.'],

    // ---- Distractors so the top-5 cut-off actually matters ----
    ['[TEST] Basic Python Programming', 'Python is a programming language with simple syntax. Variables, loops and functions are the first concepts students learn.'],
    ['[TEST] Marathon Training Plan', 'A sixteen week marathon plan builds weekly mileage gradually, with long runs on weekends and rest days between hard sessions.'],
    ['[TEST] Introduction to Photosynthesis', 'Plants convert sunlight, water and carbon dioxide into glucose and oxygen inside chloroplasts.'],
    ['[TEST] Medieval Armenian Architecture', 'Armenian monasteries were built from volcanic stone, featuring conical domes, carved khachkars and thick defensive walls.'],
    ['[TEST] Personal Budgeting Tips', 'Track monthly income and expenses, set aside savings first, and review subscriptions regularly to cut waste.'],
    ['[TEST] How Vaccines Work', 'Vaccines train the immune system to recognise a pathogen by exposing it to a harmless piece or weakened form of it.'],
];

$stmt = $db->prepare("
    INSERT INTO articles (title, content, language, embedding)
    VALUES (?, ?, 'English', ?)
");

foreach ($records as [$title, $content]) {
    $resp = $client->embeddingModel('gemini-embedding-001')->embedContent($title . "\n" . $content);
    $stmt->execute([$title, $content, json_encode($resp->embedding->values)]);
    echo "Inserted: $title\n";
}

rebuildFts($db);
echo "Done.\n";

function rebuildFts(\PDO $db): void {
    try {
        // Works for external-content / contentless FTS5 tables.
        $db->exec("INSERT INTO articles_fts(articles_fts) VALUES('rebuild')");
        echo "FTS index rebuilt.\n";
    } catch (\Throwable $e) {
        echo "FTS rebuild skipped (" . $e->getMessage() . "). If your FTS isn't auto-synced by triggers, reindex it your usual way.\n";
    }
}