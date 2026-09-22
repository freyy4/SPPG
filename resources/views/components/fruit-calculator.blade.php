@php
    $fruitDefaultRegion = auth()->user()->region ?: (auth()->user()->isSuperAdmin() ? (session('filter_region') ?: ($regions->first()?->name ?? 'Pusat')) : 'Pusat');
@endphp
<section id="kalkulator-buah" x-cloak x-show="activeView === 'fruit'"
    x-data="fruitCalculator({ base: @js(route('fruit-calculator.index', [], false)), csrf: @js(csrf_token()), region: @js($fruitDefaultRegion) })"
    x-init="$watch('activeView', value => { if (value === 'fruit' && !loaded) load(); }); if (activeView === 'fruit') load();"
    @resize.window.debounce.150ms="drawChart()"
    class="fruit-tool mx-auto min-h-screen max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <style>
        .fruit-tool { letter-spacing: 0; }
        .fruit-tool label { font-size: 12px; font-weight: 600; color: #435348; }
        .fruit-tool input:not([type=checkbox]), .fruit-tool select { display: block; width: 100%; min-width: 0; margin-top: 6px; border: 1px solid #cdd7cf; border-radius: 6px; background: white; padding: 10px 12px; font-size: 14px; color: #1c3328; }
        .fruit-tool input[type=checkbox] { width: 17px; height: 17px; accent-color: #123d2c; flex-shrink: 0; }
        .fruit-tool button:disabled { opacity: .45; cursor: not-allowed; }
        .fruit-tool .fruit-btn { display: inline-flex; justify-content: center; align-items: center; gap: 6px; min-height: 38px; padding: 8px 12px; border: 1px solid #cdd7cf; border-radius: 6px; background: white; font-size: 12px; font-weight: 700; }
        .fruit-tool .fruit-primary { color: white; background: #123d2c; border-color: #123d2c; }
        .fruit-tool th { padding: 12px; font-size: 11px; font-weight: 700; color: #576a60; background: #edf2ef; text-align: left; white-space: nowrap; }
        .fruit-tool td { padding: 12px; border-bottom: 1px solid #e2e8e4; font-size: 12px; }
        .fruit-tool table { width: 100%; border-collapse: collapse; }
        .fruit-tool .fruit-tab { padding: 12px 8px; border-bottom: 2px solid transparent; font-size: 13px; font-weight: 700; }
        .fruit-tool .fruit-tab[aria-selected=true] { border-color: #123d2c; color: #123d2c; }
        .fruit-tool .fruit-parameter-dialog { width: min(42rem, calc(100% - 2rem)); max-height: calc(100dvh - 2rem); padding: 0; border: 1px solid #dce1d6; border-radius: 8px; overflow-y: auto; }
        .fruit-tool .fruit-parameter-dialog::backdrop { background: rgb(10 41 30 / .6); backdrop-filter: blur(3px); }

        .fruit-tool { color: #1c3328; --fruit-line: #e1e7df; }
        .fruit-tool .fruit-hero { padding: 28px; border-radius: 20px; background: #123d2c; color: #fff; }
        .fruit-tool .fruit-hero h1 { color: #fff; font-size: clamp(24px, 3vw, 32px); letter-spacing: -.7px; }
        .fruit-tool .fruit-hero p, .fruit-tool .fruit-hero label { color: #d0e0d4; }
        .fruit-tool .fruit-hero .fruit-btn { color: #123d2c; }
        .fruit-tool .fruit-hero select { min-width: 170px; }
        .fruit-tool .fruit-subtitle { margin-top: 10px; font-size: 13px; line-height: 1.7; max-width: 520px; }
        .fruit-tool .fruit-navigation { display: flex; gap: 4px; padding: 5px; width: fit-content; max-width: 100%; overflow-x: auto; border: 1px solid var(--fruit-line); border-radius: 12px; background: #eaf0e7; }
        .fruit-tool .fruit-tab { padding: 11px 20px; border: 0; border-radius: 8px; white-space: nowrap; color: #526454; }
        .fruit-tool .fruit-tab[aria-selected=true] { background: #fff; color: #123d2c; box-shadow: 0 2px 5px #123d2c0d; }
        .fruit-tool .fruit-panel { min-width: 0; padding: 24px; border: 1px solid var(--fruit-line); border-radius: 16px; background: #fff; box-shadow: 0 3px 14px #123d2c04; }
        .fruit-tool .fruit-section-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .fruit-tool .fruit-section-heading h2 { font-size: 16px; font-weight: 800; }
        .fruit-tool .fruit-section-heading p { font-size: 12px; color: #687168; margin-top: 5px; line-height: 1.6; }
        .fruit-tool .fruit-step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; margin-right: 8px; border-radius: 8px; background: #edf3e9; color: #426435; font-size: 12px; vertical-align: middle; }
        .fruit-tool .fruit-count { padding: 6px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #426435; background: #edf3e9; }
        .fruit-tool input:not([type=checkbox]), .fruit-tool select { min-height: 46px; border-radius: 9px; border-color: #d9e2d7; background: #fbfcfa; font-size: 16px; font-variant-numeric: tabular-nums; transition: border-color .15s, box-shadow .15s; }
        .fruit-tool input:focus, .fruit-tool select:focus { outline: none; border-color: #587547; box-shadow: 0 0 0 3px #58754720; background: #fff; }
        .fruit-tool input[readonly] { background: #edf1eb; color: #687168; }
        .fruit-tool button:focus-visible { outline: 3px solid #a7bd95; outline-offset: 3px; }
        .fruit-tool .fruit-btn { min-height: 42px; border-radius: 9px; padding: 10px 14px; transition: background .15s, border-color .15s; }
        .fruit-tool .fruit-btn:not(:disabled):hover { background: #edf3e9; border-color: #9cae95; }
        .fruit-tool .fruit-primary:not(:disabled):hover { background: #20523b; border-color: #20523b; }
        .fruit-tool .fruit-card { padding: 18px; border: 1px solid var(--fruit-line); border-radius: 12px; background: #fff; transition: border-color .15s, background .15s; }
        .fruit-tool .fruit-card:hover { border-color: #a7b99b; }
        .fruit-tool .fruit-card.is-selected { border-color: #587547; background: #f5f8f1; box-shadow: inset 0 0 0 1px #587547; }
        .fruit-tool .fruit-card strong { font-size: 16px; }
        .fruit-tool .fruit-card .fruit-price { margin-top: 12px; font-size: 18px; font-weight: 800; color: #123d2c; font-variant-numeric: tabular-nums; }
        .fruit-tool .fruit-card .fruit-btn { width: 100%; margin-top: 16px; background: transparent; }
        .fruit-tool .fruit-empty { padding: 24px; border: 1px dashed #d4decf; border-radius: 10px; background: #fafbf8; line-height: 1.7; }
        .fruit-tool .overflow-x-auto, .fruit-tool .max-h-64 { border: 1px solid var(--fruit-line); border-radius: 10px; }
        .fruit-tool th { padding: 14px 16px; background: #f2f5ee; font-size: 11px; }
        .fruit-tool td { padding: 15px 16px; font-size: 13px; font-variant-numeric: tabular-nums; }
        .fruit-tool tbody tr:last-child td { border-bottom: 0; }
        .fruit-tool tbody tr:hover { background: #fafbf8; }
        .fruit-tool .fruit-status { display: inline-block; padding: 5px 9px; border-radius: 6px; background: #f1f5ed; font-size: 11px; white-space: nowrap; }
        .fruit-tool dl > div { padding: 14px; border-radius: 10px; background: #f6f8f3; }
        .fruit-tool .fruit-parameter-dialog { border-radius: 16px; }
        @media (max-width: 639px) {
            .fruit-tool .fruit-hero { padding: 22px 18px; border-radius: 14px; }
            .fruit-tool .fruit-panel { padding: 18px 14px; border-radius: 12px; }
            .fruit-tool .fruit-navigation { width: 100%; }
            .fruit-tool .fruit-tab { flex: 1; padding: 11px 10px; font-size: 12px; }
            .fruit-tool .fruit-hero-controls { width: 100%; }
            .fruit-tool .fruit-hero-controls label { flex: 1; }
            .fruit-tool .fruit-section-heading { margin-bottom: 16px; }
        }
        @media (prefers-reduced-motion: reduce) { .fruit-tool * { transition: none !important; } }
        .fruit-tool .fruit-price-summary { display: flex; flex-direction: column; }
        .fruit-tool .fruit-price-summary > .flex h3 { min-width: 0; }
        .fruit-tool .fruit-price-summary .fruit-count { max-width: 50%; white-space: normal; overflow-wrap: anywhere; }
        .fruit-tool .fruit-sparkline { margin: 18px 0 12px; height: 64px; display: flex; align-items: center; border-bottom: 1px solid #e1e7df; }
        .fruit-tool .fruit-sparkline svg { width: 100%; height: 56px; }
        .fruit-tool .fruit-price-dialog { width: min(48rem, calc(100% - 2rem)); max-height: calc(100dvh - 2rem); margin: auto; padding: 0; border: 0; border-radius: 16px; overflow-y: auto; color: #1c3328; box-shadow: 0 24px 80px #0a291e40; }
        .fruit-tool .fruit-price-dialog::backdrop { background: rgb(10 41 30 / .6); backdrop-filter: blur(3px); }
        .fruit-tool .fruit-price-dialog > .fruit-panel { border: 0; border-radius: 0; margin: 0; }
    </style>
    <div class="fruit-hero flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-xs font-semibold text-[#687168]">Perencanaan menu MBG</p><h1 class="sbg-heading mt-1 text-2xl font-extrabold text-[#1c3328]">Kalkulator Buah Harian</h1><p class="fruit-subtitle">Atur anggaran dan porsi, bandingkan pilihan buah, lalu simpan keputusan menu harian.</p></div>
        <div class="fruit-hero-controls flex items-end gap-2">
            @if(auth()->user()->isSuperAdmin())
                <label>Wilayah<select x-model="region" @change="changeRegion()" :disabled="busy || loading">
                    <option value="Semua Wilayah">Semua Wilayah</option>
                    @foreach($regions->pluck('name')->push('Pusat')->push(auth()->user()->region ?: 'Pusat')->unique() as $fruitRegion)
                        <option value="{{ $fruitRegion }}">{{ $fruitRegion }}</option>
                    @endforeach
                </select></label>
            @else
                <span class="fruit-count" x-text="region"></span>
            @endif
            <button type="button" class="fruit-btn" @click="refresh()" :disabled="busy || loading" title="Muat ulang data" aria-label="Muat ulang data"><span aria-hidden="true">&#8635;</span></button>
        </div>
    </div>
    <div class="fruit-navigation" role="tablist" aria-label="Kalkulator buah">
        <button id="fruit-tab-calculate" class="fruit-tab" role="tab" aria-controls="fruit-calculate" :aria-selected="tab === 'calculate'" @click="tab = 'calculate'" :disabled="busy || loading">Kalkulator</button>
        <button id="fruit-tab-master" class="fruit-tab" role="tab" aria-controls="fruit-master" :aria-selected="tab === 'master'" @click="tab = 'master'; $nextTick(() => drawChart())" :disabled="busy || loading">Katalog Buah</button>
        <button id="fruit-tab-archive" class="fruit-tab" role="tab" aria-controls="fruit-archive" :aria-selected="tab === 'archive'" @click="tab = 'archive'; loadArchive(1)" :disabled="busy || loading">Arsip Keputusan</button>
    </div>
    <div x-show="error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></div>
    <div x-show="message" role="status" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" x-text="message"></div>
    <p x-show="loading" role="status" class="text-sm text-[#687168]">Memuat data...</p>

    <div id="fruit-calculate" role="tabpanel" aria-labelledby="fruit-tab-calculate" x-show="tab === 'calculate'" class="space-y-6">
        <div class="fruit-panel">
            <div class="fruit-section-heading"><div><h2><span class="fruit-step">01</span>Rencana menu</h2><p>Isi tanggal, anggaran buah, dan jumlah penerima manfaat (PM).</p></div></div>
        <fieldset :disabled="busy || loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label>Tanggal menu<input type="date" x-model="date" required></label>
            <label>Sisa anggaran buah (Rp)<input type="text" x-model="budgetInput" @input="budget = readMoney($event.target); budgetInput = $event.target.value" inputmode="decimal" placeholder="0" required></label>
            <label>PM Porsi Besar (PB)<input type="number" x-model="pb" min="0" max="1000000" step="1" inputmode="numeric" placeholder="0" required></label>
            <label>PM Porsi Kecil (PK)<input type="number" x-model="pk" min="0" max="1000000" step="1" inputmode="numeric" placeholder="0" required></label>
        </fieldset>
        </div>
        <div class="fruit-panel">
            <div class="fruit-section-heading"><div><h2><span class="fruit-step">02</span>Pilih kandidat buah</h2><p>Pilih buah dari katalog untuk dibandingkan.</p></div><div class="flex items-center gap-3"><span class="fruit-count" x-text="selectedIds.length + ' buah dipilih'"></span><button class="fruit-btn fruit-primary" @click="addFruit()" :disabled="busy || loading || region === 'Semua Wilayah'">Tambah buah</button></div></div>
            <p x-show="loaded && !fruits.length" class="fruit-empty text-sm text-[#687168]">Katalog buah masih kosong. Klik Tambah buah untuk mengisi katalog.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="fruit in fruits" :key="fruit.id">
                    <div class="fruit-card" :class="{ 'is-selected': selectedIds.includes(fruit.id) }">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input class="mt-1" type="checkbox" :value="fruit.id" x-model.number="selectedIds" :disabled="busy || loading || (!selectedIds.includes(fruit.id) && selectedIds.length >= 100)">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-sm text-[#1c3328]" x-text="fruit.name"></strong>
                                <span class="mt-1 block text-xs text-[#687168]" x-text="fruit.source_region"></span>
                                <span class="fruit-price block" x-text="fruit.price ? money(fruit.price) + ' / kg' : 'Harga per kg belum diisi'"></span>
                                <span class="mt-1 block text-xs font-normal" x-text="ready(fruit) ? number(fruit.units_per_kg, 4) + ' ' + fruit.unit.toLowerCase() + '/kg | PB ' + number(fruit.portion_pb, 4) + ' | PK ' + number(fruit.portion_pk, 4) : 'Parameter porsi belum lengkap'"></span>
                                <span x-show="increase(fruit) > 15" class="mt-2 block text-xs text-rose-700" x-text="'Harga naik ' + number(increase(fruit), 1) + '% dari rata-rata harga 7 hari sebelumnya'"></span>
                            </span>
                        </label>
                        <button class="fruit-btn mt-3" @click="edit(fruit)" :disabled="busy || loading">Edit buah</button>
                    </div>
                </template>
            </div>
        </div>
        <template x-if="editor">
            <dialog x-ref="parameterDialog" class="fruit-parameter-dialog shadow-xl" aria-labelledby="fruit-parameter-title" @cancel.prevent="closeEditor()" @click="if ($event.target === $el) closeEditor()">
            <form @submit.prevent="saveFruit()" class="bg-white p-5 sm:p-6">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <h3 id="fruit-parameter-title" class="min-w-0 break-words text-lg font-bold text-[#1c3328]" x-text="editor.id ? 'Edit buah' : 'Tambah buah'"></h3>
                    <button type="button" @click="closeEditor()" :disabled="busy" class="fruit-btn shrink-0" aria-label="Tutup parameter porsi" title="Tutup">&#215;</button>
                </div>
                <p x-show="error" role="alert" class="mb-4 rounded-md bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></p>
                <fieldset :disabled="busy || loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label>Nama buah<input x-model="editor.name" maxlength="255" required></label>
                    <label>Satuan porsi<select x-model="editor.unit"><option>Butir</option><option>Sisir</option><option>Potong</option><option>Buah</option></select></label>
                    <label>Estimasi isi per kg<input type="number" x-model="editor.units_per_kg" min="0.0001" max="100000" step="0.0001" required></label>
                    <label>Standar porsi PB<input type="number" x-model="editor.portion_pb" min="0.0001" max="1000" step="0.0001" required></label>
                    <label>Standar porsi PK<input type="number" x-model="editor.portion_pk" min="0.0001" max="1000" step="0.0001" required></label>
                    <label>Harga pasar per kg (Rp)<input type="text" x-model="priceInput" @input="editor.price = readMoney($event.target); priceInput = $event.target.value" inputmode="decimal" required></label>
                </fieldset>
                <p x-show="increase(editor) > 15" class="mt-3 text-sm text-rose-700" x-text="'Kenaikan ' + number(increase(editor), 1) + '% dari rata-rata 7 hari sebelumnya (' + money(editor.previous_week_average) + ').'"></p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="fruit-btn" @click="closeEditor()" :disabled="busy">Batal</button><button type="submit" class="fruit-btn fruit-primary" :disabled="busy || loading" x-text="busy ? 'Menyimpan...' : 'Simpan'"></button></div>
            </form>
            </dialog>
        </template>
        <div class="fruit-panel">
            <div class="fruit-section-heading"><div><h2><span class="fruit-step">03</span>Perbandingan kebutuhan</h2><p>Bandingkan biaya dan sisa anggaran, lalu pilih buah untuk menu harian.</p></div></div>
            <p x-show="!validParameters" class="fruit-empty text-sm text-[#687168]">Tanggal, anggaran, dan jumlah PM belum lengkap atau tidak valid. Total PM minimal 1.</p>
            <p x-show="validParameters && !selectedIds.length" class="fruit-empty text-sm text-[#687168]">Belum ada kandidat dipilih.</p>
            <p x-show="incompleteSelection" class="py-3 text-sm text-amber-800">Parameter porsi atau harga kandidat terpilih belum lengkap.</p>
            <template x-if="results.length">
                <div class="space-y-3">
                    <p class="rounded-md p-3 text-sm" :class="recommendation ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'" x-text="recommendation ? 'Biaya terendah: ' + recommendation.name + ' - ' + money(recommendation.cost) : 'Semua kandidat melebihi anggaran.'"></p>
                    <div class="overflow-x-auto"><table><thead><tr><th>Buah</th><th>Kebutuhan porsi</th><th>Kebutuhan (kg)</th><th>Total biaya</th><th>Sisa / kurang</th><th>Status</th><th></th></tr></thead><tbody>
                        <template x-for="fruit in results" :key="fruit.id"><tr><td class="font-bold" x-text="fruit.name + (region === 'Semua Wilayah' ? ' · ' + fruit.source_region : '')"></td><td class="whitespace-nowrap" x-text="number(fruit.portions, 4) + ' ' + fruit.unit"></td><td x-text="number(fruit.kg, 6)"></td><td class="whitespace-nowrap" x-text="money(fruit.cost)"></td><td class="whitespace-nowrap" :class="fruit.within_budget ? 'text-emerald-700' : 'text-rose-700'" x-text="money(fruit.remaining)"></td><td><span class="fruit-status font-semibold" :class="fruit.within_budget ? 'text-emerald-700' : 'text-rose-700'" x-text="fruit.within_budget ? 'Masuk Anggaran' : 'Melebihi Anggaran'"></span></td><td><button class="fruit-btn fruit-primary" @click="choose(fruit)" :disabled="busy || loading || !fruit.within_budget" :aria-label="'Pilih ' + fruit.name">Pilih</button></td></tr></template>
                    </tbody></table></div>
                </div>
            </template>
        </div>
    </div>

    <div id="fruit-master" role="tabpanel" aria-labelledby="fruit-tab-master" x-show="tab === 'master'" class="space-y-6">
        <div class="fruit-section-heading"><div><h2>Katalog buah</h2><p>Isi buah, harga per kg, dan porsi untuk digunakan di kalkulator.</p></div><button class="fruit-btn fruit-primary" @click="addFruit()" :disabled="busy || loading || region === 'Semua Wilayah'">Tambah buah</button></div>
        <p x-show="region === 'Semua Wilayah'" class="text-sm text-[#687168]">Pilih satu wilayah untuk menambahkan buah.</p>
        <p x-show="loaded && !fruits.length" class="fruit-empty text-sm text-[#687168]">Katalog buah masih kosong. Tambahkan buah pertama Anda.</p>
        <div x-show="fruits.length" class="fruit-panel overflow-x-auto"><table>
            <thead><tr><th>Buah</th><th>Wilayah</th><th>Satuan porsi</th><th>Isi/kg</th><th>Porsi PB / PK</th><th>Harga/kg</th><th>Aksi</th></tr></thead>
            <tbody><template x-for="fruit in fruits" :key="fruit.id"><tr>
                <td class="font-bold" x-text="fruit.name"></td><td x-text="fruit.source_region"></td><td x-text="fruit.unit"></td>
                <td x-text="number(fruit.units_per_kg, 4)"></td><td x-text="number(fruit.portion_pb, 4) + ' / ' + number(fruit.portion_pk, 4)"></td>
                <td class="whitespace-nowrap" x-text="money(fruit.price)"></td><td><div class="flex gap-2"><button class="fruit-btn" @click="edit(fruit)" :disabled="busy || loading">Edit</button><button class="fruit-btn" @click="openPriceDetail(fruit)" :disabled="busy || loading">Riwayat harga</button></div></td>
            </tr></template></tbody>
        </table></div>
        <dialog id="fruit-price-detail" x-ref="priceDetail" class="fruit-price-dialog" aria-labelledby="fruit-price-title" @cancel.prevent="closePriceDetail()" @click="if ($event.target === $el) closePriceDetail()">
            <div class="fruit-panel space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4"><div><h2 id="fruit-price-title" class="text-base font-bold" x-text="'Riwayat harga ' + (historyFruit?.name || '')"></h2><p class="mt-1 text-xs text-[#687168]" x-text="(historyFruit?.source_region || '') + ' · Harga per ' + 'kg'"></p></div><div class="flex items-end gap-3"><label>Periode<select x-model="days" @change="loadHistory()"><option value="7">7 hari</option><option value="30">30 hari</option><option value="90">90 hari</option></select></label><button class="fruit-btn" @click="closePriceDetail()" aria-label="Tutup riwayat harga">Tutup</button></div></div>
            <p x-show="error" role="alert" class="rounded-md bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></p>
            <p x-show="historyLoading" class="text-sm text-[#687168]">Memuat riwayat...</p>
            <p x-show="!history.length && !historyLoading" class="text-sm text-[#687168]" x-text="historyId ? 'Tidak ada pencatatan harga pada periode ini.' : 'Belum ada buah dipilih.'"></p>
            <div x-show="history.length" class="min-w-0 bg-white py-3"><canvas x-ref="priceChart" class="block h-[240px] w-full" role="img" aria-label="Grafik perubahan harga buah dalam rupiah"></canvas></div>
            <div x-show="history.length" class="max-h-64 overflow-auto"><table><thead><tr><th>Waktu pencatatan</th><th x-text="'Harga per ' + 'kg'"></th></tr></thead><tbody><template x-for="(point, index) in history" :key="index"><tr><td x-text="stamp(point.created_at)"></td><td x-text="money(point.price)"></td></tr></template></tbody></table></div>
        </div>
        </dialog>
    </div>

    <div id="fruit-archive" role="tabpanel" aria-labelledby="fruit-tab-archive" x-show="tab === 'archive'" class="fruit-panel space-y-6">
        <h2 class="text-base font-bold text-[#1c3328]">Arsip Keputusan Harian</h2>
        <p x-show="!archive.length && !loading" class="text-sm text-[#687168]">Belum ada keputusan tersimpan.</p>
        <div x-show="archive.length" class="overflow-x-auto"><table><thead><tr><th>No.</th><th>Tanggal menu</th><th>Buah dipilih</th><th>Total biaya</th><th>Penyusun</th><th></th></tr></thead><tbody><template x-for="entry in archive" :key="entry.id"><tr><td x-text="'#' + entry.id"></td><td class="whitespace-nowrap" x-text="entry.decision_date"></td><td class="font-bold" x-text="entry.snapshot.selected.name"></td><td class="whitespace-nowrap" x-text="money(entry.snapshot.selected.cost)"></td><td x-text="entry.snapshot.author"></td><td><button class="fruit-btn" @click="openReport(entry.id)" :disabled="busy || loading">Lihat laporan</button></td></tr></template></tbody></table></div>
        <div x-show="lastPage > 1" class="flex items-center justify-end gap-3 text-xs"><button class="fruit-btn" @click="loadArchive(page - 1)" :disabled="page <= 1 || loading || busy" aria-label="Halaman sebelumnya">&larr;</button><span x-text="page + ' / ' + lastPage"></span><button class="fruit-btn" @click="loadArchive(page + 1)" :disabled="page >= lastPage || loading || busy" aria-label="Halaman berikutnya">&rarr;</button></div>
        <template x-if="report"><div class="space-y-4 border-t border-[#dce1d6] pt-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-lg font-bold" x-text="'Laporan #' + report.id"></h3><div class="flex gap-2"><button class="fruit-btn" @click="exportReport('pdf')" :disabled="exporting"><span aria-hidden="true">&#8595;</span> PDF</button><button class="fruit-btn" @click="exportReport('xlsx')" :disabled="exporting"><span aria-hidden="true">&#8595;</span> Excel</button></div></div>
            <p x-show="exporting" role="status" class="text-xs text-[#687168]">Menyiapkan laporan...</p>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-[#687168]">Tanggal menu</dt><dd class="mt-1 font-semibold" x-text="report.snapshot.decision_date"></dd></div>
                <div><dt class="text-xs text-[#687168]">Waktu keputusan</dt><dd class="mt-1 font-semibold" x-text="stamp(report.snapshot.recorded_at)"></dd></div>
                <div><dt class="text-xs text-[#687168]">Penyusun / wilayah</dt><dd class="mt-1 break-words font-semibold" x-text="report.snapshot.author + ' / ' + report.snapshot.region"></dd></div>
                <div><dt class="text-xs text-[#687168]">PM PB / PK</dt><dd class="mt-1 font-semibold" x-text="number(report.snapshot.pb) + ' / ' + number(report.snapshot.pk)"></dd></div>
                <div><dt class="text-xs text-[#687168]">Anggaran</dt><dd class="mt-1 font-semibold" x-text="money(report.snapshot.budget)"></dd></div>
                <div><dt class="text-xs text-[#687168]">Buah dipilih</dt><dd class="mt-1 break-words font-semibold" x-text="report.snapshot.selected.name"></dd></div>
                <div><dt class="text-xs text-[#687168]">Total biaya</dt><dd class="mt-1 font-semibold" x-text="money(report.snapshot.selected.cost)"></dd></div>
                <div><dt class="text-xs text-[#687168]">Sisa anggaran</dt><dd class="mt-1 font-semibold text-emerald-700" x-text="money(report.snapshot.selected.remaining)"></dd></div>
            </dl>
            <div class="overflow-x-auto"><table><thead><tr><th>Kandidat</th><th>Isi/kg</th><th>Porsi PB / PK</th><th>Harga/kg</th><th>Kebutuhan</th><th>Kg</th><th>Biaya</th><th>Status</th></tr></thead><tbody><template x-for="fruit in report.snapshot.candidates" :key="fruit.id"><tr><td x-text="fruit.name" class="font-bold"></td><td x-text="number(fruit.units_per_kg, 4)"></td><td x-text="number(fruit.portion_pb, 4) + ' / ' + number(fruit.portion_pk, 4)"></td><td class="whitespace-nowrap" x-text="money(fruit.price)"></td><td class="whitespace-nowrap" x-text="number(fruit.portions, 4) + ' ' + fruit.unit"></td><td x-text="number(fruit.kg, 6)"></td><td class="whitespace-nowrap" x-text="money(fruit.cost)"></td><td :class="fruit.within_budget ? 'text-emerald-700' : 'text-rose-700'" x-text="fruit.within_budget ? 'Masuk Anggaran' : 'Melebihi Anggaran'"></td></tr></template></tbody></table></div>
            <div class="grid grid-cols-2 gap-8 py-6 text-sm"><div><p>Disusun oleh,</p><p class="mt-16 break-words font-semibold" x-text="report.snapshot.author"></p></div><div><p>Disetujui oleh,</p><div class="mt-16 w-full max-w-48 border-b border-[#687168]" aria-label="Kolom tanda tangan persetujuan"></div></div></div>
        </div></template>
    </div>
</section>
