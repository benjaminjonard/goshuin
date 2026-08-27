<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Deity;
use App\Form\DataTransformer\Names;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

class DeityType extends AbstractType
{
    private const array NAME_LABELS = [
        'romanizedName' => 'label.romanized_name',
        'kanjiName' => 'label.kanji_name',
        'kanaName' => 'label.kana_name',
    ];

    public function __construct(
        private readonly Names $names,
        private readonly RequestStack $requests,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Deity::displayFields($this->locale()) as $rank => $field) {
            $builder->add($field, TextType::class, [
                'label' => self::NAME_LABELS[$field],
                'required' => false,
                'attr' => $rank === 0 ? ['autofocus' => true, 'autocomplete' => 'off'] : ['autocomplete' => 'off'],
            ]);
        }

        $builder
            ->add('additionalNames', LiveCollectionType::class, [
                'label' => 'label.additional_names',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'block_prefix' => 'names',
                'button_add_options' => ['label' => 'label.add_name', 'block_prefix' => 'name_add'],
                'button_delete_options' => ['label' => 'label.remove', 'block_prefix' => 'name_delete'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
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
        ;

        $builder->get('additionalNames')
            // Before the collection sizes itself, so a deity known by one name still offers a row.
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
                $named = $event->getData();

                if ($named === null || (is_countable($named) && \count($named) === 0)) {
                    $event->setData(['']);
                }
            }, 1)
            ->addModelTransformer($this->names)
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deity::class,
        ]);
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
