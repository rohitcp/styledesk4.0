{{--
    A client's phone numbers, or their email addresses.

    Repeatable rows rather than a single field, because a client with a mobile
    and a work line has two numbers, and a record that holds one forces
    whoever takes the next call to overwrite the other.

    Type and priority are two separate controls. "Work" says what kind of
    number it is; the radio says which to ring first. Folding them into one
    list would make a client whose work mobile is the number they answer
    unrepresentable.

    Rows are keyed by a token rather than a position, so removing the second
    of three does not renumber the rest out from under the row that was
    marked primary.
--}}
@props([
    /** Where the browser may ask whether an address is already on a client. */
    'remoteCheck' => null,
    'kind',
    'label',
    'rows',
    'types',
    'primary',
    'max',
    'required' => false,
    'optional' => false,
    'country' => 'US',
])

@php
    $isPhone = $kind === 'phone';
    $group = $isPhone ? 'phones' : 'emails';
    $blank = $isPhone
        ? ['number' => '', 'country' => null, 'type' => 'mobile']
        : ['email' => '', 'type' => 'personal'];
@endphp

@php
    $confirmLabels = [
        'title' => $isPhone ? __('clients.module.contacts.remove_phone_title') : __('clients.module.contacts.remove_email_title'),
        'message' => $isPhone ? __('clients.module.contacts.remove_phone_body', ['contact' => ':contact']) : __('clients.module.contacts.remove_email_body', ['contact' => ':contact']),
        'confirm' => __('clients.module.contacts.remove'),
    ];
@endphp

<div class="sm:col-span-2" data-contacts="{{ $group }}" data-max="{{ $max }}"
     data-confirm-labels='@json($confirmLabels)'>
    <span class="block text-[13px] font-medium text-ink mb-1.5">
        {{ $label }}
        @if ($required)
            <span class="text-danger">*</span>
        @elseif ($optional)
            <span class="text-faint font-normal">{{ __('common.optional') }}</span>
        @endif
    </span>

    <div class="space-y-2" data-contact-rows>
        @foreach ($rows as $key => $row)
            <x-clients.contact-row :kind="$kind" :group="$group" :key="$key" :row="$row" :types="$types" :country="$country"
                                   :remote-check="$remoteCheck"
                                   :checked="(string) $primary === (string) $key" />
        @endforeach
    </div>

    {{-- The blank row a new one is cloned from. Rendered by the same
         component as the real rows so the two cannot drift apart. --}}
    <template data-contact-template>
        <x-clients.contact-row :kind="$kind" :group="$group" key="__KEY__" :row="$blank" :types="$types"
                               :country="$country" :remote-check="$remoteCheck" />
    </template>

    {{-- Small and filled: adding a second number is a minor action beside the
         fields themselves, and a white full-height button reads as the thing
         to press. The tint sets it apart from the inputs above it without
         competing with the form's own Save. --}}
    <button type="button" data-add-contact
            class="styledesk_action styledesk_action--sm mt-2">
        <x-icon name="plus" size="14" />
        {{ $isPhone ? __('clients.module.contacts.add_phone') : __('clients.module.contacts.add_email') }}
    </button>

    @error($group)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    @foreach ($errors->get($group.'.*') as $messages)
        <p class="mt-1.5 text-[12px] text-danger">{{ $messages[0] }}</p>
    @endforeach
</div>
