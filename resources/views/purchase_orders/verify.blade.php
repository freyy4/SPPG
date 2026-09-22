<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEM PO</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4 font-sans">
    <main class="w-full max-w-md overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-xl">
        <div class="bg-emerald-600 px-6 py-8 text-center text-white">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-3xl">✓</div>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-100">Dokumen Resmi</p>
            <h1 class="mt-1 text-2xl font-black">VERIFIED</h1>
        </div>

        <div class="space-y-5 p-6">
            <div class="text-center">
                <h2 class="font-extrabold text-gray-900">SENTRA BUAH GARUT</h2>
                <p class="mt-1 text-xs text-gray-500">Jl. Ahmad Yani Timur Karangpawitan Garut</p>
            </div>

            <dl class="divide-y divide-gray-100 rounded-2xl border border-gray-100 bg-gray-50/60 px-4">
                <div class="py-4">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Nomor PO</dt>
                    <dd class="mt-1 font-extrabold text-gray-900">{{ $purchaseOrder->po_number }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Item PO</dt>
                    <dd class="mt-1 font-bold text-gray-900">{{ $purchaseOrder->item_name }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Tanggal PO</dt>
                    <dd class="mt-1 font-bold text-gray-900">{{ $purchaseOrder->transaction_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                </div>
            </dl>

            <p class="text-center text-[11px] leading-relaxed text-gray-400">
                Data Purchase Order telah diverifikasi dan tercatat pada sistem.
            </p>
        </div>
    </main>
</body>
</html>
