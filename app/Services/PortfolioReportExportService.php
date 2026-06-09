<?php

namespace App\Services;

use App\Support\CsvExportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioReportExportService
{
    public function __construct(
        private InventoryPortfolioReportService $report,
    ) {}

    public function csvResponse(int $days, ?int $userId = null, ?string $adminUsername = null): StreamedResponse
    {
        $days = in_array($days, [1, 7, 30], true) ? $days : 7;
        $data = $this->report->build($days, $userId, $adminUsername);
        $filename = 'thong-ke-kho-'.$days.'ngay-'.now()->format('Y-m-d-His').'.csv';

        return CsvExportWriter::download($filename, function ($handle) use ($data, $days) {
            $this->writeCsv($handle, $data, $days);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfData(int $days, ?int $userId = null, ?string $adminUsername = null): array
    {
        $days = in_array($days, [1, 7, 30], true) ? $days : 7;

        return [
            'days' => $days,
            'report' => $this->report->build($days, $userId, $adminUsername),
            'generated_at' => now()->timezone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y H:i'),
            'site_name' => config('site.name', 'CheckPrice CS2'),
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $data
     */
    private function writeCsv($handle, array $data, int $days): void
    {
        $summary = $data['summary'] ?? [];
        $current = $summary['current'] ?? [];
        $delta = $summary['delta'] ?? [];
        $deltaPct = $summary['delta_pct'] ?? [];

        CsvExportWriter::row($handle, ['Báo cáo', 'Thống kê kho', $days.' ngày', now()->format('d/m/Y H:i')]);

        CsvExportWriter::section($handle, 'TỔNG QUAN');
        CsvExportWriter::row($handle, ['Chỉ số', 'Hiện tại', 'Biến động', '%']);
        CsvExportWriter::row($handle, ['Buff CNY', $current['total_cny'] ?? '', $delta['total_cny'] ?? '', $deltaPct['total_cny'] ?? '']);
        CsvExportWriter::row($handle, ['Buff VND', $current['total_vnd'] ?? '', $delta['total_vnd'] ?? '', '']);
        CsvExportWriter::row($handle, ['Empire CNY', $current['total_empire_cny'] ?? '', $delta['total_empire_cny'] ?? '', $deltaPct['total_empire_cny'] ?? '']);
        CsvExportWriter::row($handle, ['Số skin', $current['item_count'] ?? '', $delta['item_count'] ?? '', '']);

        CsvExportWriter::section($handle, 'BIẾN ĐỘNG THEO NGÀY');
        CsvExportWriter::row($handle, ['Ngày', 'Buff CNY', 'Empire CNY', 'Buff VND']);
        foreach ($data['trend'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['date'] ?? '',
                $row['total_cny'] ?? '',
                $row['total_empire_cny'] ?? '',
                $row['total_vnd'] ?? '',
            ]);
        }

        CsvExportWriter::section($handle, 'SKIN TĂNG GIÁ');
        CsvExportWriter::row($handle, ['Skin', 'SL', 'Giá hiện tại ¥', 'Biến động ¥', '%', 'Kho']);
        foreach ($data['gainers'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['display_name'] ?? $row['market_hash_name'],
                $row['amount'] ?? 1,
                $row['current_cny'] ?? '',
                $row['delta_cny'] ?? '',
                $row['delta_pct'] ?? '',
                $row['inventory_label'] ?? '',
            ]);
        }

        CsvExportWriter::section($handle, 'SKIN GIẢM GIÁ');
        CsvExportWriter::row($handle, ['Skin', 'SL', 'Giá hiện tại ¥', 'Biến động ¥', '%', 'Kho']);
        foreach ($data['losers'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['display_name'] ?? $row['market_hash_name'],
                $row['amount'] ?? 1,
                $row['current_cny'] ?? '',
                $row['delta_cny'] ?? '',
                $row['delta_pct'] ?? '',
                $row['inventory_label'] ?? '',
            ]);
        }

        CsvExportWriter::section($handle, 'SKIN MỚI THÊM');
        CsvExportWriter::row($handle, ['Skin', 'SL', 'Giá Buff ¥', 'Line CNY', 'Empire CNY', 'Kho']);
        foreach ($data['added'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['display_name'] ?? $row['market_hash_name'],
                $row['amount'] ?? 1,
                $row['buff_price_cny'] ?? '',
                $row['line_total_cny'] ?? '',
                $row['line_total_empire_cny'] ?? '',
                $row['inventory_label'] ?? '',
            ]);
        }

        CsvExportWriter::section($handle, 'SKIN MẤT');
        CsvExportWriter::row($handle, ['Skin', 'SL', 'Giá Buff ¥', 'Line CNY', 'Empire CNY', 'Kho']);
        foreach ($data['removed'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['display_name'] ?? $row['market_hash_name'],
                $row['amount'] ?? 1,
                $row['buff_price_cny'] ?? '',
                $row['line_total_cny'] ?? '',
                $row['line_total_empire_cny'] ?? '',
                $row['inventory_label'] ?? '',
            ]);
        }
    }
}
