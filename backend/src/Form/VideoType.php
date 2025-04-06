<?php

namespace App\Form;

use App\Entity\Image;
use App\Entity\Video;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Video>
 */
class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', EntityType::class, [
                'class' => Image::class,
                'choice_label' => 'description',
                'placeholder' => 'Выберите картинку', // Опционально
                'required' => false,
            ])
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'YouTube URL',
            ])
            ->add('title', TextType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
        ]);
    }
}
