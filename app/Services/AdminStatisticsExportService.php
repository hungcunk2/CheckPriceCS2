<?php

namespace App\Services;

use App\Support\CsvExportWriter;
use App\Support\SubscriptionPlans;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStatisticsExportService
{
    public function __construct(
        private AdminStatisticsService $statistics,
    ) {}

    public function csvResponse(): StreamedResponse
    {
        $stats = $this->statistics->build();
        $filename = 'thong-ke-'.now()->format('Y-m-d-His').'.csv';

        return CsvExportWriter::download($filename, function ($handle) use ($stats) {
            $this->writeCsv($handle, $stats);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfData(): array
    {
        return [
            'stats' => $this->statistics->build(),
            'generated_at' => now()->timezone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y H:i'),
            'site_name' => config('site.name', 'CheckPrice CS2'),
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $stats
     */
    private function writeCsv($handle, array $stats): void
    {
        $o = $stats['overview'] ?? [];
        $sub = $stats['subscription'] ?? [];
        $sync = $stats['sync_quality'] ?? [];
        $aum = $stats['aum'] ?? [];
        $support = $stats['support'] ?? [];
        $api = $stats['api_ops'] ?? [];

        CsvExportWriter::row($handle, ['Báo cáo', 'Dashboard thống kê', now()->format('d/m/Y H:i')]);

        CsvExportWriter::section($handle, 'TỔNG QUAN');
        CsvExportWriter::row($handle, ['Chỉ số', 'Giá trị']);
        CsvExportWriter::row($handle, ['User trả phí (active)', $o['paid_active_users'] ?? 0]);
        CsvExportWriter::row($handle, ['Hết hạn ≤7 ngày', $o['expiring_7_days'] ?? 0]);
        CsvExportWriter::row($handle, ['Hết hạn ≤30 ngày', $o['expiring_30_days'] ?? 0]);
        CsvExportWriter::row($handle, ['Doanh thu tháng (VND)', $o['revenue_month_vnd'] ?? 0]);
        CsvExportWriter::row($handle, ['Đơn chờ duyệt', $o['pending_orders'] ?? 0]);
        CsvExportWriter::row($handle, ['Tổng giá trị kho (VND)', $o['total_inventory_vnd'] ?? 0]);
        CsvExportWriter::row($handle, ['Kho admin / member', ($o['admin_inventories'] ?? 0).' / '.($o['member_inventories'] ?? 0)]);
        CsvExportWriter::row($handle, ['Kho sync trễ', $o['overdue_sync'] ?? 0]);
        CsvExportWriter::row($handle, ['Tỷ lệ có giá Buff (%)', $o['pricing_coverage_pct'] ?? '']);
        CsvExportWriter::row($handle, ['MRR ước tính (VND)', $sub['mrr_estimate_vnd'] ?? 0]);

        CsvExportWriter::section($handle, 'USER ACTIVE THEO GÓI');
        CsvExportWriter::row($handle, ['Gói', 'Số user']);
        foreach ($sub['active_by_plan'] ?? [] as $plan => $count) {
            $name = SubscriptionPlans::get($plan)['name'] ?? strtoupper($plan);
            CsvExportWriter::row($handle, [$name, $count]);
        }

        CsvExportWriter::section($handle, 'DOANH THU 6 THÁNG');
        CsvExportWriter::row($handle, ['Tháng', 'VND']);
        foreach ($sub['revenue_by_month'] ?? [] as $row) {
            CsvExportWriter::row($handle, [$row['label'] ?? '', $row['amount_vnd'] ?? 0]);
        }

        CsvExportWriter::section($handle, 'ĐƠN PENDING');
        CsvExportWriter::row($handle, ['>24h', $sub['pending_over_24h'] ?? 0]);
        CsvExportWriter::row($handle, ['>48h', $sub['pending_over_48h'] ?? 0]);
        CsvExportWriter::row($handle, ['Thời gian', 'Email', 'Gói', 'Tháng', 'VND']);
        foreach ($sub['pending_orders'] ?? [] as $order) {
            CsvExportWriter::row($handle, [
                $order->created_at?->format('d/m/Y H:i'),
                $order->user?->email,
                $order->plan,
                $order->months,
                $order->amount_vnd,
            ]);
        }

        CsvExportWriter::section($handle, 'USER SẮP HẾT HẠN (7 NGÀY)');
        CsvExportWriter::row($handle, ['Tên', 'Email', 'Gói', 'Hết hạn']);
        foreach ($sub['expiring_this_week'] ?? [] as $user) {
            CsvExportWriter::row($handle, [
                $user->name,
                $user->email,
                $user->subscription_plan,
                $user->paid_until?->format('d/m/Y'),
            ]);
        }

        CsvExportWriter::section($handle, 'SYNC & GIÁ');
        CsvExportWriter::row($handle, ['Kho stale >24h', $sync['stale_over_24h'] ?? 0]);
        CsvExportWriter::row($handle, ['Sync trễ', $sync['overdue_sync'] ?? 0]);
        CsvExportWriter::row($handle, ['Skin có giá Empire', ($sync['empire_priced_skins'] ?? 0).' ('.($sync['empire_coverage_pct'] ?? 0).'%)']);
        CsvExportWriter::row($handle, ['Buff wins / Empire wins', ($sync['buff_sell_wins'] ?? 0).' / '.($sync['empire_sell_wins'] ?? 0)]);
        CsvExportWriter::row($handle, ['Kho', 'Có giá', 'Failed', 'Coverage %', 'Sync cuối']);
        foreach ($sync['worst_inventories'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['label'],
                ($row['priced_count'] ?? 0).'/'.($row['item_count'] ?? 0),
                $row['failed_count'] ?? 0,
                $row['coverage_pct'] ?? '',
                $row['last_checked_at'] ?? '',
            ]);
        }

        CsvExportWriter::section($handle, 'TOP 10 KHO (AUM)');
        CsvExportWriter::row($handle, ['Kho', 'Loại', 'VND', 'CNY', 'Skin']);
        foreach ($aum['top_inventories'] ?? [] as $row) {
            CsvExportWriter::row($handle, [
                $row['label'],
                $row['owner'],
                $row['total_vnd'],
                $row['total_cny'],
                $row['item_count'],
            ]);
        }

        CsvExportWriter::section($handle, 'PHÂN LOẠI VŨ KHÍ');
        CsvExportWriter::row($handle, ['Loại', 'Số lượng']);
        foreach ($aum['weapon_stats'] ?? [] as $row) {
            CsvExportWriter::row($handle, [$row['label'], $row['count']]);
        }

        if (! empty($support['available'])) {
            CsvExportWriter::section($handle, 'HỖ TRỢ');
            CsvExportWriter::row($handle, ['Chưa đọc', $support['unread_conversations'] ?? 0]);
            CsvExportWriter::row($handle, ['TG phản hồi TB (phút)', $support['avg_response_minutes'] ?? '']);
            CsvExportWriter::row($handle, ['User có ticket 14 ngày', $support['active_users_with_ticket'] ?? 0]);
        }

        CsvExportWriter::section($handle, 'CS2CAP / API');
        CsvExportWriter::row($handle, ['Acc Buff', $api['buff_accounts'] ?? 0]);
        CsvExportWriter::row($handle, ['CS2Cap active/total', ($api['cs2cap_active'] ?? 0).'/'.($api['cs2cap_total'] ?? 0)]);
        CsvExportWriter::row($handle, ['CS2Cap hết quota', $api['cs2cap_exhausted'] ?? 0]);
        CsvExportWriter::row($handle, ['Label', 'Active', 'Tier', 'Quota', 'Trạng thái']);
        foreach ($api['cs2cap_keys'] ?? [] as $key) {
            $quota = ($key['quota_remaining'] !== null && $key['quota_limit'] !== null)
                ? $key['quota_remaining'].'/'.$key['quota_limit']
                : '';
            $status = ! empty($key['exhausted']) ? 'Hết quota' : (! empty($key['active']) ? 'OK' : 'Tắt');
            CsvExportWriter::row($handle, [$key['label'], $key['active'] ? 'Yes' : 'No', $key['tier'] ?? '', $quota, $status]);
        }
    }
}
