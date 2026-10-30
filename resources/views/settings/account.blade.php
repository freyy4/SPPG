@php
    $user = $user ?? auth()->user();
    $tab = in_array(request('tab'), ['profile','account','security'], true) ? request('tab') : 'profile';
    $securityState = array_merge(['pin_enabled'=>false,'totp_enabled'=>false,'recovery_codes_remaining'=>0,'setup'=>null], $securityState ?? []);
    $activeSessions = $activeSessions ?? [];
    $sessionManagementSupported = $sessionManagementSupported ?? false;
    $recoveryCodes = $recoveryCodes ?? [];
    $accountUrl = fn($name, $path) => Route::has($name) ? route($name) : url($path);
    $initial = mb_strtoupper(mb_substr($user->name, 0, 1));
    $roleLabel = ['super_admin'=>'Super Admin','kepala_dapur'=>'Kepala Dapur','admin'=>'Admin'][$user->role] ?? $user->role;
    $statuses = ['profile-updated'=>'Informasi profil berhasil disimpan.','photo-updated'=>'Foto profil berhasil diperbarui.','password-updated'=>'Password berhasil diperbarui.','pin-enabled'=>'PIN keamanan berhasil diaktifkan.','pin-changed'=>'PIN keamanan berhasil diubah.','pin-disabled'=>'PIN keamanan dinonaktifkan.','pin-verified'=>'PIN berhasil diverifikasi.','totp-setup'=>'Pindai kode QR, lalu konfirmasi kode untuk mengaktifkan 2FA.','totp-enabled'=>'Verifikasi dua langkah berhasil diaktifkan. Simpan kode pemulihan Anda.','totp-disabled'=>'Verifikasi dua langkah dinonaktifkan.','recovery-regenerated'=>'Kode pemulihan baru dibuat. Kode sebelumnya tidak berlaku.','sessions-revoked'=>'Sesi lain telah dicabut.'];
@endphp
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Pengaturan Akun — Sentra Buah Garut</title><link rel="icon" href="{{ asset('favicon.svg') }}">@include('components.sbg.theme-head')</head>
<body class="sbg-settings-page">
<div class="sbg-settings-layout" data-sbg-settings data-active-tab="{{ $tab }}">
    <aside class="sbg-settings-sidebar" aria-label="Navigasi aplikasi">
        <a href="{{ route('dashboard') }}" class="sbg-auth-brand"><img src="{{ asset('favicon.svg') }}" width="42" height="42" alt=""><span><strong>Sentra Buah Garut</strong><small>SISTEM PURCHASE ORDER</small></span></a>
        <nav><a href="{{ route('dashboard') }}"><span aria-hidden="true">⌂</span> Beranda</a><a href="{{ route('dashboard') }}#data-po"><span aria-hidden="true">▤</span> Purchase Order</a><a href="{{ route('dashboard') }}#kalkulator-buah"><span aria-hidden="true">♧</span> Data Buah</a><a href="{{ route('dashboard') }}#master-data"><span aria-hidden="true">♙</span> Mitra Dapur</a><a href="{{ $accountUrl('account.settings','/settings/account') }}" aria-current="page"><span aria-hidden="true">⚙</span> Pengaturan</a></nav>
        <div class="sbg-settings-promo"><strong>Buah Segar<br>untuk Dapur Indonesia</strong><p>Bersama petani lokal, untuk masa depan lebih sehat.</p><img src="{{ asset('images/sbg-v25/fruit-support.webp') }}" alt="" width="240" height="140"></div>
    </aside>
    <main class="sbg-settings-main" id="account-content">
        <header class="sbg-settings-header"><a href="{{ route('dashboard') }}">⌂ &nbsp; / &nbsp; Pengaturan</a><div class="sbg-settings-identity"><span class="sbg-avatar-small">@if($user->avatar_path)<img src="{{ $accountUrl('account.avatar','/settings/account/avatar') }}" alt="">@else{{ $initial }}@endif</span><div><strong>{{ $user->name }}</strong><small>{{ $user->region ?: 'Wilayah belum ditetapkan' }}</small></div></div></header>
        <div class="sbg-settings-title"><h1>Pengaturan Akun</h1><p>Kelola informasi profil, akun, dan keamanan Anda dengan mudah dan aman.</p></div>
        @if(session('status'))<div class="sbg-feedback" role="status">{{ $statuses[session('status')] ?? session('status') }}</div>@endif
        @if($errors->any())<div class="sbg-feedback sbg-feedback-error" role="alert" tabindex="-1"><strong>Perubahan belum dapat disimpan.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <section class="sbg-settings-workspace" aria-label="Pengaturan akun">
            <nav class="sbg-settings-tabs" aria-label="Bagian pengaturan">@foreach(['profile'=>['♙','Profil'],'account'=>['▣','Akun'],'security'=>['◇','Keamanan']] as $key=>$item)<a href="{{ $accountUrl('account.settings','/settings/account') }}?tab={{ $key }}" @if($tab===$key) aria-current="page" @endif><span aria-hidden="true">{{ $item[0] }}</span>{{ $item[1] }}</a>@endforeach</nav>
            <div class="sbg-settings-columns">
                <section class="sbg-settings-panel" aria-labelledby="settings-section-heading">
                    @if($tab==='profile')
                        <div class="sbg-section-accent" aria-hidden="true"></div><h2 id="settings-section-heading">Informasi Profil</h2><p>Kelola informasi dasar akun Anda.</p>
                        <form class="sbg-photo-form" method="POST" action="{{ $accountUrl('account.photo.update','/settings/account/photo') }}" enctype="multipart/form-data" data-submit-loading>
                            @csrf
                            <div class="sbg-avatar-large">@if($user->avatar_path)<img src="{{ $accountUrl('account.avatar','/settings/account/avatar') }}" alt="Foto profil Anda">@else<span aria-label="Inisial {{ $user->name }}">{{ $initial }}</span>@endif</div>
                            <div class="sbg-field"><label for="photo">Foto Profil</label><input type="file" name="photo" id="photo" accept="image/jpeg,image/png" required aria-describedby="photo-help"><small id="photo-help">JPG atau PNG, maksimal 2 MB. Foto disimpan privat.</small></div><button type="submit" class="sbg-secondary" data-loading-label="Mengunggah…">Unggah foto</button>
                        </form>
                        <form method="POST" action="{{ $accountUrl('account.profile.update','/settings/account/profile') }}" data-submit-loading>
                            @csrf @method('PATCH')
                            <div class="sbg-field-grid">
                                <div class="sbg-field"><label for="profile-name">Nama Lengkap <span aria-hidden="true">*</span></label><input id="profile-name" name="name" value="{{ old('name',$user->name) }}" required maxlength="255" autocomplete="name"></div>
                                <div class="sbg-field"><label for="profile-role">Jabatan</label><input id="profile-role" value="{{ $roleLabel }}" readonly aria-readonly="true"><small>Dikelola administrator.</small></div>
                                <div class="sbg-field"><label for="profile-email">Email</label><input id="profile-email" type="email" value="{{ $user->email }}" readonly aria-readonly="true"><small>Email masuk dikelola administrator.</small></div>
                                <div class="sbg-field"><label for="profile-phone">Nomor Telepon</label><input id="profile-phone" name="phone" type="tel" value="{{ old('phone',$user->phone) }}" maxlength="32" autocomplete="tel"></div>
                                <div class="sbg-field sbg-field-full"><label for="profile-region">Wilayah / Dapur</label><input id="profile-region" value="{{ $user->region ?: 'Belum ditetapkan' }}" readonly aria-readonly="true"><small>Penugasan wilayah mengikuti hak akses akun.</small></div>
                            </div><button type="submit" class="sbg-primary">Simpan Perubahan</button><p class="sbg-submit-status" role="status"></p>
                        </form>
                    @elseif($tab==='account')
                        <div class="sbg-section-accent" aria-hidden="true"></div><h2 id="settings-section-heading">Preferensi Akun</h2><p>Identitas masuk dan tampilan workspace Anda.</p>
                        <div class="sbg-field-grid"><div class="sbg-field sbg-field-full"><label for="account-email">Email masuk</label><input id="account-email" value="{{ $user->email }}" readonly aria-readonly="true"><small>Hubungi administrator untuk perubahan identitas akun.</small></div><div class="sbg-field"><label for="account-role">Peran</label><input id="account-role" value="{{ $roleLabel }}" readonly aria-readonly="true"></div><div class="sbg-field"><label for="account-region">Wilayah</label><input id="account-region" value="{{ $user->region ?: 'Belum ditetapkan' }}" readonly aria-readonly="true"></div></div>
                        <label class="sbg-account-theme" for="account-theme"><strong>Tema tampilan</strong><select id="account-theme" data-theme-select><option value="light">Terang</option><option value="dark">Gelap</option><option value="system">Ikuti sistem</option></select><small>Disimpan pada browser ini untuk akun Anda. Mode sistem mengikuti pengaturan perangkat.</small></label>
                        <p class="sbg-muted">Bahasa aplikasi: Bahasa Indonesia. Koneksi akun eksternal dan perangkat tepercaya belum didukung.</p>
                    @else
                        @include('settings.partials.security')
                    @endif
                </section>
                <aside class="sbg-support-rail" aria-label="Panduan dan bantuan akun">
                    <div class="sbg-support-card"><h3>{{ $tab==='security' ? 'Akun yang Aman, Transaksi yang Lancar' : 'Profil yang lengkap' }}</h3><p>{{ $tab==='security' ? 'Lindungi akun dengan password yang kuat, PIN keamanan, dan verifikasi dua langkah.' : 'Informasi akun yang terbaru membantu komunikasi dan transaksi dengan mitra dapur.' }}</p></div>
                    <div class="sbg-support-card"><h3>{{ $tab==='security' ? 'Tips Keamanan Akun' : 'Tips Profil' }}</h3><ul>@if($tab==='security')<li>Gunakan password yang unik dan kuat.</li><li>Simpan kode pemulihan di tempat aman.</li><li>Jangan bagikan PIN atau kode verifikasi.</li><li>Periksa sesi masuk secara berkala.</li>@else<li>Gunakan nama lengkap sesuai identitas.</li><li>Pastikan nomor telepon aktif.</li><li>Penugasan wilayah dikelola administrator.</li><li>Foto profil membantu identifikasi akun.</li>@endif</ul></div>
                    @if($tab==='security')@include('settings.partials.sessions')@endif
                </aside>
            </div>
        </section>
    </main>
</div>
<nav class="sbg-settings-mobile-nav" aria-label="Navigasi mobile"><a href="{{ route('dashboard') }}">⌂ &nbsp; Beranda</a><a href="{{ route('dashboard') }}#data-po">▤ &nbsp; Purchase Order</a><a href="{{ $accountUrl('account.settings','/settings/account') }}" aria-current="page">⚙ &nbsp; Akun</a></nav>
</body></html>
