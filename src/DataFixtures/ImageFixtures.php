<?php

namespace App\DataFixtures;

use App\Entity\Image;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ImageFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        for ($i = 0; $i < 1000; $i++) {
            $image = new Image();
            $image->setDescription("Тестовое изображение $i");
            $image->setFileName('67fa4b9d94abb.jpg');
            $image->setIsFeatured($i % 10 === 0);
            $image->setIsPublished(true);


            $manager->persist($image);

            // Пакетное сохранение для оптимизации
            if ($i % 50 === 0) {
                $manager->flush();
                $manager->clear();
            }
        }

        $manager->flush();
    }
}
