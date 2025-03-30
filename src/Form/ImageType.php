<?php
namespace App\Form;

use App\Entity\Image;
use App\Entity\Video;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        if ($options['include_parent_field']) {
            $builder->add('parentId', EntityType::class, [
                'class' => Image::class,
                'choice_label' => 'description',
                'label' => 'Parent Image',
                'required' => false, // Поле не обязательно
                'placeholder' => 'Select a parent image (optional)',
            ]);
        }
        $builder->add('filename', FileType::class, [
            'label' => 'New Image',
            'required' => false, // Поле не обязательно для заполнения
            'mapped' => false, // Поле не связано с сущностью
        ]);
        if ($options['include_description_field']) {
            $builder->add('description', TextType::class, [
                'required' => false,
            ]);
        }
        if ($options['include_videos_field']) {
            $builder->add('videos', EntityType::class, [
                'class' => Video::class,
                'choice_label' => 'title',
                'multiple' => true, // Позволяет выбрать несколько видео
                'required' => false,
                'placeholder' => 'Select videos',
            ]);
        }
        if ($options['isPublished']) {
            $builder->add('isPublished', CheckboxType::class, [
                'label' => 'Опубликовано',
                'required' => false,
            ]);
        }
        if ($options['isFeatured']) {
            $builder->add('isFeatured', CheckboxType::class, [
                'label' => 'В галерее админки',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Image::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token', // Имя поля CSRF-токена
            'csrf_token_id' => 'edit_image', // Идентификатор токена (опционально)
            'include_parent_field' => true,
            'include_description_field' => true,
            'include_videos_field' => true,
            'isPublished' => true,
            'isFeatured' => true
        ]);
    }
}