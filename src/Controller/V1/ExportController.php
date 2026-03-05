<?php

namespace App\Controller\V1;

use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/export')]
#[IsGranted('ROLE_USER')]
class ExportController extends AbstractController
{
    public function __construct(
        private ExportService $exportService,
    ) {
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

    #[Route('/signalements/pdf', name: 'export_signalements_pdf', methods: ['GET'])]
    public function exportSignalementsPDF(Request $request): StreamedResponse
    {
        $category = $request->query->get('category');
        $status = $request->query->get('status');

        return $this->exportService->exportSignalementsToPDF($category, $status);
    }

    #[Route('/signalements/excel', name: 'export_signalements_excel', methods: ['GET'])]
    public function exportSignalementsExcel(Request $request): StreamedResponse
    {
        $category = $request->query->get('category');
        $status = $request->query->get('status');

        return $this->exportService->exportSignalementsToExcel($category, $status);
    }

    #[Route('/statistics/pdf', name: 'export_statistics_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatisticsPDF(): StreamedResponse
    {
        return $this->exportService->generateStatisticsReportPDF();
    }

    #[Route('/statistics/excel', name: 'export_statistics_excel', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatisticsExcel(): StreamedResponse
    {
        return $this->exportService->generateStatisticsReportExcel();
    }
}
