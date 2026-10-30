/* One presentation calculation path for both Add and Edit. Financial totals stay server-owned. */
(function (root) {
    const prices = ['supplier_price', 'supplier_price_b', 'maker_price', 'partner_margin'];
    function sbgPoForm(mode, seed = {}, errors = {}) {
        return {
            mode, draft: {}, errors, busy: false, message: '', baseline: '',
            load(record) {
                this.draft = { ...record };
                this.draft.transaction_date = String(record.transaction_date || '').slice(0, 10);
                this.draft.quantity_sbg = record.quantity_sbg ?? record.quantity ?? 0;
                this.draft.quantity_b = record.quantity_b ?? 0;
                prices.forEach(field => { this.draft[field] = root.formatCurrencyValue(field === 'supplier_price_b' ? (record[field] ?? record.supplier_price) : record[field]); });
                this.baseline = JSON.stringify(this.draft);
            },
            init() {
                // Alpine starts before DOMContentLoaded. Keep the server marker for
                // compatibility, but opt out of sbg-v2's legacy create-only validator.
                this.$el.querySelector('[data-po-form]')?.removeAttribute('data-sbg-create-form');
                this.load(seed);
                if (this.errors.quantity && !this.errors.quantity_sbg) this.errors.quantity_sbg = this.errors.quantity;
                if (Object.keys(this.errors).length) this.message = 'Periksa kolom yang ditandai.';
                if (mode === 'edit') this.$watch('editPo', value => { this.load(value); this.errors = {}; this.message = ''; });
            },
            dirty() { return JSON.stringify(this.draft) !== this.baseline; },
            requestClose() {
                if (this.busy) return;
                if (this.dirty() && !root.confirm('Perubahan belum disimpan. Tutup dan buang perubahan?')) return;
                if (this.mode === 'edit') {
                    this.isEditModalOpen = false;
                    this.poEditRequest++;
                    this.$nextTick(() => {
                        const target = this.poEditReturnFocus;
                        if (target?.isConnected && target.getClientRects().length) target.focus({preventScroll:true});
                    });
                }
                else {
                    this.load(seed);
                    this.errors = {};
                    this.message = '';
                    this.isCreateModalOpen = false;
                }
            },
            formatPriceInput(input) {
                const raw = input.value.replace(/[^\d,]/g, '');
                const [whole, ...decimals] = raw.split(',');
                const integer = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                input.value = integer + (raw.includes(',') ? ',' + decimals.join('').slice(0, 2) : '');
                return input.value;
            },
            focusError() {
                this.$nextTick(() => {
                    const target = this.$el.querySelector('[aria-invalid="true"]:not(:disabled)') || this.$el.querySelector('[data-po-message]');
                    target?.focus();
                });
            },
            async submit(event) {
                if (this.busy) return;
                const form = event.target;
                this.message = '';
                this.errors = {};
                for (const field of form.querySelectorAll('input:not([type=hidden]), select, textarea')) {
                    if (!field.disabled && !field.checkValidity()) this.errors[field.name] = [field.validationMessage || 'Periksa kembali kolom ini.'];
                }
                const sbg = form.elements.quantity_sbg, b = form.elements.quantity_b;
                if (sbg.value !== '' && b.value !== '' && Number(sbg.value) + Number(b.value) <= 0) this.errors.quantity_sbg = ['Jumlah kedua gudang harus lebih dari 0.'];
                if (Object.keys(this.errors).length) { this.focusError(); return; }
                // Capture enabled inputs before Alpine applies busy/fieldset disabled.
                const payload = new root.FormData(form);
                this.busy = true;
                if (this.mode === 'create') {
                    root.HTMLFormElement.prototype.submit.call(form);
                    return;
                }
                try {
                    const response = await root.fetch(form.action, { method: 'POST', body: payload, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    const result = await response.json();
                    if (response.status === 422) {
                        this.errors = result.errors || {};
                        if (this.errors.quantity && !this.errors.quantity_sbg) this.errors.quantity_sbg = this.errors.quantity;
                        this.message = 'Periksa kolom yang ditandai.';
                    }
                    else if (!response.ok) this.message = result.message || 'PO gagal diperbarui. Perubahan Anda tetap tersedia.';
                    else if (result.success) { this.baseline = JSON.stringify(this.draft); root.location.reload(); return; }
                    else this.message = 'Respons tidak valid. Periksa data sebelum mencoba kembali.';
                } catch (_) {
                    this.message = 'Koneksi terputus atau sesi berakhir. Perubahan Anda tetap tersedia; periksa status PO sebelum mencoba kembali.';
                }
                this.busy = false;
                this.focusError();
            },
            currency(value) { return Number(String(value || 0).replaceAll('.', '').replace(',', '.')) || 0; },
            warehouseValue(warehouse) { return Number(this.draft['quantity_' + warehouse] || 0) * this.currency(this.draft.maker_price); }
        };
    }
    root.sbgPoForm = sbgPoForm;
})(typeof window === 'undefined' ? globalThis : window);
