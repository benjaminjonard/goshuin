<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Goshuincho;
use App\Entity\Location;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoshuinchoType extends AbstractType
{
    public function __construct(
        private readonly RequestStack $requests,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'attr' => ['autofocus' => true],
            ])
            ->add('boughtAt', EntityType::class, [
                'label' => 'label.bought_at',
                'class' => Location::class,
                'choice_label' => fn (Location $location): string => (string) $location->getDisplayName($this->locale()),
                'required' => false,
                'block_prefix' => 'location',
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
            ->add('coverFrontFile', FileType::class, [
                'label' => 'label.cover_front',
                'required' => false,
                'block_prefix' => 'cover',
            ])
            ->add('removeCoverFront', CheckboxType::class, [
                'label' => 'label.remove_cover',
                'required' => false,
            ])
            ->add('coverBackFile', FileType::class, [
                'label' => 'label.cover_back',
                'required' => false,
                'block_prefix' => 'cover',
            ])
            ->add('removeCoverBack', CheckboxType::class, [
                'label' => 'label.remove_cover',
                'required' => false,
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
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
