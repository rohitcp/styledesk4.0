{{--
    Contact details and how this client may be reached.

    Guarded by clients.view_contact: a business that separated "can see the
    client list" from "can see their phone number" meant it, and the section
    says so rather than rendering blank.
--}}
<h2 class="styledesk_heading">{{ __('clients.module.workspace.contact.title') }}</h2>

@unless ($canViewContact)
    <p class="text-[12px] text-sub mt-2 leading-relaxed">{{ __('clients.module.workspace.contact.hidden') }}</p>
@else
    <div class="mt-3 space-y-3">
        {{-- The primary alone, with the rest behind a disclosure: the number
             to ring is the answer most readers came for. --}}
        <div>
            <p class="styledesk_label">{{ __('clients.module.workspace.contact.phone') }}</p>

            @if ($primaryPhone)
                <div class="flex items-start gap-2 mt-1">
                    <div class="min-w-0 flex-1">
                        <a href="tel:{{ $primaryPhone->number }}" class="text-[13px] text-head font-medium hover:text-link transition-colors break-all">
                            {{ $primaryPhone->number }}
                        </a>
                        <p class="text-[12px] text-sub">{{ $primaryPhone->typeLabel() }} · {{ __('clients.module.contacts.primary') }}</p>
                    </div>

                    <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon shrink-0"
                            data-copy="{{ $primaryPhone->number }}"
                            data-tip="{{ __('clients.module.workspace.contact.copy') }}"
                            aria-label="{{ __('clients.module.workspace.contact.copy') }}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8.5" y="8.5" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 5.5h-9a2 2 0 00-2 2v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                @if ($client->phones->count() > 1)
                    <details class="mt-1.5">
                        <summary class="text-[12px] font-medium text-link cursor-pointer hover:underline">
                            {{ __('clients.module.workspace.contact.view_all_numbers', ['count' => $client->phones->count()]) }}
                        </summary>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($client->phones->skip(1) as $phone)
                                <li class="text-[12px] text-ink">
                                    <a href="tel:{{ $phone->number }}" class="hover:text-link transition-colors">{{ $phone->number }}</a>
                                    <span class="text-sub">· {{ $phone->typeLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @else
                <p class="text-[12px] text-faint mt-1">{{ __('clients.module.workspace.contact.none_recorded') }}</p>
            @endif
        </div>

        <div>
            <p class="styledesk_label">{{ __('clients.module.workspace.contact.email') }}</p>

            @if ($primaryEmail)
                <div class="flex items-start gap-2 mt-1">
                    <div class="min-w-0 flex-1">
                        <a href="mailto:{{ $primaryEmail->email }}" class="text-[13px] text-head font-medium hover:text-link transition-colors break-all">
                            {{ $primaryEmail->email }}
                        </a>
                        <p class="text-[12px] text-sub">{{ $primaryEmail->typeLabel() }} · {{ __('clients.module.contacts.primary') }}</p>
                    </div>

                    <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon shrink-0"
                            data-copy="{{ $primaryEmail->email }}"
                            data-tip="{{ __('clients.module.workspace.contact.copy') }}"
                            aria-label="{{ __('clients.module.workspace.contact.copy') }}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8.5" y="8.5" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 5.5h-9a2 2 0 00-2 2v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                @if ($client->emails->count() > 1)
                    <details class="mt-1.5">
                        <summary class="text-[12px] font-medium text-link cursor-pointer hover:underline">
                            {{ __('clients.module.workspace.contact.view_all_emails', ['count' => $client->emails->count()]) }}
                        </summary>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($client->emails->skip(1) as $email)
                                <li class="text-[12px] text-ink break-all">
                                    <a href="mailto:{{ $email->email }}" class="hover:text-link transition-colors">{{ $email->email }}</a>
                                    <span class="text-sub">· {{ $email->typeLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @else
                <p class="text-[12px] text-faint mt-1">{{ __('clients.module.workspace.contact.none_recorded') }}</p>
            @endif
        </div>

        @if (filled($address) || $country)
            <div>
                <p class="styledesk_label">{{ __('clients.module.workspace.contact.address') }}</p>

                <div class="flex items-start gap-2 mt-1">
                    <p class="text-[13px] text-ink min-w-0 flex-1 leading-relaxed">
                        {{ $address }}@if ($address && $country), @endif{{ $country }}
                    </p>

                    <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon shrink-0"
                            data-copy="{{ trim($address.($country ? ', '.$country : '')) }}"
                            data-tip="{{ __('clients.module.workspace.contact.copy') }}"
                            aria-label="{{ __('clients.module.workspace.contact.copy') }}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8.5" y="8.5" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 5.5h-9a2 2 0 00-2 2v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- How they may be reached, which is not the same as how to reach them:
         these are permissions the client gave. Every channel is listed with
         its state rather than only the granted ones appearing — a reader
         checking whether they may text someone needs to see the answer, not
         infer it from an absence. --}}
    <div class="mt-4 pt-3 border-t border-line">
        <p class="styledesk_label">{{ __('clients.module.workspace.preferences.contact_title') }}</p>

        <dl class="mt-1.5 space-y-1">
            @foreach (['comm_email' => 'email', 'comm_sms' => 'sms', 'comm_phone' => 'phone'] as $switch => $method)
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-[12px] text-sub">{{ $methods[$method] }}</dt>
                    <dd><x-consent-status :granted="$client->{$switch}" /></dd>
                </div>
            @endforeach

            @foreach (['marketing_email' => 'email', 'marketing_sms' => 'sms'] as $switch => $method)
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-[12px] text-sub">{{ __('clients.module.workspace.preferences.marketing') }} · {{ $methods[$method] }}</dt>
                    <dd><x-consent-status :granted="$client->{$switch}" /></dd>
                </div>
            @endforeach
        </dl>
    </div>
@endunless
