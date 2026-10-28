@php($filterCount = collect(['search', 'status', 'priority', 'region', 'date_from', 'date_to'])->filter(fn ($key) => filled(session('filter_'.$key)))->count())
<section id="data-po" class="sbg-panel sbg-filter" aria-label="Filter purchase order">
    <form id="po-filter-form" method="POST" action="{{ route('dashboard') }}" class="sbg-filter-form">
        @csrf
        <div class="sbg-filter-field sbg-filter-search">
            <label for="po-filter-search">Pencarian PO</label>
            <div><x-sbg.icon name="search" :size="18"/><input id="po-filter-search" type="search" name="search" value="{{ session('filter_search') }}" placeholder="Cari barang, supplier, atau nomor PO…"></div>
        </div>
        <button type="button" class="sbg-filter-toggle sbg-button sbg-button-outline" @click="filterOpen = true" :aria-expanded="filterOpen" aria-controls="po-filter-options" aria-label="Buka filter lanjutan"><x-sbg.icon name="filter"/>@if($filterCount)<span class="sbg-filter-count">{{ $filterCount }}</span>@endif</button>
        <div x-cloak x-show="filterOpen && !wideFilters" class="sbg-filter-backdrop" @click="filterOpen = false"></div>
        <div id="po-filter-options" x-cloak x-show="filterOpen || wideFilters" class="sbg-filter-options" :role="wideFilters ? null : 'dialog'" :aria-modal="wideFilters ? null : 'true'" aria-label="Filter purchase order" @keydown.escape.stop.prevent="filterOpen = false" data-sbg-dialog>
            <div class="sbg-filter-sheet-header"><h2>Filter Purchase Order</h2><button type="button" class="sbg-icon-button" @click="filterOpen = false" aria-label="Tutup filter" data-dialog-close><x-sbg.icon name="close"/></button></div>
            <div class="sbg-filter-field"><label for="po-filter-status">Status</label><select id="po-filter-status" name="status"><option value="">Semua status</option>@foreach(['Pending', 'Proses', 'Selesai', 'Batal'] as $status)<option @selected(session('filter_status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="sbg-filter-field"><label for="po-filter-priority">Prioritas</label><select id="po-filter-priority" name="priority"><option value="">Semua prioritas</option>@foreach(['Urgent', 'High', 'Normal'] as $priority)<option @selected(session('filter_priority') === $priority)>{{ $priority }}</option>@endforeach</select></div>
            <div class="sbg-filter-field"><label for="po-filter-region">Wilayah</label><select id="po-filter-region" name="region" @disabled(!auth()->user()->isSuperAdmin())><option value="">Semua wilayah</option>@foreach($regions as $reg)<option value="{{ $reg->name }}" @selected((session('filter_region') ?? auth()->user()->region) == $reg->name)>{{ $reg->name }}</option>@endforeach</select></div>
            <div class="sbg-filter-field"><label for="date_from">Tanggal mulai</label><input id="date_from" type="date" name="date_from" value="{{ old('date_from', session('filter_date_from')) }}">@error('date_from')<p class="sbg-field-error">{{ $message }}</p>@enderror</div>
            <div class="sbg-filter-field"><label for="date_to">Tanggal akhir</label><input id="date_to" type="date" name="date_to" value="{{ old('date_to', session('filter_date_to')) }}">@error('date_to')<p class="sbg-field-error">{{ $message }}</p>@enderror</div>
            <div class="sbg-filter-actions"><button type="submit" class="sbg-button sbg-button-primary"><x-sbg.icon name="filter" :size="16"/><span>Terapkan<span class="sbg-filter-word"> Filter</span></span></button><a href="{{ route('dashboard.reset') }}" class="sbg-button sbg-button-outline">Reset</a></div>
        </div>
    </form>
    @if($filterCount)<p class="sbg-filter-active">{{ $filterCount }} filter aktif · {{ number_format($purchaseOrders->total(), 0, ',', '.') }} PO ditemukan</p>@endif
</section>
