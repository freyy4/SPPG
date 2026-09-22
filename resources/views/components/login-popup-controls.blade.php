@if(!$popupPerUserReady)
    <span class="text-[10px] font-medium text-amber-700">Kolom user_id belum dibuat</span>
@else
<div class="flex flex-col items-start gap-2">
    <form method="POST" action="{{ route('dashboard.login-popup.toggle', $account) }}" class="flex items-center gap-2">
        @csrf
        <input type="hidden" name="enabled" value="{{ $accountPopup?->enabled ? 0 : 1 }}">
        <button type="submit" role="switch" aria-label="Popup login untuk {{ $account->name }}" aria-checked="{{ $accountPopup?->enabled ? 'true' : 'false' }}" class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $accountPopup?->enabled ? 'bg-emerald-600' : 'bg-gray-300' }}">
            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $accountPopup?->enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
        </button>
        <span class="text-[10px] font-bold {{ $accountPopup?->enabled ? 'text-emerald-700' : 'text-gray-500' }}">{{ $accountPopup?->enabled ? 'Aktif' : 'Nonaktif' }}</span>
    </form>
    <button type="button"
        data-account-id="{{ $account->id }}"
        data-account-name="{{ $account->name }}"
        data-title="{{ $accountPopup?->title }}"
        data-message="{{ $accountPopup?->message }}"
        @click="openLoginPopupSettings($el.dataset)"
        class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-[10px] font-bold text-indigo-700 hover:bg-indigo-100">Setting Popup</button>
</div>
@endif
