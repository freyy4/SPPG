(() => {
  'use strict';

  // Isi dengan nomor WhatsApp Sales dalam format internasional, misalnya 62812….
  // Biarkan kosong untuk memilih sendiri kontak tujuan di WhatsApp.
  const WHATSAPP_NUMBER = '';

  // Theme selection is owned by sbg-v25-theme.js on every surface.

  const initializeWelcome = () => {
    document.documentElement.classList.add('js-enabled');

    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav = document.querySelector('#main-nav');

    if (menuToggle && mainNav) {
      const desktopLayout = window.matchMedia('(min-width: 901px)');

      const setMenuOpen = (isOpen, restoreFocus = false) => {
        mainNav.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('menu-open', isOpen);
        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');

        if (restoreFocus) menuToggle.focus();
      };

      menuToggle.addEventListener('click', () => {
        if (desktopLayout.matches) return;
        setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
      });

      mainNav.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) setMenuOpen(false);
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
          setMenuOpen(false, true);
        }
      });

      document.addEventListener('click', (event) => {
        if (!menuToggle.contains(event.target) && !mainNav.contains(event.target)) {
          setMenuOpen(false);
        }
      });

      const resetMenuForLayout = () => setMenuOpen(false);
      if (typeof desktopLayout.addEventListener === 'function') {
        desktopLayout.addEventListener('change', resetMenuForLayout);
      } else {
        desktopLayout.addListener(resetMenuForLayout);
      }
      resetMenuForLayout();
    }

    const filters = document.querySelectorAll('[data-filter]');
    const products = document.querySelectorAll('.product-card[data-category]');

    filters.forEach((filter) => {
      filter.addEventListener('click', () => {
        const category = filter.dataset.filter;

        filters.forEach((button) => {
          const active = button === filter;
          button.classList.toggle('active', active);
          button.setAttribute('aria-pressed', String(active));
        });

        products.forEach((product) => {
          product.hidden = category !== 'all' && product.dataset.category !== category;
        });
      });
    });

    const productField = document.querySelector('#product');
    if (productField) {
      document.querySelectorAll('a[data-product]').forEach((link) => {
        link.addEventListener('click', () => {
          productField.value = link.dataset.product || '';
        });
      });
    }

    const year = document.querySelector('#year');
    if (year) year.textContent = String(new Date().getFullYear());

    const form = document.querySelector('#quote-form');
    if (!form) return;

    const nameField = form.querySelector('[name="name"]');
    const note = document.querySelector('#whatsapp-note');
    const status = document.querySelector('#form-status');
    const whatsappNumber = WHATSAPP_NUMBER.replace(/\D/g, '');

    if (note) {
      note.textContent = whatsappNumber
        ? 'Pesan akan disiapkan untuk WhatsApp Sales. Periksa pesan sebelum mengirim.'
        : 'Nomor Sales belum diatur. WhatsApp akan meminta Anda memilih kontak tujuan.';
    }

    if (nameField) {
      nameField.addEventListener('input', () => nameField.setCustomValidity(''));
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();

      if (nameField) {
        nameField.value = nameField.value.trim();
        nameField.setCustomValidity(nameField.value ? '' : 'Masukkan nama atau perusahaan Anda.');
      }

      if (!form.reportValidity()) return;

      const valueOf = (name) => {
        const field = form.querySelector(`[name="${name}"]`);
        return field ? field.value.trim() : '';
      };

      const message = [
        'Halo tim Sales Sentra Buah Garut, saya ingin meminta penawaran buah.',
        '',
        `Nama / perusahaan: ${valueOf('name')}`,
        `Segmen usaha: ${valueOf('segment')}`,
      ];

      const product = valueOf('product');
      const volume = valueOf('volume');
      if (product) message.push(`Kebutuhan buah: ${product}`);
      if (volume) message.push(`Estimasi volume & jadwal: ${volume}`);

      const whatsappUrl = new URL(`https://wa.me/${whatsappNumber}`);
      whatsappUrl.searchParams.set('text', message.join('\n'));

      if (status) {
        const link = document.createElement('a');
        link.href = whatsappUrl.href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Buka pesan di WhatsApp';
        status.replaceChildren('Pesan siap. Jika WhatsApp belum terbuka, ', link, '.');
        status.hidden = false;
      }

      window.open(whatsappUrl.href, '_blank', 'noopener,noreferrer');
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWelcome, { once: true });
  } else {
    initializeWelcome();
  }
})();
