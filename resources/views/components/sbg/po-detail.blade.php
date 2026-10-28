<div id="po-detail" x-show="isDetailModalOpen" x-cloak class="sbg-modal sbg-detail-overlay" role="dialog" aria-modal="true" aria-labelledby="po-detail-title" @keydown.escape.window="if (isDetailModalOpen) isDetailModalOpen = false" x-transition.opacity.duration.180ms>
    <div class="sbg-detail-panel" @click.outside="isDetailModalOpen = false" :aria-busy="detailLoading">
        <header class="sbg-detail-header">
            <x-sbg.fruit-thumbnail dynamic/>
            <div class="sbg-detail-title"><h2 id="po-detail-title" x-text="selectedPo.item_name"></h2><p x-text="selectedPo.po_number"></p><x-sbg.status-badge dynamic/></div>
            <div class="sbg-detail-created"><span>Dibuat pada</span><p x-text="shortDate(selectedPo.created_at)"></p></div>
            <button type="button" class="sbg-icon-button sbg-detail-close" @click="isDetailModalOpen = false" aria-label="Tutup detail PO" data-dialog-close><x-sbg.icon name="close"/></button>
        </header>
        <div class="sbg-detail-content">
            <p x-cloak x-show="detailLoading" class="sbg-detail-loading" role="status">Memperbarui detail PO…</p>
            <p x-cloak x-show="detailError" class="sbg-detail-error" role="alert"><span x-text="detailError"></span> <button type="button" @click="openDetailModal(selectedPo)">Coba lagi</button></p>
            <div class="sbg-detail-columns">
                <section class="sbg-detail-section" aria-labelledby="po-information-title"><h3 id="po-information-title"><x-sbg.icon name="po" :size="18"/>Informasi PO</h3>
                    <dl class="sbg-info-rows">
                        <div><dt>Tanggal</dt><dd x-text="shortDate(selectedPo.transaction_date)"></dd></div>
                        <div><dt>Dibuat</dt><dd x-text="shortDate(selectedPo.created_at)"></dd></div>
                        <div><dt>Prioritas</dt><dd><span class="sbg-status" x-text="selectedPo.priority || 'Normal'"></span></dd></div>
                        <div><dt>Tahapan</dt><dd><x-sbg.status-badge dynamic/></dd></div>
                        <div><dt>Kategori</dt><dd x-text="selectedPo.category || '-'"></dd></div>
                        <div><dt>Supplier</dt><dd x-text="selectedPo.supplier_name || '-'"></dd></div>
                        <div><dt>Wilayah</dt><dd x-text="selectedPo.region || '-'"></dd></div>
                        <div><dt>Jumlah</dt><dd x-text="(selectedPo.quantity ?? 0) + ' ' + (selectedPo.unit || '')"></dd></div>
                    </dl>
                </section>
                <section class="sbg-detail-section" aria-labelledby="po-cost-title"><h3 id="po-cost-title"><x-sbg.icon name="calculator" :size="18"/>Rincian Biaya</h3>
                    <dl class="sbg-info-rows sbg-money-rows">
                        @if(auth()->user()->canManageFinancials())
                            <div class="sbg-cost-sbg"><dt>Harga Supplier Gudang SBG</dt><dd x-text="formatRupiah(selectedPo.supplier_price)"></dd></div>
                            <div class="sbg-cost-b"><dt>Harga Supplier Gudang B</dt><dd x-text="formatRupiah(selectedPo.supplier_price_b ?? selectedPo.supplier_price)"></dd></div>
                        @endif
                        <div class="sbg-cost-maker"><dt>Harga Maker</dt><dd x-text="formatRupiah(selectedPo.maker_price)"></dd></div>
                        <div class="sbg-cost-sbg"><dt>Fee Mitra/unit SBG</dt><dd x-text="formatRupiah(selectedPo.partner_margin)"></dd></div>
                        <div class="sbg-warehouse"><dt>Qty per Gudang</dt><dd><span x-text="'SBG: ' + (selectedPo.quantity_sbg ?? selectedPo.quantity ?? 0) + ' ' + (selectedPo.unit || '')"></span><span x-text="'B: ' + (selectedPo.quantity_b ?? 0) + ' ' + (selectedPo.unit || '')"></span></dd></div>
                        @if(auth()->user()->canManageFinancials())
                            <div><dt>Sisa Bayar Supplier</dt><dd x-text="formatRupiah(selectedPo.is_supplier_paid ? 0 : selectedPo.total_supplier_amount)"></dd></div>
                        @endif
                        <div><dt>Sisa Laba Mitra</dt><dd x-text="formatRupiah(selectedPo.is_partner_fee_paid ? 0 : selectedPo.total_partner_fee)"></dd></div>
                        @if(auth()->user()->canManageFinancials())
                            <div><dt>Sisa Fee KSPPG</dt><dd x-text="formatRupiah(selectedPo.is_ksppg_fee_paid ? 0 : selectedPo.total_ksppg_fee)"></dd></div>
                        @endif
                    </dl>
                </section>
            </div>
            <details class="sbg-po-notes" x-cloak x-show="selectedPo.notes"><summary>Catatan PO</summary><p x-text="selectedPo.notes"></p></details>
            <section class="sbg-detail-actions" aria-labelledby="po-actions-title"><h3 id="po-actions-title"><x-sbg.icon name="wallet" :size="18"/>Pembayaran & Tindakan</h3>
                @if(auth()->user()->canManageFinancials())
                    <div class="sbg-payment-actions">
                        @foreach(['partner_fee' => ['is_partner_fee_paid', 'Mitra', 'users'], 'supplier' => ['is_supplier_paid', 'Supplier', 'box'], 'ksppg_fee' => ['is_ksppg_fee_paid', 'KSPPG', 'shield']] as $type => [$flag, $label, $icon])
                            <form :action="'/dashboard/po/' + selectedPo.id + '/toggle-payment'" method="POST" data-payment-label="{{ $label }}">@csrf @method('PATCH')<input type="hidden" name="payment_type" value="{{ $type }}"><button type="submit" class="sbg-button sbg-button-primary" :disabled="detailLoading || !!detailError" :title="selectedPo.{{ $flag }} ? 'Sudah lunas. Klik untuk meninjau perubahan menjadi belum bayar.' : 'Tinjau pembayaran {{ $label }}'"><x-sbg.icon :name="$icon" :size="18"/><span x-text="selectedPo.{{ $flag }} ? 'Lunas {{ $label }}' : 'Bayar {{ $label }}'"></span></button></form>
                        @endforeach
                    </div>
                    <p x-show="detailLoading || detailError" class="sbg-payment-help">Pembayaran tersedia setelah detail PO berhasil diperbarui.</p>
                @endif
                <div class="sbg-management-actions">
                    <button type="button" @click="openEditModal(selectedPo)" class="sbg-button sbg-button-outline"><x-sbg.icon name="edit" :size="17"/>Edit PO</button>
                    <form :action="'/dashboard/po/' + selectedPo.id" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus PO ini?')">@csrf @method('DELETE')<button type="submit" class="sbg-button sbg-button-danger"><x-sbg.icon name="trash" :size="17"/>Hapus PO</button></form>
                    <button type="button" @click="isDetailModalOpen = false; $dispatch('open-nota-dapur', { po_number: selectedPo.po_number, verification_url: selectedPo.verification_url, item_name: selectedPo.item_name, category: selectedPo.category, quantity: selectedPo.quantity, unit: selectedPo.unit, maker_price: selectedPo.maker_price, transaction_date: formatDate(selectedPo.transaction_date), priority: selectedPo.priority || 'Normal', status_stage: selectedPo.status_stage || 'Selesai', notes: selectedPo.notes })" class="sbg-button sbg-button-tinted"><x-sbg.icon name="print" :size="17"/><span>Buat Nota Dapur</span></button>
                </div>
            </section>
        </div>
    </div>
</div>
