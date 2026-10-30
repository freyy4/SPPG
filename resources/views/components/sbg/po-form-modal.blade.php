@php
    $isEdit = $poMode === 'edit';
    $restore = $isEdit ? (bool) old('_sbg_edit') : old('_sbg_create') === '1';
    $poSeed = [];
    foreach (['item_name', 'category', 'priority', 'transaction_date', 'created_by', 'supplier_name', 'quantity_sbg', 'quantity_b', 'unit', 'supplier_price', 'supplier_price_b', 'maker_price', 'partner_margin', 'status_stage', 'notes', 'updated_at'] as $field) {
        $poSeed[$field] = $restore ? old($field, '') : '';
    }
    if (!$restore) $poSeed = array_replace($poSeed, ['transaction_date' => date('Y-m-d'), 'priority' => 'Normal', 'quantity_sbg' => 0, 'quantity_b' => 0, 'supplier_price_b' => 0]);
    $poSeed['id'] = $isEdit ? old('_sbg_edit', '') : '';
    $poErrors = $restore ? (session('errors')?->getBag('default')->toArray() ?? []) : [];
@endphp
<div x-data="sbgPoForm(@js($poMode), @js($poSeed), @js($poErrors))" x-show="{{ $isEdit ? 'isEditModalOpen' : 'isCreateModalOpen' }}" x-cloak
     class="sbg-modal sbg-po25-overlay {{ $isEdit ? 'sbg-edit-overlay' : 'sbg-create-overlay' }}" data-po-mode="{{ $poMode }}"
     @click.self="requestClose()" @keydown.escape.stop.prevent="requestClose()" :data-po-dirty="dirty() ? 'true' : 'false'"
     aria-labelledby="po25-{{ $poMode }}-title">
    <section class="sbg-modal-panel sbg-po25-panel" :aria-busy="busy || (mode === 'edit' && poEditLoading)">
        <header class="sbg-po25-header">
            <span class="sbg-po25-intent" aria-hidden="true"><x-sbg.icon :name="$isEdit ? 'edit' : 'plus'" :size="26"/></span>
            <div><h2 id="po25-{{ $poMode }}-title">{{ $isEdit ? 'Edit Purchase Order' : 'Tambah Purchase Order' }}</h2>
                <p x-text="mode === 'edit' ? (draft.po_number || 'Memuat purchase order…') : 'Lengkapi informasi transaksi dan rincian harga.'"></p></div>
            <img class="sbg-po25-art" src="{{ asset('images/sbg-v22/banner.webp') }}" alt="" aria-hidden="true" width="269" height="91">
            <button type="button" @click="requestClose()" data-dialog-close class="sbg-po25-close" aria-label="Tutup">&times;</button>
        </header>
        <form method="POST" {{ !$isEdit ? 'data-sbg-create-form' : '' }} data-po-form data-server-errors="{{ json_encode($poErrors) }}"
              class="sbg-po25-form" {{ !$isEdit ? 'action='.route('dashboard.po.store') : '' }} :action="mode === 'edit' ? '/dashboard/po/' + draft.id : '{{ route('dashboard.po.store') }}'"
              @submit.prevent="submit($event)" novalidate>
            @csrf
            @if($isEdit)
                @method('PUT')
                <input type="hidden" name="_sbg_edit" :value="draft.id">
                <input type="hidden" name="updated_at" :value="draft.updated_at || ''">
            @else
                <input type="hidden" name="_sbg_create" value="1">
            @endif
            <div class="sbg-po25-body">
                <p x-show="mode === 'edit' && poEditLoading" role="status">Memuat data PO terbaru…</p>
                <p class="sbg-po25-error-summary" x-show="message || (mode === 'edit' && poEditError)" x-text="message || (mode === 'edit' ? poEditError : '')" role="alert" tabindex="-1" data-po-message></p>
                <fieldset :disabled="busy || (mode === 'edit' && (poEditLoading || !!poEditError))">
                    @include('components.sbg.po-transaction-section')
                    @include('components.sbg.po-product-section')
                    @include('components.sbg.po-pricing-section')
                    @include('components.sbg.po-status-section')
                </fieldset>
            </div>
            <footer class="sbg-po25-footer">
                <button type="button" @click="requestClose()" :disabled="busy" class="sbg-po25-cancel">Batal</button>
                <button type="submit" :disabled="busy || (mode === 'edit' && (poEditLoading || !!poEditError || !draft.id))" :aria-busy="busy" class="sbg-po25-submit" data-po-submit>
                    <span x-text="busy ? 'Menyimpan…' : (mode === 'edit' ? 'Update PO' : 'Simpan PO')">{{ $isEdit ? 'Update PO' : 'Simpan PO' }}</span><span aria-hidden="true">→</span>
                </button>
            </footer>
        </form>
    </section>
</div>
