document.addEventListener('alpine:init', () => {
    Alpine.data('fruitCalculator', (config) => ({
        region: config.region, tab: 'calculate', loaded: false, loading: false, busy: false,
        error: '', message: '', fruits: [], selectedIds: [], budget: '', budgetInput: '', priceInput: '', pb: '', pk: '',
        date: new Date().toLocaleDateString('en-CA'), editor: null,
        historyId: '', days: 7, history: [], historyLoading: false, historyRequest: 0, priceDetailOpen: false, priceDetailOverflow: '',
        archive: [], page: 1, lastPage: 1, report: null, pendingDecision: null, exporting: false,
        init() {
            this.$nextTick(() => {
                const observer = new ResizeObserver(() => this.drawChart());
                observer.observe(this.$refs.priceChart.parentElement);
                this.stopChartObserver = () => observer.disconnect();
            });
        },
        destroy() {
            this.stopChartObserver?.();
            if (this.priceDetailOpen) this.closePriceDetail();
            if (this.editor) document.body.style.overflow = this.editorOverflow || '';
        },
        ready(fruit) { return ['units_per_kg', 'portion_pb', 'portion_pk', 'price'].every(key => Number(fruit[key]) > 0); },
        get incompleteSelection() { return this.fruits.some(f => this.selectedIds.includes(f.id) && !this.ready(f)); },
        number(value, digits = 2) { return Number(value).toLocaleString('id-ID', { maximumFractionDigits: digits }); },
        money(value) { return 'Rp ' + this.number(value); },
        readMoney(input) {
            const formatted = formatCurrencyInput(input);
            return formatted === '' ? '' : formatted.replace(/\./g, '').replace(',', '.');
        },
        stamp(value) { return new Date(value).toLocaleString('id-ID'); },
        async api(path = '', method = 'GET', data = null) {
            const url = new URL(config.base + path, location.origin);
            url.searchParams.set('region', this.region);
            const response = await fetch(url, {
                method, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                ...(data ? { body: JSON.stringify(data) } : {}),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat().join(' ') : body.message;
                throw new Error(response.status === 419 ? 'Sesi berakhir. Muat ulang halaman dan masuk kembali.' : (message || 'Permintaan gagal. Coba lagi.'));
            }
            return body;
        },
        async load() {
            this.loading = true; this.error = '';
            try {
                const data = await this.api(); this.fruits = data.fruits;
                this.selectedIds = this.selectedIds.filter(id => this.fruits.some(f => f.id === id));
                this.loaded = true;
            } catch (error) { this.error = error.message; }
            finally { this.loading = false; }
        },
        async changeRegion() {
            if (this.priceDetailOpen) this.closePriceDetail();
            this.selectedIds = []; this.editor = null; this.report = null;
            this.historyId = ''; this.history = []; this.historyRequest++; this.archive = []; this.pendingDecision = null;
            this.message = ''; await this.load();
            if (this.tab === 'archive') await this.loadArchive(1);
        },
        async refresh() {
            await this.load();
            if (this.tab === 'archive') await this.loadArchive(this.page);
            if (this.historyId) await this.loadHistory();
        },
        get validParameters() {
            return this.budget !== '' && Number.isFinite(Number(this.budget)) && Number(this.budget) >= 0 && Number(this.budget) <= 1e12
                && Math.abs(Number(this.budget) * 100 - Math.round(Number(this.budget) * 100)) < 0.00001
                && [this.pb, this.pk].every(n => n !== '' && Number.isInteger(Number(n)) && Number(n) >= 0 && Number(n) <= 1000000)
                && Number(this.pb) + Number(this.pk) > 0 && /^\d{4}-\d{2}-\d{2}$/.test(this.date);
        },
        get results() {
            if (!this.validParameters || this.incompleteSelection) return [];
            return this.fruits.filter(f => this.selectedIds.includes(f.id)).map(f => {
                const portions = Number(this.pb) * Number(f.portion_pb) + Number(this.pk) * Number(f.portion_pk);
                const kg = portions / Number(f.units_per_kg);
                const cost = Math.round((kg * Number(f.price) + Number.EPSILON) * 100) / 100;
                return { ...f, portions, kg, cost, remaining: Number(this.budget) - cost, within_budget: cost <= Number(this.budget) };
            }).sort((a, b) => a.cost - b.cost || a.name.localeCompare(b.name));
        },
        get recommendation() { return this.results.find(f => f.within_budget); },
        increase(fruit) {
            const average = Number(fruit.previous_week_average);
            return average > 0 ? ((Number(fruit.price) / average) - 1) * 100 : null;
        },
        addFruit() {
            this.edit({ name: '', unit: 'Buah', units_per_kg: '', portion_pb: '', portion_pk: '', price: '' });
        },
        edit(fruit) {
            this.error = ''; this.message = ''; this.editor = { ...fruit }; this.priceInput = formatCurrencyValue(fruit.price); this.tab = 'calculate';
            this.editorOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                const dialog = this.$refs.parameterDialog;
                dialog.showModal();
                dialog.querySelector('input')?.focus();
            });
        },
        closeEditor(saved = false) {
            if (this.busy && !saved) return;
            this.$refs.parameterDialog?.close();
            document.body.style.overflow = this.editorOverflow || '';
            this.editor = null;
        },
        async saveFruit() {
            this.busy = true; this.error = ''; this.message = '';
            try {
                const draft = { ...this.editor };
                await this.api(draft.id ? '/fruits/' + draft.id : '/fruits', draft.id ? 'PUT' : 'POST', draft);
                this.closeEditor(true); this.message = 'Katalog buah tersimpan.'; await this.load();
                if (this.historyId) await this.loadHistory();
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async choose(fruit) {
            if (this.busy || !this.validParameters || !fruit.within_budget) return;
            const payload = {
                decision_date: this.date, budget: Number(this.budget), pb: Number(this.pb), pk: Number(this.pk),
                selected_id: fruit.id, candidates: this.results.map(f => ({ id: f.id, version: f.version })),
            };
            const signature = JSON.stringify({ region: this.region, ...payload });
            if (!this.pendingDecision || this.pendingDecision.signature !== signature) this.pendingDecision = { signature, key: crypto.randomUUID() };
            this.busy = true; this.error = ''; this.message = '';
            try {
                this.report = await this.api('/decisions', 'POST', { ...payload, request_key: this.pendingDecision.key });
                this.message = 'Keputusan tersimpan sebagai arsip #' + this.report.id + '.';
                this.tab = 'archive'; await this.loadArchive(1);
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async loadArchive(page = 1) {
            this.loading = true; this.error = '';
            try {
                const result = await this.api('/decisions?page=' + page);
                this.archive = result.data; this.page = result.current_page; this.lastPage = result.last_page;
            } catch (error) { this.error = error.message; }
            finally { this.loading = false; }
        },
        async openReport(id) {
            this.busy = true; this.error = '';
            try { this.report = await this.api('/decisions/' + id); }
            catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        get historyFruit() { return this.fruits.find(fruit => String(fruit.id) === String(this.historyId)); },
        async openPriceDetail(card) {
            this.historyId = String(card.id);
            this.history = [];
            this.priceDetailOverflow = document.body.style.overflow;
            this.priceDetailOpen = true;
            document.body.style.overflow = 'hidden';
            await this.$nextTick();
            this.$refs.priceDetail.showModal();
            this.$refs.priceDetail.querySelector('button').focus();
            await this.loadHistory();
        },
        closePriceDetail() {
            this.$refs.priceDetail?.close();
            if (this.priceDetailOpen) document.body.style.overflow = this.priceDetailOverflow;
            this.priceDetailOpen = false;
            this.historyId = ''; this.history = []; this.historyRequest++; this.historyLoading = false;
        },
        async loadHistory() {
            const request = ++this.historyRequest; this.history = [];
            if (!this.historyId) { this.historyLoading = false; return; }
            this.historyLoading = true; this.error = '';
            try {
                const rows = await this.api('/fruits/' + this.historyId + '/history?days=' + this.days);
                if (request !== this.historyRequest) return;
                this.history = rows; await this.$nextTick(); this.drawChart();
            } catch (error) { if (request === this.historyRequest) this.error = error.message; }
            finally { if (request === this.historyRequest) this.historyLoading = false; }
        },
        drawChart() {
            const canvas = this.$refs.priceChart;
            if (!canvas || !this.history.length) return;
            const width = canvas.parentElement.clientWidth;
            if (!width) return;
            const height = 240, dpr = window.devicePixelRatio || 1;
            canvas.width = width * dpr; canvas.height = height * dpr;
            const ctx = canvas.getContext('2d'); ctx.scale(dpr, dpr);
            const values = this.history.map(row => Number(row.price));
            const min = Math.min(...values) * 0.9, max = Math.max(...values) * 1.1;
            const left = 78, right = width - 16, top = 20, bottom = 207;
            ctx.font = '11px sans-serif'; ctx.textAlign = 'right';
            for (let i = 0; i <= 4; i++) {
                const y = top + (bottom - top) * i / 4;
                ctx.fillStyle = '#687168'; ctx.fillText(this.number(max - (max - min) * i / 4, 0), left - 8, y + 4);
                ctx.strokeStyle = '#e1e6e2'; ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(right, y); ctx.stroke();
            }
            const times = this.history.map(row => new Date(row.created_at.replace(' ', 'T')).getTime());
            const first = Math.min(...times), last = Math.max(...times);
            const points = values.map((value, i) => [last === first ? (left + right) / 2 : left + (right - left) * (times[i] - first) / (last - first), bottom - (value - min) / (max - min || 1) * (bottom - top)]);
            ctx.strokeStyle = '#267c66'; ctx.lineWidth = 2; ctx.beginPath();
            points.forEach(([x, y], i) => i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); ctx.stroke();
            ctx.fillStyle = '#267c66'; points.forEach(([x, y]) => { ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fill(); });
            ctx.fillStyle = '#687168'; ctx.textAlign = 'left'; ctx.fillText(new Date(first).toLocaleDateString('id-ID'), left, 232);
            ctx.textAlign = 'right'; ctx.fillText(new Date(last).toLocaleDateString('id-ID'), right, 232);
        },
        async exportReport(type) {
            if (this.exporting) return;
            this.error = ''; this.exporting = true;
            try {
                const s = this.report.snapshot;
                const headings = ['Buah', 'Satuan', 'Isi/kg', 'Porsi PB', 'Porsi PK', 'Harga/kg', 'Kebutuhan', 'Kg', 'Biaya', 'Sisa anggaran', 'Status'];
                const rows = s.candidates.map(f => [f.name, f.unit, f.units_per_kg, f.portion_pb, f.portion_pk, f.price, f.portions, f.kg, f.cost, f.remaining, f.within_budget ? 'Masuk Anggaran' : 'Melebihi Anggaran']);
                const metadata = [
                    ['No. arsip', this.report.id], ['Tanggal menu', s.decision_date], ['Waktu keputusan', s.recorded_at],
                    ['Penyusun', s.author], ['Wilayah', s.region], ['Anggaran (Rp)', s.budget],
                    ['PM PB', s.pb], ['PM PK', s.pk], ['Buah dipilih', s.selected.name],
                    ['Kebutuhan (kg)', s.selected.kg], ['Total biaya (Rp)', s.selected.cost], ['Sisa anggaran (Rp)', s.selected.remaining],
                ];
                const filename = 'Keputusan-Buah-' + s.decision_date + '-' + this.report.id;
                if (type === 'pdf') {
                    if (!window.jspdf) throw new Error('PDF belum siap. Muat ulang halaman dan coba lagi.');
                    const doc = new window.jspdf.jsPDF({ orientation: 'landscape' });
                    doc.setFontSize(16); doc.text('Laporan Keputusan Buah Harian MBG', 14, 17);
                    doc.autoTable({ startY: 24, body: metadata.map(([label, value]) => [label, label.includes('(Rp)') ? this.number(value) : value]), theme: 'plain', styles: { fontSize: 9, cellPadding: 1 }, columnStyles: { 0: { cellWidth: 50 } } });
                    doc.autoTable({ startY: doc.lastAutoTable.finalY + 7, head: [headings], body: rows.map(row => row.map((value, index) => [5, 8, 9].includes(index) ? this.number(value) : value)), styles: { fontSize: 8 }, headStyles: { fillColor: [18, 61, 44] } });
                    let y = doc.lastAutoTable.finalY + 14;
                    if (y > 160) { doc.addPage(); y = 22; }
                    doc.setFontSize(10); doc.text('Disusun oleh,', 18, y); doc.text('Disetujui oleh,', 180, y);
                    doc.text(s.author, 18, y + 28, { maxWidth: 130 }); doc.text('________________________', 180, y + 28); doc.save(filename + '.pdf');
                } else {
                    if (!window.ExcelJS) {
                        await new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            const fail = () => { clearTimeout(timer); script.remove(); reject(new Error('Library Excel gagal dimuat. Coba unduh kembali.')); };
                            const timer = setTimeout(fail, 15000);
                            script.src = 'https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js';
                            script.onload = () => { clearTimeout(timer); resolve(); };
                            script.onerror = fail;
                            document.head.append(script);
                        });
                    }
                    const workbook = new ExcelJS.Workbook(); const sheet = workbook.addWorksheet('Keputusan Buah');
                    sheet.addRow(['Laporan Keputusan Buah Harian MBG']);
                    metadata.forEach(row => sheet.addRow(row)); sheet.addRow([]); sheet.addRow(headings); rows.forEach(row => sheet.addRow(row));
                    sheet.addRow([]); sheet.addRow(['Disusun oleh', s.author, '', 'Disetujui oleh']);
                    sheet.addRow([]); sheet.addRow(['Tanda tangan', '________________', '', '________________']);
                    sheet.columns.forEach(column => { column.width = 22; });
                    sheet.getRow(1).font = { bold: true, size: 16 }; sheet.getRow(metadata.length + 3).font = { bold: true };
                    const buffer = await workbook.xlsx.writeBuffer();
                    const url = URL.createObjectURL(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
                    const link = document.createElement('a'); link.href = url; link.download = filename + '.xlsx'; link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }
            } catch (error) { this.error = error.message; }
            finally { this.exporting = false; }
        },
    }));
});
