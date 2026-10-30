<section class="sbg-po25-section" data-po-section="product">
    <header><span aria-hidden="true"><x-sbg.icon name="box" :size="23"/></span><div><h3>2. Informasi Barang</h3><p>Pilih barang dan masukkan jumlah dari masing-masing gudang.</p></div></header>
    <div class="sbg-po25-grid {{ auth()->user()->canManageFinancials() ? 'sbg-po25-grid-two' : '' }}">
        @include('components.sbg.po-field', ['field' => 'item_name', 'label' => 'Nama Barang / PO', 'required' => true, 'maxlength' => 255])
        @if(auth()->user()->canManageFinancials())
            @include('components.sbg.po-field', ['field' => 'supplier_name', 'label' => 'Supplier', 'maxlength' => 255])
        @endif
    </div>
    <div class="sbg-po25-grid sbg-po25-grid-two">
        @foreach(['sbg' => 'SBG', 'b' => 'B'] as $warehouse => $caption)
        <div class="sbg-po25-quantity sbg-po25-quantity-{{ $warehouse }}">
            @include('components.sbg.po-field', ['field' => 'quantity_'.$warehouse, 'label' => 'Qty Gudang '.$caption, 'type' => 'number', 'required' => true])
            <p data-po-warehouse-value="{{ $warehouse }}">× Harga Maker = <strong x-text="formatRupiah(warehouseValue('{{ $warehouse }}'))"></strong></p>
        </div>
        @endforeach
    </div>
    @include('components.sbg.po-field', ['field' => 'unit', 'label' => 'Satuan', 'required' => true, 'maxlength' => 50])
</section>
