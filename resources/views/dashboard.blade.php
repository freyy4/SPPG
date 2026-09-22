<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEM PO</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
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
                this.selectedIds = this.allSelected()
                    ? []
                    : this.selectableItems().map(item => String(item.id));
            },

            selectedItems() {
                return this.items.filter(item => this.selectedIds.includes(String(item.id)));
            },

            selectedTotal() {
                return this.selectedItems().reduce((total, item) => {
                    return total + this.amount(item);
                }, 0);
            },

            async verificationQrDataUrl() {
                const response = await fetch('{{ route('dashboard.payment.verification-link') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ items: this.selectedIds, type: this.paymentType })
                });

                if (!response.ok) throw new Error('Gagal membuat tautan verifikasi.');

                const data = await response.json();
                const verificationUrl = new URL(data.url, window.location.origin).href;
                return createQrDataUrl(verificationUrl);
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

                const safe = value => String(value ?? '-').replace(/[&<>'"]/g, character => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
                })[character]);
                const rupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
                let qrDataUrl = '';
                try {
                    qrDataUrl = await this.verificationQrDataUrl();
                } catch (error) {
                    printWindow.close();
                    alert(error.message);
                    return;
                }
                const qrMarkup = qrDataUrl ? '<img src="' + qrDataUrl + '" alt="QR Verified">' : '';
                const rows = selected.map((item, index) => {
                    const subtotal = this.noteAmount(item);
                    return `<tr>
                        <td>${index + 1}</td>
                        <td><strong>${safe(item.item_name)}</strong><small>${safe(item.po_number)}</small></td>
                        <td>${safe(item.quantity)} ${safe(item.unit || '')}</td>
                        <td>${rupiah(item.maker_price)}</td>
                        <td class="right"><strong>${rupiah(subtotal)}</strong></td>
                    </tr>`;
                }).join('');

                printWindow.document.write(`<!DOCTYPE html>
                    <html lang="id"><head><meta charset="UTF-8"><title>SISTEM PO</title>
                    <style>
                        *{box-sizing:border-box} body{margin:0;padding:28px;font-family:Arial,sans-serif;color:#111827;font-size:12px}
                        .note{max-width:800px;margin:auto;border:1px solid #d1d5db;border-radius:14px;overflow:hidden}
                        .bar{height:8px;background:#111827}.header{text-align:center;padding:22px;border-bottom:1px solid #e5e7eb}
                        h1{font-size:20px;margin:4px 0}.label{font-size:9px;letter-spacing:2px;color:#6b7280;font-weight:bold}
                        .address{color:#6b7280;margin:0}.meta{display:flex;justify-content:space-between;padding:14px 18px;background:#f9fafb}
                        table{width:100%;border-collapse:collapse} th{background:#f3f4f6;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:.5px}
                        th,td{padding:11px;border-bottom:1px solid #e5e7eb} td small{display:block;color:#6b7280;margin-top:3px}.right{text-align:right}
                        .total{display:flex;justify-content:space-between;align-items:center;margin:18px;padding:16px;background:#111827;color:white;border-radius:10px}
                        .total strong{font-size:20px}.footer{text-align:center;color:#6b7280;font-size:9px;padding:0 18px 18px}
                        .verify{text-align:center;border-top:1px solid #e5e7eb;padding:18px}.verify img{width:130px;height:130px}.verified{font-size:10px;font-weight:bold;letter-spacing:2px;color:#047857;margin:8px 0 3px}
                        @media print{body{padding:0}.note{border:none;max-width:none}}
                    </style></head><body>
                    <section class="note"><div class="bar"></div><header class="header">
                        <h1>SENTRA BUAH GARUT</h1>
                        <p class="address">Jl. Ahmad Yani Timur Karangpawitan Garut</p>
                    </header><div class="meta"><span>Tanggal cetak: <strong>${new Date().toLocaleDateString('id-ID')}</strong></span><span>${selected.length} barang</span></div>
                    <table><thead><tr><th>No.</th><th>Item / Nomor PO</th><th>Qty</th><th>Harga Maker</th><th class="right">Subtotal</th></tr></thead>
                    <tbody>${rows}</tbody></table>
                    <div class="total"><span>TOTAL</span><strong>${rupiah(this.noteTotal())}</strong></div>
                    <div class="verify">${qrMarkup}<p class="verified">✓ VERIFIED</p><p>Scan untuk melihat daftar barang terpilih</p></div>
                    <p class="footer">Dokumen ini dibuat melalui sistem Purchase Order Sentra Buah Garut.</p></section>
                    <script>window.onload=()=>{window.print();window.onafterprint=()=>window.close()}<\/script>
                    </body></html>`);
                printWindow.document.close();
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
                    qrDataUrl = await this.verificationQrDataUrl();
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

                documentPdf.setFillColor(17, 24, 39);
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
                    head: [['No.', 'Item / Nomor PO', 'Qty', 'Harga Maker', 'Subtotal']],
                    body: rows,
                    theme: 'grid',
                    styles: { font: 'helvetica', fontSize: 9, cellPadding: 3, textColor: [17, 24, 39] },
                    headStyles: { fillColor: [31, 41, 55], textColor: [255, 255, 255], fontStyle: 'bold' },
                    columnStyles: {
                        0: { cellWidth: 12, halign: 'center' },
                        2: { cellWidth: 26 },
                        3: { cellWidth: 35, halign: 'right' },
                        4: { cellWidth: 38, halign: 'right', fontStyle: 'bold' }
                    }
                });

                const totalY = documentPdf.lastAutoTable.finalY + 8;
                documentPdf.setFillColor(17, 24, 39);
                documentPdf.roundedRect(120, totalY, 76, 16, 2, 2, 'F');
                documentPdf.setTextColor(255, 255, 255);
                documentPdf.setFont('helvetica', 'bold');
                documentPdf.setFontSize(9);
                documentPdf.text('TOTAL HARGA MAKER', 125, totalY + 6);
                documentPdf.setFontSize(13);
                documentPdf.text(rupiah(this.noteTotal()), 191, totalY + 11, { align: 'right' });

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
            activeView: location.hash === '#kalkulator-buah' ? 'fruit' : 'dashboard',
            isDetailModalOpen: false,
            isCreateModalOpen: false,
            isEditModalOpen: false,
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
                message: @json(old('message', ''))
            },

            openLoginPopupSettings(account) {
                this.popupTarget = {
                    accountId: account.accountId,
                    accountName: account.accountName,
                    title: account.title || '',
                    message: account.message || ''
                };
                this.isLoginPopupSettingsOpen = true;
            },
            
            // State Modal Summary
            isSummaryModalOpen: false,
            summaryModalTitle: '',
            summaryModalItems: [],

            // State Selected PO & Edit PO
            selectedPo: {},
            editPo: {},
            createPrices: {
                supplier_price: '',
                maker_price: '',
                partner_margin: ''
            },

            allPoData: @json($purchaseOrders->items()),

            openSummaryModal(type) {
                this.summaryModalTitle = type;
                
                if (type === 'Total Nilai PO') {
                    this.summaryModalItems = this.allPoData;
                } else if (type === 'PO Pending') {
                    this.summaryModalItems = this.allPoData.filter(po => po.status_stage === 'Pending');
                } else if (type === 'PO Selesai') {
                    this.summaryModalItems = this.allPoData.filter(po => po.status_stage === 'Selesai');
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

            openEditAdminModal(region, admin) {
                this.editAdminForm.region_id = region.id;
                this.editAdminForm.account_id = admin ? admin.id : null;
                this.editAdminForm.code = region.code || '';
                this.editAdminForm.name = region.name || '';
                this.editAdminForm.role = admin ? admin.role : 'admin';
                this.editAdminForm.admin_name = admin ? admin.name : '';
                this.editAdminForm.admin_email = admin ? admin.email : '';
                this.isEditAdminModalOpen = true;
            },

            openDetailModal(po) {
                this.selectedPo = po;
                this.isDetailModalOpen = true;

                fetch(`/dashboard/po/${po.id}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            this.selectedPo = res.data;
                        }
                    })
                    .catch(err => console.error("Error fetching detail:", err));
            },

            openEditModal(po) {
                this.isDetailModalOpen = false;
                this.editPo = JSON.parse(JSON.stringify(po));
                this.editPo.transaction_date = this.toDateInputValue(po.transaction_date);
                this.editPo.supplier_price = formatCurrencyValue(po.supplier_price);
                this.editPo.maker_price = formatCurrencyValue(po.maker_price);
                this.editPo.partner_margin = formatCurrencyValue(po.partner_margin);
                this.isEditModalOpen = true;
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
    <script src="{{ asset('js/fruit-calculator.js') }}?v=7"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        :root { --sbg-green: #123d2c; --sbg-dark: #0a291e; --sbg-cream: #f7f7f0; --sbg-gold: #f3a51d; }
        body { font-family: 'DM Sans', sans-serif; }
        .sbg-heading { font-family: 'Manrope', sans-serif; }
        .sbg-card, .sbg-panel {
            border: 1px solid #dce1d6;
            background: #fff;
            box-shadow: 0 10px 30px rgba(18, 61, 44, .055);
        }
        .sbg-card { border-radius: 10px; }
        .sbg-panel { border-radius: 12px; }
        .sbg-card-interactive { transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
        .sbg-card-interactive:hover { transform: translateY(-3px); border-color: #aab7a3; box-shadow: 0 16px 34px rgba(18, 61, 44, .10); }

        /* Modal surfaces follow the dashboard's Sentra Buah Garut palette. */
        .sbg-modal { color: #1c3328; }
        .sbg-modal-panel, .sbg-modal-confirm {
            border: 1px solid #dce1d6;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 24px 64px rgba(10, 41, 30, .22);
        }
        .sbg-modal-panel { max-height: calc(100dvh - 2rem); overflow-y: auto; }
        .sbg-modal :is(h2, h3, h4):not(#printable-kitchen-note *) {
            font-family: 'Manrope', sans-serif;
            letter-spacing: -.025em;
        }
        .sbg-modal :is(input, select, textarea):not([type="checkbox"], [type="radio"], [type="hidden"]) {
            border-color: #dce1d6;
            border-radius: 6px;
        }
        .sbg-modal :is(input, select, textarea):focus {
            border-color: #587547;
            --tw-ring-color: rgba(88, 117, 71, .2);
        }
        .sbg-modal button:focus-visible {
            outline: 2px solid #c27909;
            outline-offset: 3px;
        }
    </style>
</head>
<body class="bg-[#f7f7f0] font-sans text-gray-800 antialiased selection:bg-[#f3a51d] selection:text-[#0a291e]" x-data="dashboardApp" @hashchange.window="activeView = location.hash === '#kalkulator-buah' ? 'fruit' : 'dashboard'">

    <!-- NAVBAR: identitas visual mengikuti landing page Sentra Buah Garut -->
    <header x-data="{ mobileNav: false, accountOpen: false }" class="sticky top-0 z-50 border-b border-[#dce1d6] bg-[#f7f7f0]/95 backdrop-blur-xl">
        <div class="mx-auto flex min-h-[82px] max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="Sentra Buah Garut - beranda">
                <img src="{{ asset('favicon.svg') }}" alt="" class="h-11 w-11 object-contain">
                <span class="flex flex-col">
                    <strong class="sbg-heading text-[15px] font-extrabold tracking-tight text-[#1c3328] sm:text-[17px]">Sentra Buah Garut</strong>
                    <small class="text-[9px] font-semibold uppercase tracking-[.2em] text-[#687168]">PT Garuta Food</small>
                </span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-semibold text-[#435348] md:flex" aria-label="Navigasi dashboard">
                <a @click="activeView = 'dashboard'" href="#ringkasan" class="transition hover:text-[#896016]">Ringkasan</a>
                <a @click="activeView = 'dashboard'" href="#data-po" class="transition hover:text-[#896016]">Data PO</a>
                <a @click="activeView = 'fruit'" href="#kalkulator-buah" :aria-current="activeView === 'fruit' ? 'page' : null" class="transition hover:text-[#896016]" :class="activeView === 'fruit' ? 'text-[#896016]' : ''">Kalkulator Buah</a>
            </nav>

            <div class="flex items-center gap-2">
                <div class="relative hidden md:block" @click.outside="accountOpen = false">
                    <button type="button" @click="accountOpen = !accountOpen" class="flex items-center gap-3 rounded-md border border-[#dce1d6] bg-white px-3 py-2 text-left transition hover:bg-[#edf1e8]" :aria-expanded="accountOpen.toString()">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[#123d2c] text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="hidden flex-col lg:flex"><strong class="max-w-36 truncate text-xs text-[#1c3328]">{{ auth()->user()->name }}</strong><small class="text-[10px] text-[#687168]">{{ auth()->user()->region ?? 'Pusat' }}</small></span>
                        <svg class="h-4 w-4 text-[#687168]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="accountOpen" x-cloak x-transition class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-[#dce1d6] bg-white p-2 shadow-xl">
                        <button type="button" @click="accountOpen = false; isLogoutModalOpen = true" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Keluar dari akun</button>
                    </div>
                </div>
                <button type="button" @click="mobileNav = !mobileNav" class="grid h-11 w-11 place-items-center rounded-md border border-[#dce1d6] text-[#123d2c] md:hidden" :aria-expanded="mobileNav.toString()" aria-label="Buka menu">
                    <svg x-show="!mobileNav" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileNav" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <nav x-show="mobileNav" x-cloak x-transition @click.outside="mobileNav = false" class="border-t border-[#dce1d6] bg-[#f7f7f0] px-4 py-4 md:hidden" aria-label="Navigasi dashboard mobile">
            <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold text-[#1c3328]">
                <a @click="mobileNav = false; activeView = 'dashboard'" href="#ringkasan" class="rounded-md px-3 py-3 hover:bg-[#e9efe6]">Ringkasan</a>
                <a @click="mobileNav = false; activeView = 'dashboard'" href="#data-po" class="rounded-md px-3 py-3 hover:bg-[#e9efe6]">Data PO</a>
                <a @click="mobileNav = false; activeView = 'fruit'" href="#kalkulator-buah" class="rounded-md px-3 py-3 hover:bg-[#e9efe6]">Kalkulator Buah</a>
                <button type="button" @click="mobileNav = false; isLogoutModalOpen = true" class="mt-2 rounded-md bg-[#123d2c] px-3 py-3 text-left text-white">Keluar dari akun</button>
            </div>
        </nav>
    </header>

    @include('components.fruit-calculator')

    <div x-show="activeView === 'dashboard'" class="min-h-screen p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <!-- ALERT SUCCESS / ERROR -->
        @if (session('success'))
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

        <!-- HEADER DASHBOARD -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5 overflow-hidden bg-[#123d2c] p-6 sm:p-8 rounded-2xl border border-[#123d2c] shadow-sm text-white">
            <div>
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[.2em] text-[#d0e0cc]">Sistem Purchase Order</p>
                <h1 class="sbg-heading text-2xl sm:text-3xl font-extrabold tracking-tight">Dashboard Purchase Order</h1>
                <p class="text-xs sm:text-sm text-[#cedbca] mt-2">
                    Selamat datang, <span class="font-bold text-white">{{ auth()->user()->name }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#f3a51d] text-[#0a291e] ml-1">
                        {{ auth()->user()->region ?? 'Pusat' }}
                    </span>
                </p>
            </div>
            
            <div class="flex items-center space-x-3 w-full sm:w-auto">
                <button @click="isCreateModalOpen = true" class="flex-1 sm:flex-initial px-4 py-3 bg-[#f3a51d] hover:bg-[#ffc055] active:scale-95 text-[#0a291e] font-bold rounded-md text-xs transition shadow-sm flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Buat PO Baru</span>
                </button>

                <button 
                    type="button" 
                    @click="$dispatch('open-analisa-modal')"
                    class="px-4 py-3 border border-[#80917b] bg-white/5 text-white font-semibold rounded-md text-xs hover:bg-white/10 transition flex items-center gap-1.5 shadow-sm">
                    <span>📊</span>
                    <span>Analisa</span>
                </button>

            </div>
        </div>

        <!-- METRIC SUMMARY CARDS -->
        <div id="ringkasan" class="scroll-mt-28 space-y-4">
            <div class="flex items-end justify-between gap-4 pb-1">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[.2em] text-[#74825f]">Ringkasan operasional</p>
                    <h2 class="sbg-heading mt-1 text-xl font-extrabold text-[#1c3328]">Kondisi Purchase Order</h2>
                </div>
                <span class="hidden text-xs text-[#687168] sm:block">Klik kartu untuk melihat detail</span>
            </div>
            <!-- Baris 1: Status PO & Total Nilai -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">  
                <!-- Total Nilai PO -->
                <div @click="openSummaryModal('Total Nilai PO')" 
                    class="sbg-card sbg-card-interactive cursor-pointer bg-[#123d2c] p-6 text-white group">
                    <div class="mb-7 flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-md bg-[#f3a51d] font-bold text-[#0a291e]">Rp</span><span class="text-[10px] font-bold tracking-widest text-[#b9cbb4]">01</span></div>
                    <p class="text-xs font-bold uppercase tracking-wider text-[#cedbca]">Total Nilai PO</p>
                    <div class="sbg-heading mt-2 text-2xl font-extrabold leading-tight break-words">
                        <span class="text-sm font-semibold text-[#d8e3d5]">Rp</span> 
                        <span>{{ number_format($summary['total_nilai_po'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- PO Pending -->
                <div @click="openSummaryModal('PO Pending')" 
                    class="sbg-card sbg-card-interactive cursor-pointer p-6 group">
                    <div class="mb-7 flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-md bg-[#fff3d7] text-lg text-[#a66a00]">◷</span><span class="text-[10px] font-bold tracking-widest text-[#839078]">02</span></div>
                    <p class="text-xs font-bold uppercase tracking-wider text-[#74825f]">PO Pending</p>
                    <p class="sbg-heading mt-2 text-3xl font-extrabold text-[#1c3328]">{{ number_format($summary['po_pending'] ?? 0) }}</p>
                </div>

                <!-- PO Selesai -->
                <div @click="openSummaryModal('PO Selesai')" 
                    class="sbg-card sbg-card-interactive cursor-pointer p-6 group">
                    <div class="mb-7 flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-md bg-[#e9efe6] text-lg font-bold text-[#123d2c]">✓</span><span class="text-[10px] font-bold tracking-widest text-[#839078]">03</span></div>
                    <p class="text-xs font-bold uppercase tracking-wider text-[#74825f]">PO Selesai</p>
                    <p class="sbg-heading mt-2 text-3xl font-extrabold text-[#1c3328]">{{ number_format($summary['po_selesai'] ?? 0) }}</p>
                </div>
            </div>

            <!-- Baris 2: Card Fee Mitra, Bayar Supplier, & Fee KSPPG -->
            <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-3' : 'max-w-md' }} gap-4">
                
                <!-- Card Fee Mitra -->
                <div class="sbg-card sbg-card-interactive border-l-4 border-l-[#f3a51d] p-5 space-y-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-[#896016]">Sisa Fee Mitra</p>
                        <p class="sbg-heading text-2xl font-extrabold text-[#1c3328] mt-1">
                            Rp {{ number_format($summary['total_laba_mitra'] ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                    <button type="button" 
                            @click="$dispatch('open-modal-fee-mitra', { items: allPoData, total: {{ $summary['total_laba_mitra'] ?? 0 }}, mode: 'detail' })"
                            class="w-full bg-[#123d2c] hover:bg-[#0a291e] active:scale-95 text-white font-bold py-2.5 px-4 rounded-md text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                        <span>$</span> Lihat Detail
                    </button>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <button type="button" @click="$dispatch('open-modal-fee-mitra', { items: allPoData, total: {{ $summary['total_laba_mitra'] ?? 0 }}, mode: 'print' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                            🖨️ Print
                        </button>
                        <button type="button" @click="$dispatch('open-modal-fee-mitra', { items: allPoData, total: {{ $summary['total_laba_mitra'] ?? 0 }}, mode: 'download' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                            📥 Download
                        </button>
                    </div>
                </div>

                @if(auth()->user()->canManageFinancials())
                    <!-- Card Bayar Supplier -->
                    <div class="sbg-card sbg-card-interactive border-l-4 border-l-[#74825f] p-5 space-y-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#74825f]">Sisa Bayar Supplier</p>
                            <p class="sbg-heading text-2xl font-extrabold text-[#1c3328] mt-1">
                                Rp {{ number_format($summary['total_bayar_supplier'] ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <button type="button" 
                                @click="$dispatch('open-modal-supplier', { items: allPoData, total: {{ $summary['total_bayar_supplier'] ?? 0 }} , mode: 'detail', paymentType: 'supplier' })"
                                class="w-full bg-[#123d2c] hover:bg-[#0a291e] active:scale-95 text-white font-bold py-2.5 px-4 rounded-md text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                            <span>$</span> Lihat Detail
                        </button>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <button type="button" @click="$dispatch('open-modal-supplier', { items: allPoData, total: {{ $summary['total_bayar_supplier'] ?? 0 }} , mode: 'print', paymentType: 'supplier' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                                🖨️ Print
                            </button>
                            <button type="button" @click="$dispatch('open-modal-supplier', { items: allPoData, total: {{ $summary['total_bayar_supplier'] ?? 0 }} , mode: 'download', paymentType: 'supplier' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                                📥 Download
                            </button>
                        </div>
                    </div>

                    <!-- Card Fee KSPPG -->
                    <div class="sbg-card sbg-card-interactive border-l-4 border-l-[#123d2c] p-5 space-y-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#576f51]">Sisa Fee KSPPG</p>
                            <p class="sbg-heading text-2xl font-extrabold text-[#1c3328] mt-1">
                                Rp {{ number_format($summary['total_laba_ksppg'] ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <button type="button" 
                                @click="$dispatch('open-modal-fee-ksppg', { items: allPoData, total: {{ $summary['total_laba_ksppg'] ?? 0 }} , mode: 'detail', paymentType: 'ksppg_fee' })"
                                class="w-full bg-[#123d2c] hover:bg-[#0a291e] active:scale-95 text-white font-bold py-2.5 px-4 rounded-md text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                            <span>$</span> Lihat Detail
                        </button>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <button type="button" @click="$dispatch('open-modal-fee-ksppg', { items: allPoData, total: {{ $summary['total_laba_ksppg'] ?? 0 }} , mode: 'print', paymentType: 'ksppg_fee' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                                🖨️ Print
                            </button>
                            <button type="button" @click="$dispatch('open-modal-fee-ksppg', { items: allPoData, total: {{ $summary['total_laba_ksppg'] ?? 0 }} , mode: 'download', paymentType: 'ksppg_fee' })" class="bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold py-2 rounded-md transition flex items-center justify-center gap-1 border border-[#dce1d6]">
                                📥 Download
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- FILTER BAR -->
        <form id="data-po" method="POST" action="{{ route('dashboard') }}" class="sbg-panel scroll-mt-28 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 md:grid-cols-5">
            @csrf
            <input type="text" name="search" value="{{ session('filter_search') }}" placeholder="Cari Barang / Supplier / PO..." class="w-full text-xs sm:text-sm border-gray-200 rounded-xl px-3.5 py-2.5 border focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
            
            <select name="status" class="w-full text-xs sm:text-sm border-gray-200 rounded-xl px-3.5 py-2.5 border focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white">
                <option value="">Semua Status</option>
                <option value="Pending" {{ session('filter_status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                <option value="Proses" {{ session('filter_status') == 'Proses' ? 'selected' : '' }}>Proses</option>
                <option value="Selesai" {{ session('filter_status') == 'Selesai' ? 'selected' : '' }}>Selesai</option>
                <option value="Batal" {{ session('filter_status') == 'Batal' ? 'selected' : '' }}>Batal</option>
            </select>

            <select name="priority" class="w-full text-xs sm:text-sm border-gray-200 rounded-xl px-3.5 py-2.5 border focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white">
                <option value="">Semua Prioritas</option>
                <option value="Urgent" {{ session('filter_priority') == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                <option value="High" {{ session('filter_priority') == 'High' ? 'selected' : '' }}>High</option>
                <option value="Normal" {{ session('filter_priority') == 'Normal' ? 'selected' : '' }}>Normal</option>
            </select>

            <!-- SELECT OPTION DAERAH -->
            <select name="region" class="w-full text-xs sm:text-sm border-gray-200 rounded-xl px-3.5 py-2.5 border focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition bg-white" {{ !auth()->user()->isSuperAdmin() ? 'disabled' : '' }}>
                <option value="">Semua Wilayah</option>
                @foreach($regions as $reg)
                    <option value="{{ $reg->name }}" {{ (session('filter_region') ?? auth()->user()->region) == $reg->name ? 'selected' : '' }}>
                        {{ $reg->name }}
                    </option>
                @endforeach
            </select>

            <div class="flex space-x-2">
                <button type="submit" class="w-full bg-[#123d2c] text-white rounded-md text-xs sm:text-sm font-bold py-2.5 hover:bg-[#0a291e] transition active:scale-95 shadow-sm">Filter</button>
                <a href="{{ route('dashboard.reset') }}" class="px-4 py-2.5 bg-[#e9efe6] text-[#435348] rounded-md text-xs sm:text-sm font-bold hover:bg-[#dce3d3] transition text-center flex items-center justify-center">Reset</a>
            </div>
        </form>

        <!-- TABLE PURCHASE ORDERS -->
        <div class="sbg-panel overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="sbg-heading text-base font-extrabold text-[#1c3328]">Daftar Purchase Order</h2>
                    <p class="mt-1 text-[11px] text-[#687168]">Klik salah satu baris untuk melihat detail lengkap.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-[#e9efe6] border-b border-[#dce1d6] text-[#576f51] uppercase text-[10px] font-bold tracking-wider">
                            <th class="p-4">No. PO</th>
                            <th class="p-4">Tanggal</th>
                            <th class="p-4">Dibuat</th>
                            <th class="p-4">Nama Barang</th>
                            <th class="p-4">Jumlah</th>
                            <th class="p-4">Wilayah</th>
                            <th class="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($purchaseOrders as $po)
                            <tr
                                @click="openDetailModal({{ json_encode($po) }})"
                                @keydown.enter.prevent="openDetailModal({{ json_encode($po) }})"
                                @keydown.space.prevent="openDetailModal({{ json_encode($po) }})"
                                role="button"
                                tabindex="0"
                                aria-label="Lihat detail PO {{ $po->po_number }}"
                                class="cursor-pointer transition hover:bg-[#f1f4ed] focus:bg-[#f1f4ed] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#587547]"
                            >
                                <td class="p-4 font-bold text-gray-900">{{ $po->po_number }}</td>
                                <td class="p-4 text-gray-600">{{ \Carbon\Carbon::parse($po->transaction_date)->format('d/m/Y') }}</td>
                                <td class="p-4 text-gray-600">{{ $po->created_at?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-4 text-gray-900 font-semibold">{{ $po->item_name }}</td>
                                <td class="p-4 text-gray-600">{{ $po->quantity }} {{ $po->unit }}</td>
                                <td class="p-4 text-gray-600">{{ $po->region }}</td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-[11px] font-bold inline-block
                                        @if($po->status_stage == 'Selesai') bg-emerald-100 text-emerald-700 border border-emerald-200
                                        @elseif($po->status_stage == 'Proses') bg-blue-100 text-blue-700 border border-blue-200
                                        @elseif($po->status_stage == 'Pending') bg-amber-100 text-amber-700 border border-amber-200
                                        @else bg-rose-100 text-rose-700 border border-rose-200 @endif">
                                        {{ $po->status_stage }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-10 text-center text-gray-400 font-medium">Belum ada data Purchase Order.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-100">
                {{ $purchaseOrders->links() }}
            </div>
        </div>

        <!-- TABEL DAERAH & AKUN WILAYAH -->
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isKepalaDapur())
            <div class="sbg-panel overflow-hidden mt-8">
                <div class="p-5 border-b border-gray-100 flex flex-wrap justify-between items-center gap-3 bg-gray-50/50">
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900">Daftar Daerah & Penanggung Jawab</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ auth()->user()->isSuperAdmin() ? 'Seluruh akun Admin dan Kepala Dapur' : 'Akun yang terdaftar di wilayah Anda' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="isAddAdminModalOpen = true" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs transition shadow-sm flex items-center gap-1">
                            <span>+</span> Tambah Baru
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm">
                        <thead>
                            <tr class="bg-gray-100/50 border-b border-gray-100 text-gray-400 uppercase text-[10px] font-bold tracking-wider">
                                <th class="p-4">Kode</th>
                                <th class="p-4">Nama Daerah / Dapur</th>
                                <th class="p-4">Penanggung Jawab</th>
                                <th class="p-4">Email / Role</th>
                                <th class="p-4">Tanggal Dibuat</th>
                                @if(auth()->user()->isSuperAdmin())<th class="p-4">Popup Login</th>@endif
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @if(auth()->user()->isSuperAdmin())
                                @foreach($superAdmins as $superAdmin)
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="p-4 font-mono text-xs font-bold text-indigo-600">SUPER</td>
                                        <td class="p-4 font-bold text-gray-900">{{ $superAdmin->region ?? 'Pusat' }}</td>
                                        <td class="p-4 font-medium text-gray-800">{{ $superAdmin->name }}</td>
                                        <td class="p-4 text-gray-600"><span class="block">{{ $superAdmin->email }}</span><span class="mt-1 inline-block rounded-full bg-indigo-100 px-2 py-0.5 text-[9px] font-bold text-indigo-700">SUPER ADMIN</span></td>
                                        <td class="p-4 text-xs text-gray-500">{{ $superAdmin->created_at?->format('d/m/Y') ?? '-' }}</td>
                                        <td class="p-4">@include('components.login-popup-controls', ['account' => $superAdmin, 'accountPopup' => $loginPopupSettings->get($superAdmin->id)])</td>
                                        <td class="p-4 text-center text-xs text-gray-400">-</td>
                                    </tr>
                                @endforeach
                            @endif
                            @forelse($regions as $reg)
                                @php
                                    $regionAccounts = \App\Models\User::where('region', $reg->name)
                                        ->whereIn('role', ['admin', 'kepala_dapur'])
                                        ->orderBy('name')
                                        ->get();
                                @endphp
                                @forelse($regionAccounts as $account)
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="p-4 font-mono text-xs font-bold text-blue-600">{{ $reg->code ?? '-' }}</td>
                                        <td class="p-4 font-bold text-gray-900">{{ $reg->name }}</td>
                                        <td class="p-4 font-medium text-gray-800">{{ $account->name }}</td>
                                        <td class="p-4 text-gray-600">
                                            <span class="block">{{ $account->email }}</span>
                                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[9px] font-bold {{ $account->isKepalaDapur() ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                                {{ $account->isKepalaDapur() ? 'KEPALA DAPUR' : 'ADMIN' }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-xs text-gray-500">{{ $account->created_at?->format('d/m/Y') ?? '-' }}</td>
                                        @if(auth()->user()->isSuperAdmin())
                                            <td class="p-4">@include('components.login-popup-controls', ['account' => $account, 'accountPopup' => $loginPopupSettings->get($account->id)])</td>
                                        @endif
                                        <td class="p-4 text-center">
                                            @if(auth()->user()->isKepalaDapur() && $account->is(auth()->user()))
                                                <button type="button" @click="isResetOwnPasswordModalOpen = true" class="rounded-lg bg-purple-600 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-purple-700 transition shadow-sm">
                                                    Lupa Password
                                                </button>
                                            @elseif(auth()->user()->isSuperAdmin() || $account->role === 'admin')
                                                <div class="flex items-center justify-center gap-2">
                                                    <button type="button" @click="openEditAdminModal({{ json_encode($reg) }}, {{ json_encode($account) }})" class="rounded-lg bg-amber-500 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-amber-600 transition shadow-sm">Edit</button>
                                                    @if(!$account->is(auth()->user()))
                                                        <form action="{{ route('admin.account.destroy', $account) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-rose-700 transition shadow-sm">Hapus</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-[10px] font-bold text-gray-400">Tidak ada aksi</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="p-4 font-mono text-xs font-bold text-blue-600">{{ $reg->code ?? '-' }}</td>
                                        <td class="p-4 font-bold text-gray-900">{{ $reg->name }}</td>
                                        <td colspan="{{ auth()->user()->isSuperAdmin() ? 4 : 3 }}" class="p-4 text-gray-400">Belum ada akun pada wilayah ini.</td>
                                        <td class="p-4 text-center">-</td>
                                    </tr>
                                @endforelse
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()->isSuperAdmin() ? 7 : 6 }}" class="p-10 text-center text-gray-400 font-medium">Belum ada data daerah terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    <!-- ========================================================= -->
    <!-- MODAL DETAIL FEE MITRA                                     -->
    <!-- ========================================================= -->
    <div x-data="partnerFeeModal"
         @open-modal-fee-mitra.window="openModal($event.detail)"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="open = false" class="sbg-modal-panel bg-white w-full max-w-lg rounded-xl p-6 space-y-4 border border-[#dce1d6] relative max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-[#dce1d6] shrink-0">
                <div>
                    <h3 class="text-lg font-extrabold text-[#123d2c]">Rincian Fee Mitra</h3>
                    <p class="text-xs text-[#687168]">Daftar akumulasi fee untuk mitra/dapur</p>
                </div>
                <button @click="open = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold leading-none">&times;</button>
            </div>

            <div class="overflow-y-auto space-y-3 flex-1 pr-1">
                <div x-show="selectableItems().length > 0" class="flex items-center justify-between px-1 pb-1">
                    <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#435348] cursor-pointer">
                        <input type="checkbox" :checked="allSelected()" @change="toggleAll()" class="w-4 h-4 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547]">
                        <span>Pilih semua yang belum dibayar</span>
                    </label>
                    <span class="text-[10px] font-bold text-[#123d2c]" x-text="selectedIds.length + ' dipilih'"></span>
                </div>
                <template x-if="items.length > 0">
                    <div>
                        <template x-for="item in items" :key="item.id">
                            <label class="p-3.5 bg-[#e9efe6]/40 rounded-lg border border-[#dce1d6] flex items-center gap-3 text-xs mb-3" :class="item.is_partner_fee_paid ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'">
                                <input type="checkbox" :value="String(item.id)" x-model="selectedIds" :disabled="item.is_partner_fee_paid" class="w-4 h-4 shrink-0 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547] disabled:cursor-not-allowed">
                                <div class="space-y-0.5 min-w-0 flex-1">
                                    <h4 class="font-bold text-[#1c3328]" x-text="item.item_name"></h4>
                                    <p class="text-[#687168] text-[11px]" x-text="inputDate(item)"></p>
                                    <p class="text-[#687168] text-[11px]" x-text="(item.quantity || 0) + ' ' + (item.unit || '') + ' x ' + formatRupiah(item.partner_margin)"></p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#123d2c] text-sm" x-text="formatRupiah(item.is_partner_fee_paid ? 0 : (item.total_partner_fee || (item.partner_margin * item.quantity)))"></span>
                                    <span :class="item.is_partner_fee_paid ? 'bg-[#e9efe6] text-[#123d2c]' : 'bg-amber-100 text-amber-700'" class="block text-[10px] px-2 py-0.5 rounded-full font-bold mt-1 text-center" x-text="item.is_partner_fee_paid ? 'Lunas' : 'Belum Bayar'"></span>
                                </div>
                            </label>
                        </template>
                    </div>
                </template>
                <template x-if="items.length === 0">
                    <div class="py-8 text-center text-[#687168] text-xs font-medium">Belum ada rincian transaksi fee mitra.</div>
                </template>
            </div>

            <div class="pt-3 border-t border-[#dce1d6] shrink-0 space-y-3">
                <div class="flex justify-between items-center text-sm font-bold">
                    <span class="text-[#435348]">Total Data Dipilih:</span>
                    <span class="text-lg font-black text-[#123d2c]" x-text="formatRupiah(selectedTotal())"></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button type="button" @click="open = false" class="py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Tutup</button>
                    <button x-show="mode === 'print'" type="button" @click="printSelectedNote()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Print Nota</button>
                    <button x-show="mode === 'download'" type="button" @click="downloadSelectedPdf()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Download PDF</button>
                    <button x-show="mode === 'detail'" type="button" @click="sendToWhatsApp()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Kirim ke WhatsApp</button>
                    @if(auth()->user()->isSuperAdmin())
                        <form x-data="{ confirming: false, submitting: false }" x-show="mode === 'detail'" x-effect="if (!open) confirming = false" @submit.prevent="if (confirming && !submitting) { submitting = true; $el.submit(); }" action="{{ route('dashboard.partner-fee.pay-all') }}" method="POST" class="sm:col-span-2">
                            @csrf
                            <template x-for="item in items.filter(item => !item.is_partner_fee_paid)" :key="item.id">
                                <input type="hidden" name="ids[]" :value="item.id">
                            </template>
                            <button type="button" @click="confirming = true; $nextTick(() => $refs.cancelPayment.focus())" :disabled="submitting || !items.some(item => !item.is_partner_fee_paid)" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Bayar Semua Fee Mitra</button>
                            <div x-show="confirming" x-cloak x-transition.opacity @keydown.escape.stop.prevent="if (!submitting) confirming = false" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-[#0a291e]/40 backdrop-blur-sm p-5">
                                <div role="dialog" aria-modal="true" aria-labelledby="confirm-partner-payment-title" aria-describedby="confirm-partner-payment-description" class="sbg-modal-confirm w-full rounded-lg bg-white p-6 shadow-xl space-y-4">
                                    <h4 id="confirm-partner-payment-title" class="text-lg font-extrabold text-[#123d2c]">Bayar Semua Fee Mitra?</h4>
                                    <p id="confirm-partner-payment-description" class="text-sm text-[#435348]">Apakah Anda yakin ingin bayar semua?</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button x-ref="cancelPayment" type="button" @click="confirming = false" :disabled="submitting" class="rounded-md bg-[#f7f7f0] px-4 py-2.5 text-xs font-bold text-[#435348] hover:bg-[#e9efe6] disabled:opacity-50">Batal</button>
                                        <button type="submit" :disabled="submitting" class="rounded-md bg-[#123d2c] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0a291e] disabled:opacity-50" x-text="submitting ? 'Memproses...' : 'Ya, Bayar Semua'">Ya, Bayar Semua</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL DETAIL BAYAR SUPPLIER                                     -->
    <!-- ========================================================= -->
    <div x-data="partnerFeeModal"
         @open-modal-supplier.window="openModal($event.detail)"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="open = false" class="sbg-modal-panel bg-white w-full max-w-lg rounded-xl p-6 space-y-4 border border-[#dce1d6] relative max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-[#dce1d6] shrink-0">
                <div>
                    <h3 class="text-lg font-extrabold text-[#123d2c]">Rincian Bayar Supplier</h3>
                    <p class="text-xs text-[#687168]">Daftar akumulasi pembayaran supplier</p>
                </div>
                <button @click="open = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold leading-none">&times;</button>
            </div>

            <div class="overflow-y-auto space-y-3 flex-1 pr-1">
                <div x-show="selectableItems().length > 0" class="flex items-center justify-between px-1 pb-1">
                    <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#435348] cursor-pointer">
                        <input type="checkbox" :checked="allSelected()" @change="toggleAll()" class="w-4 h-4 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547]">
                        <span>Pilih semua yang belum dibayar</span>
                    </label>
                    <span class="text-[10px] font-bold text-[#123d2c]" x-text="selectedIds.length + ' dipilih'"></span>
                </div>
                <template x-if="items.length > 0">
                    <div>
                        <template x-for="item in items" :key="item.id">
                            <label class="p-3.5 bg-[#e9efe6]/40 rounded-lg border border-[#dce1d6] flex items-center gap-3 text-xs mb-3" :class="item.is_supplier_paid ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'">
                                <input type="checkbox" :value="String(item.id)" x-model="selectedIds" :disabled="item.is_supplier_paid" class="w-4 h-4 shrink-0 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547] disabled:cursor-not-allowed">
                                <div class="space-y-0.5 min-w-0 flex-1">
                                    <h4 class="font-bold text-[#1c3328]" x-text="item.item_name"></h4>
                                    <p class="text-[#687168] text-[11px]" x-text="inputDate(item)"></p>
                                    <p class="text-[#123d2c] font-semibold text-[11px]" x-text="'Supplier: ' + (item.supplier_name || '-')"></p>
                                    <p class="text-[#687168] text-[11px]" x-text="(item.quantity || 0) + ' ' + (item.unit || '') + ' x ' + formatRupiah(item.supplier_price)"></p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#123d2c] text-sm" x-text="formatRupiah(item.is_supplier_paid ? 0 : (item.total_supplier_amount || (item.supplier_price * item.quantity)))"></span>
                                    <span :class="item.is_supplier_paid ? 'bg-[#e9efe6] text-[#123d2c]' : 'bg-amber-100 text-amber-700'" class="block text-[10px] px-2 py-0.5 rounded-full font-bold mt-1 text-center" x-text="item.is_supplier_paid ? 'Lunas' : 'Belum Bayar'"></span>
                                </div>
                            </label>
                        </template>
                    </div>
                </template>
                <template x-if="items.length === 0">
                    <div class="py-8 text-center text-[#687168] text-xs font-medium">Belum ada rincian transaksi pembayaran supplier.</div>
                </template>
            </div>

            <div class="pt-3 border-t border-[#dce1d6] shrink-0 space-y-3">
                <div class="flex justify-between items-center text-sm font-bold">
                    <span class="text-[#435348]">Total Data Dipilih:</span>
                    <span class="text-lg font-black text-[#123d2c]" x-text="formatRupiah(selectedTotal())"></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button type="button" @click="open = false" class="py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Tutup</button>
                    <button x-show="mode === 'print'" type="button" @click="printSelectedNote()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Print Nota</button>
                    <button x-show="mode === 'download'" type="button" @click="downloadSelectedPdf()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Download PDF</button>
                    <button x-show="mode === 'detail'" type="button" @click="sendToWhatsApp()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Kirim ke WhatsApp</button>
                    @if(auth()->user()->isSuperAdmin())
                        <form x-data="{ confirming: false, submitting: false }" x-show="mode === 'detail'" x-effect="if (!open) confirming = false" @submit.prevent="if (confirming && !submitting) { submitting = true; $el.submit(); }" action="{{ route('dashboard.supplier.pay-all') }}" method="POST" class="sm:col-span-2">
                            @csrf
                            <template x-for="item in items.filter(item => !item.is_supplier_paid)" :key="item.id">
                                <input type="hidden" name="ids[]" :value="item.id">
                            </template>
                            <button type="button" @click="confirming = true; $nextTick(() => $refs.cancelPayment.focus())" :disabled="submitting || !items.some(item => !item.is_supplier_paid)" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Bayar Semua Supplier</button>
                            <div x-show="confirming" x-cloak x-transition.opacity @keydown.escape.stop.prevent="if (!submitting) confirming = false" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-[#0a291e]/40 backdrop-blur-sm p-5">
                                <div role="dialog" aria-modal="true" aria-labelledby="confirm-supplier-payment-title" aria-describedby="confirm-supplier-payment-description" class="sbg-modal-confirm w-full rounded-lg bg-white p-6 shadow-xl space-y-4">
                                    <h4 id="confirm-supplier-payment-title" class="text-lg font-extrabold text-[#123d2c]">Bayar Semua Supplier?</h4>
                                    <p id="confirm-supplier-payment-description" class="text-sm text-[#435348]">Apakah Anda yakin ingin bayar semua?</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button x-ref="cancelPayment" type="button" @click="confirming = false" :disabled="submitting" class="rounded-md bg-[#f7f7f0] px-4 py-2.5 text-xs font-bold text-[#435348] hover:bg-[#e9efe6] disabled:opacity-50">Batal</button>
                                        <button type="submit" :disabled="submitting" class="rounded-md bg-[#123d2c] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0a291e] disabled:opacity-50" x-text="submitting ? 'Memproses...' : 'Ya, Bayar Semua'">Ya, Bayar Semua</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL DETAIL FEE KSPPG                                     -->
    <!-- ========================================================= -->
    <div x-data="partnerFeeModal"
         @open-modal-fee-ksppg.window="openModal($event.detail)"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="open = false" class="sbg-modal-panel bg-white w-full max-w-lg rounded-xl p-6 space-y-4 border border-[#dce1d6] relative max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-[#dce1d6] shrink-0">
                <div>
                    <h3 class="text-lg font-extrabold text-[#123d2c]">Rincian Fee KSPPG</h3>
                    <p class="text-xs text-[#687168]">Daftar akumulasi fee KSPPG</p>
                </div>
                <button @click="open = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold leading-none">&times;</button>
            </div>

            <div class="overflow-y-auto space-y-3 flex-1 pr-1">
                <div x-show="selectableItems().length > 0" class="flex items-center justify-between px-1 pb-1">
                    <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#435348] cursor-pointer">
                        <input type="checkbox" :checked="allSelected()" @change="toggleAll()" class="w-4 h-4 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547]">
                        <span>Pilih semua yang belum dibayar</span>
                    </label>
                    <span class="text-[10px] font-bold text-[#123d2c]" x-text="selectedIds.length + ' dipilih'"></span>
                </div>
                <template x-if="items.length > 0">
                    <div>
                        <template x-for="item in items" :key="item.id">
                            <label class="p-3.5 bg-[#e9efe6]/40 rounded-lg border border-[#dce1d6] flex items-center gap-3 text-xs mb-3" :class="item.is_ksppg_fee_paid ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'">
                                <input type="checkbox" :value="String(item.id)" x-model="selectedIds" :disabled="item.is_ksppg_fee_paid" class="w-4 h-4 shrink-0 rounded border-[#dce1d6] text-[#123d2c] focus:ring-[#587547] disabled:cursor-not-allowed">
                                <div class="space-y-0.5 min-w-0 flex-1">
                                    <h4 class="font-bold text-[#1c3328]" x-text="item.item_name"></h4>
                                    <p class="text-[#687168] text-[11px]" x-text="inputDate(item)"></p>
                                    <p class="text-[#687168] text-[11px]" x-text="(item.quantity || 0) + ' ' + (item.unit || '') + ' x ' + formatRupiah(item.ksppg_margin_per_unit)"></p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#123d2c] text-sm" x-text="formatRupiah(item.is_ksppg_fee_paid ? 0 : (item.total_ksppg_fee || (item.ksppg_margin_per_unit * item.quantity)))"></span>
                                    <span :class="item.is_ksppg_fee_paid ? 'bg-[#e9efe6] text-[#123d2c]' : 'bg-amber-100 text-amber-700'" class="block text-[10px] px-2 py-0.5 rounded-full font-bold mt-1 text-center" x-text="item.is_ksppg_fee_paid ? 'Lunas' : 'Belum Bayar'"></span>
                                </div>
                            </label>
                        </template>
                    </div>
                </template>
                <template x-if="items.length === 0">
                    <div class="py-8 text-center text-[#687168] text-xs font-medium">Belum ada rincian transaksi fee KSPPG.</div>
                </template>
            </div>

            <div class="pt-3 border-t border-[#dce1d6] shrink-0 space-y-3">
                <div class="flex justify-between items-center text-sm font-bold">
                    <span class="text-[#435348]">Total Data Dipilih:</span>
                    <span class="text-lg font-black text-[#123d2c]" x-text="formatRupiah(selectedTotal())"></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button type="button" @click="open = false" class="py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Tutup</button>
                    <button x-show="mode === 'print'" type="button" @click="printSelectedNote()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Print Nota</button>
                    <button x-show="mode === 'download'" type="button" @click="downloadSelectedPdf()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Download PDF</button>
                    <button x-show="mode === 'detail'" type="button" @click="sendToWhatsApp()" :disabled="selectedIds.length === 0" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Kirim ke WhatsApp</button>
                    @if(auth()->user()->isSuperAdmin())
                        <form x-data="{ confirming: false, submitting: false }" x-show="mode === 'detail'" x-effect="if (!open) confirming = false" @submit.prevent="if (confirming && !submitting) { submitting = true; $el.submit(); }" action="{{ route('dashboard.ksppg-fee.pay-all') }}" method="POST" class="sm:col-span-2">
                            @csrf
                            <template x-for="item in items.filter(item => !item.is_ksppg_fee_paid)" :key="item.id">
                                <input type="hidden" name="ids[]" :value="item.id">
                            </template>
                            <button type="button" @click="confirming = true; $nextTick(() => $refs.cancelPayment.focus())" :disabled="submitting || !items.some(item => !item.is_ksppg_fee_paid)" class="w-full py-2.5 px-3 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm disabled:bg-gray-300 disabled:cursor-not-allowed">Bayar Semua Fee KSPPG</button>
                            <div x-show="confirming" x-cloak x-transition.opacity @keydown.escape.stop.prevent="if (!submitting) confirming = false" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-[#0a291e]/40 backdrop-blur-sm p-5">
                                <div role="dialog" aria-modal="true" aria-labelledby="confirm-ksppg-payment-title" aria-describedby="confirm-ksppg-payment-description" class="sbg-modal-confirm w-full rounded-lg bg-white p-6 shadow-xl space-y-4">
                                    <h4 id="confirm-ksppg-payment-title" class="text-lg font-extrabold text-[#123d2c]">Bayar Semua Fee KSPPG?</h4>
                                    <p id="confirm-ksppg-payment-description" class="text-sm text-[#435348]">Apakah Anda yakin ingin bayar semua?</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button x-ref="cancelPayment" type="button" @click="confirming = false" :disabled="submitting" class="rounded-md bg-[#f7f7f0] px-4 py-2.5 text-xs font-bold text-[#435348] hover:bg-[#e9efe6] disabled:opacity-50">Batal</button>
                                        <button type="submit" :disabled="submitting" class="rounded-md bg-[#123d2c] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0a291e] disabled:opacity-50" x-text="submitting ? 'Memproses...' : 'Ya, Bayar Semua'">Ya, Bayar Semua</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

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
    <div x-show="isLogoutModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="isLogoutModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-sm rounded-xl p-6 text-center space-y-4 border border-[#dce1d6] relative">
            <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </div>
            
            <div class="space-y-1">
                <h3 class="text-lg font-extrabold text-[#1c3328]">Konfirmasi Logout</h3>
                <p class="text-xs text-[#687168]">Apakah Anda yakin ingin keluar dari sistem SPPG?</p>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <button type="button" @click="isLogoutModalOpen = false" class="py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">
                    Batal
                </button>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-md text-xs transition shadow-sm">
                        Ya, Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

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
    <div x-show="isCreateModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-start sm:items-center justify-center p-2 sm:p-4"
        x-cloak>

        <div @click.away="isCreateModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-2xl rounded-xl overflow-hidden p-4 sm:p-6 space-y-5 relative max-h-[90vh] overflow-y-auto border border-[#dce1d6]">
            
            <div class="sticky top-0 z-10 -mx-4 -mt-4 sm:-mx-6 sm:-mt-6 px-4 sm:px-6 py-4 bg-white/95 backdrop-blur border-b border-[#dce1d6] flex items-center justify-between">
                <div>
                    <h3 class="text-lg sm:text-xl font-extrabold text-[#1c3328]">Tambah Purchase Order</h3>
                    <p class="mt-0.5 text-[11px] text-[#687168]">Lengkapi informasi transaksi dan rincian harga.</p>
                </div>
                <button type="button" @click="isCreateModalOpen = false" aria-label="Tutup" class="w-9 h-9 shrink-0 rounded-full bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#687168] flex items-center justify-center text-xl font-bold transition">&times;</button>
            </div>

            <form action="{{ route('dashboard.po.store') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Kelompok 1: Informasi Transaksi -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">1. Informasi Transaksi</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : 'md:grid-cols-3' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Tanggal</label>
                            <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Kategori</label>
                            <select name="category" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="" disabled selected>Pilih Kategori</option>
                                <option value="Karbo">Karbo</option>
                                <option value="Protein Nabati">Protein Nabati</option>
                                <option value="Protein Hewani">Protein Hewani</option>
                                <option value="Sayur">Sayur</option>
                                <option value="Buah">Buah</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Prioritas</label>
                            <select name="priority" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="Normal">Normal</option>
                                <option value="Tinggi">Tinggi</option>
                                <option value="Rendah">Rendah</option>
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
                </div>

                <!-- Kelompok 2: Barang & Supplier -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">2. {{ auth()->user()->canManageFinancials() ? 'Barang & Supplier' : 'Informasi Barang' }}</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : '' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Barang / PO</label>
                            <input type="text" name="item_name" placeholder="Contoh: Muli" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Supplier</label>
                            <input type="text" name="supplier_name" placeholder="Nama Supplier" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Jumlah (Qty)</label>
                            <input type="number" step="any" name="quantity" placeholder="106" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Satuan</label>
                            <input type="text" name="unit" placeholder="Kg, Pcs, Box" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                    </div>
                </div>

                <!-- Kelompok 3: Detail Harga -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6] bg-[#e9efe6]/40 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">3. Rincian Harga & Margin</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-3 items-end">
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Harga Supplier</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="supplier_price" placeholder="8.500" x-model="createPrices.supplier_price" @input="createPrices.supplier_price = formatCurrencyInput($event.target)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                            </div>
                        </div>
                        @endif
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Harga Maker</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="maker_price" placeholder="13.500" x-model="createPrices.maker_price" @input="createPrices.maker_price = formatCurrencyInput($event.target)"
                                    @if(auth()->user()->canManageFinancials()) :disabled="!hasMinimumCurrencyDigits(createPrices.supplier_price)" @endif
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
                </div>

                <!-- Kelompok 4: Tahapan & Catatan -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">4. Status & Catatan Tambahan</p>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Tahapan Status</label>
                        <select name="status_stage" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                            <option value="" disabled selected>Pilih Tahapan Status</option>
                            <option value="Cari Supplier">Cari Supplier</option>
                            <option value="Sortir">Sortir</option>
                            <option value="Pengiriman">Pengiriman</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Retur">Retur</option>
                            <option value="Kekurangan">Kekurangan</option>
                            <option value="Perubahan Item">Perubahan Item</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Catatan</label>
                        <textarea name="notes" rows="2" placeholder="Contoh: Naik 11kg 148000" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition"></textarea>
                    </div>
                </div>

                <div class="sticky bottom-0 z-10 -mx-4 -mb-4 sm:-mx-6 sm:-mb-6 px-4 sm:px-6 py-4 bg-white/95 backdrop-blur flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-[#dce1d6]">
                    <button type="button" @click="isCreateModalOpen = false" class="w-full sm:w-auto px-5 py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Batal</button>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm">Simpan PO</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 2: DETAIL PO                                        -->
    <!-- ========================================================= -->
    <div x-show="isDetailModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-start sm:items-center justify-center p-2 sm:p-4"
        x-cloak>

        <div @click.away="isDetailModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-sm sm:max-w-md rounded-xl overflow-hidden p-6 space-y-4 relative max-h-[92vh] overflow-y-auto border border-[#dce1d6]">
            
            <!-- HEADER -->
            <div class="flex items-start justify-between border-b border-[#dce1d6] pb-3">
                <div class="space-y-1">
                    <h3 class="text-xl font-extrabold text-[#1c3328] leading-none" x-text="selectedPo.item_name"></h3>
                    <span class="inline-block px-3 py-0.5 text-[11px] font-bold bg-[#e9efe6] text-[#123d2c] rounded-full border border-[#dce1d6]" 
                        x-text="selectedPo.status_stage || 'Selesai'">
                    </span>
                </div>
                <button @click="isDetailModalOpen = false" class="text-[#687168] hover:text-[#435348] text-xl font-bold p-1 leading-none">&times;</button>
            </div>

            <!-- DETAIL LIST -->
            <div class="space-y-2.5 text-xs sm:text-sm">
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Tanggal</span>
                    <span class="font-bold text-[#1c3328]" x-text="formatDate(selectedPo.transaction_date)"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Dibuat</span>
                    <span class="font-bold text-[#1c3328] text-right" x-text="formatDate(selectedPo.created_at)"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Prioritas</span>
                    <span class="px-2.5 py-0.5 text-xs font-bold bg-[#e9efe6] text-[#123d2c] rounded-full border border-[#dce1d6]" 
                        x-text="selectedPo.priority || 'Normal'">
                    </span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Tahapan</span>
                    <span class="px-2.5 py-0.5 text-xs font-bold bg-[#e9efe6] text-[#123d2c] rounded-full border border-[#dce1d6]" 
                        x-text="selectedPo.status_stage || 'Selesai'">
                    </span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Kategori</span>
                    <span class="font-bold text-[#1c3328]" x-text="selectedPo.category || '-'"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Supplier</span>
                    <span class="font-bold text-[#1c3328]" x-text="selectedPo.supplier_name || '-'"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Qty</span>
                    <span class="font-bold text-[#1c3328]" x-text="(selectedPo.quantity || 0) + ' ' + (selectedPo.unit || 'Kg')"></span>
                </div>
                @if(auth()->user()->canManageFinancials())
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Harga Supplier</span>
                    <span class="font-bold text-[#1c3328]" x-text="formatRupiah(selectedPo.supplier_price)"></span>
                </div>
                @endif
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Harga Maker</span>
                    <span class="font-bold text-[#1c3328]" x-text="formatRupiah(selectedPo.maker_price)"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Laba Mitra/unit</span>
                    <span class="font-bold text-[#1c3328]" x-text="formatRupiah(selectedPo.partner_margin)"></span>
                </div>
                <!-- TOTALS -->
                @if(auth()->user()->canManageFinancials())
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Sisa Bayar Supplier</span>
                    <span class="font-black text-[#123d2c]" x-text="formatRupiah(selectedPo.is_supplier_paid ? 0 : (selectedPo.total_supplier_amount || (selectedPo.supplier_price * selectedPo.quantity)))"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Sisa Laba Mitra</span>
                    <span class="font-black text-[#123d2c]" x-text="formatRupiah(selectedPo.is_partner_fee_paid ? 0 : (selectedPo.total_partner_fee || (selectedPo.partner_margin * selectedPo.quantity)))"></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                    <span class="text-[#687168] font-medium">Sisa Laba KSPPG</span>
                    <span class="font-black text-[#123d2c]" x-text="formatRupiah(selectedPo.is_ksppg_fee_paid ? 0 : (selectedPo.total_ksppg_fee ?? 0))"></span>
                </div>
                @endif
                <div class="flex justify-between items-start py-1">
                    <span class="text-[#687168] font-medium">Catatan</span>
                    <span class="font-bold text-[#1c3328] text-right max-w-[200px]" x-text="selectedPo.notes || '-'"></span>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="space-y-2 pt-2">
                @if(auth()->user()->canManageFinancials())
                <form :action="'/dashboard/po/' + selectedPo.id + '/toggle-payment'" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="payment_type" value="partner_fee">
                    <button type="submit" 
                        :class="selectedPo.is_partner_fee_paid ? 'bg-[#123d2c] hover:bg-[#0a291e]' : 'bg-[#123d2c] hover:bg-[#123d2c]'"
                        class="w-full py-2.5 text-white font-bold rounded-lg text-xs transition flex items-center justify-center space-x-2 shadow-sm">
                        <span x-text="selectedPo.is_partner_fee_paid ? '✓ Lunas Mitra' : '$ Bayar Mitra'"></span>
                    </button>
                </form>

                <form :action="'/dashboard/po/' + selectedPo.id + '/toggle-payment'" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="payment_type" value="supplier">
                    <button type="submit" 
                        :class="selectedPo.is_supplier_paid ? 'bg-[#123d2c] hover:bg-[#0a291e]' : 'bg-[#123d2c] hover:bg-[#123d2c]'"
                        class="w-full py-2.5 text-white font-bold rounded-lg text-xs transition flex items-center justify-center space-x-2 shadow-sm">
                        <span x-text="selectedPo.is_supplier_paid ? '✓ Lunas Supplier' : '$ Bayar Supplier'"></span>
                    </button>
                </form>

                <form :action="'/dashboard/po/' + selectedPo.id + '/toggle-payment'" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="payment_type" value="ksppg_fee">
                    <button type="submit" 
                        :class="selectedPo.is_ksppg_fee_paid ? 'bg-[#123d2c] hover:bg-[#0a291e]' : 'bg-[#123d2c] hover:bg-[#123d2c]'"
                        class="w-full py-2.5 text-white font-bold rounded-lg text-xs transition flex items-center justify-center space-x-2 shadow-sm">
                        <span x-text="selectedPo.is_ksppg_fee_paid ? '✓ Lunas KSPPG' : '$ Bayar KSPPG'"></span>
                    </button>
                </form>
                @endif

                <div class="flex space-x-2">
                    <button type="button" 
                        @click="openEditModal(selectedPo)" 
                        class="flex-1 py-2.5 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-lg text-xs transition shadow-sm text-center">
                        Edit
                    </button>
                    
                    <form :action="'/dashboard/po/' + selectedPo.id" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus PO ini?')" class="w-24">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                            Hapus
                        </button>
                    </form>
                </div>

                <!-- DYNAMIC TRIGGER NOTA DAPUR -->
                <button type="button" 
                        @click="isDetailModalOpen = false; $dispatch('open-nota-dapur', { 
                            po_number: selectedPo.po_number,
                            verification_url: selectedPo.verification_url,
                            item_name: selectedPo.item_name,
                            category: selectedPo.category,
                            quantity: selectedPo.quantity,
                            unit: selectedPo.unit,
                            maker_price: selectedPo.maker_price,
                            transaction_date: formatDate(selectedPo.transaction_date),
                            priority: selectedPo.priority || 'Normal',
                            status_stage: selectedPo.status_stage || 'Selesai',
                            notes: selectedPo.notes
                        })" 
                        class="w-full py-2.5 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center justify-center gap-1.5">
                    <span>🖨️</span> Buat Nota Dapur
                </button>
            </div>

        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 3: EDIT PO                                          -->
    <!-- ========================================================= -->
    <div x-show="isEditModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
        x-cloak>

        <div @click.away="isEditModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-2xl rounded-xl overflow-hidden p-4 sm:p-6 space-y-5 relative max-h-[90vh] overflow-y-auto border border-[#dce1d6]">
            
            <div class="sticky top-0 z-10 -mx-4 -mt-4 sm:-mx-6 sm:-mt-6 px-4 sm:px-6 py-4 bg-white/95 backdrop-blur border-b border-[#dce1d6] flex items-center justify-between">
                <div>
                    <h3 class="text-lg sm:text-xl font-extrabold text-[#1c3328]">Edit Purchase Order</h3>
                    <p class="mt-0.5 text-[11px] text-[#687168]" x-text="editPo.po_number || 'Perbarui informasi transaksi.'"></p>
                </div>
                <button type="button" @click="isEditModalOpen = false" aria-label="Tutup" class="w-9 h-9 shrink-0 rounded-full bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#687168] flex items-center justify-center text-xl font-bold transition">&times;</button>
            </div>

            <form :action="'/dashboard/po/' + editPo.id" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Kelompok 1: Informasi Transaksi -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">1. Informasi Transaksi</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : 'md:grid-cols-3' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Tanggal</label>
                            <input type="date" name="transaction_date" x-model="editPo.transaction_date" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Kategori</label>
                            <select name="category" x-model="editPo.category" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="Karbo">Karbo</option>
                                <option value="Protein Nabati">Protein Nabati</option>
                                <option value="Protein Hewani">Protein Hewani</option>
                                <option value="Sayur">Sayur</option>
                                <option value="Buah">Buah</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Prioritas</label>
                            <select name="priority" x-model="editPo.priority" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="Normal">Normal</option>
                                <option value="Tinggi">Tinggi</option>
                                <option value="Rendah">Rendah</option>
                            </select>
                        </div>
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Penanggung Jawab</label>
                            <select name="created_by" x-model.number="editPo.created_by" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                                <option value="" disabled>Pilih Penanggung Jawab</option>
                                @foreach($admins as $adminOption)
                                    <option value="{{ $adminOption->id }}">{{ $adminOption->name }} — {{ $adminOption->region ?? 'Tanpa Wilayah' }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Kelompok 2: Barang & Supplier -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">2. {{ auth()->user()->canManageFinancials() ? 'Barang & Supplier' : 'Informasi Barang' }}</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-2' : '' }} gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Barang / PO</label>
                            <input type="text" name="item_name" x-model="editPo.item_name" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Supplier</label>
                            <input type="text" name="supplier_name" x-model="editPo.supplier_name" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                        </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Jumlah (Qty)</label>
                            <input type="number" step="any" name="quantity" x-model="editPo.quantity" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Satuan</label>
                            <input type="text" name="unit" x-model="editPo.unit" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                        </div>
                    </div>
                </div>

                <!-- Kelompok 3: Detail Harga -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6] bg-[#e9efe6]/40 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">3. Rincian Harga & Margin</p>
                    <div class="grid grid-cols-1 {{ auth()->user()->canManageFinancials() ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-3 items-end">
                        @if(auth()->user()->canManageFinancials())
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Harga Supplier</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="supplier_price" x-model="editPo.supplier_price" @input="editPo.supplier_price = formatCurrencyInput($event.target)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white">
                            </div>
                        </div>
                        @endif
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Harga Maker</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="maker_price" x-model="editPo.maker_price" @input="editPo.maker_price = formatCurrencyInput($event.target)"
                                    @if(auth()->user()->canManageFinancials()) :disabled="!hasMinimumCurrencyDigits(editPo.supplier_price)" @endif
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white disabled:bg-[#f7f7f0] disabled:text-[#687168] disabled:cursor-not-allowed">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#435348] mb-1">Laba Mitra / unit</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-[#687168]">Rp</span>
                                <input type="text" inputmode="decimal" name="partner_margin" x-model="editPo.partner_margin" @input="editPo.partner_margin = formatCurrencyInput($event.target)"
                                    :disabled="!hasMinimumCurrencyDigits(editPo.maker_price)"
                                    class="w-full pl-9 pr-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition bg-white disabled:bg-[#f7f7f0] disabled:text-[#687168] disabled:cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kelompok 4: Tahapan & Catatan -->
                <div class="space-y-3 rounded-lg border border-[#dce1d6]/80 bg-[#f7f7f0]/60 p-4 shadow-sm">
                    <p class="text-xs font-bold text-[#123d2c] uppercase tracking-wide border-b border-[#dce1d6] pb-1">4. Status & Catatan Tambahan</p>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Tahapan Status</label>
                        <select name="status_stage" x-model="editPo.status_stage" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                            <option value="Cari Supplier">Cari Supplier</option>
                            <option value="Sortir">Sortir</option>
                            <option value="Pengiriman">Pengiriman</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Retur">Retur</option>
                            <option value="Kekurangan">Kekurangan</option>
                            <option value="Perubahan Item">Perubahan Item</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#435348] mb-1">Catatan</label>
                        <textarea name="notes" x-model="editPo.notes" rows="2" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition"></textarea>
                    </div>
                </div>

                <div class="sticky bottom-0 z-10 -mx-4 -mb-4 sm:-mx-6 sm:-mb-6 px-4 sm:px-6 py-4 bg-white/95 backdrop-blur flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-[#dce1d6]">
                    <button type="button" @click="isEditModalOpen = false" class="w-full sm:w-auto px-5 py-2.5 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] font-bold rounded-md text-xs transition">Batal</button>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#123d2c] hover:bg-[#0a291e] text-white font-bold rounded-md text-xs transition shadow-sm">Update PO</button>
                </div>
            </form>

        </div>
    </div>

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
                        <span class="font-medium text-gray-500">Prioritas</span>
                        <span class="font-bold" x-text="po.priority || 'Normal'"></span>
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

    <!-- MODAL SUMMARY PO (TOTAL / SELESAI / PENDING) -->
    <div x-show="isSummaryModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
         x-cloak>

        <div @click.away="isSummaryModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-md rounded-xl overflow-hidden p-6 space-y-4 relative max-h-[85vh] flex flex-col border border-[#dce1d6]">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-2 border-b border-[#dce1d6] flex-shrink-0">
                <h3 class="text-lg font-extrabold text-[#1c3328]" x-text="summaryModalTitle"></h3>
                <button @click="isSummaryModalOpen = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Modal Body (List Items / Empty State) -->
            <div class="overflow-y-auto pr-1 space-y-3 flex-1">
                <template x-if="summaryModalItems.length > 0">
                    <div>
                        <template x-for="item in summaryModalItems" :key="item.id">
                            <div class="p-4 bg-[#f7f7f0]/60 rounded-lg border border-[#dce1d6]/80 space-y-3 mb-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-bold text-[#1c3328] text-sm" x-text="item.item_name"></h4>
                                        <p class="text-xs text-[#687168] mt-0.5" x-text="formatDate(item.transaction_date)"></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 text-xs pt-1 border-t border-[#dce1d6] gap-2">
                                    <div>
                                        <span class="text-[#687168] font-medium block">Kategori</span>
                                        <span class="font-bold text-[#1c3328]" x-text="item.category || '-'"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[#687168] font-medium block">Qty</span>
                                        <span class="font-bold text-[#1c3328]" x-text="(item.quantity || 0) + ' ' + (item.unit || '')"></span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-[#dce1d6]">
                                    <span class="text-xs text-[#687168] font-medium block">Total Nilai</span>
                                    <span class="text-sm font-black text-[#1c3328]" x-text="formatRupiah(item.total_supplier_price || (item.supplier_price * item.quantity))"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Empty State -->
                <template x-if="summaryModalItems.length === 0">
                    <div class="py-12 text-center text-[#687168] text-sm font-medium">
                        <p x-text="'Tidak ada PO yang ' + (summaryModalTitle === 'PO Pending' ? 'pending' : summaryModalTitle === 'PO Selesai' ? 'selesai' : 'ditemukan')"></p>
                    </div>
                </template>
            </div>

        </div>
    </div>

    <!-- MODAL EDIT ADMIN / DAERAH -->
    <div x-show="isEditAdminModalOpen" 
         x-transition 
         class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4" 
         x-cloak>
        <div @click.away="isEditAdminModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-md rounded-xl p-6 space-y-4 border border-[#dce1d6]">
            <div class="flex justify-between items-center pb-2 border-b border-[#dce1d6]">
                <h3 class="text-lg font-extrabold text-[#1c3328]">{{ auth()->user()->isSuperAdmin() ? 'Edit Data Region & Admin' : 'Edit Akun Admin' }}</h3>
                <button @click="isEditAdminModalOpen = false" class="text-[#687168] hover:text-[#435348] text-xl font-bold">&times;</button>
            </div>

            <form @if(auth()->user()->isSuperAdmin()) x-bind:action="'/admin/region/' + editAdminForm.region_id" @else x-bind:action="'/admin/account/' + editAdminForm.account_id" @endif method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="account_id" x-model="editAdminForm.account_id">

                @if(auth()->user()->isSuperAdmin())
                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Kode Region</label>
                    <input type="text" name="code" x-model="editAdminForm.code" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Daerah / Dapur</label>
                    <input type="text" name="name" x-model="editAdminForm.name" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Role Akun</label>
                    <select name="account_role" x-model="editAdminForm.role" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none bg-white transition">
                        <option value="admin">Admin</option>
                        <option value="kepala_dapur">Kepala Dapur</option>
                    </select>
                </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Nama Admin</label>
                    <input type="text" name="admin_name" x-model="editAdminForm.admin_name" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Email Admin</label>
                    <input type="email" name="admin_email" x-model="editAdminForm.admin_email" required class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#435348] mb-1">Password Baru (Opsional)</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak diubah" class="w-full px-3 py-2 border border-[#dce1d6] rounded-md text-xs sm:text-sm focus:ring-2 focus:ring-[#587547]/20 focus:border-[#587547] focus:outline-none transition">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-[#dce1d6]">
                    <button type="button" @click="isEditAdminModalOpen = false" class="px-4 py-2 bg-[#f7f7f0] hover:bg-[#e9efe6] text-[#435348] text-xs font-bold rounded-md transition">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-[#123d2c] hover:bg-[#0a291e] text-white text-xs font-bold rounded-md transition shadow-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div x-data="analisaKeuntunganModal()"
        x-show="isAnalysisModalOpen" 
        x-on:open-analisa-modal.window="isAnalysisModalOpen = true; fetchData();"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="sbg-modal fixed inset-0 z-50 overflow-y-auto bg-[#0a291e]/60 backdrop-blur-sm flex items-center justify-center p-4"
        x-cloak>

        <div @click.away="isAnalysisModalOpen = false" class="sbg-modal-panel bg-white w-full max-w-lg rounded-xl p-6 space-y-5 border border-[#dce1d6] relative max-h-[90vh] overflow-y-auto">
            
            <!-- Header Modal -->
            <div class="flex items-center justify-between border-b border-[#dce1d6] pb-3">
                <h3 class="text-lg font-extrabold text-[#1c3328]">Analisa Keuntungan</h3>
                <button @click="isAnalysisModalOpen = false" class="text-[#687168] hover:text-[#435348] text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Filter Period Pills -->
            <div class="flex flex-wrap gap-2">
                <template x-for="(label, key) in filterOptions" :key="key">
                    <button type="button" 
                            @click="activeFilter = key; fetchData();" 
                            :class="activeFilter === key ? 'bg-[#123d2c] text-white shadow-sm' : 'bg-[#f7f7f0] text-[#435348] hover:bg-[#e9efe6]'"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition"
                            x-text="label">
                    </button>
                </template>
            </div>

            <!-- State Loading -->
            <div x-show="loading" class="py-8 text-center text-xs text-[#687168] font-medium">
                Memuat data analisa...
            </div>

            <div x-show="!loading && error" class="rounded-md bg-rose-50 p-4 text-center text-xs font-medium text-rose-700">
                <span x-text="error"></span>
                <button type="button" @click="fetchData()" class="ml-2 font-bold underline">Coba lagi</button>
            </div>

            <!-- Content Analyst Data -->
            <div x-show="!loading && !error" class="space-y-5">
                <!-- Section: Top Items -->
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-[#1c3328]">
                        <span>🎗️</span>
                        <span>Top Items</span>
                    </div>
                    <template x-for="(item, index) in data.topItems" :key="item.name">
                        <div class="flex items-center justify-between gap-3 p-4 bg-amber-50/60 border border-amber-200/60 rounded-lg">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="text-lg font-black text-amber-500" x-text="'#' + (index + 1)"></span>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-[#1c3328] text-xs sm:text-sm break-words" x-text="item.name"></h4>
                                    <p class="text-[10px] text-[#687168] font-medium">Total KSPPG</p>
                                </div>
                            </div>
                            <span class="shrink-0 font-extrabold text-amber-600 text-xs sm:text-sm" x-text="formatRupiah(item.total)"></span>
                        </div>
                    </template>
                    <p x-show="data.topItems.length === 0" class="text-xs text-[#687168]">Belum ada data item.</p>
                </div>

                <!-- Section: Ranking Kategori -->
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-[#1c3328]">
                        <span>📚</span>
                        <span>Ranking Kategori</span>
                    </div>
                    <template x-for="(category, index) in data.topCategories" :key="category.name">
                        <div class="flex items-center justify-between gap-3 p-4 bg-[#e9efe6]/60 border border-[#dce1d6]/60 rounded-lg">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="text-lg font-black text-[#123d2c]" x-text="'#' + (index + 1)"></span>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-[#1c3328] text-xs sm:text-sm break-words" x-text="category.name"></h4>
                                    <p class="text-[10px] text-[#687168] font-medium">Total KSPPG</p>
                                </div>
                            </div>
                            <span class="shrink-0 font-extrabold text-[#123d2c] text-xs sm:text-sm" x-text="formatRupiah(category.total)"></span>
                        </div>
                    </template>
                    <p x-show="data.topCategories.length === 0" class="text-xs text-[#687168]">Belum ada data kategori.</p>
                </div>

                <!-- Section Grid: Item Terbaik & Terendah -->
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div class="p-4 bg-[#e9efe6]/60 border border-[#dce1d6]/60 rounded-lg">
                        <p class="text-[10px] font-semibold text-[#687168]">Item Terbaik</p>
                        <h4 class="font-bold text-[#1c3328] text-xs sm:text-sm mt-1" x-text="data.bestItem.name"></h4>
                        <p class="font-extrabold text-[#123d2c] text-xs sm:text-sm mt-0.5" x-text="formatRupiah(data.bestItem.total)"></p>
                    </div>
                    <div class="p-4 bg-rose-50/60 border border-rose-200/60 rounded-lg">
                        <p class="text-[10px] font-semibold text-[#687168]">Item Terendah</p>
                        <h4 class="font-bold text-[#1c3328] text-xs sm:text-sm mt-1" x-text="data.worstItem.name"></h4>
                        <p class="font-extrabold text-rose-600 text-xs sm:text-sm mt-0.5" x-text="formatRupiah(data.worstItem.total)"></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @if($loginPopup?->enabled && $showLoginPopup)
        <div x-data="{ open: true }" x-show="open" x-cloak class="sbg-modal fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-[#0a291e]/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="login-popup-title" @keydown.escape.window="open = false">
            <div class="sbg-modal-panel w-full max-w-lg overflow-hidden rounded-xl bg-white">
                <div class="space-y-4 p-6 text-center">
                    <img src="{{ asset('favicon.svg') }}" alt="Logo aplikasi" class="mx-auto h-20 w-20 object-contain">
                    @if($loginPopup->title)
                        <h2 id="login-popup-title" class="text-xl font-extrabold text-[#1c3328]">{{ $loginPopup->title }}</h2>
                    @else
                        <h2 id="login-popup-title" class="sr-only">Pengumuman</h2>
                    @endif
                    @if($loginPopup->message)
                        <p class="whitespace-pre-line text-sm leading-relaxed text-[#435348]">{{ $loginPopup->message }}</p>
                    @endif
                    <button type="button" @click="open = false" class="w-full rounded-md bg-[#123d2c] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#0a291e]">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    @if(auth()->user()->isSuperAdmin())
        <div x-show="isLoginPopupSettingsOpen" x-cloak class="sbg-modal fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-[#0a291e]/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="login-popup-settings-title" @keydown.escape.window="isLoginPopupSettingsOpen = false">
            <div class="sbg-modal-panel w-full max-w-lg rounded-xl bg-white p-6">
                <div class="mb-5 flex items-center justify-between">
                    <h2 id="login-popup-settings-title" class="text-lg font-extrabold text-[#1c3328]">Atur Popup: <span x-text="popupTarget.accountName"></span></h2>
                    <button type="button" @click="isLoginPopupSettingsOpen = false" class="text-2xl text-[#687168] hover:text-[#435348]" aria-label="Tutup pengaturan">&times;</button>
                </div>
                <form method="POST" :action="'/dashboard/users/' + popupTarget.accountId + '/login-popup'" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_popup_form" value="1">
                    <input type="hidden" name="_popup_user_id" :value="popupTarget.accountId">
                    <input type="hidden" name="_popup_user_name" :value="popupTarget.accountName">
                    <div>
                        <label for="login-popup-title-input" class="mb-1 block text-xs font-bold text-[#435348]">Judul / Header</label>
                        <input id="login-popup-title-input" type="text" name="title" maxlength="150" x-model="popupTarget.title" class="w-full rounded-md border border-[#dce1d6] px-3 py-2.5 text-sm focus:border-[#587547] focus:outline-none">
                    </div>
                    <div>
                        <label for="login-popup-message" class="mb-1 block text-xs font-bold text-[#435348]">Pesan</label>
                        <textarea id="login-popup-message" name="message" rows="5" maxlength="5000" x-model="popupTarget.message" class="w-full rounded-md border border-[#dce1d6] px-3 py-2.5 text-sm focus:border-[#587547] focus:outline-none"></textarea>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="isLoginPopupSettingsOpen = false" class="flex-1 rounded-md bg-[#f7f7f0] px-4 py-2.5 text-sm font-bold text-[#435348] hover:bg-[#e9efe6]">Batal</button>
                        <button type="submit" class="flex-1 rounded-md bg-[#123d2c] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#0a291e]">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
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
</body>
</html>
