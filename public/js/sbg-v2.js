// Shared keyboard behavior, without changing transaction or calculator state.
document.addEventListener('DOMContentLoaded', () => {
  const filters = document.getElementById('data-po');
  const desktop = matchMedia('(min-width: 1280px)');
  const syncFilters = () => { if (filters) filters.open = desktop.matches; };
  syncFilters(); desktop.addEventListener('change', syncFilters);

  document.querySelectorAll('.sbg-modal').forEach(modal => {
    modal.setAttribute('role', modal.getAttribute('role') || 'dialog');
    modal.setAttribute('aria-modal', 'true');
    if (!modal.hasAttribute('aria-label') && !modal.hasAttribute('aria-labelledby')) modal.setAttribute('aria-label', modal.querySelector('h2,h3,h4')?.textContent.trim() || 'Dialog');
    let previousFocus;
    const visible = () => getComputedStyle(modal).display !== 'none';
    new MutationObserver(() => {
      if (visible() && !modal.dataset.focused) {
        previousFocus = document.activeElement;
        modal.dataset.focused = 'true';
        modal.tabIndex = -1;
        modal.focus();
      } else if (!visible() && modal.dataset.focused) {
        delete modal.dataset.focused;
        if (previousFocus?.isConnected) previousFocus.focus();
      }
    }).observe(modal, {attributes:true, attributeFilter:['style']});
    modal.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        const close = [...modal.querySelectorAll('button')].find(button => /false/.test(button.getAttribute('@click') || '') && button.textContent.trim() === '×');
        close?.click();
      }
      if (event.key !== 'Tab') return;
      const targets = [...modal.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled):not([type=hidden]),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')].filter(el => el.getClientRects().length);
      const first = targets[0], last = targets.at(-1);
      if (!first) { event.preventDefault(); return; }
      if (event.shiftKey && (document.activeElement === first || document.activeElement === modal)) {event.preventDefault();last.focus();}
      else if (!event.shiftKey && (document.activeElement === last || document.activeElement === modal)) {event.preventDefault();first.focus();}
    });
  });
  document.querySelectorAll('form').forEach(form => {
    if (!(form.getAttribute(':action') || '').includes('/toggle-payment')) return;
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) {event.preventDefault();return;}
      if (!confirm('Tinjau perubahan status pembayaran PO ini. Lanjutkan?')) {event.preventDefault();return;}
      form.dataset.submitting = 'true';
      form.querySelectorAll('button[type=submit]').forEach(button => {button.disabled=true;button.textContent='Memproses…';});
    });
  });
});
