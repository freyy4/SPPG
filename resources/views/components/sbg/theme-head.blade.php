@php($themeAccount = auth()->check() ? (string) auth()->id() : 'guest')
<script>
(function(){var root=document.documentElement,account=@json($themeAccount),choice='system';try{choice=localStorage.getItem('sbg-theme:'+account)||'system'}catch(e){}if(!['light','dark','system'].includes(choice))choice='system';root.dataset.themeAccount=account;root.dataset.themePreference=choice;root.dataset.theme=choice==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):choice;root.style.colorScheme=root.dataset.theme;})();
</script>
<link rel="stylesheet" href="{{ asset('css/sbg-v25-theme.css') }}?v=2.5">
<link rel="stylesheet" href="{{ asset('css/sbg-v25-experience.css') }}?v=2.5">
<script src="{{ asset('js/sbg-v25-theme.js') }}?v=2.5" defer></script>
<script src="{{ asset('js/sbg-v25-experience.js') }}?v=2.5" defer></script>
