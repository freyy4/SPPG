<div x-cloak x-show="navOpen" class="sbg-nav-backdrop" @click="navOpen = false"></div>
<aside id="sbg-sidebar" :inert="!navOpen && !desktopNav" class="sbg-sidebar" :class="navOpen ? 'is-open' : ''" @keydown.escape.window="navOpen = false">
    <a href="{{ route('dashboard') }}" class="sbg-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="38" height="38"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    <p class="sbg-nav-caption">WORKSPACE</p>
    <nav aria-label="Navigasi utama">
        <a href="#ringkasan" @click="activeView = 'dashboard'; mobilePage = 'home'; navOpen = false" :class="activeView === 'dashboard' && mobilePage === 'home' ? 'is-active' : ''"><x-sbg.icon name="home"/>Dashboard</a>
        <a href="#data-po" @click="activeView = 'dashboard'; mobilePage = 'orders'; navOpen = false" :class="activeView === 'dashboard' && mobilePage === 'orders' ? 'is-active' : ''"><x-sbg.icon name="po"/>Purchase Order</a>
        <a href="#kalkulator-buah" @click="openFruitTab('calculate')"><x-sbg.icon name="calculator"/>Kalkulator Buah</a>
        <a href="#kalkulator-buah" @click="openFruitTab('master')"><x-sbg.icon name="leaf"/>Katalog Buah</a>
        <a href="#kalkulator-buah" @click="openFruitTab('archive')"><x-sbg.icon name="po"/>Arsip Keputusan</a>
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isKepalaDapur())<a href="#master-data" @click="activeView = 'dashboard'; mobilePage = 'admin'; navOpen = false"><x-sbg.icon name="users"/>Master Data</a>@endif
        <button type="button" @click="navOpen = false; $dispatch('open-analisa-modal')"><x-sbg.icon name="chart"/>Analisa</button>
    </nav>
    <div class="sbg-sidebar-bottom"><span class="sbg-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->region ?? 'Pusat' }}</small></div><button type="button" @click="isLogoutModalOpen = true; navOpen = false"><x-sbg.icon name="logout"/>Keluar</button></div>
</aside>
<header class="sbg-topbar" x-data="{ accountOpen: false }">
    <button type="button" class="sbg-icon-button sbg-menu-toggle" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar" aria-label="Buka navigasi"><x-sbg.icon name="menu"/></button>
    <a href="{{ route('dashboard') }}" class="sbg-brand sbg-mobile-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="34" height="34"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    <button type="button" class="sbg-top-search" @click="focusPoSearch()"><x-sbg.icon name="search" :size="18"/><span>Cari PO, supplier, atau nama barang…</span><kbd>⌘ K</kbd></button>
    <button type="button" class="sbg-icon-button sbg-mobile-create" @click="isCreateModalOpen = true" aria-label="Buat PO Baru" title="Buat PO Baru"><x-sbg.icon name="plus"/></button>
    <div class="sbg-account" @click.outside="accountOpen = false"><button type="button" class="sbg-account-toggle" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen" aria-label="Menu akun {{ auth()->user()->name }}"><span class="sbg-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="sbg-account-name"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->region ?? 'Pusat' }}</small></span><span class="sbg-account-chevron" aria-hidden="true">⌄</span></button><div x-cloak x-show="accountOpen" class="sbg-account-menu"><button type="button" @click="accountOpen = false; isLogoutModalOpen = true">Keluar dari akun</button></div></div>
</header>
<nav class="sbg-bottom-nav" aria-label="Navigasi mobile">
    <a href="#ringkasan" @click.prevent="navigateDashboard('home')" :aria-current="activeView === 'dashboard' && mobilePage === 'home' ? 'page' : null"><x-sbg.icon name="home"/><span>Beranda</span></a>
    <a href="#data-po" @click.prevent="navigateDashboard('orders')" :aria-current="activeView === 'dashboard' && mobilePage === 'orders' ? 'page' : null"><x-sbg.icon name="po"/><span>Purchase Order</span></a>
    <a href="#kalkulator-buah" @click="openFruitTab('calculate')" :aria-current="activeView === 'fruit' ? 'page' : null"><x-sbg.icon name="calculator"/><span>Kalkulator</span></a>
    <button type="button" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar"><x-sbg.icon name="grid"/><span>Lainnya</span></button>
</nav>
