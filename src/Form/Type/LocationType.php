<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\City;
use App\Entity\Location;
use App\Form\DataTransformer\CityName;
use App\Form\DataTransformer\DeityNames;
use App\Form\DataTransformer\PrefectureName;
use App\Enum\LocationType as Kind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

class LocationType extends AbstractType
{
    public function __construct(
        private readonly DeityNames $names,
        private readonly CityName $city,
        private readonly PrefectureName $prefecture,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('romanizedName', TextType::class, [
                'label' => 'label.romanized_name',
                'attr' => ['autofocus' => true],
            ])
            ->add('japaneseName', TextType::class, [
                'label' => 'label.japanese_name',
                'required' => false,
            ])
            ->add('type', EnumType::class, [
                'label' => 'label.location_type',
                'class' => Kind::class,
                'choice_label' => static fn (Kind $kind): string => 'label.'.$kind->value,
                'required' => false,
                'placeholder' => 'label.unrecorded',
            ])
        ;

        if ($options['editing']) {
            $builder
                ->add('deities', LiveCollectionType::class, [
                    'label' => 'label.deities',
                    'entry_type' => TextType::class,
                    'entry_options' => ['label' => false],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'required' => false,
                    'by_reference' => false,
                    'block_prefix' => 'deities',
                    'button_add_options' => ['label' => 'label.add_deity', 'block_prefix' => 'deity_add'],
                    'button_delete_options' => ['label' => 'label.remove', 'block_prefix' => 'deity_delete'],
                ])
                ->add('foundation', TextType::class, [
                    'label' => 'label.foundation',
                    'required' => false,
                ])
            ;
        }

        $builder
            ->add('city', TextType::class, [
                'label' => 'label.city',
                'required' => false,
            ])
            ->add('prefecture', TextType::class, [
                'label' => 'label.prefecture',
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => 'label.address',
                'required' => false,
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'label.latitude',
                'required' => false,
                'scale' => 6,
                'html5' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'label.longitude',
                'required' => false,
                'scale' => 6,
                'html5' => false,
                'attr' => ['inputmode' => 'decimal'],
            ])
        ;

        $builder->get('city')->addModelTransformer($this->city);
        $builder->get('prefecture')->addModelTransformer($this->prefecture);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $location = $event->getData();

            if (!$location instanceof Location) {
                return;
            }

            $city = $location->getCity();

            if ($city instanceof City && $city->getPrefecture() === null) {
                $city->setPrefecture($location->getPrefecture());
            }
        });

        if ($options['editing']) {
            $builder
                ->add('photographFile', FileType::class, [
                    'label' => 'label.main_photograph',
                    'required' => false,
                    'block_prefix' => 'cover',
                    'attr' => ['data-untitled' => true],
                ])
                ->add('removePhotograph', CheckboxType::class, [
                    'label' => 'label.remove',
                    'required' => false,
                    'block_prefix' => 'discard',
                ])
                ->add('notes', TextareaType::class, [
                    'label' => 'label.notes',
                    'required' => false,
                ])
            ;

            $builder->get('deities')
                // Before the collection sizes itself, so an untouched location still offers a row.
                ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
                    $named = $event->getData();

                    if ($named === null || (is_countable($named) && \count($named) === 0)) {
                        $event->setData(['']);
                    }
                }, 1)
                ->addModelTransformer($this->names)
            ;
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => Location::class, 'editing' => true])
            ->setAllowedTypes('editing', 'bool')
        ;
    }
}
