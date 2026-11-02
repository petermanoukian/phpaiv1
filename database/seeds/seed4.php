<?php
/**
 * Two themed sets of 6 test records, each with a real Gemini embedding.
 * Put next to search3.php, then run from the command line:
 *   php seed_two_sets.php          -> clean old [TEST] rows, insert these 12
 *   php seed_two_sets.php clean    -> only delete [TEST] rows
 *
 * If your articles table has other NOT NULL columns, add them to the INSERT.
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;
use Central\GeminiAccess;

$db = Database::getConnection();

$n = $db->exec("DELETE FROM articles WHERE title LIKE '[TEST]%'");
echo "Removed $n old [TEST] rows.\n";

if (($argv[1] ?? '') !== 'clean') {
    $client = GeminiAccess::getClient();

    $records = [
        // ===== SET A: COFFEE =====
        ['[TEST] A1 Espresso Brewing Guide',
         'Espresso is made by forcing hot water through finely ground coffee at about nine bars of pressure. A good shot takes roughly thirty seconds and has a thick golden crema.'],
        ['[TEST] A2 Cold Brew Method',
         'Cold brew is made by steeping coarse grounds in cold water for eighteen hours. The result is a smooth, low acid drink that is served over ice.'],
        ['[TEST] A3 Coffee Bean Origins',
         'Arabica beans grow at high altitude in Ethiopia and Colombia and taste sweet and fruity. Robusta is grown in Vietnam and is stronger and more bitter.'],
        ['[TEST] A4 Caffeine and Sleep',
         'Caffeine blocks adenosine receptors in the brain. Drinking it late in the afternoon can delay falling asleep and reduce deep sleep.'],
        ['[TEST] A5 Grinder Manual',
         'The burr grinder model KR7731 has forty grind settings. Clean the burrs once a month and never grind oily dark roast beans without wiping the hopper.'],
        ['[TEST] A6 Cafe Staff Profile',
         'Tigran is the head barista at the Kaffa Corner cafe. He trains new staff on latte art and has won two regional competitions.'],

        // ===== SET B: SOLAR POWER =====
        ['[TEST] B1 How Solar Panels Work',
         'Photovoltaic cells convert sunlight directly into electricity. Each cell uses silicon layers that release electrons when light hits them.'],
        ['[TEST] B2 Home Battery Storage',
         'Home batteries store extra power produced during the day so it can be used in the evening when the panels stop producing.'],
        ['[TEST] B3 Installation Costs and Payback',
         'A typical home system costs a few thousand dollars per kilowatt installed. Most owners recover the investment in six to eight years through lower bills.'],
        ['[TEST] B4 Cleaning and Maintenance',
         'Dust and bird droppings reduce panel output. Rinse the panels with water in the early morning and check the mounting bolts once a year.'],
        ['[TEST] B5 Inverter Datasheet',
         'The inverter model SX9904 converts direct current from the panels into alternating current for the house. It has ninety seven percent efficiency and a ten year warranty.'],
        ['[TEST] B6 Net Metering Rules',
         'Under net metering the utility company credits homeowners for surplus electricity they send back to the grid, which lowers the monthly bill.'],
    ];

    $stmt = $db->prepare("INSERT INTO articles (title, content, language, embedding) VALUES (?, ?, 'English', ?)");
    foreach ($records as [$title, $content]) {
        $vec = $client->embeddingModel('gemini-embedding-001')->embedContent($title . "\n" . $content)->embedding->values;
        $stmt->execute([$title, $content, json_encode($vec)]);
        echo "Inserted: $title\n";
    }
}

// Sync the keyword index
try {
    $db->exec("INSERT INTO articles_fts(articles_fts) VALUES('rebuild')");
    echo "FTS rebuilt (external-content table).\n";
} catch (\Throwable $e) {
    echo "FTS 'rebuild' failed: " . $e->getMessage() . "\n";
    echo "If articles_fts is a standalone table, run:\n";
    echo "  DELETE FROM articles_fts;\n";
    echo "  INSERT INTO articles_fts(rowid, title, content) SELECT id, title, content FROM articles;\n";
}
echo "Done.\n";
