<?php
namespace Central;
use PDO;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            //require_once __DIR__ . '/../config/config.php';
            
            $dbPath = APP_ROOT . '/database/phpapi1.sqlite';
            self::$instance = new PDO("sqlite:" . $dbPath);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::$instance->exec("
                CREATE TABLE IF NOT EXISTS articles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    content TEXT NOT NULL,
                    language TEXT NOT NULL
                )
            ");
        }
        return self::$instance;
    }
}