<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Form\DataTransformer\TagNames;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoshuinType extends AbstractType
{
    public function __construct(
        private readonly TagNames $tags,
        private readonly RequestStack $requests,
    ) {
    }

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
                'choice_label' => fn (Location $location): string => (string) $location->getDisplayName($this->locale()),
                'block_prefix' => 'location',
            ])
            ->add('receivedOn', DateType::class, [
                'label' => 'label.received_on',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'label.image',
                'required' => false,
                'block_prefix' => 'image',
            ])
            ->add('type', EnumType::class, [
                'label' => 'label.goshuin_type',
                'class' => \App\Enum\GoshuinType::class,
                'choice_label' => static fn (\App\Enum\GoshuinType $type): string => 'label.type_'.$type->value,
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'block_prefix' => 'chipset',
            ])
            ->add('price', IntegerType::class, [
                'label' => 'label.price_paid',
                'required' => false,
                'block_prefix' => 'yen',
            ])
            ->add('tags', TextType::class, [
                'label' => 'label.tags',
                'required' => false,
                'by_reference' => false,
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'label.tags_example'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'label.notes',
                'required' => false,
            ])
        ;

        $builder->get('tags')->addModelTransformer($this->tags);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Goshuin::class,
            'validation_groups' => static function (FormInterface $form): array {
                $goshuin = $form->getData();

                if ($goshuin instanceof Goshuin && $goshuin->getImage() !== null) {
                    return ['Default'];
                }

                return ['Default', 'goshuin:create'];
            },
        ]);
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
