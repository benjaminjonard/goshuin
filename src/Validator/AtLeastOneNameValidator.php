<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Interface\Named;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AtLeastOneNameValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AtLeastOneName) {
            return;
        }

        if (!$value instanceof Named) {
            throw new UnexpectedValueException($value, Named::class);
        }

        if ($value->hasAName()) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
