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
        $builder
            ->add('parentId', EntityType::class, [
                'class' => Image::class,
                'choice_label' => 'description',
                'label' => 'Parent Image',
                'required' => false, // Поле не обязательно
                'placeholder' => 'Select a parent image (optional)',
            ])
            ->add('filename', FileType::class, [
                'label' => 'New Image',
                'required' => false, // Поле не обязательно для заполнения
                'mapped' => false, // Поле не связано с сущностью
            ])
            ->add('description', TextType::class, [
                'required' => false,
            ])
            ->add('videos', EntityType::class, [
                'class' => Video::class,
                'choice_label' => 'title',
                'multiple' => true, // Позволяет выбрать несколько видео
                'required' => false,
                'placeholder' => 'Select videos',
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Опубликовано',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Image::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token', // Имя поля CSRF-токена
            'csrf_token_id' => 'edit_image', // Идентификатор токена (опционально)
        ]);
    }
}