<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RefreshTokenRequest
{
    #[Assert\NotBlank(message: 'Refresh token is required')]
    public string $refreshToken;

    #[Assert\NotBlank(message: 'User ID is required')]
    #[Assert\Type('integer', message: 'User ID must be an integer')]
    public int $userId;
}
