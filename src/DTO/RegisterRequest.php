<?php

namespace App\DTO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "RegisterRequest",
    required: ["email", "password", "firstName", "lastName"],
    properties: [
        new OA\Property(property: "email", type: "string", example: "user@example.com"),
        new OA\Property(property: "password", type: "string", example: "Password123"),
        new OA\Property(property: "firstName", type: "string", example: "Mariam"),
        new OA\Property(property: "lastName", type: "string", example: "Ben Ali"),
        new OA\Property(property: "phone", type: "string", example: "12345678")
    ]
)]

class RegisterRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email address')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    #[Assert\Regex(
        pattern: '/[A-Z]/',
        message: 'Password must contain at least one uppercase letter'
    )]
    #[Assert\Regex(
        pattern: '/[0-9]/',
        message: 'Password must contain at least one number'
    )]
    public string $password;

    #[Assert\NotBlank(message: 'First name is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'First name must be at least 2 characters')]
    public string $firstName;

    #[Assert\NotBlank(message: 'Last name is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Last name must be at least 2 characters')]
    public string $lastName;

    #[Assert\Length(max: 8, maxMessage: 'Phone number must not exceed 8 characters')]
    public ?string $phone = null;
}
