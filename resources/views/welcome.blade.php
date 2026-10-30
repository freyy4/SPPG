
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sentra Buah Garut | Pemasok Buah Segar B2B</title>
  <meta name="description" content="Sentra Buah Garut dari PT Garuta Food menyediakan pasokan buah segar lokal dan impor untuk HoReCa, grosir, serta Dapur MBG.">
  <meta name="keywords" content="supplier buah hotel, grosir buah segar, penyedia buah dapur MBG, buah segar Garut, pemasok buah B2B">
  <meta name="theme-color" content="#f7f7f0">
  <script src="{{ asset('app.js') }}"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="stylesheet" href="{{ asset('style.css') }}">
    @include('components.sbg.theme-head')
</head>
<body>
  <header class="site-header" id="top">
    <a class="brand" href="#top" aria-label="Sentra Buah Garut - beranda">
      <img class="brand-mark" src="{{ asset('favicon.svg') }}" alt="" width="46" height="46">
      <span class="brand-copy"><strong>Sentra Buah Garut</strong><small>PT Garuta Food</small></span>
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Navigasi utama">
      <a href="#solusi">Solusi</a>
      <a href="#katalog">Katalog</a>
      <a href="#mbg">Dapur MBG</a>
      <a href="#tentang">Tentang Kami</a>
      <a class="nav-cta" href="#kontak">Hubungi Sales <span aria-hidden="true">↗</span></a>
    </nav>
    <div class="header-actions">
      <button class="theme-toggle" data-theme-toggle type="button" aria-label="Mode gelap" aria-pressed="false" title="Aktifkan mode gelap" hidden>
        <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 13.1A9 9 0 0 1 10.9 3.2 9 9 0 1 0 20.8 13.1Z"/></svg>
        <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <button class="menu-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
    </div>
  </header>

  <main id="main-content">
    <!-- BERANDA: gambar merupakan ilustrasi, bukan foto fasilitas perusahaan. -->
    <section class="hero" aria-labelledby="hero-title">
      <img class="hero-image" src="{{ asset('images/fruit-hub.svg') }}" alt="Ilustrasi buah segar dalam peti di pusat distribusi dengan latar pegunungan" fetchpriority="high" width="1600" height="1000">
      <div class="hero-shade"></div>
      <div class="hero-content">
        <p class="eyebrow"><span></span> Pasokan buah B2B dari Garut</p>
        <h1 id="hero-title">Satu pintu untuk pasokan buah segar yang <em>selalu siap.</em></h1>
        <p class="hero-lead">Dari petani lokal pilihan dan jaringan importir resmi, kami menjaga kualitas, volume, dan ketepatan pengiriman untuk kebutuhan bisnis Anda.</p>
        <div class="hero-actions">
          <a class="button button-primary" href="#katalog">Lihat katalog <span aria-hidden="true">↓</span></a>
          <a class="button button-ghost" href="#kontak">Minta penawaran</a>
        </div>
      </div>
      <div class="hero-proof" aria-label="Keunggulan utama">
        <div><strong>QC</strong><span>Berlapis</span></div>
        <div><strong>Cold</strong><span>Storage</span></div>
        <div><strong>Bulk</strong><span>Ready</span></div>
      </div>
    </section>

    <section class="trust-strip" aria-label="Segmen layanan">
      <p>Solusi rantai pasok untuk</p>
      <div><span>01</span> Hotel & Restoran</div>
      <div><span>02</span> Grosir Buah</div>
      <div><span>03</span> Dapur MBG</div>
      <div><span>04</span> Korporasi</div>
    </section>

    <section class="section value-section" id="solusi">
      <div class="section-heading">
        <p class="eyebrow dark"><span></span> Standar kerja kami</p>
        <h2>Bukan sekadar kirim buah.<br>Kami menjaga rantai pasok Anda.</h2>
        <p>Setiap alur dirancang untuk mengurangi tiga risiko utama pengadaan: mutu yang berubah, stok yang tidak pasti, dan pengiriman yang terlambat.</p>
      </div>
      <div class="value-grid">
        <article class="value-card featured">
          <span class="value-number">01</span>
          <div class="value-icon">✦</div>
          <h3>Mutu & kesegaran terjaga</h3>
          <p>Seleksi sumber, grading terukur, QC berlapis, dan penyimpanan dingin sebelum distribusi.</p>
          <ul><li>Standar grade jelas</li><li>Penanganan pascapanen</li></ul>
        </article>
        <article class="value-card">
          <span class="value-number">02</span>
          <div class="value-icon">◎</div>
          <h3>Katalog satu pintu</h3>
          <p>Buah lokal musiman dan buah impor premium dalam satu proses pengadaan.</p>
        </article>
        <article class="value-card">
          <span class="value-number">03</span>
          <div class="value-icon">▦</div>
          <h3>Siap volume besar</h3>
          <p>Perencanaan suplai untuk kebutuhan harian, mingguan, dan kontrak berkelanjutan.</p>
        </article>
        <article class="value-card">
          <span class="value-number">04</span>
          <div class="value-icon">◇</div>
          <h3>Kemitraan transparan</h3>
          <p>Informasi grade, kemasan, musim panen, dan penawaran disampaikan sejak awal.</p>
        </article>
      </div>
    </section>

    <!-- KATALOG: data contoh; sesuaikan produk, grade, dan kemasan sebelum rilis. -->
    <section class="section catalog-section" id="katalog">
      <div class="catalog-head">
        <div>
          <p class="eyebrow dark"><span></span> Katalog pengadaan</p>
          <h2>Pilih buah sesuai kebutuhan bisnis.</h2>
        </div>
        <div class="filter-group" role="group" aria-label="Filter katalog">
          <button class="filter active" type="button" data-filter="all" aria-pressed="true">Semua</button>
          <button class="filter" type="button" data-filter="lokal" aria-pressed="false">Buah lokal</button>
          <button class="filter" type="button" data-filter="impor" aria-pressed="false">Buah impor</button>
        </div>
      </div>
      <div class="product-grid" aria-live="polite">
        <article class="product-card" data-category="lokal">
          <div class="fruit-art orange" aria-hidden="true"><span>JRG</span></div>
          <div class="product-content"><span class="tag">Lokal • Jawa Barat</span><h3>Jeruk</h3><p>Grade A/B • krat, dus, atau kilogram</p><a href="#kontak" data-product="Jeruk">Minta harga <span>↗</span></a></div>
        </article>
        <article class="product-card" data-category="lokal">
          <div class="fruit-art dragon" aria-hidden="true"><span>BNG</span></div>
          <div class="product-content"><span class="tag">Lokal • Nusantara</span><h3>Buah Naga</h3><p>Grade A/B • krat atau kilogram</p><a href="#kontak" data-product="Buah Naga">Minta harga <span>↗</span></a></div>
        </article>
        <article class="product-card" data-category="lokal">
          <div class="fruit-art pineapple" aria-hidden="true"><span>NNS</span></div>
          <div class="product-content"><span class="tag">Lokal • Nusantara</span><h3>Nanas</h3><p>Ukuran pilihan • satuan atau krat</p><a href="#kontak" data-product="Nanas">Minta harga <span>↗</span></a></div>
        </article>
        <article class="product-card" data-category="impor">
          <div class="fruit-art apple" aria-hidden="true"><span>APL</span></div>
          <div class="product-content"><span class="tag">Impor • Premium</span><h3>Apel</h3><p>Varietas pilihan • dus atau kilogram</p><a href="#kontak" data-product="Apel">Minta harga <span>↗</span></a></div>
        </article>
        <article class="product-card" data-category="impor">
          <div class="fruit-art pear" aria-hidden="true"><span>PIR</span></div>
          <div class="product-content"><span class="tag">Impor • Premium</span><h3>Pir</h3><p>Varietas pilihan • dus atau kilogram</p><a href="#kontak" data-product="Pir">Minta harga <span>↗</span></a></div>
        </article>
        <article class="product-card" data-category="impor">
          <div class="fruit-art grape" aria-hidden="true"><span>AGR</span></div>
          <div class="product-content"><span class="tag">Impor • Premium</span><h3>Anggur</h3><p>Varietas pilihan • dus atau kilogram</p><a href="#kontak" data-product="Anggur">Minta harga <span>↗</span></a></div>
        </article>
      </div>
      <p class="catalog-note">Contoh katalog. Produk, grade, kemasan, harga, dan ketersediaan perlu dikonfirmasi kepada tim Sales.</p>
    </section>

    <section class="mbg-section" id="mbg">
      <div class="mbg-visual">
        <img src="{{ asset('images/fruit-hub.svg') }}" alt="Ilustrasi beragam buah segar untuk kebutuhan distribusi skala besar" loading="lazy" decoding="async" width="1600" height="1000">
        <div class="capacity-card"><span>Kapasitas suplai</span><strong>Harian & terjadwal</strong><small>Sesuai kebutuhan dapur</small></div>
      </div>
      <div class="mbg-copy">
        <p class="eyebrow"><span></span> Solusi Dapur MBG</p>
        <h2>Pasokan bergizi, higienis, dan tidak putus.</h2>
        <p>Kami membantu pengelola Dapur Makan Bergizi Gratis merencanakan suplai buah dalam volume besar dengan mutu yang seragam dan jadwal yang terukur.</p>
        <div class="check-list"><div><b>✓</b><span><strong>Higienitas terkontrol</strong>Penanganan dan penyimpanan sesuai standar.</span></div><div><b>✓</b><span><strong>Tonase stabil</strong>Skema pasokan harian atau mingguan.</span></div><div><b>✓</b><span><strong>Distribusi terjadwal</strong>Koordinasi kebutuhan per dapur.</span></div></div>
        <a class="text-link" href="#kontak">Konsultasikan kebutuhan Dapur MBG <span>→</span></a>
      </div>
    </section>

    <section class="section about-section" id="tentang">
      <div class="about-copy">
        <p class="eyebrow dark"><span></span> Tentang kami</p>
        <h2>Berakar di Garut.<br>Bergerak untuk kebutuhan nasional.</h2>
      </div>
      <div class="about-body">
        <p><strong>Sentra Buah Garut</strong> adalah unit usaha di bawah PT Garuta Food yang mempertemukan hasil terbaik petani lokal dengan kebutuhan pasar B2B secara lebih terstruktur.</p>
        <p>Melalui jaringan pasok, proses quality control, dan hub distribusi, kami membangun kemitraan jangka panjang yang menguntungkan produsen sekaligus memberi kepastian kepada pembeli.</p>
        <div class="process"><div><span>01</span><b>Sumber terpilih</b></div><i>→</i><div><span>02</span><b>Sortir & QC</b></div><i>→</i><div><span>03</span><b>Penyimpanan</b></div><i>→</i><div><span>04</span><b>Distribusi</b></div></div>
      </div>
    </section>

    <!-- KONTAK: nomor WhatsApp Sales diatur pada WHATSAPP_NUMBER di app.js. -->
    <section class="contact-section" id="kontak">
      <div class="contact-copy">
        <p class="eyebrow"><span></span> Mulai kemitraan</p>
        <h2>Ceritakan kebutuhan pasokan Anda.</h2>
        <p>Isi detail singkat di samping. Pesan penawaran akan otomatis disiapkan untuk dikirim melalui WhatsApp.</p>
        <div class="contact-points"><div><span>⌖</span><p><small>Wilayah</small><strong>Garut, Jawa Barat</strong></p></div><div><span>◷</span><p><small>Layanan B2B</small><strong>Konsultasi penawaran</strong></p></div></div>
      </div>
      <form class="quote-form" id="quote-form">
        <div class="field"><label for="name">Nama / perusahaan</label><input id="name" name="name" placeholder="Contoh: Hotel Nusantara" autocomplete="organization" maxlength="150" required></div>
        <div class="field-row"><div class="field"><label for="segment">Segmen usaha</label><select id="segment" name="segment" required><option value="">Pilih segmen</option><option>Hotel / Restoran</option><option>Grosir Buah</option><option>Dapur MBG</option><option>Korporasi</option><option>Lainnya</option></select></div><div class="field"><label for="product">Kebutuhan buah</label><input id="product" name="product" placeholder="Jenis buah"></div></div>
        <div class="field"><label for="volume">Estimasi volume & jadwal</label><textarea id="volume" name="volume" rows="3" maxlength="2000" placeholder="Contoh: 200 kg per minggu, kirim setiap Senin"></textarea></div>
        <button class="button button-accent" type="submit">Siapkan pesan WhatsApp <span>↗</span></button>
        <p class="form-note" id="whatsapp-note">Nomor Sales belum diatur. WhatsApp akan meminta Anda memilih kontak tujuan.</p>
        <p class="form-status" id="form-status" role="status" aria-live="polite" hidden></p>
        <noscript><p>Aktifkan JavaScript untuk menyiapkan pesan penawaran melalui WhatsApp.</p></noscript>
      </form>
    </section>
  </main>

  <footer>
    <div class="footer-brand"><img class="brand-mark small" src="{{ asset('favicon.svg') }}" alt="" width="40" height="40"><p><strong>Sentra Buah Garut</strong><small>One stop solution rantai pasok buah segar B2B.</small></p></div>
    <div class="footer-links"><a href="#solusi">Solusi</a><a href="#katalog">Katalog</a><a href="#mbg">Dapur MBG</a><a href="#tentang">Tentang</a></div>
    <p class="copyright">© <span id="year">{{ date('Y') }}</span> PT Garuta Food</p>
  </footer>
</body>
</html>
