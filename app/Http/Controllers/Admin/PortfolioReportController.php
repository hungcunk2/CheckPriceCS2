<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryPortfolioReportService;
use App\Services\PortfolioReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioReportController extends Controller
{
    public function index(Request $request, InventoryPortfolioReportService $report): View
    {
        $days = $this->resolveDays($request);

        return view('admin.portfolio-report.index', [
            'days' => $days,
            'report' => $report->build($days),
        ]);
    }

    public function exportCsv(Request $request, PortfolioReportExportService $export): StreamedResponse
    {
        return $export->csvResponse($this->resolveDays($request));
    }

    public function exportPdf(Request $request, PortfolioReportExportService $export): Response
    {
        $days = $this->resolveDays($request);
        $data = $export->pdfData($days);
        $filename = 'thong-ke-kho-'.$days.'ngay-'.now()->format('Y-m-d-His').'.pdf';

        return Pdf::loadView('admin.exports.portfolio-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function resolveDays(Request $request): int
    {
        $days = (int) $request->query('days', 7);

        return in_array($days, [1, 7, 30], true) ? $days : 7;
    }
}
