<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — Sentra Buah Garut</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @include('components.sbg.theme-head')
</head>
<body class="sbg-auth-page">
<main class="sbg-auth-shell" data-sbg-login>
    <a href="{{ route('home') }}" class="sbg-auth-brand" aria-label="Sentra Buah Garut — beranda"><img src="{{ asset('favicon.svg') }}" width="54" height="54" alt=""><span><strong>Sentra Buah Garut</strong><small>SISTEM PURCHASE ORDER</small></span></a>
    <section class="sbg-auth-story" aria-labelledby="auth-heading">
        <p class="sbg-auth-eyebrow">SEGAR SETIAP HARI, MUDAH DIKELOLA</p>
        <h1 id="auth-heading">Kelola Buah<br>Lebih Mudah<br>dan <em>Terukur</em></h1>
        <p>Satu sistem untuk mengelola purchase order, menghitung kebutuhan buah, dan memantau pembayaran mitra dapur.</p>
        <ul class="sbg-auth-trust"><li><span aria-hidden="true">▥</span>Data<br>real-time</li><li><span aria-hidden="true">✿</span>Mudah<br>digunakan</li><li><span aria-hidden="true">◇</span>Aman &amp;<br>terpercaya</li></ul>
    </section>
    <section class="sbg-auth-card" aria-labelledby="login-heading">
        <div class="sbg-section-accent" aria-hidden="true"></div>
        <h2 id="login-heading">Selamat Datang Kembali <span aria-hidden="true">👋</span></h2>
        <p class="sbg-muted">Kelola purchase order Anda.</p>
        @if(session('status'))<p class="sbg-feedback" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="sbg-feedback sbg-feedback-error" role="alert"><strong>Tidak dapat masuk.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('login') }}" data-submit-loading>
            @csrf
            <label for="email">Alamat Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="nama@contoh.com" @error('email') aria-invalid="true" @enderror>
            <label for="password">Password</label>
            <div class="sbg-password-field"><input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Masukkan password" @error('password') aria-invalid="true" @enderror><button type="button" data-password-toggle="password" aria-controls="password" aria-label="Tampilkan password" aria-pressed="false">◉</button></div>
            <div class="sbg-auth-options"><label class="sbg-check"><input type="checkbox" name="remember" @checked(old('remember'))> Ingat saya</label>@if(Route::has('password.request'))<a href="{{ route('password.request') }}">Lupa password?</a>@endif</div>
            <button type="submit" class="sbg-primary sbg-auth-submit" data-loading-label="Memeriksa…"><span>Masuk ke Sistem</span><span aria-hidden="true">→</span></button>
            <p class="sbg-submit-status" role="status" aria-live="polite"></p>
        </form>
        <div class="sbg-auth-help"><span class="sbg-help-icon" aria-hidden="true">♧</span><div><strong>Butuh bantuan?</strong><p>Hubungi administrator SBG untuk bantuan akses akun.</p><a href="{{ route('home') }}">Kembali ke beranda</a></div></div>
    </section>
</main>
</body>
</html>
