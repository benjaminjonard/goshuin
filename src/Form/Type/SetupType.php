<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use App\Service\LocaleHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SetupType extends AbstractType
{
    public function __construct(
        private readonly LocaleHelper $locales,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.your_name',
                'attr' => ['autofocus' => true, 'autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'label.language',
                'choices' => $this->locales->choices(),
                'choice_translation_domain' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'label.password',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => 'label.password_help',
                ],
                'second_options' => [
                    'label' => 'label.password_repeat',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'error.password_mismatch',
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'user:password'],
        ]);
    }
}
