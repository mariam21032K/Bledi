<?php

namespace App\Controller\V2;

use App\Entity\Category;
use App\Entity\AuditLog;
use App\Repository\CategoryRepository;
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

#[Route('/api/categories')]
#[IsGranted('ROLE_USER')]
class CategoryV2Controller extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private ValidationService $validationService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list_categories', methods: ['GET'])]
    public function listCategories(): JsonResponse
    {
        try {
            $categories = $this->categoryRepository->findBy(['isActive' => true], ['name' => 'ASC']);
            $data = array_map(fn(Category $c) => $this->serializeCategory($c), $categories);

            return $this->json([
                'success' => true,
                'code' => 'CATEGORIES_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'categories' => $data,
                    'total' => count($data),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve categories',
                'code' => 'RETRIEVAL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'get_category', methods: ['GET'])]
    public function getCategory(Category $category): JsonResponse
    {
        if (!$category->isActive()) {
            return $this->json([
                'success' => false,
                'error' => 'Category not found',
                'code' => 'CATEGORY_NOT_FOUND',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'code' => 'CATEGORY_RETRIEVED',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'data' => $this->serializeCategory($category)
        ], Response::HTTP_OK);
    }

    #[Route('', name: 'create_category', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createCategory(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        // Validation
        $errors = [];
        if (!isset($data['name']) || empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 3 || strlen($data['name']) > 100) {
            $errors['name'] = 'Name must be between 3 and 100 characters';
        }

        if (!isset($data['description']) || empty($data['description'])) {
            $errors['description'] = 'Description is required';
        } elseif (strlen($data['description']) < 5 || strlen($data['description']) > 500) {
            $errors['description'] = 'Description must be between 5 and 500 characters';
        }

        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'errors' => $errors,
                'code' => 'VALIDATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->categoryRepository->findOneBy(['name' => $data['name']])) {
            return $this->json([
                'success' => false,
                'error' => 'Category with this name already exists',
                'code' => 'DUPLICATE_CATEGORY',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_CONFLICT);
        }

        try {
            $category = new Category();
            $category->setName($data['name']);
            $category->setDescription($data['description']);
            $category->setIsActive(true);
            $category->setCreatedAt(new \DateTimeImmutable());
            $category->setUpdatedAt(new \DateTimeImmutable());
            $category->setCreatedBy($user);
            $category->setUpdatedBy($user);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $auditLog = new AuditLog();
            $auditLog->setAction('CREATE');
            $auditLog->setEntityName('Category');
            $auditLog->setEntityId($category->getId());
            $auditLog->setChanges($data);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'CATEGORY_CREATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => $this->serializeCategory($category)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create category',
                'code' => 'CREATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update_category', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateCategory(Category $category, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];
        $changes = [];
        $errors = [];

        try {
            if (isset($data['name'])) {
                if (strlen($data['name']) < 3 || strlen($data['name']) > 100) {
                    $errors['name'] = 'Name must be between 3 and 100 characters';
                } else {
                    $existing = $this->categoryRepository->findOneBy(['name' => $data['name']]);
                    if ($existing && $existing->getId() !== $category->getId()) {
                        $errors['name'] = 'Name already exists';
                    } else {
                        $changes['name'] = ['from' => $category->getName(), 'to' => $data['name']];
                        $category->setName($data['name']);
                    }
                }
            }

            if (isset($data['description'])) {
                if (strlen($data['description']) < 5 || strlen($data['description']) > 500) {
                    $errors['description'] = 'Description must be between 5 and 500 characters';
                } else {
                    $changes['description'] = ['from' => $category->getDescription(), 'to' => $data['description']];
                    $category->setDescription($data['description']);
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

            $category->setUpdatedAt(new \DateTimeImmutable());
            $category->setUpdatedBy($user);
            $this->entityManager->flush();

            $auditLog = new AuditLog();
            $auditLog->setAction('UPDATE');
            $auditLog->setEntityName('Category');
            $auditLog->setEntityId($category->getId());
            $auditLog->setChanges($changes);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'CATEGORY_UPDATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'changes' => $changes,
                    'category' => $this->serializeCategory($category)
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update category',
                'code' => 'UPDATE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete_category', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(Category $category): JsonResponse
    {
        $user = $this->getUser();

        try {
            $activeSignalements = $this->entityManager->getRepository(\App\Entity\Signalement::class)
                ->findBy(['category' => $category, 'isActive' => true]);
            
            if (count($activeSignalements) > 0) {
                return $this->json([
                    'success' => false,
                    'error' => 'Cannot delete category with active reports',
                    'code' => 'CATEGORY_IN_USE',
                    'details' => ['activeSignalementCount' => count($activeSignalements)],
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $category->setIsActive(false);
            $category->setUpdatedAt(new \DateTimeImmutable());
            $category->setUpdatedBy($user);
            $this->entityManager->flush();

            $auditLog = new AuditLog();
            $auditLog->setAction('DELETE');
            $auditLog->setEntityName('Category');
            $auditLog->setEntityId($category->getId());
            $auditLog->setChanges(['isActive' => false]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'CATEGORY_DELETED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => ['categoryId' => $category->getId()]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to delete category',
                'code' => 'DELETE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'signalementCount' => count($category->getSignalements()->filter(fn($s) => $s->isActive())),
            'isActive' => $category->isActive(),
            'createdAt' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'createdBy' => $category->getCreatedBy()?->getEmail(),
            'updatedBy' => $category->getUpdatedBy()?->getEmail(),
        ];
    }
}
