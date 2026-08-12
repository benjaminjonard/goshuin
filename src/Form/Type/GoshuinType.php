<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoshuinType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goshuincho', EntityType::class, [
                'label' => 'label.goshuincho',
                'class' => Goshuincho::class,
                'choice_label' => 'title',
            ])
            ->add('location', EntityType::class, [
                'label' => 'label.received_at',
                'class' => Location::class,
                'choice_label' => 'romanizedName',
                'block_prefix' => 'location',
            ])
            ->add('receivedOn', DateType::class, [
                'label' => 'label.received_on',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'label.image',
                'required' => false,
                'block_prefix' => 'image',
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Goshuin::class,
            'validation_groups' => ['Default', 'goshuin:create'],
        ]);
    }
}
