<?php

namespace App\DTO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
#[OA\Schema(
    schema: "LoginRequest",
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", example: "user@example.com"),
        new OA\Property(property: "password", type: "string", example: "Password123")
    ]
)]

class LoginRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email address')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 1, minMessage: 'Password must not be empty')]
    public string $password;
}
