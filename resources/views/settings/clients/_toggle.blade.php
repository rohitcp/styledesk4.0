{{--
    One switch, with its own explanation beside it.

    A partial rather than repeated markup: this page has twenty-five of them,
    and twenty-five copies of the same six lines is where one of them quietly
    loses its label association.
--}}
@props(['name', 'label', 'hint' => null, 'checked' => false, 'disabled' => false])

<label class="flex items-start gap-2.5 @if ($disabled) opacity-60 cursor-not-allowed @else cursor-pointer @endif">
    <input type="checkbox" name="{{ $name }}" value="1" class="sd-check mt-0.5"
           @checked($checked) @disabled($disabled)>
    <span class="min-w-0">
        <span class="block text-[13px] text-ink">{{ $label }}</span>
        @if ($hint)
            <span class="block text-[12px] text-sub mt-0.5 leading-relaxed">{{ $hint }}</span>
        @endif
    </span>
</label>
