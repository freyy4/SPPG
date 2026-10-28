document.addEventListener('DOMContentLoaded', () => {
    const dialogs = [...document.querySelectorAll('.sbg-modal, [data-sbg-dialog]')];
    const stack = [];
    const visible = element => getComputedStyle(element).display !== 'none' && element.getClientRects().length > 0;
    const targets = element => [...element.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled):not([type=hidden]), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')].filter(item => visible(item) && !item.closest('[inert]'));
    const dialogScope = dialog => [...dialog.querySelectorAll('[role=dialog], [role=alertdialog]')].filter(visible).at(-1) || dialog;

    dialogs.forEach((dialog, index) => {
        if (dialog.classList.contains('sbg-modal')) {
            dialog.setAttribute('role', dialog.getAttribute('role') || 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
                const heading = dialog.querySelector('h2,h3,h4');
                if (heading) {
                    heading.id ||= `sbg-dialog-title-${index}`;
                    dialog.setAttribute('aria-labelledby', heading.id);
                } else dialog.setAttribute('aria-label', 'Dialog');
            }
        }
        dialog.querySelectorAll('button').forEach(button => {
            if ((button.textContent.trim() === '×' || (!button.textContent.trim() && button.querySelector('svg'))) && /false/.test(button.getAttribute('@click') || '')) {
                button.setAttribute('aria-label', button.getAttribute('aria-label') || 'Tutup dialog');
                button.setAttribute('data-dialog-close', '');
                button.classList.add('sbg-icon-button');
            }
        });
        let opener;
        const sync = () => {
            const opened = dialog.getAttribute('aria-modal') === 'true' && visible(dialog);
            const index = stack.indexOf(dialog);
            if (opened && index === -1) {
                opener = document.activeElement;
                stack.push(dialog);
                dialog.tabIndex = -1;
                (dialog.querySelector('[data-dialog-initial-focus]') || dialog).focus({preventScroll:true});
            } else if (!opened && index !== -1) {
                stack.splice(index, 1);
                const current = stack.at(-1);
                if (current) current.focus({preventScroll:true});
                else if (opener?.isConnected && visible(opener) && !opener.closest('[inert]')) opener.focus({preventScroll:true});
                else {
                    // A menu/drawer trigger may have hidden itself while opening.
                    const fallback = opener?.closest('.sbg-account')?.querySelector('.sbg-account-toggle')
                        || [...document.querySelectorAll('.sbg-menu-toggle, .sbg-bottom-nav button')].find(visible);
                    fallback?.focus({preventScroll:true});
                }
            }
            document.body.classList.toggle('sbg-scroll-locked', stack.length > 0);
        };
        new MutationObserver(sync).observe(dialog, {attributes:true, attributeFilter:['style','aria-modal','x-cloak']});
        sync();
    });
    document.addEventListener('keydown', event => {
        const dialog = stack.at(-1);
        if (!dialog) return;
        const scope = dialogScope(dialog);
        if (event.key === 'Escape' && scope === dialog) {
            const close = dialog.querySelector('[data-dialog-close]') || [...dialog.querySelectorAll('button')].find(button => /false/.test(button.getAttribute('@click') || '') && /^(×|Tutup|Batal)$/.test(button.textContent.trim()));
            close?.click();
        }
        if (event.key !== 'Tab') return;
        const focusable = targets(scope);
        const first = focusable[0], last = focusable.at(-1);
        if (!first) { event.preventDefault(); return; }
        if (event.shiftKey && (document.activeElement === first || !scope.contains(document.activeElement) || document.activeElement === dialog)) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !scope.contains(document.activeElement) || document.activeElement === dialog)) {
            event.preventDefault(); first.focus();
        }
    });
    document.addEventListener('focusin', event => {
        const dialog = stack.at(-1);
        if (dialog && !dialog.contains(event.target)) (targets(dialogScope(dialog))[0] || dialog).focus({preventScroll:true});
    });

    document.querySelectorAll('form').forEach(form => {
        if (!(form.getAttribute(':action') || '').includes('/toggle-payment')) return;
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            const label = form.dataset.paymentLabel || '';
            const number = form.closest('.sbg-detail-panel')?.querySelector('.sbg-detail-title p')?.textContent || '';
            if (!confirm(`Tinjau perubahan status pembayaran ${label} untuk ${number}. Lanjutkan?`)) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type=submit]').forEach(button => {
                button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Memproses…';
            });
        });
    });

    document.querySelectorAll('[data-sbg-logout]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            const button = form.querySelector('button[type=submit]');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });

    document.querySelectorAll('[data-sbg-create-form]').forEach(form => {
        const fields = [...form.querySelectorAll('input:not([type=hidden]), select, textarea')];
        let serverErrors = {};
        try { serverErrors = JSON.parse(form.dataset.serverErrors || '{}'); } catch (_) { /* Empty validation bag. */ }
        const errors = new Map();
        fields.forEach(field => {
            field.id ||= `sbg-create-${field.name}`;
            let container = field.parentElement;
            while (container && container !== form && !container.querySelector(':scope > label')) container = container.parentElement;
            const label = container?.querySelector(':scope > label');
            if (label) label.htmlFor = field.id;
            const error = document.createElement('p');
            error.id = `${field.id}-error`;
            error.className = 'sbg-create-error';
            error.setAttribute('role', 'alert');
            field.setAttribute('aria-describedby', error.id);
            (field.parentElement.classList.contains('relative') ? field.parentElement : field).after(error);
            error.textContent = (serverErrors[field.name] || (field.name === 'quantity_sbg' ? serverErrors.quantity : []) || [])[0] || '';
            field.setAttribute('aria-invalid', String(Boolean(error.textContent)));
            errors.set(field, error);
        });
        const quantities = () => {
            const sbg = form.elements.quantity_sbg, b = form.elements.quantity_b;
            sbg.setCustomValidity('');
            if (sbg.value !== '' && b.value !== '' && Number(sbg.value) + Number(b.value) <= 0) sbg.setCustomValidity('Jumlah kedua gudang harus lebih dari 0.');
        };
        const validate = field => {
            const validity = field.validity;
            let message = '';
            if (!validity.valid) {
                if (validity.customError) message = field.validationMessage;
                else if (validity.valueMissing) message = 'Kolom ini wajib diisi.';
                else if (validity.rangeUnderflow) message = `Nilai minimal ${field.min}.`;
                else if (validity.rangeOverflow) message = `Nilai maksimal ${field.max}.`;
                else if (validity.stepMismatch) message = 'Gunakan maksimal 2 angka desimal.';
                else message = 'Periksa kembali nilai yang dimasukkan.';
            }
            errors.get(field).textContent = message;
            field.setAttribute('aria-invalid', String(Boolean(message)));
            return !message;
        };
        fields.forEach(field => {
            field.addEventListener('blur', () => { quantities(); validate(field); });
            field.addEventListener('input', () => {
                quantities();
                if (field.getAttribute('aria-invalid') === 'true') validate(field);
                if (field.name.startsWith('quantity_') && form.elements.quantity_sbg.getAttribute('aria-invalid') === 'true') validate(form.elements.quantity_sbg);
            });
        });
        form.addEventListener('submit', event => {
            quantities();
            const invalid = fields.filter(field => !field.disabled && !validate(field));
            if (invalid.length) {
                event.preventDefault();
                invalid[0].focus({preventScroll:true});
                invalid[0].scrollIntoView({block:'center', behavior:'instant'});
            }
        });
    });

    const kpis = document.getElementById('ringkasan');
    if (kpis) {
        let previousWidth = 0;
        const fitValues = (force = false) => {
            const width = kpis.clientWidth;
            if (!force && width === previousWidth) return;
            previousWidth = width;
            delete kpis.dataset.wideValues;
            const values = [...kpis.querySelectorAll('.sbg-kpi-value')];
            values.forEach(value => value.style.removeProperty('font-size'));
            // Premium cards reserve a separate artwork lane, including on mobile.
            values.filter(value => value.closest('.sbg-kpi-payment')).forEach(value => {
                let size = parseFloat(getComputedStyle(value).fontSize);
                while (value.scrollWidth > value.clientWidth + 1 && size > 14) value.style.fontSize = `${--size}px`;
            });
            if (innerWidth < 1280) return;
            let needsWiderCards = false;
            values.forEach(value => {
                let size = parseFloat(getComputedStyle(value).fontSize);
                while (value.scrollWidth > value.clientWidth + 1 && size > 20) value.style.fontSize = `${--size}px`;
                if (value.scrollWidth > value.clientWidth + 1) needsWiderCards = true;
            });
            if (needsWiderCards) {
                kpis.dataset.wideValues = 'true';
                values.forEach(value => value.style.removeProperty('font-size'));
            }
        };
        new ResizeObserver(() => fitValues()).observe(kpis);
        new MutationObserver(() => fitValues(true)).observe(kpis, {subtree:true, childList:true, characterData:true});
        document.fonts.ready.then(() => fitValues(true));
    }
});
