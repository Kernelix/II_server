<?php
namespace App\DataFixtures;

use App\Entity\Image;
use App\Entity\Video;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class VideoFixtures extends Fixture
{
    private const POPULAR_VIDEO_IDS = [
        'Xqf4o5Kvxqs', // Пример видео 1
        'dQw4w9WgXcQ', // Rick Astley - Never Gonna Give You Up
        '9bZkp7q19f0', // PSY - GANGNAM STYLE
        'JGwWNGJdvx8', // Ed Sheeran - Shape of You
        'kJQP7kiw5Fk', // Luis Fonsi - Despacito
        'RgKAFK5djSk', // Wiz Khalifa - See You Again
        'OPf0YbXqDm0', // Mark Ronson - Uptown Funk
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for ($i = 0; $i < 1000; $i++) {
            $video = new Video();

            // Генерация заголовка
            $video->setTitle($this->generateVideoTitle($faker));

            // Генерация embed-ссылки
            $videoId = self::POPULAR_VIDEO_IDS[array_rand(self::POPULAR_VIDEO_IDS)];
            $video->setYoutubeUrl(sprintf('https://www.youtube.com/embed/%s', $videoId));

            // Связь со случайным изображением (ID 1-1000)
            if ($faker->boolean(80)) { // 80% вероятность наличия изображения
                $video->setImage($manager->getReference(Image::class, $faker->numberBetween(1, 1000)));
            }

            $manager->persist($video);

            if ($i % 20 === 0) {
                $manager->flush();
            }
        }

        $manager->flush();
    }

    private function generateVideoTitle(\Faker\Generator $faker): string
    {
        $formats = [
            'Video about %s',
            'Amazing %s video',
            '%s tutorial',
            'How to %s',
            'Best of %s'
        ];

        $topic = $faker->randomElement([
            'nature', 'technology', 'cooking',
            'travel', 'music', 'sports'
        ]);

        return sprintf(
            $faker->randomElement($formats),
            $topic
        );
    }
}