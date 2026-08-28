{{--
    A named set of checkboxes posting as name[].

    Used for every "choose which of these apply" group on the page — search
    fields, booking panels, history panels, duplicate rules, creation sources.
    One partial, so a set added later cannot come out looking different from
    the five already there.
--}}
@props(['name', 'legend', 'options' => [], 'selected' => [], 'hint' => null, 'columns' => 2, 'unavailable' => []])

<fieldset>
    <legend class="text-[13px] font-medium text-ink mb-2">{{ $legend }}</legend>

    {{-- One card per row. `columns` still widens the grid on a large screen
         for the long sets, because twenty options in a single column is a
         scroll rather than a choice. --}}
    <div class="styledesk_choicelist @if ($columns === 3) sm:grid-cols-3 @elseif ($columns === 2) sm:grid-cols-2 @endif">
        @foreach ($options as $value => $label)
            @php $isUnavailable = in_array($value, $unavailable, true); @endphp

            {{-- An unavailable option is shown and disabled rather than
                 hidden: it is on the roadmap, and a business that expects
                 point of sale should see that we know about it rather than
                 wonder whether we do. --}}
            <x-choice :name="$name.'[]'" :value="$value"
                      :checked="in_array($value, $selected, true)"
                      :disabled="$isUnavailable">
                {{ $label }}
                @if ($isUnavailable)
                    <span class="text-[12px] text-faint">— {{ __('clients.creation_unavailable') }}</span>
                @endif
            </x-choice>
        @endforeach
    </div>

    @if ($hint)
        <p class="mt-2 text-[12px] text-sub leading-relaxed">{{ $hint }}</p>
    @endif
</fieldset>
