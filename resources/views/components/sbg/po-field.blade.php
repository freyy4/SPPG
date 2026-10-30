@php($poFieldId = 'po25-'.$poMode.'-'.$field)
<div class="sbg-po25-field">
    <label for="{{ $poFieldId }}">{{ $label }}</label>
    @if(($type ?? 'text') === 'select')
        <select id="{{ $poFieldId }}" name="{{ $field }}" x-model="draft.{{ $field }}" {{ !empty($required) ? 'required' : '' }} :aria-invalid="!!errors.{{ $field }}" aria-describedby="{{ $poFieldId }}-error">
            <option value="" disabled>Pilih {{ $label }}</option>
            @foreach($options as $value => $caption)<option value="{{ $value }}">{{ $caption }}</option>@endforeach
        </select>
    @elseif(($type ?? 'text') === 'textarea')
        <textarea id="{{ $poFieldId }}" name="{{ $field }}" x-model="draft.{{ $field }}" rows="2" placeholder="Tulis catatan tambahan di sini…" :aria-invalid="!!errors.{{ $field }}" aria-describedby="{{ $poFieldId }}-error"></textarea>
    @else
        <div class="{{ ($type ?? '') === 'currency' ? 'sbg-po25-money' : '' }}">
            @if(($type ?? '') === 'currency')<span aria-hidden="true">Rp</span>@endif
            <input id="{{ $poFieldId }}" name="{{ $field }}" type="{{ ($type ?? 'text') === 'currency' ? 'text' : ($type ?? 'text') }}" x-model="draft.{{ $field }}"
                   {{ !empty($required) ? 'required' : '' }} {{ isset($maxlength) ? 'maxlength='.$maxlength : '' }}
                   @if(($type ?? '') === 'number') min="0" max="99999999.99" step="0.01" @endif
                   @if(($type ?? '') === 'currency') inputmode="decimal" @input="draft.{{ $field }} = formatPriceInput($event.target)" @endif
                   @if(!empty($disabled)) :disabled="{{ $disabled }}" @endif
                   :aria-invalid="!!errors.{{ $field }}" aria-describedby="{{ $poFieldId }}-error">
        </div>
    @endif
    <p id="{{ $poFieldId }}-error" class="sbg-po25-field-error" x-show="errors.{{ $field }}" x-text="(errors.{{ $field }} || [])[0]" role="alert"></p>
</div>
