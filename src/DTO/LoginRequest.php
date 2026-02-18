<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class LoginRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email address')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 1, minMessage: 'Password must not be empty')]
    public string $password;
}
