{{--
    Reach them: email, SMS, call.

    Its own partial because the right column orders it above the payment
    summary while the rest of the client context sits below — three things in
    one file cannot be interleaved with a fourth.

    The same three the client profile offers, in the same order and the same
    shape. Disabled rather than hidden where the detail is missing: "no email
    on file" is worth knowing, and a row that changes shape per client is a row
    nobody learns.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('clients.module.workspace.contact.title') }}</h2>

    <div class="grid grid-cols-3 gap-2 mt-2.5">
        @foreach ([
            ['scheme' => 'mailto:', 'value' => $email, 'icon' => 'envelope', 'label' => __('clients.module.workspace.contact.email_short'), 'tip' => __('clients.module.workspace.contact.send_email'), 'none' => __('clients.module.workspace.contact.no_email')],
            ['scheme' => 'sms:', 'value' => $phone, 'icon' => 'comment-sms', 'label' => __('clients.module.workspace.contact.sms_short'), 'tip' => __('clients.module.workspace.contact.send_sms'), 'none' => __('clients.module.workspace.contact.no_phone')],
            ['scheme' => 'tel:', 'value' => $phone, 'icon' => null, 'label' => __('clients.module.workspace.contact.call'), 'tip' => __('clients.module.workspace.contact.call'), 'none' => __('clients.module.workspace.contact.no_phone')],
        ] as $action)
            @if ($action['value'])
                <a href="{{ $action['scheme'].$action['value'] }}" class="styledesk_action styledesk_action--sm justify-center"
                   data-tip="{{ $action['tip'] }}">
                    @if ($action['icon'])<x-icon :name="$action['icon']" size="13" />@endif
                    <span class="truncate">{{ $action['label'] }}</span>
                </a>
            @else
                <span class="styledesk_action styledesk_action--sm justify-center opacity-50 cursor-not-allowed"
                      aria-disabled="true" data-tip="{{ $action['none'] }}">
                    @if ($action['icon'])<x-icon :name="$action['icon']" size="13" />@endif
                    <span class="truncate">{{ $action['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
</section>
