<section id="support" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">Hỗ trợ</h2>
    @if(empty($support['available']))
        <div class="alert alert-secondary py-2 small">Module chat hỗ trợ chưa được cài (migration support).</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="panel-admin rounded border p-3">
                    <div class="text-muted small">Hội thoại chưa đọc</div>
                    <div class="fs-4 fw-semibold {{ ($support['unread_conversations'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $support['unread_conversations'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel-admin rounded border p-3">
                    <div class="text-muted small">TG phản hồi TB (30 ngày)</div>
                    <div class="fs-4 fw-semibold">
                        @if($support['avg_response_minutes'] !== null)
                            {{ number_format($support['avg_response_minutes'], 0, ',', '.') }} phút
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel-admin rounded border p-3">
                    <div class="text-muted small">User active có ticket (14 ngày)</div>
                    <div class="fs-4 fw-semibold">{{ $support['active_users_with_ticket'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="panel-admin rounded border">
            <div class="p-3 border-bottom fw-semibold small">Hội thoại cần phản hồi</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>User</th><th>Tin cuối</th><th></th></tr></thead>
                    <tbody>
                    @forelse($support['recent_unread'] ?? [] as $conv)
                        <tr>
                            <td>
                                <div>{{ $conv->user?->name ?? '—' }}</div>
                                <div class="small text-muted">{{ $conv->user?->email }}</div>
                            </td>
                            <td class="small text-muted">{{ $conv->last_message_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.support.show', $conv->user_id) }}" class="btn btn-sm btn-outline-primary">Mở chat</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted small p-3">Không có hội thoại chưa đọc.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
