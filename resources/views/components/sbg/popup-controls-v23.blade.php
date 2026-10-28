@if(!$popupPerUserReady)
<span class="sbg-v23-muted">Kolonne user_id belum tersedia</span>
@else
<div class="sbg-v23-popup-actions-wrap"><div class="sbg-v23-popup-actions">
    <form method="POST" @submit.prevent="toggleMasterPopup($el)" action="{{ route('dashboard.login-popup.toggle',$account) }}">@csrf
        <input type="hidden" name="enabled" value="{{ $accountPopup?->enabled ? 0 : 1 }}">
        <button type="submit" role="switch" aria-label="Popup login untuk {{ $account->name }}" aria-checked="{{ $accountPopup?->enabled ? 'true' : 'false' }}" class="sbg-v23-switch"><span aria-hidden="true"></span></button>
    </form>
    <button type="button" class="sbg-v23-config" aria-label="Setting Popup untuk {{ $account->name }}" data-account-id="{{ $account->id }}" data-account-name="{{ $account->name }}" data-title="{{ $accountPopup?->title }}" data-message="{{ $accountPopup?->message }}" data-enabled="{{ $accountPopup?->enabled ? 1 : 0 }}" data-version="{{ \App\Support\PopupVersion::token($accountPopup) }}" @click="openLoginPopupSettings($el.dataset)"><x-sbg.icon name="calculator" :size="14"/>Atur<span class="sr-only"> Setting Popup</span></button>
</div><p class="sbg-v23-error" role="alert" data-toggle-error></p></div>
@endif
