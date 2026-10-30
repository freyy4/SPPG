<section class="sbg-settings-panel" aria-labelledby="sessions-heading"><h3 id="sessions-heading">◷ &nbsp; Sesi Aktif &amp; Aktivitas Login</h3><p>Pantau sesi yang sedang masuk ke akun Anda.</p>
@if($sessionManagementSupported)
    @if(count($activeSessions))<ul class="sbg-session-list">@foreach($activeSessions as $activeSession)<li><strong>{{ $activeSession['user_agent'] ?: 'Perangkat tidak dikenali' }}</strong><small>{{ $activeSession['ip_address'] ?? 'IP tidak tersedia' }} @if(!empty($activeSession['last_active_at'])) · {{ $activeSession['last_active_at'] }} @endif</small>@if($activeSession['is_current'] ?? false)<span class="sbg-state-badge is-enabled">Sesi saat ini</span>@endif</li>@endforeach</ul>@else<p class="sbg-muted">Belum ada data sesi yang dapat ditampilkan.</p>@endif
    <details><summary>Keluar dari sesi lain</summary><form method="POST" action="{{ $accountUrl('account.sessions.revoke','/settings/account/sessions') }}" data-submit-loading>@csrf @method('DELETE')<x-sbg.account-password-field id="sessions-password"/><button type="submit" class="sbg-secondary sbg-danger">Cabut Sesi Lain</button></form></details>
@else
    <p class="sbg-feedback">Sesi saat ini terautentikasi.</p><p class="sbg-muted"><strong>Belum didukung oleh penyimpanan sesi saat ini.</strong> Daftar perangkat dan pencabutan sesi lain tidak tersedia. Tidak ada riwayat perangkat yang direka.</p>
@endif
</section>
