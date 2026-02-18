<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class JWTTokenService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * Generate a new JWT token pair (access token + refresh token)
     */
    public function generateTokenPair(User $user): array
    {
        // Generate access token
        $accessToken = $this->jwtManager->create($user);

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken($user);

        // Save refresh token to user
        $user->setRefreshToken($refreshToken);
        $this->entityManager->flush();

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn' => 3600, // 1 hour
            'tokenType' => 'Bearer',
        ];
    }

    /**
     * Generate a new access token from a valid refresh token
     */
    public function refreshAccessToken(User $user, string $refreshToken): ?array
    {
        // Verify refresh token matches
        if ($user->getRefreshToken() !== $refreshToken) {
            return null;
        }

        // Generate new access token
        $newAccessToken = $this->jwtManager->create($user);

        // Optionally generate a new refresh token
        $newRefreshToken = $this->generateRefreshToken($user);
        $user->setRefreshToken($newRefreshToken);
        $this->entityManager->flush();

        return [
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
            'expiresIn' => 3600,
            'tokenType' => 'Bearer',
        ];
    }

    /**
     * Invalidate refresh token (logout)
     */
    public function invalidateRefreshToken(User $user): void
    {
        $user->setRefreshToken(null);
        $this->entityManager->flush();
    }

    /**
     * Generate a secure refresh token
     */
    private function generateRefreshToken(User $user): string
    {
        $data = json_encode([
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16)),
        ]);

        return hash('sha256', $data);
    }
}
