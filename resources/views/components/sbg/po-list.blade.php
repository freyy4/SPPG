<section class="sbg-panel sbg-po-list" aria-labelledby="po-list-title">
    <div class="sbg-list-header">
        <div><h2 id="po-list-title"><span class="sbg-orders-title">Daftar Purchase Order</span><span class="sbg-activity-title">Aktivitas Terbaru</span></h2><p>Klik PO untuk melihat informasi dan pembayaran.</p></div>
        <a class="sbg-view-all" href="#data-po" @click.prevent="navigateDashboard('orders')">Lihat semua <x-sbg.icon name="arrow" :size="16"/></a>
        <div class="sbg-list-tools">
            @if(auth()->user()->isSuperAdmin())<button type="button" class="sbg-price-analysis" @click="$dispatch('open-po-analysis')"><x-sbg.icon name="chart" :size="16"/>Analisa harga</button>@endif
            <div class="sbg-view-toggle" role="group" aria-label="Tampilan PO"><button type="button" @click="poView = 'table'" :aria-pressed="poView === 'table'">Tabel</button><button type="button" @click="poView = 'cards'" :aria-pressed="poView === 'cards'">Kartu</button></div>
        </div>
    </div>
    <div x-show="poView === 'table'" class="sbg-table-scroll">
        <table class="sbg-po-table"><thead><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th>Nama Barang</th><th>Jumlah</th><th>Wilayah</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
        @forelse($purchaseOrders as $po)
            <tr @click="openDetailModal({{ json_encode($po) }})" @keydown.enter.self.prevent="openDetailModal({{ json_encode($po) }})" @keydown.space.self.prevent="openDetailModal({{ json_encode($po) }})" tabindex="0" aria-label="Lihat detail PO {{ $po->po_number }}">
                <td><strong>{{ $po->po_number }}</strong></td><td>{{ $po->transaction_date?->format('d/m/Y') ?? '-' }}</td><td>{{ $po->supplier_name ?: '-' }}</td><td><strong>{{ $po->item_name }}</strong></td><td class="sbg-nowrap">{{ $po->quantity }} {{ $po->unit }}</td><td>{{ $po->region }}</td><td><x-sbg.status-badge :status="$po->status_stage"/></td>
                <td>@include('components.sbg.po-overflow', ['po' => $po])</td>
            </tr>
        @empty<tr><td colspan="8" class="sbg-empty">Belum ada Purchase Order. Buat PO baru atau ubah filter pencarian.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div x-cloak x-show="poView === 'cards'" class="sbg-po-cards">
        @forelse($purchaseOrders as $po)
            <article class="sbg-po-card">
                <button type="button" class="sbg-po-open" @click="openDetailModal({{ json_encode($po) }})" aria-label="Lihat detail PO {{ $po->po_number }}">
                    <x-sbg.fruit-thumbnail :name="$po->item_name"/>
                    <span class="sbg-po-card-copy"><strong class="sbg-po-number">{{ $po->po_number }}</strong><span class="sbg-po-item">{{ $po->item_name }}</span><span class="sbg-po-supplier">{{ $po->supplier_name ?: 'Supplier belum diisi' }}</span></span>
                    <x-sbg.status-badge :status="$po->status_stage"/>
                    <span class="sbg-po-meta"><span><x-sbg.icon name="calendar" :size="13"/>{{ $po->transaction_date?->translatedFormat('d M Y') ?? '-' }}</span><span><x-sbg.icon name="box" :size="13"/>{{ $po->quantity }} {{ $po->unit }}</span><span><x-sbg.icon name="pin" :size="13"/>{{ $po->region }}</span></span>
                    <span class="sbg-po-activity-time">{{ $po->created_at?->translatedFormat('d M, H:i') }}</span>
                </button>
                @include('components.sbg.po-overflow', ['po' => $po])
            </article>
        @empty<p class="sbg-empty">Belum ada Purchase Order. Buat PO baru atau ubah filter pencarian.</p>@endforelse
    </div>
    <div class="sbg-pagination">{{ $purchaseOrders->links() }}</div>
</section>
