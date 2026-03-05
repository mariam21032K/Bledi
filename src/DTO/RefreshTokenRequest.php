<?php

namespace App\DTO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "RefreshTokenRequest",
    required: ["refreshToken", "userId"],
    properties: [
        new OA\Property(property: "refreshToken", type: "string"),
        new OA\Property(property: "userId", type: "integer", example: 1)
    ]
)]

class RefreshTokenRequest
{
    #[Assert\NotBlank(message: 'Refresh token is required')]
    public string $refreshToken;

    #[Assert\NotBlank(message: 'User ID is required')]
    #[Assert\Type('integer', message: 'User ID must be an integer')]
    public int $userId;
}
