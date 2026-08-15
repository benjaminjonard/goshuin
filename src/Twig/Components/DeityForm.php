<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Deity;
use App\Form\Type\DeityType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class DeityForm
{
    use LiveCollectionTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(fieldName: 'edited')]
    public ?Deity $deity = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->forms->create(DeityType::class, $this->deity);
    }
}
