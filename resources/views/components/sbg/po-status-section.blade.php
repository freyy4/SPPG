<section class="sbg-po25-section" data-po-section="status">
    <header><span aria-hidden="true"><x-sbg.icon name="document" :size="23"/></span><div><h3>4. Status & Catatan Tambahan</h3><p>Atur tahapan status dan tambahkan catatan jika diperlukan.</p></div></header>
    <div class="sbg-po25-grid sbg-po25-grid-two">
        @include('components.sbg.po-field', ['field' => 'status_stage', 'label' => 'Tahapan Status', 'type' => 'select', 'required' => true, 'options' => array_combine(['Cari Supplier','Sortir','Pengiriman','Selesai','Retur','Kekurangan','Perubahan Item'], ['Cari Supplier','Sortir','Pengiriman','Selesai','Retur','Kekurangan','Perubahan Item'])])
        @include('components.sbg.po-field', ['field' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'])
    </div>
</section>
