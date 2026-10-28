<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Sentra Buah Garut</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .sbg-heading { font-family: 'Manrope', sans-serif; }
        .sbg-login-card { box-shadow: 0 16px 48px rgba(18, 61, 44, .08); }
        :focus-visible { outline: 2px solid #c27909; outline-offset: 3px; }
    </style>
<link rel="stylesheet" href="{{ asset('css/sbg-v2.css') }}?v=2"></head>
<body class="min-h-screen bg-[#f7f7f0] text-[#1c3328] antialiased selection:bg-[#f3a51d] selection:text-[#0a291e]">

    <main class="sbg-login min-h-screen flex flex-col items-center justify-center px-4 py-10 sm:px-6">
        <a href="{{ route('home') }}" class="mb-7 inline-flex items-center gap-3 rounded-md" aria-label="Sentra Buah Garut - beranda">
            <img src="{{ asset('favicon.svg') }}" alt="" class="h-12 w-12 object-contain">
            <span class="flex flex-col">
                <strong class="sbg-heading text-base font-extrabold tracking-tight">Sentra Buah Garut</strong>
                <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#687168]">Sistem Purchase Order</span>
            </span>
        </a>

    <div class="sbg-login-shell"><section class="sbg-login-story"><span class="sbg-eyebrow">SEGAR SETIAP HARI, MUDAH DIKELOLA</span><h1>Kelola Buah <br>Lebih Mudah <br>dan <em>Terukur</em></h1><p>Satu sistem untuk mengelola purchase order, menghitung kebutuhan buah, dan memantau pembayaran mitra dapur.</p><div class="sbg-trust"><span>✓ Data real-time</span><span>✓ Mudah digunakan</span><span>✓ Aman & terpercaya</span></div><img src="{{ asset('images/sbg-orchard.svg') }}" alt="Ilustrasi kebun dan keranjang buah Sentra Buah Garut" width="600" height="270"></section>
    <div class="sbg-login-card w-full max-w-md rounded-xl border border-[#dce1d6] bg-white p-6 sm:p-8">
        <div class="mb-7">
            <div class="mb-5 h-1 w-10 rounded-full bg-[#f3a51d]" aria-hidden="true"></div>
            <h1 class="sbg-heading text-2xl font-extrabold tracking-tight">Selamat Datang Kembali 👋</h1>
            <p class="mt-2 text-sm leading-relaxed text-[#687168]">Kelola purchase order Anda.</p>
        </div>

        <!-- Session Status / Alert Error General -->
        @if (session('status'))
            <div role="status" class="mb-5 rounded-md border border-[#dce1d6] bg-[#e9efe6] p-3 text-sm text-[#123d2c]">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-semibold text-[#435348]">Alamat Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email') }}" 
                    required 
                    autofocus
                    autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-2 w-full rounded-md border bg-white px-3.5 py-3 text-sm text-[#1c3328] placeholder:text-[#80877d] focus:border-[#587547] focus:ring-2 focus:ring-[#587547]/20 focus:outline-none transition @error('email') border-red-500 @else border-[#dce1d6] @enderror"
                    placeholder="nama@contoh.com"
                >
                @error('email')
                    <p id="email-error" role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-[#435348]">Password</label>
                <div class="relative mt-2">
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="w-full rounded-md border bg-white py-3 pl-3.5 pr-12 text-sm text-[#1c3328] placeholder:text-[#80877d] focus:border-[#587547] focus:ring-2 focus:ring-[#587547]/20 focus:outline-none transition @error('password') border-red-500 @else border-[#dce1d6] @enderror"
                    placeholder="••••••••"
                >
                    <button type="button" id="toggle-password" aria-label="Tampilkan password" aria-controls="password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md text-[#687168] transition hover:bg-[#e9efe6] hover:text-[#123d2c]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                            <circle cx="12" cy="12" r="3" />
                            <path id="password-eye-slash" d="m3 3 18 18" style="display: none" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p id="password-error" role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <label class="flex min-h-10 items-center text-sm text-[#435348] cursor-pointer">
                    <input type="checkbox" name="remember" @checked(old('remember')) class="h-4 w-4 rounded border-[#dce1d6] accent-[#123d2c]">
                    <span class="ml-2">Ingat saya</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full rounded-md bg-[#123d2c] px-4 py-3 text-sm font-bold text-white shadow-sm transition duration-200 hover:bg-[#0a291e]"
            >
                Masuk
            </button>
        </form>
    </div></div>
        <a href="{{ route('home') }}" class="mt-6 rounded text-sm font-medium text-[#687168] transition hover:text-[#123d2c]">&larr; Kembali ke beranda</a>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggle-password');
        const passwordEyeSlash = document.getElementById('password-eye-slash');

        passwordToggle.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');
            passwordEyeSlash.style.display = showPassword ? '' : 'none';
        });
    </script>
</body>
</html>
