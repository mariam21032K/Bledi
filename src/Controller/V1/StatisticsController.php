<?php
namespace App\Controller\V1;

use App\Entity\User;
use App\Enum\SignalementStatus;
use App\Repository\SignalementRepository;
use App\Repository\CategoryRepository;
use App\Repository\InterventionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/statistics')]
#[IsGranted('ROLE_USER')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private SignalementRepository $signalementRepository,
        private CategoryRepository $categoryRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_stats', methods: ['GET'])]
    public function getDashboardStats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get user's signalements if citizen
        $userSignalementCount = 0;
        if ($user->getUserRole()->value === 'CITIZEN') {
            $userSignalementCount = $this->signalementRepository->count(['user' => $user, 'isActive' => true]);
        }

        // Total signalements
        $totalSignalements = $this->signalementRepository->count(['isActive' => true]);

        // Signalements by status
        $statuses = array_map(fn(SignalementStatus $s) => $s->value, SignalementStatus::cases());
        $byStatus = [];
        foreach ($statuses as $status) {
            $byStatus[$status] = $this->signalementRepository->count(['status' => $status, 'isActive' => true]);
        }

        // Signalements by category
        $categories = $this->categoryRepository->findBy(['isActive' => true]);
        $byCategory = [];
        foreach ($categories as $category) {
            $byCategory[$category->getName()] = $this->signalementRepository->count(['category' => $category, 'isActive' => true]);
        }

        // Recent signalements
        $recentSignalements = $this->signalementRepository->findBy(['isActive' => true], ['createdAt' => 'DESC'], 10);
        $recentData = array_map(fn($s) => [
            'id' => $s->getId(),
            'title' => $s->getTitle(),
            'status' => $s->getStatus()->value,
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d'),
        ], $recentSignalements);

        // Get active interventions count
        $activeInterventions = $this->interventionRepository->count(['isActive' => true]);

        return $this->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'totalSignalements' => $totalSignalements,
                    'userSignalements' => $userSignalementCount,
                    'activeInterventions' => $activeInterventions,
                    'totalCategories' => count($byCategory),
                ],
                'byStatus' => $byStatus,
                'byCategory' => $byCategory,
                'recent' => $recentData,
            ],
        ]);
    }

    #[Route('/signalements/timeline', name: 'signalements_timeline', methods: ['GET'])]
    public function getSignalementsTimeline(Request $request): JsonResponse
    {
        $days = min(90, max(7, (int) $request->query->get('days', 30)));
        $startDate = new \DateTime("-$days days");

        $signalements = $this->signalementRepository->findBy(['isActive' => true]);

        // Group by date
        $timeline = [];
        foreach ($signalements as $signalement) {
            if ($signalement->getCreatedAt() >= $startDate) {
                $date = $signalement->getCreatedAt()->format('Y-m-d');
                $timeline[$date] = ($timeline[$date] ?? 0) + 1;
            }
        }

        ksort($timeline);

        return $this->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    #[Route('/interventions/stats', name: 'interventions_stats', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function getInterventionsStats(): JsonResponse
    {
        $totalInterventions = $this->interventionRepository->count(['isActive' => true]);
        $completedInterventions = $this->interventionRepository->count(['isActive' => true]);

        // Count by agent (simplified)
        $interventions = $this->interventionRepository->findBy(['isActive' => true]);

        return $this->json([
            'success' => true,
            'data' => [
                'totalInterventions' => $totalInterventions,
                'completedCount' => count(array_filter($interventions, fn($i) => $i->getEndDate() !== null)),
                'pendingCount' => count(array_filter($interventions, fn($i) => $i->getEndDate() === null)),
            ],
        ]);
    }

    #[Route('/top-signalements', name: 'top_signalements', methods: ['GET'])]
    public function getTopSignalements(Request $request): JsonResponse
    {
        $sortBy = $request->query->get('sortBy', 'recent'); // recent, commented, rated
        $limit = min(20, max(5, (int) $request->query->get('limit', 10)));

        $signalements = $this->signalementRepository->findBy(['isActive' => true], ['createdAt' => 'DESC'], $limit);

        $data = array_map(fn($s) => [
            'id' => $s->getId(),
            'title' => $s->getTitle(),
            'status' => $s->getStatus()->value,
            'priority' => $s->getPriority()->value,
            'category' => $s->getCategory()->getName(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $signalements);

        // Sort based on parameter
        if ($sortBy === 'commented') {
            usort($data, fn($a, $b) => $b['commentCount'] <=> $a['commentCount']);
        }

        return $this->json([
            'success' => true,
            'data' => array_slice($data, 0, $limit),
        ]);
    }
}
