<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatisticsExportService;
use App\Services\AdminStatisticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatisticsController extends Controller
{
    public function index(AdminStatisticsService $stats): View
    {
        return view('admin.statistics.index', [
            'stats' => $stats->build(),
        ]);
    }

    public function exportCsv(AdminStatisticsExportService $export): StreamedResponse
    {
        return $export->csvResponse();
    }

    public function exportPdf(AdminStatisticsExportService $export): Response
    {
        $data = $export->pdfData();
        $filename = 'thong-ke-'.now()->format('Y-m-d-His').'.pdf';

        return Pdf::loadView('admin.exports.statistics-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
