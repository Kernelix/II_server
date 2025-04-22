<?php

namespace App\Repository\Interface;

interface BaseRepositoryInterface
{
    public function save($entity, bool $flush = false): void;
    public function remove($entity, bool $flush = false): void;
}
