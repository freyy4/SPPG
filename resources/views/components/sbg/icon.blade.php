@props(['name', 'size' => 20])
<svg {{ $attributes->class('sbg-icon') }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>@break
        @case('po')<path d="M8 3H5v18h14V3h-3M9 2h6v4H9zM8 11h8M8 15h6"/>@break
        @case('calculator')<rect x="4" y="2" width="16" height="20" rx="3"/><path d="M8 6h8M8 11h1m6 0h1m-8 4h1m6 0h1m-8 4h1m6 0h1"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>@break
        @case('filter')<path d="M3 5h18l-7 8v6l-4 2v-8Z"/>@break
        @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>@break
        @case('check')<path d="m5 12 4 4L20 5"/>@break
        @case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 5"/>@break
        @case('wallet')<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m-6 5h3"/>@break
        @case('shield')<path d="m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6Z"/><path d="m8 11 3 3 5-5"/>@break
        @case('coins')<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v5c0 4 16 4 16 0V5M4 10v5c0 4 16 4 16 0v-5M4 15v4c0 4 16 4 16 0v-4"/>@break
        @case('plus')<path d="M12 4v16M4 12h16"/>@break
        @case('chart')<path d="M5 20V9m7 11V4m7 16v-7"/>@break
        @case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
        @case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
        @case('more')<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>@break
        @case('edit')<path d="m15 4 5 5M3 21l5-1L21 7l-5-5L3 15Z"/>@break
        @case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>@break
        @case('print')<path d="M6 9V3h12v6M6 17H3v-7h18v7h-3M6 14h12v7H6zM17 11h1"/>@break
        @case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>@break
        @case('box')<path d="m12 2 9 5v10l-9 5-9-5V7Zm-9 5 9 5 9-5M12 12v10M7 4l10 6"/>@break
        @case('pin')<path d="M19 9c0 5-7 12-7 12S5 14 5 9a7 7 0 1 1 14 0Z"/><circle cx="12" cy="9" r="2"/>@break
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/>@break
        @case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
        @case('logout')<path d="M9 4H4v16h5m5-13 5 5-5 5m-6-5h12"/>@break
        @case('leaf')<path d="M4 20C0 8 12 1 21 3c1 11-6 18-17 17Zm0 0L16 8"/>@break
        @default<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>
    @endswitch
</svg>
