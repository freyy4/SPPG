<div x-data="partnerFeeModal" x-on:{{ $feeEvent }}.window="openModal($event.detail)" x-show="open" x-cloak
    class="sbg-modal sbg-v22-overlay sbg-fee-overlay">
    <section @click.outside="open = false" class="sbg-modal-panel sbg-fee-panel" aria-label="Rincian {{ $feeTitle }}">
        <header class="sbg-v22-header">
            <span class="sbg-v22-header-icon"><x-sbg.icon name="chart" :size="28"/></span>
            <div><h2>Rincian {{ $feeTitle }}</h2><p>{{ $feeDescription }}</p></div>
            <img class="sbg-v22-banner" src="{{ asset('images/sbg-v22/banner.webp') }}" alt="" aria-hidden="true" width="269" height="91">
            <button type="button" @click="open = false" data-dialog-close class="sbg-v22-close" aria-label="Tutup">&times;</button>
        </header>
        <nav class="sbg-payment-tools sbg-fee-tabs" aria-label="Tindakan rincian pembayaran">
            <button type="button" @click="mode = 'detail'" :aria-pressed="mode === 'detail'"><x-sbg.icon name="wallet"/>Pembayaran</button>
            <button type="button" @click="mode = 'print'" :aria-pressed="mode === 'print'"><x-sbg.icon name="print"/>Print</button>
            <button type="button" @click="mode = 'download'" :aria-pressed="mode === 'download'"><x-sbg.icon name="download"/>Download</button>
        </nav>
        <div class="sbg-fee-select">
            <label><input type="checkbox" :checked="allSelected()" @change="toggleAll()" :disabled="selectableItems().length === 0"><span>Pilih semua yang belum dibayar</span></label>
            <span x-text="selectedIds.length + ' dipilih'"></span>
        </div>
        <div class="sbg-fee-list" tabindex="0" aria-label="Daftar rincian {{ $feeTitle }}">
            <p class="sbg-fee-scope">Rincian dari halaman PO aktif · <span x-text="items.length"></span> transaksi. Gunakan filter / halaman PO untuk rincian lainnya.</p>
            <template x-for="item in items" :key="item.id">
                <label class="sbg-fee-item" :class="item[config.paid] ? 'is-paid' : ''">
                    <input type="checkbox" :value="String(item.id)" x-model="selectedIds" :disabled="item[config.paid]" :aria-label="'Pilih ' + item.item_name">
                    <span class="sbg-fee-thumb" aria-hidden="true"><svg viewBox="0 0 80 80"><use :href="'{{ asset('images/sbg-fruits.svg') }}#' + fruitSymbol(item.item_name)"></use></svg></span>
                    <div class="sbg-fee-item-copy"><h3 x-text="item.item_name"></h3><p x-text="inputDate(item)"></p>@if($feeType === 'supplier')<small x-text="'Supplier: ' + (item.supplier_name || '-')"></small>@endif</div>
                    <span class="sbg-fee-status" :data-paid="item[config.paid]" x-text="item[config.paid] ? 'Lunas' : 'Belum Bayar'"></span>
                    <div class="sbg-fee-splits">
                        @if($feeType === 'partner_fee')
                        <div data-warehouse="sbg"><strong x-text="'SBG = ' + formatRupiah(Number((item.quantity_sbg ?? item.quantity ?? 0)) * Number(item.partner_margin || 0))"></strong><p x-text="(item.quantity_sbg ?? item.quantity ?? 0) + ' ' + (item.unit || '') + ' × ' + formatRupiah(item.partner_margin)"></p></div>
                        <div data-warehouse="b"><strong x-text="'B = ' + formatRupiah(Number((item.quantity_b ?? 0)) * Number(item.maker_price || 0))"></strong><p x-text="(item.quantity_b ?? 0) + ' ' + (item.unit || '') + ' × ' + formatRupiah(item.maker_price)"></p></div>
                        @elseif($feeType === 'supplier')
                        <div data-warehouse="sbg"><strong x-text="'SBG = ' + formatRupiah(Number((item.quantity_sbg ?? item.quantity ?? 0)) * Number(item.supplier_price || 0))"></strong><p x-text="(item.quantity_sbg ?? item.quantity ?? 0) + ' ' + (item.unit || '') + ' × ' + formatRupiah(item.supplier_price)"></p></div>
                        <div data-warehouse="b"><strong>Gudang B</strong><p>Tidak termasuk pembayaran supplier ini.</p></div>
                        @else
                        <div data-warehouse="sbg"><strong x-text="'SBG = ' + formatRupiah(Number((item.quantity_sbg ?? item.quantity ?? 0)) * Number(item.ksppg_margin_per_unit || 0))"></strong><p x-text="(item.quantity_sbg ?? item.quantity ?? 0) + ' ' + (item.unit || '') + ' × ' + formatRupiah(item.ksppg_margin_per_unit)"></p></div>
                        <div data-warehouse="b"><strong>Gudang B</strong><p>Tidak termasuk fee KSPPG ini.</p></div>
                        @endif
                    </div>
                    <div class="sbg-fee-item-total"><span>TOTAL</span><strong x-text="formatRupiah(item[config.paid] ? 0 : (item[config.total] ?? 0))"></strong></div>
                </label>
            </template>
            <p x-show="items.length === 0" class="sbg-empty">Belum ada rincian transaksi {{ strtolower($feeTitle) }}.</p>
        </div>
        <footer class="sbg-fee-footer">
            <div class="sbg-fee-summary"><span class="sbg-v22-header-icon"><x-sbg.icon name="po" :size="26"/></span><div><p>Total Data Dipilih</p><strong x-text="formatRupiah(selectedTotal())"></strong></div></div>
            <div class="sbg-fee-footer-actions">
                <button type="button" @click="open = false" class="sbg-button sbg-fee-dismiss">Tutup</button>
                <button x-show="mode === 'print'" type="button" @click="printSelectedNote()" :disabled="selectedIds.length === 0" class="sbg-button sbg-button-primary"><x-sbg.icon name="print"/>Print Nota</button>
                <button x-show="mode === 'download'" type="button" @click="downloadSelectedPdf()" :disabled="selectedIds.length === 0" class="sbg-button sbg-button-primary"><x-sbg.icon name="download"/>Download PDF</button>
                <button x-show="mode === 'detail'" type="button" @click="sendToWhatsApp()" :disabled="selectedIds.length === 0" class="sbg-button sbg-button-primary"><x-sbg.icon name="whatsapp" :size="22"/>Kirim ke WhatsApp</button>
            </div>
            @if(auth()->user()->isSuperAdmin())
            <form x-data="{ confirming: false, submitting: false }" x-show="mode === 'detail'" x-effect="if (!open) confirming = false" @submit.prevent="if (confirming && !submitting && selectedIds.length > 0) { submitting = true; $el.submit(); }" action="{{ route($feePayRoute) }}" method="POST" class="sbg-fee-pay-form">
                @csrf
                <template x-for="item in selectedItems().filter(item => !item[config.paid])" :key="item.id"><input type="hidden" name="ids[]" :value="item.id"></template>
                <button x-ref="reviewPayment" type="button" @click="confirming = true; $nextTick(() => $refs.cancelPayment.focus())" :disabled="submitting || selectedIds.length === 0" class="sbg-button sbg-button-outline">Bayar yang sudah diceklis</button>
                <div x-show="confirming" x-cloak @keydown.escape.stop.prevent="if (!submitting) { confirming = false; $nextTick(() => $refs.reviewPayment.focus()); }" class="sbg-fee-confirm-overlay">
                    <div role="dialog" aria-modal="true" aria-labelledby="confirm-{{ $feeType }}-title" class="sbg-modal-confirm">
                        <h3 id="confirm-{{ $feeType }}-title">Bayar yang sudah diceklis?</h3><p>Apakah Anda yakin ingin membayar hanya item yang sudah diceklis?</p>
                        <p><strong x-text="selectedIds.length"></strong> transaksi · <strong x-text="formatRupiah(selectedTotal())"></strong></p>
                        <div><button x-ref="cancelPayment" type="button" @click="confirming = false; $nextTick(() => $refs.reviewPayment.focus())" :disabled="submitting" class="sbg-button sbg-button-outline">Batal</button><button type="submit" :disabled="submitting || selectedIds.length === 0" class="sbg-button sbg-button-primary" x-text="submitting ? 'Memproses…' : 'Ya, Bayar yang Dipilih'"></button></div>
                    </div>
                </div>
            </form>
            @endif
        </footer>
    </section>
</div>
