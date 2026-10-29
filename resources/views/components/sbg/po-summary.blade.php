<div x-show="isSummaryModalOpen" x-cloak class="sbg-modal sbg-v24-overlay" role="dialog" aria-modal="true" aria-labelledby="sbg-summary-title">
    <section class="sbg-modal-panel sbg-v24-summary" @click.outside="isSummaryModalOpen = false">
        <header class="sbg-v24-modal-header"><span class="sbg-v24-modal-icon"><x-sbg.icon name="po" :size="28"/></span><div><p class="sbg-v24-eyebrow">RINGKASAN PURCHASE ORDER</p><h2 id="sbg-summary-title" x-text="summaryModalTitle"></h2><p>Seluruh transaksi sesuai filter yang aktif.</p></div><button type="button" class="sbg-icon-button" @click="isSummaryModalOpen = false" aria-label="Tutup ringkasan PO" data-dialog-close><x-sbg.icon name="close"/></button></header>
        <div class="sbg-v24-summary-list" tabindex="0" aria-label="Daftar PO sesuai filter">
            <template x-for="item in summaryModalItems" :key="item.id">
                <article class="sbg-v24-summary-card"><span class="sbg-v24-fruit-thumb" aria-hidden="true"><svg viewBox="0 0 80 80"><use :href="'/images/sbg-fruits.svg#' + sbgFruitSymbol(item.item_name)"></use></svg></span><div class="sbg-v24-summary-copy"><h3 x-text="item.item_name"></h3><p x-text="formatDate(item.transaction_date)"></p><small x-text="item.po_number"></small><span x-show="item.status_stage === 'Selesai'" class="sbg-v24-success">✓ Selesai</span></div><dl><div><dt>Kategori</dt><dd x-text="item.category || '—'"></dd></div><div><dt>Qty</dt><dd x-text="item.quantity + ' ' + (item.unit || '')"></dd></div><div><dt>Total nilai PO</dt><dd class="sbg-v24-value" x-text="formatRupiah(item.ui_po_value)"></dd></div></dl></article>
            </template>
            <div x-show="!summaryModalItems.length" class="sbg-v24-empty"><x-sbg.icon name="leaf" :size="36"/><h3>Belum ada PO</h3><p>Transaksi yang sesuai filter akan muncul di sini.</p></div>
        </div>
        <footer class="sbg-v24-summary-total"><div><span x-text="summaryModalTitle === 'PO Selesai' ? 'TOTAL NILAI PO SELESAI' : summaryModalTitle === 'PO Pending' ? 'TOTAL NILAI PO PENDING' : 'TOTAL NILAI PO'"></span><small x-text="summaryModalItems.length + ' transaksi'"></small></div><strong x-text="formatRupiah(summaryModalItems.reduce((total, item) => total + Number(item.ui_po_value), 0))"></strong></footer>
    </section>
</div>
