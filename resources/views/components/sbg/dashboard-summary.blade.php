<section id="ringkasan" class="sbg-kpi-grid" aria-label="Ringkasan purchase order">
    <x-sbg.kpi label="Total Nilai PO" :value="$summary['total_nilai_po'] ?? 0" icon="coins" money @click="openSummaryModal('Total Nilai PO')"/>
    <x-sbg.kpi label="PO Pending" :value="$summary['po_pending'] ?? 0" icon="clock" tone="orange" hint="Menunggu proses" @click="openSummaryModal('PO Pending')"/>
    <x-sbg.kpi label="PO Selesai" :value="$summary['po_selesai'] ?? 0" icon="check" tone="blue" @click="openSummaryModal('PO Selesai')"/>
    <h2 class="sbg-payment-heading">Ringkasan Pembayaran</h2>
    <x-sbg.kpi label="Sisa Fee Mitra" :value="$summary['total_laba_mitra'] ?? 0" icon="users" tone="red" money payment hint="Lihat detail" @click="$dispatch('open-modal-fee-mitra', { items: allPoData, total: {{ $summary['total_laba_mitra'] ?? 0 }}, mode: 'detail' })"/>
    @if(auth()->user()->canManageFinancials())
        <x-sbg.kpi label="Sisa Bayar Supplier" :value="$summary['total_bayar_supplier'] ?? 0" icon="wallet" tone="orange" money payment hint="Lihat detail" @click="$dispatch('open-modal-supplier', { items: allPoData, total: {{ $summary['total_bayar_supplier'] ?? 0 }}, mode: 'detail', paymentType: 'supplier' })"/>
        <x-sbg.kpi label="Sisa Fee KSPPG" :value="$summary['total_laba_ksppg'] ?? 0" icon="shield" money payment hint="Lihat detail" @click="$dispatch('open-modal-fee-ksppg', { items: allPoData, total: {{ $summary['total_laba_ksppg'] ?? 0 }}, mode: 'detail', paymentType: 'ksppg_fee' })"/>
    @endif
</section>
