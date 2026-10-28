<div x-show="isLogoutModalOpen" x-cloak class="sbg-modal sbg-v22-overlay sbg-logout-overlay">
    <section @click.outside="isLogoutModalOpen = false" class="sbg-modal-panel sbg-logout-panel">
        <button type="button" @click="isLogoutModalOpen = false" data-dialog-close class="sbg-v22-close" aria-label="Tutup">&times;</button>
        <div class="sbg-logout-hero" aria-hidden="true"><img src="{{ asset('images/sbg-v22/banner.webp') }}" alt="" width="269" height="91"><span><x-sbg.icon name="logout" :size="48"/></span></div>
        <h2>Konfirmasi Logout</h2>
        <p>Apakah Anda yakin ingin keluar dari sistem SPPG?</p>
        <div class="sbg-logout-note"><x-sbg.icon name="shield" :size="24"/><p>Sesi Anda akan diakhiri dan Anda perlu login kembali untuk mengakses data.</p></div>
        <div class="sbg-logout-actions">
            <button data-dialog-initial-focus type="button" @click="isLogoutModalOpen = false" class="sbg-button sbg-logout-cancel">Batal</button>
            <form method="POST" action="{{ route('logout') }}" data-sbg-logout>
                @csrf
                <button type="submit" class="sbg-button sbg-logout-submit"><x-sbg.icon name="logout" :size="24"/>Ya, Logout</button>
            </form>
        </div>
    </section>
</div>
