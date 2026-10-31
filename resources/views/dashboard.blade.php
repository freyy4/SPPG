<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEM PO</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="{{ asset('js/sbg-navigation.js') }}?v={{ filemtime(public_path('js/sbg-navigation.js')) }}"></script>
    <script src="{{ asset('js/sbg-v23.js') }}?v={{ filemtime(public_path('js/sbg-v23.js')) }}"></script>
    <script>
    function renderVerifiedQr(po) {
        const qrContainer = document.getElementById('verified-po-qr');
        const qrError = document.getElementById('verified-po-qr-error');
        if (!qrContainer) return;

        qrContainer.innerHTML = '';
        if (qrError) qrError.classList.add('hidden');

        if (typeof QRCode === 'undefined' || !po.verification_url) {
            if (qrError) qrError.classList.remove('hidden');
            return;
        }

        const verificationUrl = new URL(po.verification_url, window.location.origin).href;
        new QRCode(qrContainer, {
            text: verificationUrl,
            width: 220,
            height: 220,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });

        // QRCode.js menghasilkan canvas dan gambar. Gambar data-URL dipakai agar
        // QR tetap ikut saat isi nota disalin ke jendela cetak.
        setTimeout(() => {
            const canvas = qrContainer.querySelector('canvas');
            const image = qrContainer.querySelector('img');
            if (canvas && image) {
                image.src = canvas.toDataURL('image/png');
                image.style.display = 'block';
                canvas.style.display = 'none';
            }
        }, 50);
    }

    function createQrDataUrl(text) {
        if (typeof QRCode === 'undefined') return '';

        const container = document.createElement('div');
        new QRCode(container, {
            text,
            width: 300,
            height: 300,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.L
        });

        const canvas = container.querySelector('canvas');
        return canvas ? canvas.toDataURL('image/png') : '';
    }

    function formatCurrencyValue(value) {
        if (value === null || value === undefined || value === '') return '';

        return Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function formatCurrencyInput(input) {
        const parts = input.value.replace(/[^\d,]/g, '').split(',');
        const integer = (parts.shift() || '').replace(/^0+(?=\d)/, '');
        const decimal = parts.join('').slice(0, 2);
        const formattedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        input.value = formattedInteger + (parts.length ? ',' + decimal : '');
        return input.value;
    }

    document.addEventListener('alpine:init', () => {
        Alpine.data('partnerFeeModal', () => ({
            open: false,
            items: [],
            total: 0,
            mode: 'detail',
            selectedIds: [],
            paymentType: 'partner_fee',
            get config() {
                return {
                    partner_fee: { label: 'Fee Mitra', paid: 'is_partner_fee_paid', unit: 'partner_margin', total: 'total_partner_fee', file: 'fee-mitra' },
                    supplier: { label: 'Bayar Supplier', paid: 'is_supplier_paid', unit: 'supplier_price', total: 'total_supplier_amount', file: 'bayar-supplier' },
                    ksppg_fee: { label: 'Fee KSPPG', paid: 'is_ksppg_fee_paid', unit: 'ksppg_margin_per_unit', total: 'total_ksppg_fee', file: 'fee-ksppg' }
                }[this.paymentType];
            },
            amount(item) {
                if (this.paymentType === 'supplier') {
                    return Number(item.total_supplier_amount ?? (Number(item.quantity_sbg ?? item.quantity ?? 0) * Number(item.supplier_price || 0)));
                }
                if (this.paymentType === 'ksppg_fee') {
                    return Number(item.total_ksppg_fee ?? (Number(item.quantity_sbg ?? item.quantity ?? 0) * Number(item.ksppg_margin_per_unit || 0)));
                }
                return Number(item[this.config.total] ?? (Number(item[this.config.unit] || 0) * Number(item.quantity || 0)));
            },
            noteAmount(item) {
                return Number(item.maker_price || 0) * Number(item.quantity || 0);
            },
            noteTotal() {
                return this.selectedItems().reduce((total, item) => total + this.noteAmount(item), 0);
            },
            inputDate(item) {
                if (!item?.created_at) return '-';
                const date = new Date(item.created_at);
                return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('id-ID', {
                    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
                });
            },

            openModal(detail) {
                this.paymentType = detail.paymentType || 'partner_fee';
                this.items = (detail.items || []).filter(item => this.paymentType !== 'ksppg_fee' || Number(item.supplier_price) > 0);
                this.total = detail.total || 0;
                this.mode = detail.mode || 'detail';
                this.selectedIds = [];
                this.open = true;
            },

            selectableItems() {
                return this.items.filter(item => !item[this.config.paid]);
            },
            allSelected() {
                const ids = this.selectableItems().map(item => String(item.id));
                return ids.length > 0 && ids.every(id => this.selectedIds.includes(id));
            },
            toggleAll() {
                this.selectedIds = this.allSelected() ? [] : this.selectableItems().map(item => String(item.id));
            },
            selectedItems() {
                return this.items.filter(item => this.selectedIds.includes(String(item.id)));
            },
            selectedTotal() {
                return this.selectedItems().reduce((total, item) => total + this.amount(item), 0);
            },

            async verificationQrDataUrl(ids = [...this.selectedIds]) {
                const response = await fetch('{{ route('dashboard.payment.verification-link') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ items: ids, type: this.paymentType })
                });

                if (!response.ok) throw new Error('Gagal membuat tautan verifikasi.');

                const data = await response.json();
                const verificationUrl = new URL(data.url, window.location.origin).href;
                this.verificationUrl = verificationUrl;
                const qr = createQrDataUrl(verificationUrl);
                if (!qr) throw new Error('QR verifikasi gagal dimuat. Muat ulang halaman dan coba lagi.');
                return qr;
            },

            receiptLoading: false,
            async receiptData() {
                const items = this.selectedItems().map(item => ({ ...item, note_subtotal: this.noteAmount(item) }));
                const total = this.noteTotal();
                const label = this.paymentType === 'partner_fee' ? 'NOTA BUAH MITRA' : 'NOTA ' + this.config.label.toUpperCase();
                const qr = await this.verificationQrDataUrl(items.map(item => String(item.id)));
                return { items, total, label, qr, verificationUrl: this.verificationUrl, date: new Date().toLocaleDateString('id-ID') };
            },
            async previewSelectedNote() {
                if (!this.selectedIds.length || this.receiptLoading) return;
                this.receiptLoading = true;
                try { this.$dispatch('open-partner-receipt', await this.receiptData()); }
                catch (error) { alert(error.message + ' Silakan coba lagi.'); }
                finally { this.receiptLoading = false; }
            },
            async printSelectedNote() {
                const selected = this.selectedItems();

                if (selected.length === 0) {
                    alert('Pilih minimal satu barang yang akan dicetak.');
                    return;
                }

                const printWindow = window.open('', '_blank');
                if (!printWindow) {
                    alert('Pop-up diblokir browser. Izinkan pop-up untuk mencetak nota.');
                    return;
                }

                try {
                    const data = await this.receiptData();
                    printWindow.document.write(SbgReceipt.documentHtml(data));
                    printWindow.document.close();
                    await Promise.all([...printWindow.document.images].map(image => image.decode().catch(() => {})));
                    await new Promise(resolve => {
                        const sheet = printWindow.document.querySelector('link');
                        if (sheet.sheet) return resolve();
                        sheet.onload = resolve; sheet.onerror = resolve;
                        printWindow.setTimeout(resolve, 2500);
                    });
                    printWindow.focus();
                    printWindow.onafterprint = () => printWindow.close();
                    printWindow.print();
                } catch (error) {
                    printWindow.close(); alert(error.message);
                }
            },

            async downloadSelectedPdf() {
                const selected = this.selectedItems();

                if (selected.length === 0) {
                    alert('Pilih minimal satu barang yang akan diunduh.');
                    return;
                }

                if (!window.jspdf?.jsPDF) {
                    alert('Pembuat PDF gagal dimuat. Silakan muat ulang halaman.');
                    return;
                }

                let qrDataUrl = '';
                try {
                    qrDataUrl = await this.verificationQrDataUrl(selected.map(item => String(item.id)));
                } catch (error) {
                    alert(error.message);
                    return;
                }

                const { jsPDF } = window.jspdf;
                const documentPdf = new jsPDF({ unit: 'mm', format: 'a4' });
                const rupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
                const rows = selected.map((item, index) => {
                    const subtotal = this.noteAmount(item);
                    return [
                        index + 1,
                        `${item.item_name || '-'}\n${item.po_number || '-'}`,
                        `${item.quantity || 0} ${item.unit || ''}`,
                        rupiah(item.maker_price),
                        rupiah(subtotal)
                    ];
                });

                documentPdf.setFillColor(11, 93, 68);
                documentPdf.rect(0, 0, 210, 5, 'F');
                documentPdf.setFont('helvetica', 'bold');
                documentPdf.setFontSize(17);
                documentPdf.text('SENTRA BUAH GARUT', 105, 18, { align: 'center' });
                documentPdf.setFont('helvetica', 'normal');
                documentPdf.setFontSize(9);
                documentPdf.setTextColor(107, 114, 128);
                documentPdf.text('Jl. Ahmad Yani Timur Karangpawitan Garut', 105, 24, { align: 'center' });
                documentPdf.setTextColor(17, 24, 39);
                documentPdf.setFont('helvetica', 'normal');
                documentPdf.setFontSize(9);
                documentPdf.text(`Tanggal: ${new Date().toLocaleDateString('id-ID')}`, 196, 36, { align: 'right' });

                documentPdf.autoTable({
                    startY: 42,
                    head: [['No.', 'Item / Nomor PO', 'Qty', 'Harga', 'Subtotal']],
                    body: rows,
                    theme: 'grid',
                    styles: { font: 'helvetica', fontSize: 9, cellPadding: 3, textColor: [17, 24, 39] },
                    headStyles: { fillColor: [11, 93, 68], textColor: [255, 255, 255], fontStyle: 'bold' },
                    columnStyles: {
                        0: { cellWidth: 12, halign: 'center' },
                        2: { cellWidth: 26 },
                        3: { cellWidth: 35, halign: 'right' },
                        4: { cellWidth: 38, halign: 'right', fontStyle: 'bold' }
                    }
                });

                let totalY = documentPdf.lastAutoTable.finalY + 8;
                if (totalY > 268) { documentPdf.addPage(); totalY = 20; }
                documentPdf.setFillColor(11, 93, 68);
                documentPdf.roundedRect(14, totalY, 182, 16, 2, 2, 'F');
                documentPdf.setTextColor(255, 255, 255);
                documentPdf.setFont('helvetica', 'bold');
                documentPdf.setFontSize(9);
                documentPdf.text('TOTAL HARGA', 20, totalY + 10);
                documentPdf.setFontSize(13);
                documentPdf.text(rupiah(selected.reduce((total, item) => total + this.noteAmount(item), 0)), 191, totalY + 11, { align: 'right' });

                let qrY = totalY + 24;
                if (qrY > 245) {
                    documentPdf.addPage();
                    qrY = 20;
                }
                if (qrDataUrl) {
                    documentPdf.addImage(qrDataUrl, 'PNG', 89, qrY, 32, 32);
                }
                documentPdf.setTextColor(4, 120, 87);
                documentPdf.setFontSize(9);
                documentPdf.text('VERIFIED', 105, qrY + 38, { align: 'center' });
                documentPdf.setTextColor(107, 114, 128);
                documentPdf.setFont('helvetica', 'normal');
                documentPdf.setFontSize(7);
                documentPdf.text('Scan untuk melihat daftar barang terpilih', 105, qrY + 43, { align: 'center' });

                const fileDate = new Date().toISOString().slice(0, 10);
                documentPdf.save(`nota-${this.config.file}-${fileDate}.pdf`);
            },

            sendToWhatsApp() {
                const selected = this.selectedItems();

                if (selected.length === 0) {
                    alert('Pilih minimal satu barang yang akan dikirim.');
                    return;
                }

                const rupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
                const details = selected.map((item, index) => {
                    const amount = this.amount(item);
                    return `${index + 1}. ${item.item_name}\n` +
                        `   PO: ${item.po_number || '-'}\n` +
                        `   ${this.inputDate(item)}\n` +
                        (this.paymentType === 'supplier' ? `   Supplier: ${item.supplier_name || '-'}\n` : '') +
                        `   Qty: ${item.quantity || 0} ${item.unit || ''}\n` +
                        `   Total: ${rupiah(amount)}`;
                }).join('\n\n');

                const message = `*RINCIAN ${this.config.label.toUpperCase()}*\n\n${details}\n\n*Total: ${rupiah(this.selectedTotal())}*`;
                window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
            }
        }));

        Alpine.data('dashboardApp', () => ({
            ...window.sbgV23(),
            get masterResultCount() { return this.countMaster(); },
            navOpen: false, mobilePage: location.hash === '#data-po' ? 'orders' : (location.hash === '#master-data' ? 'admin' : 'home'),
            filterOpen: @json($errors->has('date_from') || $errors->has('date_to')),
            wideFilters: window.matchMedia('(min-width: 768px)').matches,
            desktopNav: window.matchMedia('(min-width: 1024px)').matches,
            detailLoading: false, detailError: '', detailRequest: 0,
            poView: window.matchMedia('(max-width: 767px)').matches ? 'cards' : 'table',
            activeView: location.hash === '#kalkulator-buah' ? 'fruit' : 'dashboard',
            isDetailModalOpen: false,
            isCreateModalOpen: @json(old('_sbg_create') === '1' && session('errors')?->any()),
            isEditModalOpen: @json((bool) old('_sbg_edit') && session('errors')?->any()),
            poEditLoading: false, poEditError: '', poEditRequest: 0, poEditReturnFocus: null,
            isAddAdminModalOpen: false,
            createNewRegion: false,
            isResetOwnPasswordModalOpen: false,
            isAnalysisModalOpen: false,
            isLogoutModalOpen: false, // State Logout Modal
            isLoginPopupSettingsOpen: @json(old('_popup_form') === '1'),
            popupTarget: {
                accountId: @json(old('_popup_user_id', '')),
                accountName: @json(old('_popup_user_name', '')),
                title: @json(old('title', '')),
                message: @json(old('message', '')),
                enabled: @json((bool) old('enabled', true)),
                version: ''
            },

            openLoginPopupSettings(account) { this.v23OpenPopup(account); },

            // State Modal Summary
            isSummaryModalOpen: false,
            summaryModalTitle: '',
            summaryModalItems: [],

            // State Selected PO & Edit PO
            selectedPo: {},
            editPo: {},

            allPoData: @json($purchaseOrders->items()),
            summaryPoData: @json($summaryPoData),

            openSummaryModal(type) {
                this.summaryModalTitle = type;
                
                if (type === 'Total Nilai PO') {
                    this.summaryModalItems = this.summaryPoData;
                } else if (type === 'PO Pending') {
                    this.summaryModalItems = this.summaryPoData.filter(po => po.status_stage === 'Pending');
                } else if (type === 'PO Selesai') {
                    this.summaryModalItems = this.summaryPoData.filter(po => po.status_stage === 'Selesai');
                }
                
                this.isSummaryModalOpen = true;
            },

            isEditAdminModalOpen: false,
            editAdminForm: {
                region_id: null,
                account_id: null,
                code: '',
                name: '',
                role: 'admin',
                admin_name: '',
                admin_email: ''
            },

            openEditAdminModal(region, admin) { this.v23OpenEdit(region, admin); },

            async openDetailModal(po) {
                const request = ++this.detailRequest;
                this.selectedPo = po;
                this.detailError = '';
                this.detailLoading = true;
                this.isDetailModalOpen = true;
                try {
                    const response = await fetch(`/dashboard/po/${po.id}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Detail PO gagal dimuat. Silakan coba lagi.');
                    const result = await response.json();
                    if (!result.success || result.data?.id !== po.id) throw new Error('Detail PO tidak tersedia.');
                    if (request === this.detailRequest && this.isDetailModalOpen) this.selectedPo = result.data;
                } catch (error) {
                    if (request === this.detailRequest) this.detailError = error.message;
                } finally {
                    if (request === this.detailRequest) this.detailLoading = false;
                }
            },
            shortDate(value) {
                if (!value) return '-';
                const date = new Date(value);
                return isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            },
            fruitSymbol(name) {
                const entry = Object.entries({ semangka: 'watermelon', pisang: 'banana', mangga: 'mango', alpukat: 'avocado', apel: 'apple', pear: 'pear', pir: 'pear' }).find(([word]) => String(name || '').toLowerCase().includes(word));
                return entry ? entry[1] : 'produce';
            },
            openFruitTab(tab) {
                this.activeView = 'fruit'; this.navOpen = false;
                this.$nextTick(() => this.$dispatch('fruit-navigation-request', { tab }));
            },
            navigateDashboard(page) {
                this.activeView = 'dashboard'; this.mobilePage = page; this.navOpen = false;
                history.replaceState(null, '', page === 'orders' ? '#data-po' : '#ringkasan');
                this.$nextTick(() => window.scrollTo({ top: 0 }));
            },
            focusPoSearch() {
                this.activeView = 'dashboard'; this.mobilePage = 'orders';
                this.$nextTick(() => { const field = document.getElementById('po-filter-search'); field.scrollIntoView({ block: 'center' }); field.focus(); });
            },

            async openEditModal(po) {
                // The invoking menu item/detail button becomes hidden. Return to
                // the same PO's visible menu trigger instead of that hidden child.
                this.poEditReturnFocus = [...document.querySelectorAll('.sbg-po-overflow > button')]
                    .find(button => button.getAttribute('aria-label') === 'Aksi PO ' + po.po_number && button.getClientRects().length)
                    || document.activeElement;
                this.isDetailModalOpen = false;
                const request = ++this.poEditRequest;
                this.poEditLoading = true;
                this.poEditError = '';
                this.editPo = {};
                this.isEditModalOpen = true;
                try {
                    const response = await fetch(`/dashboard/po/${po.id}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Data PO gagal dimuat. Tutup dan coba lagi.');
                    const result = await response.json();
                    if (!result.success || Number(result.data?.id) !== Number(po.id)) throw new Error('Data PO tidak tersedia.');
                    if (request === this.poEditRequest && this.isEditModalOpen) this.editPo = result.data;
                } catch (error) {
                    if (request === this.poEditRequest) this.poEditError = error.message;
                } finally {
                    if (request === this.poEditRequest) this.poEditLoading = false;
                }
            },

            toDateInputValue(dateString) {
                if (!dateString) return '';

                // Eloquent dapat mengirim date cast sebagai ISO datetime.
                // Input type="date" hanya menerima format YYYY-MM-DD.
                const match = String(dateString).match(/^\d{4}-\d{2}-\d{2}/);
                return match ? match[0] : '';
            },

            hasMinimumCurrencyDigits(value) {
                const integerPart = String(value ?? '').split(',')[0];
                return integerPart.replace(/\D/g, '').length >= 4;
            },

            formatRupiah(number) {
                if (number === null || number === undefined || isNaN(number)) return 'Rp 0';
                return 'Rp ' + Number(number).toLocaleString('id-ID');
            },

            formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                return date.toLocaleDateString('id-ID', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            }
        }));
    });

    function printNota() {
        const printElement = document.getElementById('printable-kitchen-note');
        if (!printElement) return;

        const printWindow = window.open('', '_blank');
        if (!printWindow) return alert('Pop-up diblokir oleh browser. Izinkan pop-up untuk mencetak nota.');

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
                <head>
                    <title>SISTEM PO</title>
                    <script src="https://cdn.tailwindcss.com"><\/script>
                </head>
                <body class="p-6 flex justify-center items-start bg-white">
                    <div class="w-full max-w-md">
                        ${printElement.innerHTML}
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 600);
    }
    function poAnalysisModal() {
        return {
            open: false, loading: false, error: '', requestId: 0, appliedMonth: '',
            filters: { source: 'supplier_sbg', category: 'Buah', year: new Date().getFullYear(), month: '', region: '', item: '' },
            months: ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],
            categories: ['Karbo','Protein Nabati','Protein Hewani','Sayur','Buah'],
            data: { rows: [], items: [], regions: [], excluded: 0, year: '' },
            rupiah(value) { return 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); },
            visibleMonths(row) { return this.appliedMonth ? row.months.filter(m => m.month === Number(this.appliedMonth)) : row.months; },
            async fetchData() {
                const requestId = ++this.requestId;
                const filters = { ...this.filters };
                this.loading = true; this.error = '';
                try {
                    const response = await fetch('{{ route('dashboard.po-analysis') }}?' + new URLSearchParams(filters), { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(response.status === 422 ? 'Filter tidak valid. Periksa tahun dan pilihan filter.' : 'Analisa PO gagal dimuat.');
                    const data = await response.json();
                    if (requestId === this.requestId) { this.data = data; this.appliedMonth = filters.month; }
                } catch (error) {
                    if (requestId === this.requestId) this.error = error.message;
                } finally { if (requestId === this.requestId) this.loading = false; }
            }
        };
    }
    function analisaKeuntunganModal() {
        return {
            isAnalysisModalOpen: false,
            loading: false,
            error: '',
            requestId: 0,
            activeFilter: 'week',
            filterOptions: {
                'week': 'Minggu Ini',
                'month': 'Bulan Ini',
                '3months': '3 Bulan Terakhir',
                'all': 'Semua'
            },
            data: {
                totalProfit: 0,
                topItems: [],
                topCategories: [],
                topItem: { name: '-', total: 0 },
                topCategory: { name: '-', total: 0 },
                bestItem: { name: '-', total: 0 },
                worstItem: { name: '-', total: 0 }
            },
            fetchData() {
                const requestId = ++this.requestId;
                this.loading = true;
                this.error = '';
                fetch(`{{ route('dashboard.analisa') }}?filter=${this.activeFilter}`)
                    .then(res => {
                        if (!res.ok) throw new Error('Data analisa gagal dimuat. Silakan coba lagi.');
                        return res.json();
                    })
                    .then(resData => {
                        if (requestId === this.requestId) this.data = resData;
                    })
                    .catch(err => {
                        if (requestId === this.requestId) this.error = err.message;
                    })
                    .finally(() => {
                        if (requestId === this.requestId) this.loading = false;
                    });
            },
            formatRupiah(val) {
                return 'Rp ' + (val || 0).toLocaleString('id-ID');
            }
        }
    }
    </script>   
    <script src="{{ asset('js/sbg-v24.js') }}?v={{ filemtime(public_path('js/sbg-v24.js')) }}"></script>
    <script src="{{ asset('js/fruit-calculator.js') }}?v={{ filemtime(public_path('js/fruit-calculator.js')) }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="stylesheet" href="{{ asset('css/sbg-v2.css') }}?v={{ filemtime(public_path('css/sbg-v2.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sbg-v23.css') }}?v={{ filemtime(public_path('css/sbg-v23.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/fruit-calculator.css') }}?v={{ filemtime(public_path('css/fruit-calculator.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sbg-v24.css') }}?v={{ filemtime(public_path('css/sbg-v24.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sbg-v24-receipt.css') }}?v={{ filemtime(public_path('css/sbg-v24-receipt.css')) }}">
    @includeIf('components.sbg.theme-head')
    <link rel="stylesheet" href="{{ asset('css/sbg-v25-po.css') }}?v={{ filemtime(public_path('css/sbg-v25-po.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sbg-navigation.css') }}?v={{ filemtime(public_path('css/sbg-navigation.css')) }}">
    <script src="{{ asset('js/sbg-v25-po.js') }}?v={{ filemtime(public_path('js/sbg-v25-po.js')) }}"></script>
    <script defer src="{{ asset('js/sbg-v2.js') }}?v={{ filemtime(public_path('js/sbg-v2.js')) }}"></script>
</head>
<body class="bg-[#f7f7f0] font-sans text-gray-800 antialiased selection:bg-[#f3a51d] selection:text-[#0a291e]" data-account-id="{{ auth()->id() }}" x-data="dashboardApp" :class="'sbg-page-' + mobilePage + (sidebarCollapsed ? ' sbg-sidebar-collapsed' : '')" @hashchange.window="syncNavigation()" @fruit-navigation-changed.window="syncFruitNavigation($event.detail.tab)" @resize.window.debounce.100ms="wideFilters = window.matchMedia('(min-width: 768px)').matches; desktopNav = window.matchMedia('(min-width: 1024px)').matches" @keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); mobilePage === 'admin' ? document.getElementById('master-search').focus() : focusPoSearch(); }">

    @include('components.sbg.app-navigation')

    @include('components.fruit-calculator')

    <div x-show="activeView === 'dashboard'" class="sbg-workspace">

        <!-- ALERT SUCCESS / ERROR -->
        @if (session('success') === 'Purchase Order berhasil dibuat.')
            <div data-sbg-po-success x-data="{ visible: true }" x-show="visible" class="sbg-po-success-toast" role="status">
                <span class="sbg-v22-header-icon"><x-sbg.icon name="check"/></span>
                <div><strong>Purchase Order berhasil dibuat.</strong><button type="button" @click="navigateDashboard('orders'); visible = false">Lihat daftar PO <span aria-hidden="true">→</span></button></div>
                <button type="button" @click="visible = false" class="sbg-v22-close" aria-label="Tutup notifikasi">&times;</button>
            </div>
        @elseif (session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-sm font-medium flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-sm space-y-1 shadow-sm">
                <div class="flex items-center gap-2 font-bold">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Terjadi kesalahan input:</span>
                </div>
                <ul class="list-disc list-inside text-xs pl-7 space-y-0.5 text-rose-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="sbg-welcome">
            <div><h1>Selamat datang, {{ auth()->user()->name }}! <x-sbg.icon name="wave" :size="24"/></h1><p>Kelola purchase order dengan mudah dan efisien di Sentra Buah Garut.</p></div>
            <div class="sbg-welcome-actions"><button type="button" @click="isCreateModalOpen = true" class="sbg-button sbg-button-orange"><x-sbg.icon name="plus" :size="18"/>Buat PO Baru</button><button type="button" @click="$dispatch('open-analisa-modal')" class="sbg-button sbg-button-outline"><x-sbg.icon name="chart" :size="18"/>Analisa</button></div>
        </section>
        @include('components.sbg.dashboard-summary')
        <div class="sbg-mobile-orders-heading"><h1>Daftar Purchase Order</h1><p>Kelola dan pantau semua purchase order.</p><button type="button" @click="isCreateModalOpen = true" class="sbg-button sbg-button-orange"><x-sbg.icon name="plus" :size="16"/>Buat PO</button></div>
        @include('components.sbg.po-filter')
        @include('components.sbg.po-list')

        @if(auth()->user()->isSuperAdmin() || auth()->user()->isKepalaDapur())
            @include('components.sbg.master-data')
        @endif

    </div>

    <!-- ========================================================= -->
    <!-- MODAL DETAIL FEE MITRA                                     -->
    <!-- ========================================================= -->
    @foreach([
        ['partner_fee', 'open-modal-fee-mitra', 'Fee Mitra', 'Daftar akumulasi fee untuk mitra/dapur', 'dashboard.partner-fee.pay-all'],
        ['supplier', 'open-modal-supplier', 'Bayar Supplier', 'Daftar akumulasi pembayaran supplier', 'dashboard.supplier.pay-all'],
        ['ksppg_fee', 'open-modal-fee-ksppg', 'Fee KSPPG', 'Daftar akumulasi fee KSPPG', 'dashboard.ksppg-fee.pay-all'],
    ] as [$feeType, $feeEvent, $feeTitle, $feeDescription, $feePayRoute])
        @include('components.sbg.fee-detail')
    @endforeach

    <!-- ========================================================= -->
    <!-- MODAL RESET PASSWORD KEPALA DAPUR                         -->
    <!-- ========================================================= -->
    @if(auth()->user()->isKepalaDapur())
    <div x-show="isResetOwnPasswordModalOpen"
         x-transition
         x-cloak
         class="sbg-modal fixed inset-0 z-50 flex items-center justify-center bg-[#0a291e]/60 p-4 backdrop-blur-sm">
        <div @click.away="isResetOwnPasswordModalOpen = false" class="sbg-modal-panel w-full max-w-sm rounded-xl border border-[#dce1d6] bg-white p-6">
            <div class="flex items-center justify-between border-b border-[#dce1d6] pb-3">
                <div>
                    <h3 class="text-lg font-extrabold text-[#1c3328]">Buat Password Baru</h3>
                    <p class="mt-0.5 text-xs text-[#687168]">Akun {{ auth()->user()->name }}</p>
                </div>
                <button type="button" @click="isResetOwnPasswordModalOpen = false" class="text-2xl font-bold text-[#687168] hover:text-[#435348]">&times;</button>
            </div>

            <form action="{{ route('account.password.update') }}" method="POST" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#435348]">Password Baru</label>
                    <input type="password" name="password" minlength="6" required autocomplete="new-password" class="w-full rounded-md border border-[#dce1d6] px-3 py-2.5 text-sm focus:border-[#587547] focus:outline-none focus:ring-2 focus:ring-[#587547]/20">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#435348]">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" minlength="6" required autocomplete="new-password" class="w-full rounded-md border border-[#dce1d6] px-3 py-2.5 text-sm focus:border-[#587547] focus:outline-none focus:ring-2 focus:ring-[#587547]/20">
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-[#dce1d6] pt-4">
                    <button type="button" @click="isResetOwnPasswordModalOpen = false" class="rounded-md bg-[#f7f7f0] px-4 py-2.5 text-xs font-bold text-[#435348] hover:bg-[#e9efe6]">Batal</button>
                    <button type="submit" class="rounded-md bg-[#123d2c] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0a291e]">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- ========================================================= -->
    <!-- MODAL KONFIRMASI LOGOUT                                   -->
    <!-- ========================================================= -->
    @include('components.sbg.logout-modal')

    <!-- MODAL TAMBAH ADMIN & DAERAH BERSAMAAN -->
    <div x-show="isAddAdminModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="isAddAdminModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-lg rounded-xl overflow-hidden p-6 space-y-4 relative border border-[#dce1d6]">
            
            <div class="flex items-center justify-between border-b border-[#dce1d6] pb-3">
                <h3 class="text-lg font-bold text-[#1c3328]">{{ auth()->user()->isSuperAdmin() ? 'Tambah Daerah & Penanggung Jawab Baru' : 'Tambah Admin Wilayah' }}</h3>
                <button @click="isAddAdminModalOpen = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold">&times;</button>
            </div>

            <form action="{{ route('dashboard.admin-region.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- SECTION DAERAH / DAPUR -->
                @if(auth()->user()->isSuperAdmin())
                <div class="p-3.5 bg-[#e9efe6]/60 rounded-lg border border-[#dce1d6] space-y-3">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide">1. Informasi Daerah / Dapur</p>

                    <input type="hidden" name="create_new_region" :value="createNewRegion ? 1 : 0">

                    <div x-show="!createNewRegion">
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Pilih Wilayah</label>
                        <select name="region" :required="!createNewRegion" :disabled="createNewRegion" class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition bg-white">
                            <option value="" {{ auth()->user()->isSuperAdmin() ? 'selected' : '' }} disabled>Pilih wilayah yang sudah terdaftar</option>
                            @foreach($regions as $regionOption)
                                <option value="{{ $regionOption->name }}" {{ auth()->user()->isKepalaDapur() && auth()->user()->region === $regionOption->name ? 'selected' : '' }}>{{ $regionOption->name }} — {{ $regionOption->code ?? 'Tanpa kode' }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(auth()->user()->isSuperAdmin())
                    <label class="inline-flex items-center gap-2 cursor-pointer rounded-md border border-[#dce1d6] bg-white px-3 py-2.5">
                        <input type="checkbox" x-model="createNewRegion" class="w-4 h-4 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547]">
                        <span class="text-xs font-bold text-[#123d2c]">Tambah daerah baru</span>
                    </label>
                    @endif

                    <div x-show="createNewRegion" x-transition class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Daerah / Dapur</label>
                            <input type="text" name="region_name" placeholder="Contoh: SPPG HARPA 4" :required="createNewRegion" :disabled="!createNewRegion" class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Kode</label>
                            <input type="text" name="region_code" placeholder="HRP4" :disabled="!createNewRegion" class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition">
                        </div>
                    </div>
                </div>
                @endif

                <!-- SECTION AKUN PENANGGUNG JAWAB -->
                <div class="p-3.5 bg-[#e9efe6]/60 rounded-lg border border-[#dce1d6] space-y-3">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide">{{ auth()->user()->isSuperAdmin() ? '2.' : '1.' }} Akun Penanggung Jawab</p>

                    @if(auth()->user()->isSuperAdmin())
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Role Akun</label>
                        <select name="account_role" required class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition bg-white">
                            <option value="admin">Admin</option>
                            <option value="kepala_dapur">Kepala Dapur</option>
                        </select>
                    </div>
                    @endif
                    
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Lengkap</label>
                        <input type="text" name="admin_name" placeholder="Contoh: Ahmad Subagja" required class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Email Log In</label>
                            <input type="email" name="email" placeholder="admin.harpa4@sppg.com" required class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Password</label>
                            <input type="password" name="password" placeholder="******" required class="w-full text-xs sm:text-sm border-[#dce1d6] rounded-md px-3 py-2 border focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] transition">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs sm:text-sm transition shadow-sm">
                        {{ auth()->user()->isSuperAdmin() ? 'Simpan Daerah & Akun Baru' : 'Simpan Admin Baru' }}
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 1: TAMBAH PO (CREATE)                               -->
    <!-- ========================================================= -->
    @include('components.sbg.create-po-modal')

    @include('components.sbg.po-detail')

    <!-- ========================================================= -->
    <!-- MODAL 3: EDIT PO                                          -->
    <!-- ========================================================= -->
    @include('components.sbg.edit-po-modal')

    <!-- ========================================================= -->
    <!-- MODAL NOTA DAPUR                                          -->
    <!-- ========================================================= -->
    <div x-data="{ open: false, po: {} }" 
         @open-nota-dapur.window="open = true; po = $event.detail; $nextTick(() => renderVerifiedQr(po))" 
         x-show="open" 
         x-cloak 
         class="sbg-modal fixed inset-0 z-50 flex items-center justify-center bg-[#0a291e]/60 p-4 backdrop-blur-sm">

        <!-- Card Modal -->
        <div @click.away="open = false" 
             class="sbg-modal-panel w-full max-w-lg rounded-xl bg-white p-6 transition-all border border-[#dce1d6]">
            
            <!-- Header Pop-up -->
            <div class="flex items-center justify-between pb-3 border-b border-[#dce1d6]">
                <h3 class="text-base font-bold text-[#1c3328]">Nota Dapur</h3>
                <button @click="open = false" class="rounded-full p-1 text-[#687168] hover:bg-[#f7f7f0] hover:text-[#435348]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- AREA YANG AKAN DICETAK -->
            <div id="printable-kitchen-note" class="my-4 overflow-hidden rounded-2xl border border-gray-300 font-sans bg-white text-gray-900">
                <div class="h-2 bg-gray-900"></div>

                <!-- Identitas Nota -->
                <div class="px-5 pt-5 pb-4 text-center">
                    <h2 class="text-lg font-black tracking-wide uppercase">SENTRA BUAH GARUT</h2>
                    <p class="mt-1 text-[10px] text-gray-500">Jl. Ahmad Yani Timur Karangpawitan Garut</p>
                </div>

                <!-- Nomor PO & Tanggal -->
                <div class="mx-5 grid grid-cols-2 divide-x divide-gray-200 rounded-xl border border-gray-200 bg-gray-50">
                    <div class="p-3">
                        <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">Nomor PO</p>
                        <p class="mt-1 text-xs font-black" x-text="po.po_number || '-'"></p>
                    </div>
                    <div class="p-3 text-right">
                        <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">Tanggal PO</p>
                        <p class="mt-1 text-xs font-bold" x-text="po.transaction_date || '-'"></p>
                    </div>
                </div>

                <!-- Item Utama -->
                <div class="px-5 py-4">
                    <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">Item Pesanan</p>
                    <h3 class="mt-1 text-xl font-black leading-tight" x-text="po.item_name || '-'"></h3>
                    <p class="mt-1 text-[10px] font-semibold text-gray-500" x-text="po.category || '-'"></p>
                </div>

                <!-- Detail Transaksi -->
                <div class="mx-5 overflow-hidden rounded-xl border border-gray-200 text-xs">
                    <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2.5">
                        <span class="font-medium text-gray-500">Jumlah Pesanan</span>
                        <span class="font-black" x-text="(po.quantity || 0) + ' ' + (po.unit || '')"></span>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2.5">
                        <span class="font-medium text-gray-500">Harga</span>
                        <span class="font-bold" x-text="formatRupiah(po.maker_price || 0)"></span>
                    </div>
                    <div class="flex items-center justify-between px-3 py-2.5">
                        <span class="font-medium text-gray-500">Status</span>
                        <span class="font-bold" x-text="po.status_stage || '-'"></span>
                    </div>
                </div>

                <!-- Total -->
                <div class="mx-5 mt-4 flex items-center justify-between rounded-xl bg-gray-900 px-4 py-3 text-white">
                    <div>
                        <p class="text-[8px] font-bold uppercase tracking-widest text-gray-400">Total Nominal</p>
                        <p class="mt-0.5 text-[9px] text-gray-400">Harga × Qty</p>
                    </div>
                    <p class="text-lg font-black" x-text="formatRupiah((po.maker_price || 0) * (po.quantity || 0))"></p>
                </div>

                <!-- Catatan -->
                <div class="mx-5 mt-4 rounded-xl border border-dashed border-gray-300 px-3 py-2.5">
                    <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">Catatan</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-gray-700" x-text="po.notes || 'Tidak ada catatan.'"></p>
                </div>

                <!-- QR Verifikasi -->
                <div class="mt-5 border-t border-gray-100 px-5 py-5 text-center">
                    <div class="flex items-center justify-center gap-4">
                        <div class="shrink-0 rounded-xl border border-gray-200 bg-white p-2">
                            <div id="verified-po-qr" class="w-[130px] h-[130px] [&>img]:w-full [&>img]:h-full"></div>
                        </div>
                        <div class="max-w-[150px] text-left">
                            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black tracking-wider text-emerald-700 border border-emerald-200">
                                <span>✓</span><span>VERIFIED</span>
                            </div>
                            <p class="mt-2 text-[9px] leading-relaxed text-gray-500">Pindai QR untuk memeriksa keaslian dan detail Purchase Order ini.</p>
                        </div>
                    </div>
                    <p id="verified-po-qr-error" class="hidden py-4 text-xs font-semibold text-rose-600">QR Code gagal dimuat. Silakan muat ulang halaman.</p>
                    <p class="mt-4 text-[8px] uppercase tracking-widest text-gray-400">Dokumen dibuat secara digital oleh sistem</p>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="grid grid-cols-2 gap-3 pt-2">
                <button @click="printNota()" 
                        type="button" 
                        class="w-full bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold py-2.5 rounded-md text-xs transition flex items-center justify-center gap-2 shadow-sm">
                    🖨️ Cetak Nota
                </button>
                <button @click="open = false" 
                        type="button" 
                        class="w-full bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2.5 rounded-md text-xs transition">
                    Tutup
                </button>
            </div>

        </div>
    </div>

    @include('components.sbg.po-summary')
    @include('components.sbg.partner-receipt')

    @include('components.sbg.master-data-dialogs')

    @include('components.sbg.analytics-modal')
    @include('components.sbg.announcement')

    @if($popupFeedback)
        <div x-data="{ open: true }" x-show="open" x-cloak class="sbg-modal fixed inset-0 z-[90] flex items-center justify-center bg-[#0a291e]/60 p-4 backdrop-blur-sm" role="alertdialog" aria-modal="true" aria-labelledby="popup-feedback-title" @keydown.escape.window="open = false">
            <div class="sbg-modal-panel w-full max-w-sm rounded-xl bg-white p-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full text-2xl {{ $popupFeedback['type'] === 'success' ? 'bg-[#e9efe6] text-[#123d2c]' : 'bg-rose-100 text-rose-700' }}">{{ $popupFeedback['type'] === 'success' ? '✓' : '!' }}</div>
                <h2 id="popup-feedback-title" class="text-lg font-extrabold text-[#1c3328]">{{ $popupFeedback['type'] === 'success' ? 'Berhasil' : 'Gagal' }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-[#435348]">{{ $popupFeedback['message'] }}</p>
                <button type="button" @click="open = false" class="mt-5 w-full rounded-md px-4 py-2.5 text-sm font-bold text-white {{ $popupFeedback['type'] === 'success' ? 'bg-[#123d2c] hover:bg-[#0a291e]' : 'bg-rose-600 hover:bg-rose-700' }}">Oke</button>
            </div>
        </div>
    @endif
    @if(auth()->user()->isSuperAdmin())
    <div x-data="poAnalysisModal()" @open-po-analysis.window="open = true; fetchData()" x-show="open" x-cloak
         @keydown.escape.window="open = false" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-3 sm:p-6">
        <section role="dialog" aria-modal="true" aria-labelledby="po-analysis-title" @click.outside="open = false"
                 class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-xl bg-[#f7f7f0] p-4 sm:p-6 shadow-xl">
            <header class="flex items-start justify-between gap-4">
                <div><h2 id="po-analysis-title" class="text-xl font-extrabold text-[#123d2c]">Analisa PO</h2>
                    <p class="mt-1 text-sm text-gray-600">Perubahan harga bulanan dan bulan harga terendah per barang.</p></div>
                <button type="button" @click="open = false" class="rounded-lg border px-3 py-2" aria-label="Tutup analisa PO">✕</button>
            </header>
            <form @submit.prevent="fetchData()" class="my-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <label class="text-xs font-semibold">Acuan harga
                    <select x-model="filters.source" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="supplier_sbg">Supplier SBG</option><option value="supplier_b">Supplier B</option><option value="maker">Harga jual</option></select></label>
                <label class="text-xs font-semibold">Kategori
                    <select x-model="filters.category" @change="filters.item = ''" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">Semua kategori</option><template x-for="category in categories" :key="category"><option :value="category" x-text="category"></option></template></select></label>
                <label class="text-xs font-semibold">Tahun
                    <input type="number" min="2000" max="2100" required x-model="filters.year" class="mt-1 w-full rounded-md border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold">Bulan
                    <select x-model="filters.month" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">Semua bulan</option><template x-for="(month, index) in months" :key="month"><option :value="index + 1" x-text="month"></option></template></select></label>
                <label class="text-xs font-semibold">Wilayah
                    <select x-model="filters.region" @change="filters.item = ''" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">Semua wilayah</option><template x-for="region in data.regions" :key="region"><option :value="region" x-text="region"></option></template></select></label>
                <label class="text-xs font-semibold">Barang
                    <select x-model="filters.item" class="mt-1 w-full rounded-md border-gray-300 text-sm"><option value="">Semua barang</option><template x-for="item in data.items" :key="item"><option :value="item" x-text="item"></option></template></select></label>
                <button type="submit" class="self-end rounded-md bg-[#123d2c] px-4 py-2 text-sm font-bold text-white disabled:opacity-50" :disabled="loading">Tampilkan</button>
            </form>
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">Harga bulanan = total (Qty × harga) ÷ total Qty. Barang dan satuan dihitung terpisah. Bulan harga rendah merupakan petunjuk dari riwayat PO, belum memastikan musim panen. Perubahan (%) dibanding bulan kalender sebelumnya; tanda — berarti data tidak tersedia.</p>
            <p x-show="loading" class="py-5 text-sm" role="status">Memuat analisa...</p>
            <div x-show="error" class="my-4 rounded-md bg-red-50 p-3 text-red-700" role="alert"><span x-text="error"></span> <button type="button" @click="fetchData()" class="underline">Coba lagi</button></div>
            <div x-show="!loading && !error" class="mt-5 space-y-4">
                <p class="text-xs text-gray-600" x-text="data.rows.length + ' kelompok barang · ' + data.excluded + ' PO tanpa Qty/harga positif tidak dihitung.'"></p>
                <p x-show="data.rows.length === 0" class="rounded-lg border bg-white p-6 text-center text-sm text-gray-600">Belum ada data harga untuk filter ini. Coba ganti tahun, kategori, atau acuan harga.</p>
                <template x-for="row in data.rows" :key="JSON.stringify([row.item,row.category,row.unit])">
                    <article class="rounded-xl border border-[#dce1d6] bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><h3 class="font-extrabold text-[#123d2c]" x-text="row.item"></h3><p class="text-xs text-gray-500" x-text="(row.category || 'Tanpa kategori') + ' · per ' + row.unit + ' · ' + row.count + ' PO / ' + row.observed_months + ' bulan pada ' + data.year"></p></div>
                            <div class="text-xs text-right"><p class="font-bold text-emerald-800" x-text="row.observed_months > 1 ? 'Bulan harga terendah: ' + row.lowest_months.map(m => months[m-1]).join(', ') : 'Data baru 1 bulan; pola belum terlihat'"></p><p class="mt-1 text-gray-600" x-text="'Rentang rata-rata: ' + rupiah(row.lowest) + ' – ' + rupiah(row.highest)"></p></div>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-xs text-left"><thead><tr class="border-b text-gray-500"><th class="p-2">Bulan</th><th class="p-2">Rata-rata harga</th><th class="p-2">Perubahan</th><th class="p-2">Harga min–maks</th><th class="p-2">Qty / PO</th><th class="p-2 min-w-[140px]">Tren harga</th></tr></thead>
                                <tbody><template x-for="month in visibleMonths(row)" :key="month.month"><tr class="border-b border-gray-100">
                                    <td class="p-2 font-semibold" x-text="months[month.month-1]"></td>
                                    <td class="p-2 whitespace-nowrap" x-text="month.average === null ? '—' : rupiah(month.average)"></td>
                                    <td class="p-2 whitespace-nowrap" :class="month.change > 0 ? 'text-red-700' : month.change < 0 ? 'text-emerald-700' : 'text-gray-500'" x-text="month.change === null ? '—' : (month.change > 0 ? '+' : '') + month.change.toLocaleString('id-ID') + '%'"></td>
                                    <td class="p-2 whitespace-nowrap" x-text="month.min === null ? '—' : rupiah(month.min) + ' – ' + rupiah(month.max)"></td>
                                    <td class="p-2 whitespace-nowrap" x-text="month.count ? month.quantity.toLocaleString('id-ID') + ' ' + row.unit + ' / ' + month.count : '—'"></td>
                                    <td class="p-2"><div class="h-3 rounded bg-gray-100"><div class="h-3 rounded bg-emerald-600" :style="{width: (month.average === null ? 0 : month.average / row.highest * 100) + '%'}"></div></div></td>
                                </tr></template></tbody>
                            </table>
                        </div>
                    </article>
                </template>
            </div>
        </section>
    </div>
    @endif
</body>
</html>
