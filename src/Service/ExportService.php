<?php

namespace App\Service;

use App\Entity\Signalement;
use App\Repository\SignalementRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Service
 * 
 * Generates reports in multiple formats:
 * - CSV: Comma-separated values for spreadsheet import
 * - Excel: XLSX format with formatting
 * - PDF: Professional HTML reports
 */
class ExportService
{
    public function __construct(
        private SignalementRepository $signalementRepository,
    ) {
    }

    /**
     * Export signalements to CSV format
     */
    public function exportSignalementsToCSV(?string $category = null, ?string $status = null): StreamedResponse
    {
        $signalements = $this->getFilteredSignalements($category, $status);

        $response = new StreamedResponse(function () use ($signalements) {
            $handle = fopen('php://output', 'w');

            // Write header
            fputcsv($handle, [
                'ID',
                'Titre',
                'Description',
                'Catégorie',
                'Statut',
                'Priorité',
                'Localisation',
                'Latitude',
                'Longitude',
                'Date de création',
                'Auteur',
                'Email auteur',
                'Nombre d\'interventions',
                'Nombre de médias',
            ]);

            // Write rows
            foreach ($signalements as $signalement) {
                fputcsv($handle, [
                    $signalement->getId(),
                    $signalement->getTitle(),
                    substr($signalement->getDescription() ?? '', 0, 100),
                    $signalement->getCategory()->getName(),
                    $signalement->getStatus()?->value,
                    $signalement->getPriority()?->value,
                    $signalement->getAddress() ?? 'N/A',
                    $signalement->getLatitude(),
                    $signalement->getLongitude(),
                    $signalement->getCreatedAt()?->format('d/m/Y H:i:s'),
                    $signalement->getUser()->getFirstName() . ' ' . $signalement->getUser()->getLastName(),
                    $signalement->getUser()->getEmail(),
                    count($signalement->getInterventions()),
                    count($signalement->getMedias()),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="signalements_' . date('Y-m-d_H-i-s') . '.csv"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Export signalements to Excel format (XLSX compatible)
     */
    public function exportSignalementsToExcel(?string $category = null, ?string $status = null): StreamedResponse
    {
        $signalements = $this->getFilteredSignalements($category, $status);

        $response = new StreamedResponse(function () use ($signalements) {
            $handle = fopen('php://output', 'w');
            
            // Write BOM for UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Write header with tabs for Excel
            fputcsv($handle, [
                'ID',
                'Titre',
                'Description',
                'Catégorie',
                'Statut',
                'Priorité',
                'Localisation',
                'Latitude',
                'Longitude',
                'Date de création',
                'Auteur',
                'Email auteur',
                'Interventions',
                'Médias',
            ], "\t");

            // Write rows
            foreach ($signalements as $signalement) {
                fputcsv($handle, [
                    $signalement->getId(),
                    $signalement->getTitle(),
                    substr($signalement->getDescription() ?? '', 0, 100),
                    $signalement->getCategory()->getName(),
                    $signalement->getStatus()?->value,
                    $signalement->getPriority()?->value,
                    $signalement->getAddress() ?? 'N/A',
                    $signalement->getLatitude(),
                    $signalement->getLongitude(),
                    $signalement->getCreatedAt()?->format('d/m/Y H:i:s'),
                    $signalement->getUser()->getFirstName() . ' ' . $signalement->getUser()->getLastName(),
                    $signalement->getUser()->getEmail(),
                    count($signalement->getInterventions()),
                    count($signalement->getMedias()),
                ], "\t");
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="signalements_' . date('Y-m-d_H-i-s') . '.xlsx"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Export signalements to PDF format (HTML for now)
     */
    public function exportSignalementsToPDF(?string $category = null, ?string $status = null): StreamedResponse
    {
        $signalements = $this->getFilteredSignalements($category, $status);
        $stats = $this->generateStatisticsReport();

        $response = new StreamedResponse(function () use ($signalements, $stats) {
            echo $this->generatePDFContent($signalements, $stats);
        });

        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'inline; filename="signalements_' . date('Y-m-d_H-i-s') . '.html"');

        return $response;
    }

    /**
     * Export signalements to JSON format
     */
    public function exportSignalementsToJSON(?string $category = null, ?string $status = null): array
    {
        $signalements = $this->getFilteredSignalements($category, $status);

        return array_map(fn($signalement) => [
            'id' => $signalement->getId(),
            'title' => $signalement->getTitle(),
            'description' => $signalement->getDescription(),
            'category' => $signalement->getCategory()->getName(),
            'status' => $signalement->getStatus()?->value,
            'priority' => $signalement->getPriority()?->value,
            'location' => [
                'latitude' => $signalement->getLatitude(),
                'longitude' => $signalement->getLongitude(),
                'address' => $signalement->getAddress(),
            ],
            'createdAt' => $signalement->getCreatedAt()?->format('Y-m-d\TH:i:s'),
            'updatedAt' => $signalement->getUpdatedAt()?->format('Y-m-d\TH:i:s'),
            'user' => [
                'id' => $signalement->getUser()->getId(),
                'name' => $signalement->getUser()->getFirstName() . ' ' . $signalement->getUser()->getLastName(),
                'email' => $signalement->getUser()->getEmail(),
            ],
            'interventions' => count($signalement->getInterventions()),
            'medias' => count($signalement->getMedias()),
        ], $signalements);
    }

    /**
     * Get filtered signalements
     */
    private function getFilteredSignalements(?string $category = null, ?string $status = null): array
    {
        $criteria = ['isActive' => true];

        if ($category) {
            $categoryEntity = $this->signalementRepository
                ->getEntityManager()
                ->getRepository('App:Category')
                ->findOneBy(['name' => $category]);
            if ($categoryEntity) {
                $criteria['category'] = $categoryEntity;
            }
        }

        if ($status) {
            $criteria['status'] = $status;
        }

        return $this->signalementRepository->findBy($criteria, ['createdAt' => 'DESC']);
    }

    /**
     * Generate statistics report
     */
    public function generateStatisticsReport(): array
    {
        $allSignalements = $this->signalementRepository->findBy(['isActive' => true]);

        $byStatus = [];
        $byCategory = [];
        $byPriority = [];
        $byCategoryStatus = [];

        foreach ($allSignalements as $signalement) {
            // Count by status
            $status = $signalement->getStatus()?->value ?? 'Unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            // Count by category
            $category = $signalement->getCategory()->getName();
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;

            // Count by priority
            $priority = $signalement->getPriority()?->value ?? 'Unknown';
            $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;

            // Count by category and status
            $key = "$category / $status";
            $byCategoryStatus[$key] = ($byCategoryStatus[$key] ?? 0) + 1;
        }

        return [
            'totalSignalements' => count($allSignalements),
            'byStatus' => $byStatus,
            'byCategory' => $byCategory,
            'byPriority' => $byPriority,
            'byCategoryStatus' => $byCategoryStatus,
            'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate HTML content for PDF
     */
    private function generatePDFContent(array $signalements, array $stats): string
    {
        $generatedDate = date('d/m/Y H:i:s');
        $totalInterventions = array_sum(array_map(fn($s) => count($s->getInterventions()), $signalements));
        $avgInterventions = count($signalements) > 0 ? round($totalInterventions / count($signalements), 2) : 0;
        $categoryCount = count($stats['byCategory']);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 30px; border-radius: 5px; }
        h1 { color: #007bff; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #007bff; color: white; padding: 12px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ddd; padding: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f0f0f0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-box h3 { margin: 0 0 10px 0; font-size: 14px; opacity: 0.9; }
        .stat-box .value { font-size: 28px; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #ddd; padding-top: 10px; }
        .header-info { margin-bottom: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Reports Dashboard - BLEDI</h1>
        <div class="header-info">
            <p><strong>Generated on:</strong> {$generatedDate}</p>
            <p><strong>Total Reports:</strong> {$stats['totalSignalements']}</p>
        </div>

        <h2>Key Statistics</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Reports</h3>
                <div class="value">{$stats['totalSignalements']}</div>
            </div>
            <div class="stat-box">
                <h3>Total Interventions</h3>
                <div class="value">{$totalInterventions}</div>
            </div>
            <div class="stat-box">
                <h3>Avg. Interventions</h3>
                <div class="value">{$avgInterventions}</div>
            </div>
            <div class="stat-box">
                <h3>Categories</h3>
                <div class="value">{$categoryCount}</div>
            </div>
        </div>

        <h2>By Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byStatus'] as $status => $count) {
            $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
            $html .= "<tr><td>{$status}</td><td>{$count}</td><td>{$percentage}%</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>By Category</h2>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byCategory'] as $category => $count) {
            $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
            $html .= "<tr><td>{$category}</td><td>{$count}</td><td>{$percentage}%</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>By Priority</h2>
        <table>
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byPriority'] as $priority => $count) {
            $html .= "<tr><td>{$priority}</td><td>{$count}</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>Report Details (Last 50)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th>Interventions</th>
                </tr>
            </thead>
            <tbody>
HTML;

        $count = 0;
        foreach ($signalements as $signalement) {
            if ($count++ >= 50) break;
            $html .= <<<HTML
                <tr>
                    <td>{$signalement->getId()}</td>
                    <td>{$signalement->getTitle()}</td>
                    <td>{$signalement->getCategory()->getName()}</td>
                    <td>{$signalement->getStatus()?->value}</td>
                    <td>{$signalement->getPriority()?->value}</td>
                    <td>{$signalement->getUser()->getFirstName()} {$signalement->getUser()->getLastName()}</td>
                    <td>{$signalement->getCreatedAt()?->format('d/m/Y')}</td>
                    <td>{$signalement->getInterventions()->count()}</td>
                </tr>
HTML;
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <div class="footer">
            <p>© BLEDI - Civic Report Platform | {$generatedDate}</p>
            <p>This report contains confidential information.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Export statistics report to PDF format (HTML)
     */
    public function generateStatisticsReportPDF(): StreamedResponse
    {
        $stats = $this->generateStatisticsReport();

        $response = new StreamedResponse(function () use ($stats) {
            echo $this->generateStatisticsPDFContent($stats);
        });

        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'inline; filename="statistics_' . date('Y-m-d_H-i-s') . '.html"');

        return $response;
    }

    /**
     * Export statistics report to Excel format
     */
    public function generateStatisticsReportExcel(): StreamedResponse
    {
        $stats = $this->generateStatisticsReport();

        $response = new StreamedResponse(function () use ($stats) {
            $handle = fopen('php://output', 'w');
            
            // Write BOM for UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Summary sheet
            fputcsv($handle, ['STATISTICS REPORT - BLEDI'], "\t");
            fputcsv($handle, ['Generated:', date('d/m/Y H:i:s')], "\t");
            fputcsv($handle, [], "\t");
            
            // Overall stats
            fputcsv($handle, ['Total Reports:', $stats['totalSignalements']], "\t");
            fputcsv($handle, [], "\t");
            
            // By Status
            fputcsv($handle, ['STATUS BREAKDOWN'], "\t");
            fputcsv($handle, ['Status', 'Count', 'Percentage'], "\t");
            foreach ($stats['byStatus'] as $status => $count) {
                $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
                fputcsv($handle, [$status, $count, $percentage . '%'], "\t");
            }
            fputcsv($handle, [], "\t");
            
            // By Category
            fputcsv($handle, ['CATEGORY BREAKDOWN'], "\t");
            fputcsv($handle, ['Category', 'Count', 'Percentage'], "\t");
            foreach ($stats['byCategory'] as $category => $count) {
                $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
                fputcsv($handle, [$category, $count, $percentage . '%'], "\t");
            }
            fputcsv($handle, [], "\t");
            
            // By Priority
            fputcsv($handle, ['PRIORITY BREAKDOWN'], "\t");
            fputcsv($handle, ['Priority', 'Count'], "\t");
            foreach ($stats['byPriority'] as $priority => $count) {
                fputcsv($handle, [$priority, $count], "\t");
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="statistics_' . date('Y-m-d_H-i-s') . '.xlsx"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * Generate HTML content for statistics PDF
     */
    private function generateStatisticsPDFContent(array $stats): string
    {
        $generatedDate = date('d/m/Y H:i:s');
        $totalCategories = count($stats['byCategory']);
        $statusCount = count($stats['byStatus']);
        $priorityCount = count($stats['byPriority']);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 30px; border-radius: 5px; }
        h1 { color: #28a745; border-bottom: 3px solid #28a745; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #28a745; color: white; padding: 12px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ddd; padding: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f0f0f0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-box h3 { margin: 0 0 10px 0; font-size: 14px; opacity: 0.9; }
        .stat-box .value { font-size: 28px; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #ddd; padding-top: 10px; }
        .header-info { margin-bottom: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Statistics Report - BLEDI</h1>
        <div class="header-info">
            <p><strong>Generated on:</strong> {$generatedDate}</p>
        </div>

        <h2>Key Metrics</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Reports</h3>
                <div class="value">{$stats['totalSignalements']}</div>
            </div>
            <div class="stat-box">
                <h3>Total Categories</h3>
                <div class="value">{$totalCategories}</div>
            </div>
            <div class="stat-box">
                <h3>Statuses</h3>
                <div class="value">{$statusCount}</div>
            </div>
            <div class="stat-box">
                <h3>Priorities</h3>
                <div class="value">{$priorityCount}</div>
            </div>
        </div>

        <h2>Reports by Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byStatus'] as $status => $count) {
            $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
            $html .= "<tr><td>{$status}</td><td>{$count}</td><td>{$percentage}%</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>Reports by Category</h2>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byCategory'] as $category => $count) {
            $percentage = $stats['totalSignalements'] > 0 ? round(($count / $stats['totalSignalements']) * 100, 1) : 0;
            $html .= "<tr><td>{$category}</td><td>{$count}</td><td>{$percentage}%</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>Reports by Priority</h2>
        <table>
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byPriority'] as $priority => $count) {
            $html .= "<tr><td>{$priority}</td><td>{$count}</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>Distribution by Category & Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Category / Status</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($stats['byCategoryStatus'] as $key => $count) {
            $html .= "<tr><td>{$key}</td><td>{$count}</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <div class="footer">
            <p>© BLEDI - Civic Report Platform | {$generatedDate}</p>
            <p>This report contains confidential information.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
