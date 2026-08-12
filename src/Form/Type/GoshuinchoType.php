<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Goshuincho;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoshuinchoType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'attr' => ['autofocus' => true],
            ])
            ->add('purchasedAt', DateType::class, [
                'label' => 'label.purchased_on',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('price', IntegerType::class, [
                'label' => 'label.price_paid',
                'required' => false,
                'block_prefix' => 'yen',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
        ;

        if ($options['with_hue']) {
            $builder->add('hue', IntegerType::class, [
                'label' => 'label.hue',
                'block_prefix' => 'hue',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Goshuincho::class,
                'with_hue' => true,
            ])
            ->setAllowedTypes('with_hue', 'bool')
        ;
    }
}
