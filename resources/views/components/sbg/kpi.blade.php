@props(['label', 'value', 'icon', 'tone' => 'green', 'money' => false, 'payment' => false, 'hint' => 'Lihat rincian'])
<button type="button" {{ $attributes->class(['sbg-kpi', 'sbg-kpi-payment' => $payment]) }} data-tone="{{ $tone }}">
    <span class="sbg-kpi-icon"><x-sbg.icon :name="$icon"/></span>
    <span class="sbg-kpi-label">{{ $label }}</span>
    <span class="sbg-kpi-value" title="{{ ($money ? 'Rp ' : '').number_format($value, 0, ',', '.') }}">@if($money)<small>Rp</small> @endif{{ number_format($value, 0, ',', '.') }}</span>
    <span class="sbg-kpi-hint">{{ $hint }} <x-sbg.icon name="arrow" :size="14"/></span>
</button>
