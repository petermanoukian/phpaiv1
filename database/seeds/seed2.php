<?php
// Go up two levels to reach project root, then load Composer autoloader
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // 1. Clear any existing records and reset SQLite primary key sequence
    $db->exec("DELETE FROM articles");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='articles'");

    // 2. Rich articles with mock vector embeddings (simulating 3-dimensional vectors for illustration)
    $articles = [
        [
            'title' => 'Mastering Local RAG Architectures in PHP',
            'content' => 'Integrating vector embeddings with SQLite and PHP allows developers to build robust Retrieval-Augmented Generation pipelines without relying on external cloud vector databases.',
            'language' => 'English',
            'embedding' => json_encode([0.123, -0.456, 0.789])
        ],
        [
            'title' => 'The Evolution of Modern Laravel Ecosystems',
            'content' => 'Laravel continues to dominate web development by combining elegant syntax with powerful packages like Livewire, Folio, and Volt for reactive full-stack applications.',
            'language' => 'English',
            'embedding' => json_encode([-0.321, 0.654, -0.987])
        ],
        [
            'title' => 'Automating Development Workflows with n8n',
            'content' => 'Local automation pipelines using n8n and Webhooks synchronize data seamlessly across multiple database instances and local microservices.',
            'language' => 'English',
            'embedding' => json_encode([0.456, 0.123, -0.321])
        ],
        [
            'title' => 'Building Lightweight REST APIs in Pure PHP 8.4',
            'content' => 'Leveraging modern PHP 8.4 features such as property hooks, asymmetric visibility, and native performance improvements enables developers to build lightning-fast APIs.',
            'language' => 'English',
            'embedding' => json_encode([-0.789, -0.654, 0.456])
        ]
    ];

    $stmt = $db->prepare("INSERT INTO articles (title, content, language, embedding) VALUES (:title, :content, :language, :embedding)");

    foreach ($articles as $article) {
        $stmt->execute($article);
    }

    echo "Seeding successful: " . count($articles) . " records inserted with embedding schemas!\n";

} catch (\Throwable $e) {
    echo "Seeding Error: " . $e->getMessage() . "\n";
}