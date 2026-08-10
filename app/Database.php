<?php
declare(strict_types=1);

final class Database {
    private static ?PDO $pdo = null;
    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        $envFile = dirname(__DIR__).'/.env';
        if (is_file($envFile)) foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$k,$v]=array_map('trim',explode('=', $line, 2)); $_ENV[$k]=trim($v,'"\'');
        }
        $env = static function(string $key, string $default=''): string { return $_ENV[$key] ?? getenv($key) ?: $default; };
        $dsn = 'mysql:host='.$env('DB_HOST','127.0.0.1').';port='.$env('DB_PORT','3306').';dbname='.$env('DB_DATABASE','remuneraciones').';charset=utf8mb4';
        self::$pdo = new PDO($dsn, $env('DB_USERNAME','root'), $env('DB_PASSWORD',''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
