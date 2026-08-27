<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Prefecture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrefectureType extends AbstractType
{
    private const array NAME_LABELS = [
        'romanizedName' => 'label.romanized_name',
        'kanjiName' => 'label.kanji_name',
        'kanaName' => 'label.kana_name',
    ];

    public function __construct(
        private readonly RequestStack $requests,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Prefecture::displayFields($this->locale()) as $rank => $field) {
            $builder->add($field, TextType::class, [
                'label' => self::NAME_LABELS[$field],
                'required' => false,
                'attr' => $rank === 0 ? ['autofocus' => true, 'autocomplete' => 'off'] : ['autocomplete' => 'off'],
            ]);
        }

        $builder
            ->add('notes', TextareaType::class, [
                'label' => 'label.notes',
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
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prefecture::class,
        ]);
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
