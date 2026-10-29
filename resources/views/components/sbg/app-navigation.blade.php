<div x-cloak x-show="navOpen" class="sbg-nav-backdrop" @click="navOpen = false"></div>
<aside id="sbg-sidebar" data-sbg-dialog :role="!desktopNav && navOpen ? 'dialog' : null" :aria-modal="!desktopNav && navOpen ? 'true' : null" aria-label="Navigasi aplikasi" :inert="!navOpen && !desktopNav" class="sbg-sidebar" :class="navOpen ? 'is-open' : ''" @keydown.escape.window="navOpen = false">
    <a href="{{ route('dashboard') }}" class="sbg-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="38" height="38"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    <button type="button" class="sbg-icon-button sbg-v23-sidebar-toggle" @click="sidebarCollapsed = !sidebarCollapsed" :aria-expanded="!sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'" aria-controls="sbg-sidebar"><span aria-hidden="true" x-text="sidebarCollapsed ? '»' : '«'"></span></button>
    <button type="button" class="sbg-icon-button sbg-drawer-close" @click="navOpen = false" aria-label="Tutup navigasi" data-dialog-close><x-sbg.icon name="close"/></button>
    <p class="sbg-nav-caption">WORKSPACE</p>
    <nav aria-label="Navigasi utama">
        <a href="#ringkasan" @click="activeView = 'dashboard'; mobilePage = 'home'; navOpen = false" :class="activeView === 'dashboard' && mobilePage === 'home' ? 'is-active' : ''"><x-sbg.icon name="home"/>Dashboard</a>
        <a href="#data-po" @click="activeView = 'dashboard'; mobilePage = 'orders'; navOpen = false" :class="activeView === 'dashboard' && mobilePage === 'orders' ? 'is-active' : ''"><x-sbg.icon name="po"/>Purchase Order</a>
        <a href="#kalkulator-buah" @click="openFruitTab('calculate')"><x-sbg.icon name="calculator"/>Kalkulator Buah</a>
        <a href="#kalkulator-buah" @click="openFruitTab('master')"><x-sbg.icon name="leaf"/>Katalog Buah</a>
        <a href="#kalkulator-buah" @click="openFruitTab('archive')"><x-sbg.icon name="po"/>Arsip Keputusan</a>
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isKepalaDapur())<a href="#master-data" :class="activeView === 'dashboard' && mobilePage === 'admin' ? 'is-active' : ''" :aria-current="activeView === 'dashboard' && mobilePage === 'admin' ? 'page' : null" @click="activeView = 'dashboard'; mobilePage = 'admin'; navOpen = false"><x-sbg.icon name="users"/>Master Data</a>@endif
        <button type="button" @click="navOpen = false; $dispatch('open-analisa-modal')"><x-sbg.icon name="chart"/>Analisa</button>
    </nav>
    <div class="sbg-sidebar-bottom"><x-sbg.account-menu :sidebar="true"/></div>
</aside>
<header class="sbg-topbar">
    <button type="button" class="sbg-icon-button sbg-menu-toggle" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar" aria-label="Buka navigasi"><x-sbg.icon name="menu"/></button>
    <a href="{{ route('dashboard') }}" class="sbg-brand sbg-mobile-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="34" height="34"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    <button type="button" class="sbg-top-search" @click="mobilePage === 'admin' ? document.getElementById('master-search').focus() : focusPoSearch()"><x-sbg.icon name="search" :size="18"/><span x-text="mobilePage === 'admin' ? 'Cari daerah, nama, email, atau role…' : 'Cari PO, supplier, atau nama barang…'"></span><kbd>⌘ K</kbd></button>
    <button type="button" class="sbg-icon-button sbg-mobile-create" @click="isCreateModalOpen = true" aria-label="Buat PO Baru" title="Buat PO Baru"><x-sbg.icon name="plus"/></button>
    <x-sbg.account-menu/>
</header>
<nav class="sbg-bottom-nav" aria-label="Navigasi mobile">
    <a href="#ringkasan" @click.prevent="navigateDashboard('home')" :aria-current="activeView === 'dashboard' && mobilePage === 'home' ? 'page' : null"><x-sbg.icon name="home"/><span>Beranda</span></a>
    <a href="#data-po" @click.prevent="navigateDashboard('orders')" :aria-current="activeView === 'dashboard' && mobilePage === 'orders' ? 'page' : null"><x-sbg.icon name="po"/><span>Purchase Order</span></a>
    <a href="#kalkulator-buah" @click="openFruitTab('calculate')" :aria-current="activeView === 'fruit' ? 'page' : null"><x-sbg.icon name="calculator"/><span>Kalkulator</span></a>
    <button type="button" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar"><x-sbg.icon name="grid"/><span>Lainnya</span></button>
</nav>
