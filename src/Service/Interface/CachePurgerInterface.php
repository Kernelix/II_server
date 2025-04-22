<?php

namespace App\Service\Interface;

interface CachePurgerInterface
{
    /**
     * Полностью очищает кеш
     *
     * @throws \RuntimeException Если директория кеша не существует
     */
    public function purgeAll(): void;
}
