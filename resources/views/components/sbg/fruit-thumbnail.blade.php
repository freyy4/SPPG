@props(['name' => null, 'dynamic' => false])
@php
    $fruit = collect(['semangka' => 'watermelon', 'pisang' => 'banana', 'mangga' => 'mango', 'alpukat' => 'avocado', 'apel' => 'apple', 'pear' => 'pear', 'pir' => 'pear'])
        ->first(fn ($value, $key) => str_contains(strtolower($name ?? ''), $key)) ?? 'produce';
@endphp
<span {{ $attributes->class('sbg-fruit-thumbnail') }} aria-hidden="true"><svg viewBox="0 0 64 64" width="56" height="56"><use @if($dynamic) data-fruit-src="{{ asset('images/sbg-fruits.svg') }}" :href="$el.dataset.fruitSrc + '#' + fruitSymbol(selectedPo.item_name)" @else href="{{ asset('images/sbg-fruits.svg').'#'.$fruit }}" @endif/></svg></span>
