@if($loginPopup?->enabled && $showLoginPopup)
<div x-data="{ open: true }" x-show="open" x-cloak class="sbg-modal sbg-v24-overlay sbg-v24-announcement-overlay" role="dialog" aria-modal="true" aria-labelledby="login-popup-title" @keydown.escape.window="open = false">
    <section class="sbg-modal-panel sbg-v24-announcement">
        <button type="button" @click="open = false" class="sbg-icon-button sbg-v24-announcement-close" aria-label="Tutup pengumuman" data-dialog-close><x-sbg.icon name="close"/></button>
        <div class="sbg-v24-announcement-body"><div class="sbg-v24-announcement-brand"><img src="{{ asset('favicon.svg') }}" alt="Logo aplikasi" width="36" height="36"><span>Sentra Buah Garut<small>SEGAR • BERKUALITAS • TERPERCAYA</small></span></div>
        <img src="{{ asset('images/sbg-v24/farmer-480.webp') }}" alt="" class="sbg-v24-announcement-art" width="480" height="320" onerror="this.hidden=true">
        <p class="sbg-v24-eyebrow">INFORMASI TERBARU</p><h2 id="login-popup-title">{{ $loginPopup->title ?: 'Pengumuman' }}</h2><p class="sbg-v24-announcement-message">{{ $loginPopup->message ?: 'Selamat datang kembali di Sentra Buah Garut.' }}</p></div>
        <footer><button type="button" @click="open = false" class="sbg-button sbg-button-primary" data-dialog-initial-focus>Tutup pengumuman <span aria-hidden="true">→</span></button></footer>
    </section>
</div>
@endif
