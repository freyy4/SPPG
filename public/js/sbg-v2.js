document.addEventListener('DOMContentLoaded', () => {
    const dialogs = [...document.querySelectorAll('.sbg-modal, [data-sbg-dialog]')];
    const stack = [];
    const visible = element => getComputedStyle(element).display !== 'none' && element.getClientRects().length > 0;
    const targets = element => [...element.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled):not([type=hidden]), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')].filter(item => visible(item) && !item.closest('[inert]'));
    const dialogScope = dialog => [...dialog.querySelectorAll('[role=dialog], [role=alertdialog]')].filter(visible).at(-1) || dialog;

    dialogs.forEach(dialog => {
        if (dialog.classList.contains('sbg-modal')) {
            dialog.setAttribute('role', dialog.getAttribute('role') || 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
                dialog.setAttribute('aria-label', dialog.querySelector('h2,h3,h4')?.textContent.trim() || 'Dialog');
            }
        }
        let opener;
        const sync = () => {
            const opened = dialog.getAttribute('aria-modal') === 'true' && visible(dialog);
            const index = stack.indexOf(dialog);
            if (opened && index === -1) {
                opener = document.activeElement;
                stack.push(dialog);
                dialog.tabIndex = -1;
                dialog.focus({preventScroll:true});
            } else if (!opened && index !== -1) {
                stack.splice(index, 1);
                const current = stack.at(-1);
                if (current) current.focus({preventScroll:true});
                else if (opener?.isConnected && visible(opener)) opener.focus({preventScroll:true});
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
            const close = dialog.querySelector('[data-dialog-close]') || [...dialog.querySelectorAll('button')].find(button => /false/.test(button.getAttribute('@click') || '') && button.textContent.trim() === '×');
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
        document.fonts.ready.then(() => fitValues(true));
    }
});
