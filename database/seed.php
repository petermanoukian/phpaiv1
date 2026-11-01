<?php
// Load Composer autoloader (which automatically runs config/config.php and sets APP_ROOT)
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Central\Database;

try {
    $db = Database::getConnection();

    // 1. Truncate the articles table and reset auto-increment primary key
    $db->exec("DELETE FROM articles");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='articles'");

    // 2. Prepare rich, diverse content rows
    $articles = [
        [
            'title' => 'Mastering Local RAG Architectures in PHP',
            'content' => 'Integrating vector embeddings with SQLite and PHP allows developers to build robust Retrieval-Augmented Generation pipelines without relying on external cloud vector databases. This ensures complete data privacy and sub-millisecond local execution times.',
            'language' => 'English'
        ],
        [
            'title' => 'The Evolution of Modern Laravel Ecosystems',
            'content' => 'Laravel continues to dominate web development by combining elegant syntax with powerful packages like Livewire, Folio, and Volt for reactive full-stack applications without complex JavaScript build steps.',
            'language' => 'English'
        ],
        [
            'title' => 'Automating Development Workflows with n8n',
            'content' => 'Local automation pipelines using n8n and Webhooks synchronize data seamlessly across multiple database instances, local microservices, and custom APIs with visual node-based logic.',
            'language' => 'English'
        ],
        [
            'title' => 'Building Lightweight REST APIs in Pure PHP 8.4',
            'content' => 'Leveraging modern PHP 8.4 features such as property hooks, asymmetric visibility, and native performance improvements enables developers to build lightning-fast APIs without bloated framework overhead.',
            'language' => 'English'
        ],
        [
            'title' => 'SQLite for Enterprise Microservices',
            'content' => 'With WAL mode enabled and optimized synchronous settings, SQLite handles thousands of concurrent read operations effortlessly, making it an exceptional choice for modern single-server microservices.',
            'language' => 'English'
        ]
    ];

    $stmt = $db->prepare("INSERT INTO articles (title, content, language) VALUES (:title, :content, :language)");
    
    foreach ($articles as $article) {
        $stmt->execute($article);
    }

    echo "Database successfully truncated and seeded with " . count($articles) . " comprehensive articles!\n";

} catch (\Throwable $e) {
    echo "Seeding Error: " . $e->getMessage();
}