@php($settingsNavigation = $settingsNavigation ?? false)
@php($dashboardUrl = route('dashboard'))
<div x-cloak x-show="navOpen" class="sbg-nav-backdrop" @click="navOpen = false"></div>
<aside id="sbg-sidebar" data-sbg-dialog :role="!desktopNav && navOpen ? 'dialog' : null" :aria-modal="!desktopNav && navOpen ? 'true' : null" aria-label="Navigasi aplikasi" :inert="!navOpen && !desktopNav" class="sbg-sidebar" :class="navOpen ? 'is-open' : ''" @keydown.escape.window="navOpen = false">
    <a href="{{ $dashboardUrl }}" class="sbg-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="38" height="38"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    <button type="button" class="sbg-icon-button sbg-v23-sidebar-toggle" @click="sidebarCollapsed = !sidebarCollapsed" :aria-expanded="!sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'" aria-controls="sbg-sidebar"><span aria-hidden="true" x-text="sidebarCollapsed ? '»' : '«'"></span></button>
    <button type="button" class="sbg-icon-button sbg-drawer-close" @click="navOpen = false" aria-label="Tutup navigasi" data-dialog-close data-dialog-initial-focus><x-sbg.icon name="close"/></button>
    <p class="sbg-nav-caption">WORKSPACE</p>
    <nav aria-label="Navigasi utama">
        @foreach([['ringkasan','home','Dashboard','dashboard'],['data-po','po','Purchase Order','orders'],['kalkulator-buah','calculator','Kalkulator Buah','calculate'],['katalog-buah','leaf','Katalog Buah','master'],['arsip-keputusan','po','Arsip Keputusan','archive']] as [$fragment,$icon,$label,$view])
        <a href="{{ $dashboardUrl }}#{{ $fragment }}" @if(!$settingsNavigation) @click.prevent="navigateFragment('{{ $fragment }}')" :class="navigationActive('{{ $fragment }}') ? 'is-active' : ''" :aria-current="navigationActive('{{ $fragment }}') ? 'page' : null" @endif><x-sbg.icon :name="$icon"/>{{ $label }}</a>
        @endforeach
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isKepalaDapur())<a href="{{ $dashboardUrl }}#master-data" @if(!$settingsNavigation) @click.prevent="navigateFragment('master-data')" :class="navigationActive('master-data') ? 'is-active' : ''" :aria-current="navigationActive('master-data') ? 'page' : null" @endif><x-sbg.icon name="users"/>Master Data</a>@endif
        <a href="{{ url('/settings/account') }}" @if($settingsNavigation) class="is-active" aria-current="page" @endif><x-sbg.icon name="settings"/>Pengaturan Akun</a>
        <a href="{{ $dashboardUrl }}#analisa" @if(!$settingsNavigation) @click.prevent="navigateFragment('analisa')" @endif><x-sbg.icon name="chart"/>Analisa</a>
    </nav>
    <div class="sbg-sidebar-bottom"><x-sbg.account-menu :sidebar="true"/></div>
</aside>
<header class="sbg-topbar">
    <button type="button" class="sbg-icon-button sbg-menu-toggle" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar" aria-label="Buka navigasi"><x-sbg.icon name="menu"/></button>
    <a href="{{ $dashboardUrl }}" class="sbg-brand sbg-mobile-brand"><img src="{{ asset('favicon.svg') }}" alt="" width="34" height="34"><strong>Sentra Buah Garut<small>SISTEM PURCHASE ORDER</small></strong></a>
    @if($settingsNavigation)<span class="sbg-navigation-title">Pengaturan Akun</span>@else
    <button type="button" class="sbg-top-search" @click="mobilePage === 'admin' ? document.getElementById('master-search').focus() : focusPoSearch()"><x-sbg.icon name="search" :size="18"/><span x-text="mobilePage === 'admin' ? 'Cari daerah, nama, email, atau role…' : 'Cari PO, supplier, atau nama barang…'"></span><kbd>⌘ K</kbd></button>
    <button type="button" class="sbg-icon-button sbg-mobile-create" @click="isCreateModalOpen = true" aria-label="Buat PO Baru" title="Buat PO Baru"><x-sbg.icon name="plus"/></button>
    @endif
    <x-sbg.account-menu/>
</header>
<nav class="sbg-bottom-nav" aria-label="Navigasi mobile">
    <a href="{{ $dashboardUrl }}#ringkasan" @if(!$settingsNavigation) @click.prevent="navigateFragment('ringkasan')" :aria-current="navigationActive('ringkasan') ? 'page' : null" @endif><x-sbg.icon name="home"/><span>Beranda</span></a>
    <a href="{{ $dashboardUrl }}#data-po" @if(!$settingsNavigation) @click.prevent="navigateFragment('data-po')" :aria-current="navigationActive('data-po') ? 'page' : null" @endif><x-sbg.icon name="po"/><span>Purchase Order</span></a>
    @if($settingsNavigation)<a href="{{ url('/settings/account') }}" aria-current="page"><x-sbg.icon name="settings"/><span>Akun</span></a>@else<a href="{{ $dashboardUrl }}#kalkulator-buah" @click.prevent="navigateFragment('kalkulator-buah')" :aria-current="activeView === 'fruit' ? 'page' : null"><x-sbg.icon name="calculator"/><span>Kalkulator</span></a>@endif
    <button type="button" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="sbg-sidebar"><x-sbg.icon name="grid"/><span>Lainnya</span></button>
</nav>
