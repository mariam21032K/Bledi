<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\AuditLog;
use App\Repository\CategoryRepository;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/categories')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
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
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $data = array_map(fn(Category $c) => $this->serializeCategory($c), $categories);

        return $this->json(['success' => true, 'data' => $data, 'total' => count($data)]);
    }

    #[Route('/{id}', name: 'get_category', methods: ['GET'])]
    public function getCategory(Category $category): JsonResponse
    {
        if (!$category->isActive()) {
            return $this->json(['success' => false, 'error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true, 'data' => $this->serializeCategory($category)]);
    }

    #[Route('', name: 'create_category', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createCategory(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $constraint = new Assert\Collection([
            'name' => [
                new Assert\NotBlank(message: 'Name is required'),
                new Assert\Length(min: 3, max: 100),
            ],
            'description' => [
                new Assert\NotBlank(message: 'Description is required'),
                new Assert\Length(min: 5, max: 500),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraint);
        if (count($violations) > 0) {
            return $this->json(['success' => false, 'errors' => $this->validationService->formatErrors($violations)], Response::HTTP_BAD_REQUEST);
        }

        if ($this->categoryRepository->findOneBy(['name' => $data['name']])) {
            return $this->json(['success' => false, 'error' => 'Category already exists'], Response::HTTP_BAD_REQUEST);
        }

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

        return $this->json(['success' => true, 'message' => 'Category created', 'data' => $this->serializeCategory($category)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update_category', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateCategory(Category $category, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];
        $changes = [];

        if (isset($data['name'])) {
            if (strlen($data['name']) < 3 || strlen($data['name']) > 100) {
                return $this->json(['success' => false, 'error' => 'Invalid name length'], Response::HTTP_BAD_REQUEST);
            }
            $existing = $this->categoryRepository->findOneBy(['name' => $data['name']]);
            if ($existing && $existing->getId() !== $category->getId()) {
                return $this->json(['success' => false, 'error' => 'Name exists'], Response::HTTP_BAD_REQUEST);
            }
            $changes['name'] = ['from' => $category->getName(), 'to' => $data['name']];
            $category->setName($data['name']);
        }

        if (isset($data['description'])) {
            if (strlen($data['description']) < 5 || strlen($data['description']) > 500) {
                return $this->json(['success' => false, 'error' => 'Invalid description length'], Response::HTTP_BAD_REQUEST);
            }
            $changes['description'] = ['from' => $category->getDescription(), 'to' => $data['description']];
            $category->setDescription($data['description']);
        }

        if (empty($changes)) {
            return $this->json(['success' => false, 'error' => 'No changes'], Response::HTTP_BAD_REQUEST);
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

        return $this->json(['success' => true, 'message' => 'Updated', 'data' => $this->serializeCategory($category)]);
    }

    #[Route('/{id}', name: 'delete_category', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(Category $category): JsonResponse
    {
        $user = $this->getUser();

        $activeSignalements = $this->entityManager->getRepository(\App\Entity\Signalement::class)->findBy(['category' => $category, 'isActive' => true]);
        if (count($activeSignalements) > 0) {
            return $this->json(['success' => false, 'error' => 'Cannot delete with active signalements'], Response::HTTP_BAD_REQUEST);
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

        return $this->json(['success' => true, 'message' => 'Deleted']);
    }

    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'signalementCount' => count($category->getSignalements()->filter(fn($s) => $s->isActive())),
            'createdAt' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
