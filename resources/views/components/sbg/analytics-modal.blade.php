<div x-data="analisaKeuntunganModal()" x-show="isAnalysisModalOpen"
    @open-analisa-modal.window="isAnalysisModalOpen = true; fetchData()" x-cloak
    class="sbg-modal sbg-v22-overlay sbg-analytics-overlay">
    <section @click.outside="isAnalysisModalOpen = false" class="sbg-modal-panel sbg-analytics-panel" :aria-busy="loading">
        <header class="sbg-v22-header">
            <span class="sbg-v22-header-icon"><x-sbg.icon name="chart" :size="26"/></span>
            <div><h2>Analisa Keuntungan</h2><p>Lihat performa penjualan dan keuntungan dari purchase order Anda.</p></div>
            <img class="sbg-v22-banner" src="{{ asset('images/sbg-v22/banner.webp') }}" alt="" aria-hidden="true" width="269" height="91">
            <button type="button" @click="isAnalysisModalOpen = false" data-dialog-close class="sbg-v22-close" aria-label="Tutup">&times;</button>
        </header>
        <nav class="sbg-analytics-period" aria-label="Periode analisa">
            <template x-for="(label, key) in filterOptions" :key="key">
                <button type="button" @click="activeFilter = key; fetchData()" :aria-pressed="activeFilter === key"><x-sbg.icon name="calendar" :size="16"/><span x-text="label"></span></button>
            </template>
        </nav>
        <div class="sbg-analytics-content" aria-live="polite">
            <div x-show="loading" class="sbg-analytics-skeleton" role="status" aria-label="Memuat data analisa">
                <span class="sr-only">Memuat data analisa…</span>
                @for($i = 0; $i < 6; $i++)<div></div>@endfor
            </div>
            <div x-show="!loading && error" class="sbg-analytics-state" role="alert"><x-sbg.icon name="chart" :size="32"/><p x-text="error"></p><button type="button" @click="fetchData()" class="sbg-button sbg-button-primary">Coba lagi</button></div>
            <div x-show="!loading && !error" class="sbg-analytics-data">
                <div class="sbg-analytics-rankings">
                    @foreach(['topItems' => ['Top Items', 'crown'], 'topCategories' => ['Ranking Kategori', 'chart']] as $key => [$heading, $icon])
                    <section class="sbg-ranking-panel" data-ranking="{{ $key }}">
                        <h3><x-sbg.icon :name="$icon" :size="20"/>{{ $heading }}</h3>
                        <template x-for="(item, index) in data.{{ $key }}" :key="item.name">
                            <div class="sbg-ranking-row">
                                <span class="sbg-ranking-number" x-text="'#' + (index + 1)"></span>
                                <span class="sbg-ranking-fruit" aria-hidden="true"><svg viewBox="0 0 80 80"><use :href="'{{ asset('images/sbg-fruits.svg') }}#' + fruitSymbol(item.name)"></use></svg></span>
                                <div class="sbg-ranking-copy"><h4 x-text="item.name"></h4><p>{{ auth()->user()->isRegularAdmin() ? 'Sisa Fee Admin / Mitra' : 'Sisa Fee KSPPG' }}</p></div>
                                <strong x-text="formatRupiah(item.total)"></strong>
                            </div>
                        </template>
                        <p class="sbg-ranking-empty" x-show="data.{{ $key }}.length === 0">Belum ada data {{ $key === 'topItems' ? 'item' : 'kategori' }} pada periode ini.</p>
                    </section>
                    @endforeach
                </div>
                <div class="sbg-analytics-insights">
                    <article data-insight="best"><span class="sbg-insight-icon"><x-sbg.icon name="trophy" :size="22"/></span><div><p>Item Terbaik</p><h3 x-text="data.bestItem.name"></h3><strong x-text="formatRupiah(data.bestItem.total)"></strong></div></article>
                    <article data-insight="lowest"><span class="sbg-insight-icon"><x-sbg.icon name="trend-down" :size="22"/></span><div><p>Item Terendah</p><h3 x-text="data.worstItem.name"></h3><strong x-text="formatRupiah(data.worstItem.total)"></strong></div></article>
                    <article data-insight="total"><span class="sbg-insight-icon"><x-sbg.icon name="pie" :size="24"/></span><div><p>Total Keuntungan</p><strong data-total-profit x-text="formatRupiah(data.totalProfit)"></strong><small>{{ auth()->user()->isRegularAdmin() ? 'Sisa fee mitra' : 'Sisa fee KSPPG' }} · periode dipilih</small></div></article>
                </div>
            </div>
        </div>
    </section>
</div>
