<?php

namespace App\Controller\V1;

use OpenApi\Attributes as OA;
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

#[Route('/api', name: 'api_v1_')]
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
    #[OA\Post(
    path: "/api/login",
    summary: "User login",
    tags: ["Authentication"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", example: "mariam@example.com"),
        new OA\Property(property: "password", type: "string", example: "SecurePass1"),
    ]
)),
    responses: [
        new OA\Response(response: 200, description: "Login successful"),
        new OA\Response(response: 401, description: "Invalid credentials"),
        new OA\Response(response: 400, description: "Invalid input")
    ]
)]
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
   #[OA\Post(
    path: "/api/register",
    summary: "Register new user",
    tags: ["Authentication"],
    requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(       // ← add "content:" here
        required: ["email", "password", "firstName", "lastName"],
        properties: [
            new OA\Property(property: "email", type: "string", example: "jane@example.com"),
            new OA\Property(property: "password", type: "string", example: "Secure123"),
            new OA\Property(property: "firstName", type: "string", example: "Jane"),
            new OA\Property(property: "lastName", type: "string", example: "Doe"),
            new OA\Property(property: "phone", type: "string", example: "+1-555-0100"),
        ]
    )
),
responses: [
    new OA\Response(response: 201, description: "User registered successfully"),
    new OA\Response(response: 400, description: "Validation error"),
    new OA\Response(response: 409, description: "User already exists")
   ]
   )]

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
    #[OA\Get(
    path: "/api/me",
    summary: "Get current authenticated user",
    tags: ["Authentication"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "User info"),
        new OA\Response(response: 401, description: "Not authenticated")
    ]
)]
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
    #[OA\Post(
    path: "/api/logout",
    summary: "Logout current user",
    tags: ["Authentication"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Logged out successfully")
    ]
)]
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
    #[OA\Post(
    path: "/api/refresh",
    summary: "Refresh access token",
    tags: ["Authentication"],
   requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["refreshToken", "userId"],
        properties: [
            new OA\Property(property: "refreshToken", type: "string", example: "eyJ..."),
            new OA\Property(property: "userId", type: "integer", example: 42),
        ]
    )                                  // ← closes JsonContent
),                                     // ← closes RequestBody
responses: [
    new OA\Response(response: 200, description: "Token refreshed"),
    new OA\Response(response: 401, description: "Invalid refresh token"),
    new OA\Response(response: 404, description: "User not found")
])]

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

    #[Route('/users/language', name: 'get_user_language', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
    path: "/api/users/language",
    summary: "Get user language preference",
    tags: ["User"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Language retrieved")
    ]
)]
    public function getUserLanguage(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'language' => $user->getLanguage(),
            'supportedLanguages' => [
                'en' => 'English',
                'fr' => 'Français',
                'ar' => 'العربية',
                'es' => 'Español',
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/users/language', name: 'set_user_language', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Put(
    path: "/api/users/language",
    summary: "Update user language preference",
    tags: ["User"],
    security: [["bearerAuth" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["language"],
            properties: [
                new OA\Property(property: "language", type: "string", example: "en")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Language updated"),
        new OA\Response(response: 400, description: "Invalid language")
    ]
)]
    public function setUserLanguage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['language']) || empty($data['language'])) {
            return $this->json([
                'error' => 'MISSING_FIELD',
                'message' => 'Language is required',
                'field' => 'language',
            ], Response::HTTP_BAD_REQUEST);
        }

        $language = trim($data['language']);
        $supportedLanguages = ['en', 'fr', 'ar', 'es'];

        if (!in_array($language, $supportedLanguages)) {
            return $this->json([
                'error' => 'INVALID_LANGUAGE',
                'message' => 'Language not supported. Supported languages: ' . implode(', ', $supportedLanguages),
                'supportedLanguages' => $supportedLanguages,
            ], Response::HTTP_BAD_REQUEST);
        }

        $oldLanguage = $user->getLanguage();
        $user->setLanguage($language);

        // Log the language change
        $auditLog = new AuditLog();
        $auditLog->setAction('LANGUAGE_CHANGED');
        $auditLog->setUser($user);
        $auditLog->setEntityName('User');
        $auditLog->setChanges(json_encode([
            'oldLanguage' => $oldLanguage,
            'newLanguage' => $language,
        ]));
        $this->entityManager->persist($auditLog);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Language preference updated successfully',
            'language' => $user->getLanguage(),
        ], Response::HTTP_OK);
    }
}
