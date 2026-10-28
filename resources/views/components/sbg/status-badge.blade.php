@props(['status' => null, 'dynamic' => false])
<span {{ $attributes->class('sbg-status') }} @if($dynamic) :data-status="selectedPo.status_stage" x-text="selectedPo.status_stage || 'Belum ditentukan'" @else data-status="{{ $status }}" @endif>{{ $dynamic ? '' : ($status ?: 'Belum ditentukan') }}</span>
