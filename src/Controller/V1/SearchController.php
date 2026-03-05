<?php

namespace App\Controller\V1;

use App\Entity\User;
use App\Enum\SignalementStatus;
use App\Enum\PriorityLevel;
use App\Repository\SignalementRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/search')]
#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    public function __construct(
        private SignalementRepository $signalementRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/signalements', name: 'search_signalements', methods: ['GET'])]
    public function searchSignalements(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $category = $request->query->get('category');
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        // Validate inputs
        $filters = [];

        if (!empty($query)) {
            if (strlen($query) < 2) {
                return $this->json([
                    'success' => false,
                    'error' => 'Search query must be at least 2 characters',
                ], 400);
            }
            $filters['query'] = $query;
        }

        if (!empty($category)) {
            $categoryEntity = $this->categoryRepository->findOneBy(['name' => $category, 'isActive' => true]);
            if (!$categoryEntity) {
                return $this->json([
                    'success' => false,
                    'error' => 'Category not found',
                ], 404);
            }
            $filters['category'] = $categoryEntity;
        }

        if (!empty($status)) {
            $statusEnum = null;
            foreach (SignalementStatus::cases() as $s) {
                if ($s->value === $status) {
                    $statusEnum = $s;
                    break;
                }
            }
            if (!$statusEnum) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid status value',
                ], 400);
            }
            $filters['status'] = $statusEnum;
        }

        if (!empty($priority)) {
            $priorityEnum = null;
            foreach (PriorityLevel::cases() as $p) {
                if ($p->value === $priority) {
                    $priorityEnum = $p;
                    break;
                }
            }
            if (!$priorityEnum) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid priority value',
                ], 400);
            }
            $filters['priority'] = $priorityEnum;
        }

        if (!empty($startDate)) {
            try {
                $filters['startDate'] = new \DateTime($startDate);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid start date format',
                ], 400);
            }
        }

        if (!empty($endDate)) {
            try {
                $filters['endDate'] = new \DateTime($endDate);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid end date format',
                ], 400);
            }
        }

        // Build DQL query
        $qb = $this->signalementRepository->createQueryBuilder('s')
            ->where('s.isActive = true');

        if (isset($filters['query'])) {
            $qb->andWhere('s.title LIKE :query OR s.description LIKE :query')
                ->setParameter('query', '%' . $filters['query'] . '%');
        }

        if (isset($filters['category'])) {
            $qb->andWhere('s.category = :category')
                ->setParameter('category', $filters['category']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('s.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        if (isset($filters['startDate'])) {
            $qb->andWhere('s.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $filters['endDate']->setTime(23, 59, 59);
            $qb->andWhere('s.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        $qb->orderBy('s.createdAt', 'DESC');

        // Get total count
        $total = count($qb->getQuery()->getResult());

        // Apply pagination
        $results = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(fn($s) => [
            'id' => $s->getId(),
            'title' => $s->getTitle(),
            'description' => substr($s->getDescription(), 0, 200),
            'category' => $s->getCategory()->getName(),
            'status' => $s->getStatus()->value,
            'priority' => $s->getPriority()->value,
            'location' => $s->getLocation(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $s->getUser()->getId(),
                'name' => $s->getUser()->getFirstName() . ' ' . $s->getUser()->getLastName(),
            ],
        ], $results);

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Route('/filters', name: 'search_filters', methods: ['GET'])]
    public function getAvailableFilters(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $statuses = array_map(fn(SignalementStatus $s) => $s->value, SignalementStatus::cases());
        $priorities = array_map(fn(PriorityLevel $p) => $p->value, PriorityLevel::cases());

        return $this->json([
            'success' => true,
            'data' => [
                'categories' => array_map(fn($c) => $c->getName(), $categories),
                'statuses' => $statuses,
                'priorities' => $priorities,
            ],
        ]);
    }
}
