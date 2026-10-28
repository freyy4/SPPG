<div x-show="isCreateModalOpen" x-cloak class="sbg-modal sbg-v22-overlay sbg-create-overlay">
    <section @click.outside="isCreateModalOpen = false" class="sbg-modal-panel sbg-create-panel">
        <header class="sbg-v22-header">
            <span class="sbg-create-leaf" aria-hidden="true"><x-sbg.icon name="leaf" :size="32"/></span>
            <div><h2>Tambah Purchase Order</h2><p>Lengkapi informasi transaksi dan rincian harga.</p></div>
            <img class="sbg-v22-banner" src="{{ asset('images/sbg-v22/banner.webp') }}" alt="" aria-hidden="true" width="269" height="91">
            <button type="button" @click="isCreateModalOpen = false" data-dialog-close class="sbg-v22-close" aria-label="Tutup">&times;</button>
        </header>
            <form action="{{ route('dashboard.po.store') }}" method="POST" class="sbg-create-form" data-sbg-create-form data-server-errors="{{ json_encode(old('_sbg_create') === '1' ? (session('errors')?->getBag('default')->toArray() ?? []) : []) }}" novalidate>
                @csrf
                <input type="hidden" name="_sbg_create" value="1">
                <div class="sbg-create-body">

                <!-- Kelompok 1: Informasi Transaksi -->
                <section class="sbg-po-section">
                    <header class="sbg-po-section-heading"><span>1</span><div><h3>Informasi Transaksi</h3><p>Tentukan tanggal, kategori, dan prioritas purchase order.</p></div></header>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : 'md:grid-cols-3' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Tanggal</label>
                            <input type="date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Kategori</label>
                            <select name="category" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="" disabled @selected(!old('category'))>Pilih Kategori</option>
                                <option value="Karbo" @selected(old('category', 'Normal') === 'Karbo')>Karbo</option>
                                <option value="Protein Nabati" @selected(old('category', 'Normal') === 'Protein Nabati')>Protein Nabati</option>
                                <option value="Protein Hewani" @selected(old('category', 'Normal') === 'Protein Hewani')>Protein Hewani</option>
                                <option value="Sayur" @selected(old('category', 'Normal') === 'Sayur')>Sayur</option>
                                <option value="Buah" @selected(old('category', 'Normal') === 'Buah')>Buah</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Prioritas</label>
                            <select name="priority" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="Normal" @selected(old('priority', 'Normal') === 'Normal')>Normal</option>
                                <option value="Tinggi" @selected(old('priority', 'Normal') === 'Tinggi')>Tinggi</option>
                                <option value="Rendah" @selected(old('priority', 'Normal') === 'Rendah')>Rendah</option>
                            </select>
                        </div>
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Penanggung Jawab</label>
                            <select name="created_by" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="" disabled {{ old('created_by') ? '' : 'selected' }}>Pilih Penanggung Jawab</option>
                                @foreach($admins as $adminOption)
                                    <option value="{{ $adminOption->id }}" {{ (string) old('created_by', auth()->user()->isAdmin() ? auth()->id() : '') === (string) $adminOption->id ? 'selected' : '' }}>
                                        {{ $adminOption->name }} — {{ $adminOption->region ?? 'Tanpa Wilayah' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </section>

                <!-- Kelompok 2: Barang & Supplier -->
                <section class="sbg-po-section">
                    <header class="sbg-po-section-heading"><span>2</span><div><h3>Informasi Barang</h3><p>Masukkan nama barang, supplier, serta jumlah dari masing-masing gudang.</p></div></header>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : '' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Barang / PO</label>
                            <input type="text" name="item_name" value="{{ old('item_name') }}" maxlength="255" placeholder="Contoh: Muli" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Supplier</label>
                            <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" maxlength="255" placeholder="Nama Supplier" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <label class="block text-xs font-bold text-emerald-800 mb-1">Qty Gudang SBG</label>
                            <input type="number" step="0.01" min="0" max="99999999.99" name="quantity_sbg" x-model="createQuantities.sbg" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                            <p class="mt-1 text-xs text-[#687168]" x-text="'× Harga Maker = ' + formatRupiah(Number(createQuantities.sbg || 0) * Number(String(createPrices.maker_price || 0).replaceAll('.', '').replace(',', '.')))"></p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <label class="block text-xs font-bold text-blue-800 mb-1">Qty Gudang B</label>
                            <input type="number" step="0.01" min="0" max="99999999.99" name="quantity_b" x-model="createQuantities.b" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                            <p class="mt-1 text-xs text-[#687168]" x-text="'× Harga Maker = ' + formatRupiah(Number(createQuantities.b || 0) * Number(String(createPrices.maker_price || 0).replaceAll('.', '').replace(',', '.')))"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Satuan</label>
                            <input type="text" name="unit" value="{{ old('unit') }}" maxlength="50" placeholder="Kg, Pcs, Box" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                    </div>
                </section>

                <!-- Kelompok 3: Detail Harga -->
                <section class="sbg-po-section">
                    <header class="sbg-po-section-heading"><span>3</span><div><h3>Rincian Harga & Margin</h3><p>Tentukan harga supplier, harga maker, dan laba per unit.</p></div></header>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-3 items-end">
                        @if(auth()->user()->canManageFinancials())
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <label class="block text-xs font-bold text-emerald-800 mb-1">Harga Supplier Gudang SBG</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="supplier_price" placeholder="8.500" x-model="createPrices.supplier_price" @input="createPrices.supplier_price = formatCurrencyInput($event.target)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                            </div>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <label class="block text-xs font-bold text-blue-800 mb-1">Harga Supplier Gudang B</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="supplier_price_b" placeholder="8.500" x-model="createPrices.supplier_price_b" @input="createPrices.supplier_price_b = formatCurrencyInput($event.target)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                            </div>
                        </div>
                        @endif
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Harga Maker</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="maker_price" placeholder="13.500" x-model="createPrices.maker_price" @input="createPrices.maker_price = formatCurrencyInput($event.target)"
                                    @if(auth()->user()->canManageFinancials()) :disabled="!hasMinimumCurrencyDigits(createPrices.supplier_price) && !hasMinimumCurrencyDigits(createPrices.supplier_price_b)" @endif
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white disabled:bg-[#f7f7f0] disabled:text-[#687168] disabled:cursor-not-allowed">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Laba Mitra / unit</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="partner_margin" placeholder="3.000" x-model="createPrices.partner_margin" @input="createPrices.partner_margin = formatCurrencyInput($event.target)"
                                    :disabled="!hasMinimumCurrencyDigits(createPrices.maker_price)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white disabled:bg-[#f7f7f0] disabled:text-[#687168] disabled:cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Kelompok 4: Tahapan & Catatan -->
                <section class="sbg-po-section">
                    <header class="sbg-po-section-heading"><span>4</span><div><h3>Status & Catatan Tambahan</h3><p>Tentukan tahapan status purchase order.</p></div></header>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Tahapan Status</label>
                        <select name="status_stage" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                            <option value="" disabled @selected(!old('status_stage'))>Pilih Tahapan Status</option>
                            <option value="Cari Supplier" @selected(old('status_stage', 'Normal') === 'Cari Supplier')>Cari Supplier</option>
                            <option value="Sortir" @selected(old('status_stage', 'Normal') === 'Sortir')>Sortir</option>
                            <option value="Pengiriman" @selected(old('status_stage', 'Normal') === 'Pengiriman')>Pengiriman</option>
                            <option value="Selesai" @selected(old('status_stage', 'Normal') === 'Selesai')>Selesai</option>
                            <option value="Retur" @selected(old('status_stage', 'Normal') === 'Retur')>Retur</option>
                            <option value="Kekurangan" @selected(old('status_stage', 'Normal') === 'Kekurangan')>Kekurangan</option>
                            <option value="Perubahan Item" @selected(old('status_stage', 'Normal') === 'Perubahan Item')>Perubahan Item</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Catatan</label>
                        <textarea name="notes" rows="2" placeholder="Contoh: Naik 11kg 148000" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">{{ old('notes') }}</textarea>
                    </div>
                </section>
                </div>

                <footer class="sbg-create-footer">
                    <button type="button" @click="isCreateModalOpen = false" class="w-full sm:w-auto px-5 py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Batal</button>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm">Simpan PO</button>
                </footer>
            </form>
    </section>
</div>
