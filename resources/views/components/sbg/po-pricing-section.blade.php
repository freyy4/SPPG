<section class="sbg-po25-section" data-po-section="pricing">
    <header><span aria-hidden="true"><x-sbg.icon name="tag" :size="23"/></span><div><h3>3. Rincian Harga & Margin</h3><p>Atur harga maker dan laba mitra per unit.</p></div></header>
    @if(auth()->user()->canManageFinancials())
    <div class="sbg-po25-grid sbg-po25-grid-two">
        @include('components.sbg.po-field', ['field' => 'supplier_price', 'label' => 'Harga Supplier Gudang SBG', 'type' => 'currency'])
        @include('components.sbg.po-field', ['field' => 'supplier_price_b', 'label' => 'Harga Supplier Gudang B', 'type' => 'currency'])
    </div>
    @endif
    <div class="sbg-po25-grid sbg-po25-grid-three">
        @include('components.sbg.po-field', ['field' => 'maker_price', 'label' => 'Harga Maker (per unit)', 'type' => 'currency', 'disabled' => auth()->user()->canManageFinancials() ? '!hasMinimumCurrencyDigits(draft.supplier_price) && !hasMinimumCurrencyDigits(draft.supplier_price_b)' : ''])
        @include('components.sbg.po-field', ['field' => 'partner_margin', 'label' => 'Laba Mitra (per unit)', 'type' => 'currency', 'disabled' => '!hasMinimumCurrencyDigits(draft.maker_price)'])
        <aside class="sbg-po25-estimate" data-po-estimate><small>Harga jual / Harga Maker</small><strong x-text="formatRupiah(currency(draft.maker_price))"></strong><small>per unit · sudah mencakup margin</small></aside>
    </div>
    <p class="sbg-po25-total">Total nilai PO <strong data-po-total x-text="formatRupiah(warehouseValue('sbg') + warehouseValue('b'))"></strong></p>
</section>
