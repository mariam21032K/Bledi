<?php

namespace App\Controller;

use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/export')]
#[IsGranted('ROLE_USER')]
class ExportController extends AbstractController
{
    public function __construct(
        private ExportService $exportService,
    ) {
    }

    #[Route('/signalements/csv', name: 'export_signalements_csv', methods: ['GET'])]
    public function exportSignalementsCSV(Request $request): StreamedResponse
    {
        $category = $request->query->get('category');
        $status = $request->query->get('status');

        return $this->exportService->exportSignalementsToCSV($category, $status);
    }

    #[Route('/signalements/json', name: 'export_signalements_json', methods: ['GET'])]
    public function exportSignalementsJSON(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $status = $request->query->get('status');

        $data = $this->exportService->exportSignalementsToJSON($category, $status);

        return $this->json([
            'success' => true,
            'count' => count($data),
            'data' => $data,
        ]);
    }

    #[Route('/statistics', name: 'export_statistics', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatistics(): JsonResponse
    {
        $report = $this->exportService->generateStatisticsReport();

        return $this->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
