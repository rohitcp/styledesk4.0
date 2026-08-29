{{--
    One contact row. Used both for the rows a client already has and for the
    blank template a new row is cloned from, so the two cannot drift — a
    template that had quietly fallen behind the rendered rows would produce
    fields that post under names the server no longer reads.
--}}
@props([
    'kind', 'group', 'key', 'row', 'types', 'checked' => false, 'country' => 'US',
    /** Where the browser may ask whether an address is already on a client. */
    'remoteCheck' => null,
])

@php
    $isPhone = $kind === 'phone';

    /* Every row needs an id of its own: the shared validator addresses a
       message to the id of the field it is about, and these rows are cloned
       from a template where the key is a placeholder. __KEY__ is rewritten
       with the rest of the row's names when a row is added. */
    $rowId = $group.'_'.$key.'_'.($isPhone ? 'number' : 'email');
@endphp

{{-- A column, so the row's message sits under the row rather than beside the
     next control on it. --}}
<div class="flex flex-col gap-1" data-contact-row data-key="{{ $key }}">
<div class="flex flex-wrap items-center gap-2">
    @if ($isPhone)
        <div class="relative flex-1 min-w-[200px]"
             data-phone data-phone-country="{{ $row['country'] ?: $country }}">
            <div class="sd-phone">
                <button type="button" class="sd-phone__country" data-phone-toggle
                        aria-haspopup="listbox" aria-expanded="false"
                        aria-label="{{ __('clients.module.contacts.country_code') }}">
                    <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                    <span class="font-medium" data-phone-code>+1</span>
                </button>
                <input id="{{ $rowId }}" name="{{ $group }}[{{ $key }}][number]" type="tel" class="sd-phone__field"
                       data-phone-input autocomplete="tel-national" data-rules="phone"
                       aria-label="{{ __('clients.module.contacts.phone_number') }}"
                       value="{{ $row['number'] }}">
            </div>
            <div class="sd-pop" data-phone-pop hidden></div>
            <input type="hidden" name="{{ $group }}[{{ $key }}][country]" data-phone-country-value
                   value="{{ $row['country'] ?: $country }}">
        </div>
    @else
        <input id="{{ $rowId }}" name="{{ $group }}[{{ $key }}][email]" type="email"
               class="sd-input flex-1 !w-auto min-w-[180px]"
               aria-label="{{ __('clients.module.contacts.email_address') }}"
               data-rules="email|max:255"
               @if ($remoteCheck) data-remote-check="{{ $remoteCheck }}" @endif
               value="{{ $row['email'] }}">
    @endif

    <select name="{{ $group }}[{{ $key }}][type]" class="sd-input !w-[136px] shrink-0"
            data-combo data-combo-options='{"search":false,"width":"160px"}'
            aria-label="{{ $isPhone ? __('clients.module.contacts.phone_type') : __('clients.module.contacts.email_type') }}">
        @foreach ($types as $value => $typeLabel)
            <option value="{{ $value }}" @selected($row['type'] === $value)>{{ $typeLabel }}</option>
        @endforeach
    </select>

    {{-- Priority is its own control, next to the type: "work" says what kind
         of number it is, this says which to ring first. Only one row can be
         Primary, and choosing it here demotes whichever row held it — so the
         two facts cannot contradict each other on screen. --}}
    <select name="{{ $group }}[{{ $key }}][priority]" class="sd-input !w-[136px] shrink-0" data-contact-priority
            data-combo data-combo-options='{"search":false,"width":"160px"}'
            aria-label="{{ __('clients.module.contacts.priority') }}">
        <option value="primary" @selected($checked)>{{ __('clients.module.contacts.primary') }}</option>
        <option value="secondary" @selected(! $checked)>{{ __('clients.module.contacts.secondary') }}</option>
    </select>

    <button type="button" data-remove-contact
            class="styledesk_action styledesk_action--icon styledesk_action--tall styledesk_action--remove shrink-0"
            aria-label="{{ __('clients.module.contacts.remove') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 7h12M10 7V5.5a1 1 0 011-1h2a1 1 0 011 1V7M8 7l.7 12a1 1 0 001 1h4.6a1 1 0 001-1L16 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
</div>

{{-- The row's own message. Addressed to the field it is about, so a form with
     three numbers on it says which one it means. --}}
<p data-error-for="{{ $rowId }}" role="alert" class="text-[12px] text-danger" hidden></p>
</div>
