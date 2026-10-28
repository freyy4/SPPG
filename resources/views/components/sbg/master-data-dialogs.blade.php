<div x-show="isEditAdminModalOpen" x-cloak x-transition.opacity.duration.180ms class="sbg-modal sbg-v23-overlay" role="dialog" aria-modal="true" aria-labelledby="v23-edit-title" @keydown.escape.window="if (!v23Busy) isEditAdminModalOpen = false" @click.self="if (!v23Busy) isEditAdminModalOpen = false">
    <section class="sbg-modal-panel sbg-v23-dialog">
        <header class="sbg-v23-dialog-header"><div><h2 id="v23-edit-title">{{ auth()->user()->isSuperAdmin() ? 'Edit Data Region & Admin' : 'Edit Akun Admin' }}</h2><p>Perbarui informasi region, daerah/dapur dan akun admin.</p></div><button type="button" class="sbg-v23-close" @click="if (!v23Busy) isEditAdminModalOpen = false" :disabled="v23Busy" aria-label="Tutup edit" data-dialog-close><x-sbg.icon name="close" :size="18"/></button></header>
        <form method="POST" :action="{{ auth()->user()->isSuperAdmin() ? "'/admin/region/' + editAdminForm.region_id" : "'/admin/account/' + editAdminForm.account_id" }}" @submit.prevent="submitV23($el, 'edit')" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="account_id" :value="editAdminForm.account_id">
            <div class="sbg-v23-dialog-body sbg-v23-edit-grid">
                <div class="sbg-v23-fields">
                    @if(auth()->user()->isSuperAdmin())
                        @foreach([['code','Kode Region','code',50],['name','Nama Daerah / Dapur','name',255]] as [$name,$label,$model,$max])
                        <div class="sbg-v23-field"><label for="v23-edit-{{ $name }}">{{ $label }}</label><input id="v23-edit-{{ $name }}" type="text" name="{{ $name }}" x-model="editAdminForm.{{ $model }}" required maxlength="{{ $max }}" :aria-invalid="!!v23Errors.{{ $name }}" aria-describedby="v23-error-{{ $name }}"><p id="v23-error-{{ $name }}" class="sbg-v23-error" x-text="v23Errors.{{ $name }}?.[0]"></p></div>
                        @endforeach
                        <div class="sbg-v23-field"><label for="v23-edit-role">Role Akun</label><select id="v23-edit-role" name="account_role" x-model="editAdminForm.role" :aria-invalid="!!v23Errors.account_role" aria-describedby="v23-error-role"><option value="admin">Admin</option><option value="kepala_dapur">Kepala Dapur</option></select><p id="v23-error-role" class="sbg-v23-error" x-text="v23Errors.account_role?.[0]"></p></div>
                    @endif
                    @foreach([['admin_name','Nama Admin','text'],['admin_email','Email Admin','email']] as [$name,$label,$type])
                    <div class="sbg-v23-field"><label for="v23-edit-{{ $name }}">{{ $label }}</label><input id="v23-edit-{{ $name }}" type="{{ $type }}" name="{{ $name }}" x-model="editAdminForm.{{ $name }}" required maxlength="255" :aria-invalid="!!v23Errors.{{ $name }}" aria-describedby="v23-error-{{ $name }}"><p id="v23-error-{{ $name }}" class="sbg-v23-error" x-text="v23Errors.{{ $name }}?.[0]"></p></div>
                    @endforeach
                    <div class="sbg-v23-field"><label for="v23-edit-password">Password Baru (Opsional)</label><div class="sbg-v23-password"><input id="v23-edit-password" :type="passwordVisible ? 'text' : 'password'" name="password" minlength="6" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah" :aria-invalid="!!v23Errors.password" aria-describedby="v23-error-password"><button type="button" @click="passwordVisible = !passwordVisible" :aria-pressed="passwordVisible" aria-label="Tampilkan atau sembunyikan password">◉</button></div><p id="v23-error-password" class="sbg-v23-error" x-text="v23Errors.password?.[0]"></p></div>
                </div>
                <aside class="sbg-v23-edit-info" aria-label="Informasi akun"><img src="{{ asset('images/sbg-v23/region.webp') }}" alt="" width="230" height="145"><div><h3>Informasi Akun</h3><dl><div><dt>Terdaftar sejak</dt><dd x-text="shortDate(editInfo.since)"></dd></div><div><dt>Status saat ini</dt><dd><span class="sbg-v23-badge is-registered">Terdaftar</span></dd></div><div><dt>Wilayah</dt><dd x-text="editAdminForm.code || editAdminForm.name"></dd></div><div><dt>PO dibuat akun ini</dt><dd><span x-text="editInfo.poCount"></span> PO</dd></div></dl><p>Status berdasarkan akun yang belum dihapus.</p></div></aside>
            </div>
            <p class="sbg-v23-form-error" x-show="v23Error" x-text="v23Error" role="alert"></p>
            <footer class="sbg-v23-dialog-footer"><button type="button" class="sbg-button sbg-button-outline" @click="isEditAdminModalOpen = false" :disabled="v23Busy">Batal</button><button type="submit" class="sbg-button sbg-button-primary" :disabled="v23Busy || v23Saved" :aria-busy="v23Busy"><span x-text="v23Saved ? '✓ Tersimpan' : v23Busy ? 'Menyimpan…' : '✓ Simpan Perubahan'"></span></button></footer>
        </form>
    </section>
</div>
@if(auth()->user()->isSuperAdmin())
<div x-show="isLoginPopupSettingsOpen" x-cloak x-transition.opacity.duration.180ms class="sbg-modal sbg-v23-overlay" role="dialog" aria-modal="true" aria-labelledby="login-popup-settings-title" @keydown.escape.window="if (!v23Busy) isLoginPopupSettingsOpen = false" @click.self="if (!v23Busy) isLoginPopupSettingsOpen = false">
    <section class="sbg-modal-panel sbg-v23-dialog sbg-v23-popup-dialog">
        <header class="sbg-v23-dialog-header"><div><h2 id="login-popup-settings-title">Atur Popup Login</h2><p>Atur judul dan pesan yang akan ditampilkan saat pengguna login.</p><p class="sbg-v23-popup-target" x-text="popupTarget.accountName"></p></div><button type="button" class="sbg-v23-close" @click="if (!v23Busy) isLoginPopupSettingsOpen = false" :disabled="v23Busy" aria-label="Tutup pengaturan" data-dialog-close><x-sbg.icon name="close" :size="18"/></button></header>
        <form method="POST" :action="'/dashboard/users/' + popupTarget.accountId + '/login-popup'" @submit.prevent="submitV23($el, 'popup')" novalidate>
            @csrf
            <input type="hidden" name="_popup_form" value="1"><input type="hidden" name="_popup_user_id" :value="popupTarget.accountId"><input type="hidden" name="_popup_user_name" :value="popupTarget.accountName"><input type="hidden" name="_version" :value="popupTarget.version || ''" :disabled="!popupTarget.version"><input type="hidden" name="enabled" :value="popupTarget.enabled ? 1 : 0">
            <div class="sbg-v23-dialog-body sbg-v23-popup-grid">
                <div class="sbg-v23-fields">
                    <label class="sbg-v23-enabled"><span>Tampilkan Popup saat login<small>Hanya untuk akun yang dipilih</small></span><button type="button" class="sbg-v23-switch" role="switch" :aria-checked="!!popupTarget.enabled" aria-label="Tampilkan Popup saat login" @click="popupTarget.enabled = !popupTarget.enabled"><span aria-hidden="true"></span></button></label>
                    <div class="sbg-v23-field"><label for="login-popup-title-input">Judul / Header <span class="sbg-v23-counter" x-text="Array.from(popupTarget.title || '').length + '/100'" aria-live="polite"></span></label><input id="login-popup-title-input" type="text" name="title" maxlength="100" x-model="popupTarget.title" :aria-invalid="!!v23Errors.title" aria-describedby="v23-error-title"><p id="v23-error-title" class="sbg-v23-error" x-text="v23Errors.title?.[0]"></p></div>
                    <div class="sbg-v23-field"><label for="login-popup-message">Pesan <span class="sbg-v23-counter" x-text="Array.from(popupTarget.message || '').length + '/500'" aria-live="polite"></span></label><textarea id="login-popup-message" name="message" rows="3" maxlength="500" x-model="popupTarget.message" :aria-invalid="!!v23Errors.message" aria-describedby="v23-error-message"></textarea><p id="v23-error-message" class="sbg-v23-error" x-text="v23Errors.message?.[0]"></p></div>
                    <div class="sbg-v23-preview-section"><h3>Preview Tampilan</h3><div class="sbg-v23-preview" aria-label="Preview popup, tidak memicu popup login"><img src="{{ asset('images/sbg-v23/welcome.webp') }}" alt="" width="125" height="110"><div><h4 x-text="popupTarget.title || 'Judul popup'"></h4><p x-text="popupTarget.message || 'Pesan Anda akan tampil di sini.'"></p></div><span aria-hidden="true" class="sbg-v23-preview-close">×</span></div><p class="sbg-v23-muted" x-show="!popupTarget.enabled">Preview saja · Popup tidak akan ditampilkan saat login.</p></div>
                </div>
                <aside class="sbg-v23-tips"><h3><x-sbg.icon name="leaf" :size="18"/>Tips Penulisan Pesan</h3><ul><li>Gunakan kalimat yang ramah dan singkat.</li><li>Bisa berisi informasi penting atau pengingat.</li><li>Maksimal 100 karakter judul dan 500 karakter pesan.</li><li>Akan muncul sekali setelah akun ini login.</li></ul><p>Isi judul atau pesan jika popup diaktifkan. Teks ditampilkan tanpa HTML.</p></aside>
            </div>
            <div x-show="v23Error" class="sbg-v23-form-error" role="alert"><p x-text="v23Error"></p><button type="button" class="sbg-button sbg-button-outline" x-show="v23Conflict" @click="loadPopupServerVersion()">Muat versi server</button></div>
            <footer class="sbg-v23-dialog-footer"><button type="button" class="sbg-button sbg-button-outline" @click="isLoginPopupSettingsOpen = false" :disabled="v23Busy">Batal</button><button type="submit" class="sbg-button sbg-button-primary" :disabled="v23Busy || v23Saved" :aria-busy="v23Busy"><span x-text="v23Saved ? '✓ Tersimpan' : v23Busy ? 'Menyimpan…' : '✓ Simpan'"></span></button></footer>
        </form>
    </section>
</div>
@endif
