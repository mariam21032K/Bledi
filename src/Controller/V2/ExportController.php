<?php

namespace App\Controller\V2;
use App\Service\ExportService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/export')]
#[IsGranted('ROLE_USER')]
class ExportV2Controller extends AbstractController
{
    public function __construct(
        private ExportService $exportService,
    ) {
    }

    #[Route('/signalements/csv', name: 'export_signalements_csv_v2', methods: ['GET'])]
    public function exportSignalementsCSV(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $category = $request->query->get('category');
            $status = $request->query->get('status');

            $response = $this->exportService->exportSignalementsToCSV($category, $status);

            $response->headers->set('X-Export-Code', 'SIGNALEMENTS_CSV_EXPORTED');
            $response->headers->set('X-Export-Timestamp', (new DateTime())->format('Y-m-d H:i:s'));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'CSV export failed',
                'code' => 'EXPORT_CSV_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalements/json', name: 'export_signalements_json_v2', methods: ['GET'])]
    public function exportSignalementsJSON(Request $request): JsonResponse
    {
        try {
            $category = $request->query->get('category');
            $status = $request->query->get('status');

            $data = $this->exportService->exportSignalementsToJSON($category, $status);

            return $this->json([
                'success' => true,
                'code' => 'SIGNALEMENTS_JSON_EXPORTED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'exportType' => 'signalements_json',
                    'count' => count($data),
                    'filter' => [
                        'category' => $category,
                        'status' => $status,
                    ],
                    'signalements' => $data,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'JSON export failed',
                'code' => 'EXPORT_JSON_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statistics', name: 'export_statistics_v2', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatistics(): JsonResponse
    {
        try {
            $report = $this->exportService->generateStatisticsReport();

            return $this->json([
                'success' => true,
                'code' => 'STATISTICS_EXPORTED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'exportType' => 'statistics',
                    'generatedAt' => (new DateTime())->format('Y-m-d H:i:s'),
                    'report' => $report,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Statistics export failed',
                'code' => 'EXPORT_STATISTICS_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalements/pdf', name: 'export_signalements_pdf_v2', methods: ['GET'])]
    public function exportSignalementsPDF(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $category = $request->query->get('category');
            $status = $request->query->get('status');

            $response = $this->exportService->exportSignalementsToPDF($category, $status);
            $response->headers->set('X-Export-Code', 'SIGNALEMENTS_PDF_EXPORTED');
            $response->headers->set('X-Export-Timestamp', (new DateTime())->format('Y-m-d H:i:s'));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'PDF export failed',
                'code' => 'EXPORT_PDF_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalements/excel', name: 'export_signalements_excel_v2', methods: ['GET'])]
    public function exportSignalementsExcel(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $category = $request->query->get('category');
            $status = $request->query->get('status');

            $response = $this->exportService->exportSignalementsToExcel($category, $status);
            $response->headers->set('X-Export-Code', 'SIGNALEMENTS_EXCEL_EXPORTED');
            $response->headers->set('X-Export-Timestamp', (new DateTime())->format('Y-m-d H:i:s'));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Excel export failed',
                'code' => 'EXPORT_EXCEL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statistics/pdf', name: 'export_statistics_pdf_v2', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatisticsPDF(): StreamedResponse|JsonResponse
    {
        try {
            $response = $this->exportService->generateStatisticsReportPDF();
            $response->headers->set('X-Export-Code', 'STATISTICS_PDF_EXPORTED');
            $response->headers->set('X-Export-Timestamp', (new DateTime())->format('Y-m-d H:i:s'));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Statistics PDF export failed',
                'code' => 'EXPORT_STATISTICS_PDF_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statistics/excel', name: 'export_statistics_excel_v2', methods: ['GET'])]
    #[IsGranted('ROLE_MUNICIPAL_AGENT')]
    public function exportStatisticsExcel(): StreamedResponse|JsonResponse
    {
        try {
            $response = $this->exportService->generateStatisticsReportExcel();
            $response->headers->set('X-Export-Code', 'STATISTICS_EXCEL_EXPORTED');
            $response->headers->set('X-Export-Timestamp', (new DateTime())->format('Y-m-d H:i:s'));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Statistics Excel export failed',
                'code' => 'EXPORT_STATISTICS_EXCEL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
