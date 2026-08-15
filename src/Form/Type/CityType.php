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
use Symfony\Component\OptionsResolver\OptionsResolver;

class CityType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => ['autofocus' => true, 'autocomplete' => 'off'],
            ])
            ->add('prefecture', EntityType::class, [
                'label' => 'label.prefecture',
                'class' => Prefecture::class,
                'choice_label' => 'name',
                'query_builder' => static fn (PrefectureRepository $prefectures): QueryBuilder => $prefectures
                    ->createQueryBuilder('p')
                    ->orderBy('p.name', 'ASC'),
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
}
