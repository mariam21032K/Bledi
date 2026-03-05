<?php


namespace App\Controller\V2;

use App\Entity\Signalement;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Enum\UserRole;
use App\Enum\SignalementStatus;
use App\Enum\PriorityLevel;
use App\Repository\SignalementRepository;
use App\Repository\CategoryRepository;
use App\Repository\AuditLogRepository;
use App\Service\ValidationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/signalements')]
#[IsGranted('ROLE_USER')]
class SignalementV2Controller extends AbstractController
{
    public function __construct(
        private SignalementRepository $signalementRepository,
        private CategoryRepository $categoryRepository,
        private AuditLogRepository $auditLogRepository,
        private EntityManagerInterface $entityManager,
        private ValidationService $validationService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list_signalements_v2', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listSignalements(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            $offset = ($page - 1) * $limit;

            /** @var User $user */
            $user = $this->getUser();

            $criteria = ['isActive' => true];
            $orderBy = ['createdAt' => 'DESC'];

            // Citizens can only see their own signalements
            if ($user->getUserRole() === UserRole::CITIZEN) {
                $criteria['user'] = $user;
            }

            // Filter by status if provided
            if ($status = $request->query->get('status')) {
                if (in_array($status, ['NEW', 'IN_PROGRESS', 'RESOLVED', 'REJECTED'])) {
                    $criteria['status'] = $status;
                }
            }

            // Filter by priority if provided
            if ($priority = $request->query->get('priority')) {
                if (in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])) {
                    $criteria['priority'] = $priority;
                }
            }

            // Filter by category if provided
            if ($categoryId = $request->query->get('category')) {
                if ($category = $this->categoryRepository->find($categoryId)) {
                    $criteria['category'] = $category;
                }
            }

            $total = $this->signalementRepository->count($criteria);
            $signalements = $this->signalementRepository->findBy(
                $criteria,
                $orderBy,
                $limit,
                $offset
            );

            $data = array_map(fn(Signalement $s) => $this->serializeSignalement($s), $signalements);

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENTS_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'signalements' => $data,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit),
                        'hasNextPage' => $page < ceil($total / $limit),
                        'hasPreviousPage' => $page > 1,
                    ]
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve signalements',
                'code' => 'RETRIEVAL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'get_signalement_v2', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSignalement(Signalement $signalement): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            // Check access permission
            if ($user->getUserRole() === UserRole::CITIZEN && $signalement->getUser() !== $user) {
                return $this->json([
                    'success' => false,
                    'error' => 'Access denied to this signalement',
                    'code' => 'ACCESS_DENIED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENT_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => $this->serializeSignalement($signalement)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Signalement not found',
                'code' => 'SIGNALEMENT_NOT_FOUND',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('', name: 'create_signalement_v2', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSignalement(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            // Only citizens can create signalements
            if ($user->getUserRole() !== UserRole::CITIZEN) {
                return $this->json([
                    'success' => false,
                    'error' => 'Only citizens can create signalements',
                    'code' => 'FORBIDDEN_ROLE',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);

            // Validation constraints
            $constraint = new Assert\Collection([
                'title' => [
                    new Assert\NotBlank(message: 'Title is required'),
                    new Assert\Length(min: 5, max: 255, minMessage: 'Title must be at least 5 characters', maxMessage: 'Title cannot exceed 255 characters'),
                ],
                'description' => [
                    new Assert\NotBlank(message: 'Description is required'),
                    new Assert\Length(min: 10, minMessage: 'Description must be at least 10 characters'),
                ],
                'categoryId' => [
                    new Assert\NotBlank(message: 'Category is required'),
                    new Assert\Type(['type' => 'integer', 'message' => 'Category ID must be an integer']),
                ],
                'latitude' => [
                    new Assert\NotBlank(message: 'Latitude is required'),
                    new Assert\Type(['type' => 'float', 'message' => 'Latitude must be a number']),
                ],
                'longitude' => [
                    new Assert\NotBlank(message: 'Longitude is required'),
                    new Assert\Type(['type' => 'float', 'message' => 'Longitude must be a number']),
                ],
                'address' => [
                    new Assert\NotBlank(message: 'Address is required'),
                    new Assert\Length(min: 5, minMessage: 'Address must be at least 5 characters'),
                ],
                'priority' => new Assert\Optional([
                    new Assert\Choice(['choices' => ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], 'message' => 'Invalid priority value']),
                ]),
            ]);

            $violations = $this->validator->validate($data, $constraint);
            if (count($violations) > 0) {
                return $this->json([
                    'success' => false,
                    'errors' => $this->validationService->formatErrors($violations),
                    'code' => 'VALIDATION_ERROR',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            // Verify category exists
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category) {
                return $this->json([
                    'success' => false,
                    'error' => 'Category not found',
                    'code' => 'CATEGORY_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            // Create signalement
            $signalement = new Signalement();
            $signalement->setTitle($data['title']);
            $signalement->setDescription($data['description']);
            $signalement->setCategory($category);
            $signalement->setUser($user);
            $signalement->setLatitude((float) $data['latitude']);
            $signalement->setLongitude((float) $data['longitude']);
            $signalement->setAddress($data['address']);
            $signalement->setStatus(SignalementStatus::NEW);
            $signalement->setPriority(PriorityLevel::from($data['priority'] ?? 'MEDIUM'));
            $signalement->setIsActive(true);
            $signalement->setCreatedAt(new \DateTimeImmutable());
            $signalement->setUpdatedAt(new \DateTimeImmutable());
            $signalement->setCreatedBy($user->getEmail());
            $signalement->setUpdatedBy($user->getEmail());

            $this->entityManager->persist($signalement);
            $this->entityManager->flush();

            // Log action
            $auditLog = new AuditLog();
            $auditLog->setAction('CREATE');
            $auditLog->setEntityName('Signalement');
            $auditLog->setEntityId($signalement->getId());
            $auditLog->setChanges([
                'title' => $data['title'],
                'category_id' => $data['categoryId'],
                'status' => SignalementStatus::NEW->value,
            ]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);

            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENT_CREATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => $this->serializeSignalement($signalement)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create signalement',
                'code' => 'CREATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update_signalement_v2', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateSignalement(Signalement $signalement, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            // Check permissions
            if ($user->getUserRole() === UserRole::CITIZEN && $signalement->getUser() !== $user) {
                return $this->json([
                    'success' => false,
                    'error' => 'You can only update your own signalements',
                    'code' => 'ACCESS_DENIED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            // Citizens cannot update resolved/rejected signalements
            if ($user->getUserRole() === UserRole::CITIZEN && in_array($signalement->getStatus(), [SignalementStatus::RESOLVED, SignalementStatus::REJECTED])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Cannot update resolved or rejected signalements',
                    'code' => 'INVALID_STATUS_TRANSITION',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($request->getContent(), true) ?? [];
            $changes = [];
            $errors = [];

            // Update title
            if (isset($data['title'])) {
                if (strlen($data['title']) < 5 || strlen($data['title']) > 255) {
                    $errors['title'] = 'Title must be between 5 and 255 characters';
                } else {
                    $changes['title'] = ['from' => $signalement->getTitle(), 'to' => $data['title']];
                    $signalement->setTitle($data['title']);
                }
            }

            // Update description
            if (isset($data['description'])) {
                if (strlen($data['description']) < 10) {
                    $errors['description'] = 'Description must be at least 10 characters';
                } else {
                    $changes['description'] = ['from' => $signalement->getDescription(), 'to' => $data['description']];
                    $signalement->setDescription($data['description']);
                }
            }

            // Update priority
            if (isset($data['priority'])) {
                if (!in_array($data['priority'], ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])) {
                    $errors['priority'] = 'Invalid priority value';
                } else {
                    $changes['priority'] = ['from' => $signalement->getPriority()->value, 'to' => $data['priority']];
                    $signalement->setPriority(PriorityLevel::from($data['priority']));
                }
            }

            // Update address
            if (isset($data['address'])) {
                if (strlen($data['address']) < 5) {
                    $errors['address'] = 'Address must be at least 5 characters';
                } else {
                    $changes['address'] = ['from' => $signalement->getAddress(), 'to' => $data['address']];
                    $signalement->setAddress($data['address']);
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

            if (empty($changes)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No changes provided',
                    'code' => 'NO_CHANGES',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $signalement->setUpdatedAt(new \DateTimeImmutable());
            $signalement->setUpdatedBy($user->getEmail());

            $this->entityManager->flush();

            // Log action
            $auditLog = new AuditLog();
            $auditLog->setAction('UPDATE');
            $auditLog->setEntityName('Signalement');
            $auditLog->setEntityId($signalement->getId());
            $auditLog->setChanges($changes);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);

            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENT_UPDATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'changes' => $changes,
                    'signalement' => $this->serializeSignalement($signalement)
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update signalement',
                'code' => 'UPDATE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete_signalement_v2', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteSignalement(Signalement $signalement): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            // Permission checks
            if ($user->getUserRole() === UserRole::CITIZEN) {
                if ($signalement->getUser() !== $user) {
                    return $this->json([
                        'success' => false,
                        'error' => 'You can only delete your own signalements',
                        'code' => 'ACCESS_DENIED',
                        'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                    ], Response::HTTP_FORBIDDEN);
                }
                if ($signalement->getStatus() !== SignalementStatus::NEW) {
                    return $this->json([
                        'success' => false,
                        'error' => 'You can only delete NEW signalements',
                        'code' => 'INVALID_STATUS',
                        'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Soft delete
            $signalement->setIsActive(false);
            $signalement->setUpdatedAt(new \DateTimeImmutable());
            $signalement->setUpdatedBy($user->getEmail());

            $this->entityManager->flush();

            // Log action
            $auditLog = new AuditLog();
            $auditLog->setAction('DELETE');
            $auditLog->setEntityName('Signalement');
            $auditLog->setEntityId($signalement->getId());
            $auditLog->setChanges(['isActive' => false]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);

            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENT_DELETED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => ['signalementId' => $signalement->getId()]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to delete signalement',
                'code' => 'DELETE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/status', name: 'update_signalement_status_v2', methods: ['PATCH'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function updateSignalementStatus(Signalement $signalement, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['status']) || !in_array($data['status'], ['NEW', 'IN_PROGRESS', 'RESOLVED', 'REJECTED'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid status value',
                    'code' => 'INVALID_STATUS',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser();
            $oldStatus = $signalement->getStatus()->value;

            $signalement->changeStatus(SignalementStatus::from($data['status']));
            $signalement->setUpdatedAt(new \DateTimeImmutable());
            $signalement->setUpdatedBy($user);

            $this->entityManager->flush();

            // Log action
            $auditLog = new AuditLog();
            $auditLog->setAction('UPDATE_STATUS');
            $auditLog->setEntityName('Signalement');
            $auditLog->setEntityId($signalement->getId());
            $auditLog->setChanges(['status' => ['from' => $oldStatus, 'to' => $data['status']]]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);

            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'STATUS_UPDATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'changes' => ['status' => ['from' => $oldStatus, 'to' => $data['status']]],
                    'signalement' => $this->serializeSignalement($signalement)
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update status',
                'code' => 'STATUS_UPDATE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function serializeSignalement(Signalement $signalement): array
    {
        return [
            'id' => $signalement->getId(),
            'title' => $signalement->getTitle(),
            'description' => $signalement->getDescription(),
            'status' => $signalement->getStatus()->value,
            'priority' => $signalement->getPriority()->value,
            'latitude' => $signalement->getLatitude(),
            'longitude' => $signalement->getLongitude(),
            'address' => $signalement->getAddress(),
            'category' => [
                'id' => $signalement->getCategory()->getId(),
                'name' => $signalement->getCategory()->getName(),
            ],
            'user' => [
                'id' => $signalement->getUser()->getId(),
                'email' => $signalement->getUser()->getEmail(),
                'fullName' => $signalement->getUser()->getFirstName() . ' ' . $signalement->getUser()->getLastName(),
            ],
            'mediaCount' => count($signalement->getMedias()),
            'interventionCount' => count($signalement->getInterventions()),
            'createdAt' => $signalement->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $signalement->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'createdBy' => $signalement->getCreatedBy(),
            'updatedBy' => $signalement->getUpdatedBy(),
        ];
    }
}
