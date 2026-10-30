(() => {
    'use strict';
    const root = document.documentElement;
    const media = matchMedia('(prefers-color-scheme: dark)');
    const key = 'sbg-theme:' + (root.dataset.themeAccount || 'guest');

    function apply(choice, persist = false) {
        if (!['light', 'dark', 'system'].includes(choice)) choice = 'system';
        root.dataset.themePreference = choice;
        root.dataset.theme = choice === 'system' ? (media.matches ? 'dark' : 'light') : choice;
        root.style.colorScheme = root.dataset.theme;
        if (persist) { try { localStorage.setItem(key, choice); } catch {} }
        document.querySelectorAll('[data-theme-select]').forEach(select => { select.value = choice; });
        document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
            const dark = root.dataset.theme === 'dark';
            toggle.setAttribute('aria-pressed', String(dark));
            toggle.setAttribute('aria-label', dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
            toggle.title = dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            toggle.hidden = false;
        });
        const themeColor = document.querySelector?.('meta[name="theme-color"]');
        if (themeColor) themeColor.content = getComputedStyle(root).getPropertyValue('--bg').trim();
        window.dispatchEvent(new CustomEvent('sbg:themechange', { detail: { theme: root.dataset.theme, preference: choice } }));
    }

    document.addEventListener('change', event => {
        if (event.target.matches('[data-theme-select]')) apply(event.target.value, true);
    });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-theme-toggle]')) apply(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
    media.addEventListener('change', () => {
        if (root.dataset.themePreference === 'system') apply('system');
    });
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) apply(event.newValue || 'system');
    });
    apply(root.dataset.themePreference || 'system');
    requestAnimationFrame(() => { root.dataset.themeReady = 'true'; });
})();
