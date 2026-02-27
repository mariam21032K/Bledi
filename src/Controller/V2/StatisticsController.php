<?php


namespace App\Controller\V2;

use App\Entity\User;
use App\Enum\SignalementStatus;
use App\Repository\SignalementRepository;
use App\Repository\CategoryRepository;
use App\Repository\InterventionRepository;
use App\Repository\UserRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/statistics')]
#[IsGranted('ROLE_USER')]
class StatisticsV2Controller extends AbstractController
{
    public function __construct(
        private SignalementRepository $signalementRepository,
        private CategoryRepository $categoryRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_stats_v2', methods: ['GET'])]
    public function getDashboardStats(): JsonResponse
    {
        try {
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
                'priority' => $s->getPriority()->value,
                'category' => $s->getCategory()->getName(),
                'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $recentSignalements);

            // Get active interventions count
            $activeInterventions = $this->interventionRepository->count(['isActive' => true]);

            // Calculate resolution rate
            $resolvedCount = $this->signalementRepository->count(['status' => SignalementStatus::RESOLVED, 'isActive' => true]);
            $resolutionRate = $totalSignalements > 0 ? round(($resolvedCount / $totalSignalements) * 100, 2) : 0;

            return $this->json([
                'success' => true,
                'code' => 'DASHBOARD_STATS_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'summary' => [
                        'totalSignalements' => $totalSignalements,
                        'userSignalements' => $userSignalementCount,
                        'activeInterventions' => $activeInterventions,
                        'totalCategories' => count($byCategory),
                        'resolutionRate' => $resolutionRate,
                    ],
                    'byStatus' => $byStatus,
                    'byCategory' => $byCategory,
                    'recent' => [
                        'signalements' => $recentData,
                        'count' => count($recentData),
                    ],
                    'generatedAt' => (new DateTime())->format('Y-m-d H:i:s'),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve dashboard statistics',
                'code' => 'DASHBOARD_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalements/timeline', name: 'signalements_timeline_v2', methods: ['GET'])]
    public function getSignalementsTimeline(Request $request): JsonResponse
    {
        try {
            $days = min(90, max(7, (int) $request->query->get('days', 30)));
            $startDate = new \DateTime("-$days days");

            $signalements = $this->signalementRepository->findBy(['isActive' => true]);

            // Group by date and status
            $timeline = [];
            $timelineByStatus = [];

            foreach ($signalements as $signalement) {
                if ($signalement->getCreatedAt() >= $startDate) {
                    $date = $signalement->getCreatedAt()->format('Y-m-d');
                    $status = $signalement->getStatus()->value;

                    // Overall timeline
                    $timeline[$date] = ($timeline[$date] ?? 0) + 1;

                    // By status
                    if (!isset($timelineByStatus[$status])) {
                        $timelineByStatus[$status] = [];
                    }
                    $timelineByStatus[$status][$date] = ($timelineByStatus[$status][$date] ?? 0) + 1;
                }
            }

            ksort($timeline);
            foreach ($timelineByStatus as &$statusTimeline) {
                ksort($statusTimeline);
            }

            return $this->json([
                'success' => true,
                'code' => 'TIMELINE_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'overall' => $timeline,
                    'byStatus' => $timelineByStatus,
                    'period' => [
                        'startDate' => $startDate->format('Y-m-d'),
                        'endDate' => (new DateTime())->format('Y-m-d'),
                        'days' => $days,
                    ]
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve timeline statistics',
                'code' => 'TIMELINE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/interventions/stats', name: 'interventions_stats_v2', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function getInterventionsStats(): JsonResponse
    {
        try {
            $totalInterventions = $this->interventionRepository->count(['isActive' => true]);
            $interventions = $this->interventionRepository->findBy(['isActive' => true]);

            $completedCount = count(array_filter($interventions, fn($i) => $i->getEndDate() !== null));
            $pendingCount = count(array_filter($interventions, fn($i) => $i->getEndDate() === null));

            // Calculate average intervention duration
            $totalDuration = 0;
            $completedInterventions = 0;

            foreach ($interventions as $intervention) {
                if ($intervention->getEndDate() !== null) {
                    $diff = $intervention->getEndDate()->diff($intervention->getStartDate());
                    $totalDuration += $diff->h + ($diff->days * 24);
                    $completedInterventions++;
                }
            }

            $averageDuration = $completedInterventions > 0 ? round($totalDuration / $completedInterventions, 2) : 0;
            $completionRate = $totalInterventions > 0 ? round(($completedCount / $totalInterventions) * 100, 2) : 0;

            return $this->json([
                'success' => true,
                'code' => 'INTERVENTIONS_STATS_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'summary' => [
                        'totalInterventions' => $totalInterventions,
                        'completedCount' => $completedCount,
                        'pendingCount' => $pendingCount,
                        'completionRate' => $completionRate,
                        'averageDurationHours' => $averageDuration,
                    ]
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve interventions statistics',
                'code' => 'INTERVENTIONS_STATS_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/top-signalements', name: 'top_signalements_v2', methods: ['GET'])]
    public function getTopSignalements(Request $request): JsonResponse
    {
        try {
            $sortBy = $request->query->get('sortBy', 'recent'); // recent, urgent, priority
            $limit = min(20, max(5, (int) $request->query->get('limit', 10)));

            if ($sortBy === 'urgent') {
                $signalements = $this->signalementRepository->findBy(
                    ['isActive' => true],
                    ['priority' => 'DESC', 'createdAt' => 'DESC'],
                    $limit
                );
            } elseif ($sortBy === 'priority') {
                $signalements = $this->signalementRepository->findBy(
                    ['isActive' => true],
                    ['priority' => 'DESC', 'status' => 'ASC', 'createdAt' => 'DESC'],
                    $limit
                );
            } else {
                // Default: recent
                $signalements = $this->signalementRepository->findBy(
                    ['isActive' => true],
                    ['createdAt' => 'DESC'],
                    $limit
                );
            }

            $data = array_map(fn($s) => [
                'id' => $s->getId(),
                'title' => $s->getTitle(),
                'status' => $s->getStatus()->value,
                'priority' => $s->getPriority()->value,
                'category' => $s->getCategory()->getName(),
                'interventionCount' => count($s->getInterventions()),
                'mediaCount' => count($s->getMedias()),
                'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $signalements);

            return $this->json([
                'success' => true,
                'code' => 'TOP_SIGNALEMENTS_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'signalements' => $data,
                    'total' => count($data),
                    'sortedBy' => $sortBy,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve top signalements',
                'code' => 'TOP_SIGNALEMENTS_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
