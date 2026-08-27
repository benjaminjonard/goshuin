<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\City;
use App\Entity\Prefecture;
use App\Repository\PrefectureRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CityType extends AbstractType
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
        foreach (City::displayFields($this->locale()) as $rank => $field) {
            $builder->add($field, TextType::class, [
                'label' => self::NAME_LABELS[$field],
                'required' => false,
                'attr' => $rank === 0 ? ['autofocus' => true, 'autocomplete' => 'off'] : ['autocomplete' => 'off'],
            ]);
        }

        $builder
            ->add('prefecture', EntityType::class, [
                'label' => 'label.prefecture',
                'class' => Prefecture::class,
                'choice_label' => fn (Prefecture $prefecture): string => (string) $prefecture->getDisplayName($this->locale()),
                'query_builder' => fn (PrefectureRepository $prefectures): QueryBuilder => $prefectures
                    ->createQueryBuilder('p')
                    ->addSelect(sprintf('COALESCE(%s) AS HIDDEN name_order', implode(', ', array_map(
                        static fn (string $field): string => sprintf("NULLIF(p.%s, '')", $field),
                        Prefecture::orderFields($this->locale()),
                    ))))
                    ->orderBy('name_order', 'ASC'),
                'required' => false,
                'placeholder' => 'label.unrecorded',
            ])
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
            'data_class' => City::class,
        ]);
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
