<?php

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationService
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Validate a DTO object
     */
    public function validate(object $dto): array
    {
        $violations = $this->validator->validate($dto);
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            if (!isset($errors[$propertyPath])) {
                $errors[$propertyPath] = [];
            }
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return $errors;
    }

    /**
     * Check if validation has errors
     */
    public function hasErrors(array $errors): bool
    {
        return count($errors) > 0;
    }

    /**
     * Format errors for API response
     */
    public function formatErrors(array|ConstraintViolationListInterface $errors): array
    {
        if ($errors instanceof ConstraintViolationListInterface) {
            // Convert ConstraintViolationList to array
            $errorArray = [];
            foreach ($errors as $violation) {
                $propertyPath = $violation->getPropertyPath();
                if (!isset($errorArray[$propertyPath])) {
                    $errorArray[$propertyPath] = [];
                }
                $errorArray[$propertyPath][] = $violation->getMessage();
            }
            $errors = $errorArray;
        }

        $formatted = [];
        foreach ($errors as $field => $messages) {
            $formatted[] = [
                'field' => $field,
                'messages' => is_array($messages) ? $messages : [$messages],
            ];
        }

        return $formatted;
    }
}
