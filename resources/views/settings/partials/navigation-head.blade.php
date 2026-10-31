@foreach(['sbg-v2','sbg-v23','sbg-v24'] as $sheet)
<link rel="stylesheet" href="{{ asset('css/'.$sheet.'.css') }}?v={{ filemtime(public_path('css/'.$sheet.'.css')) }}">
@endforeach
@include('components.sbg.theme-head')
<link rel="stylesheet" href="{{ asset('css/sbg-navigation.css') }}?v={{ filemtime(public_path('css/sbg-navigation.css')) }}">
<script src="{{ asset('js/sbg-navigation.js') }}?v={{ filemtime(public_path('js/sbg-navigation.js')) }}"></script>
<script src="{{ asset('js/sbg-v23.js') }}?v={{ filemtime(public_path('js/sbg-v23.js')) }}"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script defer src="{{ asset('js/sbg-v2.js') }}?v={{ filemtime(public_path('js/sbg-v2.js')) }}"></script>
