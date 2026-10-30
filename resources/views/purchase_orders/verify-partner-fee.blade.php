<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEM PO</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    @include('components.sbg.theme-head')
</head>
<body class="min-h-screen bg-gray-50 p-4 font-sans text-gray-900 sm:p-8">
    <main class="mx-auto w-full max-w-2xl overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-xl">
        <header class="bg-emerald-600 px-6 py-8 text-center text-white">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-3xl">✓</div>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-100">Dokumen Resmi</p>
            <h1 class="mt-1 text-2xl font-black">VERIFIED</h1>
        </header>

        <section class="space-y-6 p-5 sm:p-7">
            <div class="text-center">
                <h2 class="text-lg font-extrabold">SENTRA BUAH GARUT</h2>
                <p class="mt-1 text-xs text-gray-500">Jl. Ahmad Yani Timur Karangpawitan Garut</p>
                <p class="mt-3 text-sm font-bold text-gray-700">Barang Terpilih pada Nota {{ ['partner_fee' => 'Fee Mitra', 'supplier' => 'Bayar Supplier', 'ksppg_fee' => 'Fee KSPPG'][$type ?? 'partner_fee'] }}</p>
            </div>

            <div class="space-y-3">
                @foreach($purchaseOrders as $purchaseOrder)
                    <article class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Item PO</p>
                                <h3 class="mt-1 font-extrabold">{{ $purchaseOrder->item_name }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ $purchaseOrder->quantity }} {{ $purchaseOrder->unit }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-bold text-emerald-700">VERIFIED</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-200 pt-3 text-xs">
                            <div>
                                <dt class="text-gray-400">Nomor PO</dt>
                                <dd class="mt-0.5 font-bold">{{ $purchaseOrder->po_number }}</dd>
                            </div>
                            <div class="text-right">
                                <dt class="text-gray-400">Tanggal PO</dt>
                                <dd class="mt-0.5 font-bold">{{ $purchaseOrder->transaction_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Harga</dt>
                                <dd class="mt-0.5 font-bold text-gray-900">Rp {{ number_format($purchaseOrder->maker_price ?? 0, 0, ',', '.') }}</dd>
                            </div>
                            <div class="text-right">
                                <dt class="text-gray-400">Total Nilai Item</dt>
                                <dd class="mt-0.5 font-extrabold text-emerald-700">Rp {{ number_format(($purchaseOrder->maker_price ?? 0) * ($purchaseOrder->quantity ?? 0), 0, ',', '.') }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <p class="text-center text-[11px] leading-relaxed text-gray-400">
                Seluruh barang di atas telah diverifikasi dan tercatat pada sistem Purchase Order.
            </p>
        </section>
    </main>
</body>
</html>
