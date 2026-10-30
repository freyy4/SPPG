<section class="sbg-po25-section" data-po-section="transaction">
    <header><span aria-hidden="true"><x-sbg.icon name="calendar" :size="23"/></span><div><h3>1. Informasi Transaksi</h3><p>Atur detail transaksi purchase order.</p></div></header>
    <div class="sbg-po25-grid sbg-po25-grid-three">
        @include('components.sbg.po-field', ['field' => 'transaction_date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true])
        @include('components.sbg.po-field', ['field' => 'category', 'label' => 'Kategori', 'type' => 'select', 'required' => true, 'options' => array_combine(['Karbo','Protein Nabati','Protein Hewani','Sayur','Buah'], ['Karbo','Protein Nabati','Protein Hewani','Sayur','Buah'])])
        @include('components.sbg.po-field', ['field' => 'priority', 'label' => 'Prioritas', 'type' => 'select', 'required' => true, 'options' => array_combine(['Normal','Tinggi','Rendah','Urgent','High','Low'], ['Normal','Tinggi','Rendah','Urgent','High','Low'])])
    </div>
    @if(auth()->user()->canManageFinancials())
        @include('components.sbg.po-field', ['field' => 'created_by', 'label' => 'Penanggung Jawab', 'type' => 'select', 'required' => true, 'options' => $admins->mapWithKeys(fn ($admin) => [$admin->id => $admin->name.' — '.($admin->region ?? 'Tanpa Wilayah')])->all()])
    @endif
</section>
