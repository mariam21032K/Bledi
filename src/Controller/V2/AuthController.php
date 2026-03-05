<?php

namespace App\Controller\V2;

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

#[Route('/api', name: 'api_v2_')]
class AuthV2Controller extends AbstractController
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

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'success' => false,
                'errors' => [
                    'email' => !isset($data['email']) ? 'Email is required' : null,
                    'password' => !isset($data['password']) ? 'Password is required' : null,
                ],
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'code' => 'VALIDATION_ERROR'
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);
        $password = $data['password'];

        $constraint = new Assert\Email();
        $violations = $this->validator->validate($email, $constraint);
        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid email format',
                'code' => 'INVALID_EMAIL',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGIN_FAILED');
            $auditLog->setEntityName('User');
            $auditLog->setChanges(json_encode(['email' => $email, 'reason' => 'invalid_credentials']));
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => false,
                'error' => 'Invalid email or password',
                'code' => 'AUTHENTICATION_FAILED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'attempts' => 1
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGIN_FAILED');
            $auditLog->setUser($user);
            $auditLog->setEntityName('User');
            $auditLog->setChanges(json_encode(['reason' => 'account_disabled']));
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => false,
                'error' => 'This account has been disabled',
                'code' => 'ACCOUNT_DISABLED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_FORBIDDEN);
        }

        $tokenPair = $this->tokenService->generateTokenPair($user);

        $auditLog = new AuditLog();
        $auditLog->setAction('LOGIN_SUCCESS');
        $auditLog->setUser($user);
        $auditLog->setEntityName('User');
        $auditLog->setChanges(json_encode(['ip' => $request->getClientIp()]));
        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 'LOGIN_SUCCESS',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => [
                'accessToken' => $tokenPair['accessToken'],
                'refreshToken' => $tokenPair['refreshToken'],
                'tokenType' => $tokenPair['tokenType'],
                'expiresIn' => $tokenPair['expiresIn'],
            ],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'role' => $user->getUserRole()?->label(),
                'language' => $user->getLanguage(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['email', 'password', 'firstName', 'lastName'];
        $errors = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = sprintf('%s is required', $field);
            }
        }

        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'errors' => $errors,
                'code' => 'VALIDATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);
        $firstName = trim($data['firstName']);
        $lastName = trim($data['lastName']);
        $password = $data['password'];
        $phone = isset($data['phone']) ? trim($data['phone']) : '';

        $constraint = new Assert\Email();
        $violations = $this->validator->validate($email, $constraint);
        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid email format',
                'code' => 'INVALID_EMAIL',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json([
                'success' => false,
                'error' => 'Password must be at least 8 characters long',
                'code' => 'PASSWORD_TOO_SHORT',
                'requirements' => ['minLength' => 8, 'currentLength' => strlen($password)],
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->json([
                'success' => false,
                'error' => 'Password must contain at least one uppercase letter and one number',
                'code' => 'PASSWORD_WEAK',
                'requirements' => [
                    'uppercase' => preg_match('/[A-Z]/', $password),
                    'number' => preg_match('/[0-9]/', $password),
                ],
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            return $this->json([
                'success' => false,
                'error' => 'An account with this email already exists',
                'code' => 'EMAIL_EXISTS',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_CONFLICT);
        }

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
            'success' => true,
            'code' => 'USER_CREATED',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => [
                'accessToken' => $tokenPair['accessToken'],
                'refreshToken' => $tokenPair['refreshToken'],
                'tokenType' => $tokenPair['tokenType'],
                'expiresIn' => $tokenPair['expiresIn'],
            ],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'role' => $user->getUserRole()?->label(),
                'language' => $user->getLanguage(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
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
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'UNAUTHORIZED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'code' => 'OK',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'role' => $user->getUserRole()?->label(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'language' => $user->getLanguage(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(TokenStorageInterface $tokenStorage): JsonResponse
    {
        $user = $this->getUser();

        if ($user) {
            $auditLog = new AuditLog();
            $auditLog->setAction('LOGOUT');
            $auditLog->setUser($user);
            $auditLog->setEntityName('User');
            $auditLog->setChanges(json_encode(['timestamp' => (new DateTime())->format('Y-m-d H:i:s')]));
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();
        }

        $tokenStorage->setToken(null);

        return $this->json([
            'success' => true,
            'code' => 'LOGOUT_SUCCESS',
            'message' => 'Logged out successfully',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
        ], Response::HTTP_OK);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refreshToken']) || !isset($data['userId'])) {
            return $this->json([
                'success' => false,
                'errors' => [
                    'refreshToken' => !isset($data['refreshToken']) ? 'refreshToken is required' : null,
                    'userId' => !isset($data['userId']) ? 'userId is required' : null,
                ],
                'code' => 'VALIDATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['userId']);

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
                'code' => 'USER_NOT_FOUND',
                'userId' => $data['userId'],
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_NOT_FOUND);
        }

        $tokenPair = $this->tokenService->refreshAccessToken($user, $data['refreshToken']);

        if (!$tokenPair) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid or expired refresh token',
                'code' => 'INVALID_REFRESH_TOKEN',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'code' => 'TOKEN_REFRESHED',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => [
                'accessToken' => $tokenPair['accessToken'],
                'refreshToken' => $tokenPair['refreshToken'],
                'tokenType' => $tokenPair['tokenType'],
                'expiresIn' => $tokenPair['expiresIn'],
                'refreshedAt' => (new DateTime())->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/users/language', name: 'get_user_language', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserLanguage(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'UNAUTHORIZED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'code' => 'OK',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => [
                'language' => $user->getLanguage(),
                'supportedLanguages' => [
                    'en' => ['name' => 'English', 'nativeName' => 'English'],
                    'fr' => ['name' => 'French', 'nativeName' => 'Français'],
                    'ar' => ['name' => 'Arabic', 'nativeName' => 'العربية'],
                    'es' => ['name' => 'Spanish', 'nativeName' => 'Español'],
                ],
                'userId' => $user->getId(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/users/language', name: 'set_user_language', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function setUserLanguage(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'User not authenticated',
                'code' => 'UNAUTHORIZED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['language']) || empty($data['language'])) {
            return $this->json([
                'success' => false,
                'error' => 'language is required',
                'code' => 'VALIDATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $language = trim($data['language']);
        $supportedLanguages = ['en', 'fr', 'ar', 'es'];

        if (!in_array($language, $supportedLanguages)) {
            return $this->json([
                'success' => false,
                'error' => 'Unsupported language',
                'code' => 'INVALID_LANGUAGE',
                'supportedLanguages' => $supportedLanguages,
                'provided' => $language,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        $oldLanguage = $user->getLanguage();
        $user->setLanguage($language);

        $auditLog = new AuditLog();
        $auditLog->setAction('LANGUAGE_CHANGED');
        $auditLog->setUser($user);
        $auditLog->setEntityName('User');
        $auditLog->setChanges(json_encode(['oldLanguage' => $oldLanguage, 'newLanguage' => $language]));
        $this->entityManager->persist($auditLog);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 'LANGUAGE_UPDATED',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => [
                'userId' => $user->getId(),
                'previousLanguage' => $oldLanguage,
                'newLanguage' => $user->getLanguage(),
                'changedAt' => (new DateTime())->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_OK);
    }
}
