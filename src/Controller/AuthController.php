<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\JWTTokenService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1', name: 'api_v1_')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private JWTTokenService $tokenService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input
        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Email and password are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);
        $password = $data['password'];

        // Validate email format
        $constraint = new Assert\Email();
        $violations = $this->validator->validate($email, $constraint);
        if (count($violations) > 0) {
            return $this->json([
                'error' => 'INVALID_EMAIL',
                'message' => 'Invalid email format',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            // Log failed authentication attempt
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGIN_FAILED');
            $auditLog->setEntityName('User');
            $auditLog->setChanges(json_encode(['email' => $email, 'reason' => 'invalid_credentials']));
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid email or password',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            // Log disabled account access attempt
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGIN_FAILED');
            $auditLog->setUser($user);
            $auditLog->setEntityName('User');
            $auditLog->setChanges(json_encode(['reason' => 'account_disabled']));
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'error' => 'ACCOUNT_DISABLED',
                'message' => 'This account has been disabled',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $this->jwtManager->create($user);

        // Log successful login
        $auditLog = new AuditLog();
        $auditLog->setAction('LOGIN_SUCCESS');
        $auditLog->setUser($user);
        $auditLog->setEntityName('User');
        $auditLog->setChanges(json_encode(['ip' => $request->getClientIp()]));
        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        $tokenPair = $this->tokenService->generateTokenPair($user);

        return $this->json([
            'accessToken' => $tokenPair['accessToken'],
            'refreshToken' => $tokenPair['refreshToken'],
            'tokenType' => $tokenPair['tokenType'],
            'expiresIn' => $tokenPair['expiresIn'],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getUserRole()?->label(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['email', 'password', 'firstName', 'lastName'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'error' => 'MISSING_FIELD',
                    'message' => ucfirst($field) . ' is required',
                    'field' => $field,
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $email = trim($data['email']);
        $firstName = trim($data['firstName']);
        $lastName = trim($data['lastName']);
        $password = $data['password'];
        $phone = isset($data['phone']) ? trim($data['phone']) : '';

        // Validate email format
        $constraint = new Assert\Email();
        $violations = $this->validator->validate($email, $constraint);
        if (count($violations) > 0) {
            return $this->json([
                'error' => 'INVALID_EMAIL',
                'message' => 'Invalid email format',
            ], Response::HTTP_BAD_REQUEST);
            }

        // Validate password strength (minimum 8 characters, at least one uppercase, one number)
        if (strlen($password) < 8) {
            return $this->json([
                'error' => 'WEAK_PASSWORD',
                'message' => 'Password must be at least 8 characters long',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->json([
                'error' => 'WEAK_PASSWORD',
                'message' => 'Password must contain at least one uppercase letter and one number',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);

        if ($existingUser) {
            return $this->json([
                'error' => 'USER_EXISTS',
                'message' => 'An account with this email already exists',
            ], Response::HTTP_CONFLICT);
        }

        // Create new user with default CITIZEN role
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPhone($phone);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);
        $user->setUserRole(UserRole::CITIZEN);
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Log user registration
        $auditLog = new AuditLog();
        $auditLog->setAction('USER_REGISTERED');
        $auditLog->setUser($user);
        $auditLog->setEntityName('User');
        $auditLog->setEntityId($user->getId());
        $auditLog->setChanges(json_encode(['email' => $email, 'ip' => $request->getClientIp()]));
        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        $tokenPair = $this->tokenService->generateTokenPair($user);

        return $this->json([
            'accessToken' => $tokenPair['accessToken'],
            'refreshToken' => $tokenPair['refreshToken'],
            'tokenType' => $tokenPair['tokenType'],
            'expiresIn' => $tokenPair['expiresIn'],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getUserRole()?->label(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'error' => 'NOT_AUTHENTICATED',
                'message' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user' => [
                /** @phpstan-ignore-next-line */
                'id' => $user->getId(),
                /** @phpstan-ignore-next-line */
                'email' => $user->getEmail(),
                /** @phpstan-ignore-next-line */
                'firstName' => $user->getFirstName(),
                /** @phpstan-ignore-next-line */
                'lastName' => $user->getLastName(),
                /** @phpstan-ignore-next-line */
                'phone' => $user->getPhone(),
                /** @phpstan-ignore-next-line */
                'role' => $user->getUserRole()?->label(),
                /** @phpstan-ignore-next-line */
                'isActive' => $user->isActive(),
                /** @phpstan-ignore-next-line */
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(TokenStorageInterface $tokenStorage): JsonResponse
    {
        $user = $this->getUser();

        // Log logout
        if ($user) {
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGOUT');
            $auditLog->setUser($user);
            $auditLog->setEntityName('User');
            $this->entityManager->persist($auditLog);
            
            // Invalidate refresh token
            $this->tokenService->invalidateRefreshToken($user);
        }

        $tokenStorage->setToken(null);

        return $this->json([
            'message' => 'Logged out successfully',
        ], Response::HTTP_OK);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refreshToken']) || !isset($data['userId'])) {
            return $this->json([
                'error' => 'MISSING_FIELD',
                'message' => 'refreshToken and userId are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['userId']);

        if (!$user) {
            return $this->json([
                'error' => 'USER_NOT_FOUND',
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $tokenPair = $this->tokenService->refreshAccessToken($user, $data['refreshToken']);

        if (!$tokenPair) {
            return $this->json([
                'error' => 'INVALID_REFRESH_TOKEN',
                'message' => 'Invalid refresh token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'accessToken' => $tokenPair['accessToken'],
            'refreshToken' => $tokenPair['refreshToken'],
            'tokenType' => $tokenPair['tokenType'],
            'expiresIn' => $tokenPair['expiresIn'],
        ], Response::HTTP_OK);
    }
}
