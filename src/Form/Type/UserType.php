<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('admin', CheckboxType::class, [
                'label' => 'label.administrator',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'label.enabled',
                'required' => false,
            ])
        ;

        if ($options['require_password']) {
            $builder->add('plainPassword', RepeatedType::class, [
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
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'require_password' => false,
                'validation_groups' => static fn (FormInterface $form): array => $form->getConfig()->getOption('require_password')
                    ? ['Default', 'user:password']
                    : ['Default'],
            ])
            ->setAllowedTypes('require_password', 'bool')
        ;
    }
}
