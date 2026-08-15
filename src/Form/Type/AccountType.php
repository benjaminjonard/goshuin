<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\User;
use App\Enum\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccountType extends AbstractType
{
    private const array FLAGS = [
        'en' => '🇬🇧',
        'fr' => '🇫🇷',
    ];

    public function __construct(
        #[Autowire('%app.locales%')] private readonly array $locales,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.your_name',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'label.language',
                'choices' => array_combine(
                    array_map(self::label(...), $this->locales),
                    $this->locales,
                ),
                'choice_translation_domain' => false,
            ])
            ->add('theme', EnumType::class, [
                'label' => 'label.theme',
                'class' => Theme::class,
                'choice_label' => static fn (Theme $theme): string => 'label.theme_'.$theme->value,
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'label.avatar',
                'required' => false,
                'block_prefix' => 'cover',
                'attr' => ['data-untitled' => true],
            ])
            ->add('removeAvatar', CheckboxType::class, [
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
            'data_class' => User::class,
        ]);
    }

    private static function label(string $locale): string
    {
        $name = Locales::getName($locale, $locale);

        return trim((self::FLAGS[$locale] ?? '').' '.mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1));
    }
}
