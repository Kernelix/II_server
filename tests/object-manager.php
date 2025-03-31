<?php

require __DIR__.'/../vendor/autoload.php';

// Устанавливаем обязательные переменные окружения
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? true;
$_ENV['DATABASE_URL'] = $_ENV['DATABASE_URL'] ?? 'mysql://root:@127.0.0.1:3306/test_db?serverVersion=8.0';

// Загружаем .env файл если есть
if (file_exists(__DIR__.'/../.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');
}

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
