<h2 id="settings-section-heading">Keamanan Akun</h2><p>Lindungi akun Anda dengan pengaturan keamanan tambahan.</p>
<section class="sbg-security-section" aria-labelledby="password-section">
    <h3 id="password-section">🔑 &nbsp; 1. Ubah Password</h3><p>Gunakan password unik: minimal 12 karakter, huruf besar dan kecil, angka, serta simbol.</p>
    <form method="POST" action="{{ $accountUrl('account.password.update','/account/password') }}" data-submit-loading>
        @csrf @method('PUT')
        <div class="sbg-field-grid sbg-password-grid"><x-sbg.account-password-field id="password-current"/><x-sbg.account-password-field id="password-new" name="password" label="Password baru" autocomplete="new-password" minlength="12" data-password-strength="password-strength"/><x-sbg.account-password-field id="password-confirmation" name="password_confirmation" label="Konfirmasi password" autocomplete="new-password" minlength="12"/></div>
        <label class="sbg-muted" for="password-strength">Kekuatan password (panduan, kebijakan diperiksa server)</label><meter class="sbg-strength" id="password-strength" min="0" max="4" low="2" high="3" optimum="4" value="0">0 dari 4 kriteria</meter>
        @if($securityState['totp_enabled'])<x-sbg.account-factor-fields prefix="password-factor"/>@endif
        <button type="submit" class="sbg-primary">Simpan Password Baru</button><p class="sbg-submit-status" role="status"></p>
    </form>
</section>
<section class="sbg-security-section" aria-labelledby="pin-section">
    <div class="sbg-security-status"><h3 id="pin-section">◇ &nbsp; 2. PIN Keamanan</h3><span class="sbg-state-badge {{ $securityState['pin_enabled'] ? 'is-enabled' : '' }}">{{ $securityState['pin_enabled'] ? 'PIN diaktifkan' : 'Belum aktif' }}</span></div><p>PIN opsional 6 digit untuk verifikasi tambahan. Tidak otomatis mengubah kebijakan transaksi.</p>
    <form method="POST" action="{{ $accountUrl($securityState['pin_enabled'] ? 'account.pin.change' : 'account.pin.enable','/settings/account/pin') }}" data-submit-loading>
        @csrf @if($securityState['pin_enabled']) @method('PUT') @endif
        <div class="sbg-field-grid"><x-sbg.account-password-field id="pin-password"/>
            @if($securityState['pin_enabled'])<div class="sbg-field"><label for="pin-current">PIN saat ini</label><input id="pin-current" type="password" name="current_pin" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" required data-pin-input data-pin-label="PIN saat ini"></div>@endif
            <div class="sbg-field"><label for="pin-new">PIN baru</label><input id="pin-new" type="password" name="pin" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" required data-pin-input data-pin-label="PIN baru"></div><div class="sbg-field"><label for="pin-confirmation">Konfirmasi PIN baru</label><input id="pin-confirmation" type="password" name="pin_confirmation" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" required data-pin-input data-pin-label="Konfirmasi PIN baru"></div>
        </div><button type="submit" class="sbg-primary">{{ $securityState['pin_enabled'] ? 'Ubah PIN Keamanan' : 'Aktifkan PIN Keamanan' }}</button><p class="sbg-submit-status" role="status"></p>
    </form>
    @if($securityState['pin_enabled'])
        <details><summary>Nonaktifkan PIN</summary><form method="POST" action="{{ $accountUrl('account.pin.disable','/settings/account/pin') }}" data-submit-loading>@csrf @method('DELETE')<div class="sbg-field-grid"><x-sbg.account-password-field id="pin-disable-password"/><div class="sbg-field"><label for="pin-disable-current">PIN saat ini</label><input id="pin-disable-current" name="current_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" required data-pin-input data-pin-label="PIN untuk menonaktifkan"></div></div><button type="submit" class="sbg-secondary sbg-danger">Nonaktifkan PIN</button></form></details>
    @endif
</section>
<section class="sbg-security-section" aria-labelledby="totp-section">
    <div class="sbg-security-status"><h3 id="totp-section">▣ &nbsp; 3. Google Authenticator (2FA)</h3><span class="sbg-state-badge {{ $securityState['totp_enabled'] ? 'is-enabled' : '' }}">{{ $securityState['totp_enabled'] ? '2FA diaktifkan' : 'Belum aktif' }}</span></div><p>Tambahkan lapisan keamanan dengan aplikasi Google Authenticator atau aplikasi TOTP yang kompatibel.</p>
    @if(!$securityState['totp_enabled'])
        @if($securityState['setup'])
            <div class="sbg-totp-setup" data-totp-setup>
                @if(!empty($securityState['setup']['qr_data_uri']))<img src="{{ $securityState['setup']['qr_data_uri'] }}" width="160" height="160" alt="Kode QR pengaturan Authenticator akun Anda">@endif
                <div><strong>Pindai dengan aplikasi Authenticator</strong><p class="sbg-muted">Belum aktif sampai kode pertama diverifikasi.</p><details open><summary>Tidak bisa memindai? Gunakan kunci manual</summary><code class="sbg-manual-key">{{ $securityState['setup']['manual_key'] ?? '' }}</code></details></div>
            </div>
            <form method="POST" action="{{ $accountUrl('account.totp.confirm','/settings/account/totp/confirm') }}" data-submit-loading>@csrf<div class="sbg-field"><label for="totp-confirm-code">Kode verifikasi 6 digit</label><input id="totp-confirm-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div><button type="submit" class="sbg-primary">Verifikasi &amp; Aktifkan 2FA</button></form>
        @else
            <form method="POST" action="{{ $accountUrl('account.totp.setup','/settings/account/totp/setup') }}" data-submit-loading>@csrf<x-sbg.account-password-field id="totp-setup-password"/><button type="submit" class="sbg-primary">Mulai Pengaturan 2FA</button><p class="sbg-submit-status" role="status"></p></form>
        @endif
    @else
        <p class="sbg-feedback">Authenticator aktif. Kode verifikasi akan diminta saat masuk.</p>
        <div class="sbg-security-status"><strong>Kode pemulihan</strong><span class="sbg-state-badge">{{ (int)$securityState['recovery_codes_remaining'] }} kode tersisa</span></div>
        @if(count($recoveryCodes))<div class="sbg-feedback" role="status" data-recovery-once><strong>Simpan kode ini sekarang — hanya ditampilkan sekali.</strong><p>Setiap kode hanya dapat dipakai sekali. Simpan secara privat di luar perangkat ini.</p><ul class="sbg-recovery-codes">@foreach($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach</ul></div>@endif
        <details><summary>Buat ulang kode pemulihan</summary><p class="sbg-muted">Semua kode lama akan dibatalkan setelah verifikasi berhasil.</p><form method="POST" action="{{ $accountUrl('account.recovery.regenerate','/settings/account/recovery-codes') }}" data-submit-loading>@csrf<x-sbg.account-password-field id="recovery-password"/><x-sbg.account-factor-fields prefix="recovery-factor"/><button type="submit" class="sbg-secondary">Buat Kode Pemulihan Baru</button></form></details>
        <details><summary>Nonaktifkan verifikasi dua langkah</summary><form method="POST" action="{{ $accountUrl('account.totp.disable','/settings/account/totp') }}" data-submit-loading>@csrf @method('DELETE')<x-sbg.account-password-field id="totp-disable-password"/><x-sbg.account-factor-fields prefix="totp-disable-factor"/><button type="submit" class="sbg-secondary sbg-danger">Nonaktifkan 2FA</button></form></details>
    @endif
    <p class="sbg-muted">Perangkat tepercaya: belum didukung. Tidak ada perangkat yang dipercaya otomatis.</p>
</section>
