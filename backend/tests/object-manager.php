<?php

require __DIR__ . '/../vendor/autoload.php';

// Жестко задаем тестовые настройки
$testEnv = [
    'APP_ENV' => 'test',
    'APP_DEBUG' => true,
    'DATABASE_URL' => 'mysql://root:@127.0.0.1:3306/test_db?charset=utf8mb4&serverVersion=8.0',
    'DATABASE_NAME' => 'test_db',
];

// Устанавливаем переменные окружения
foreach ($testEnv as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("$key=$value");
}

// Инициализация ядра
$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
