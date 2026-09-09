{{--
    The four figures at the top of a client's profile.

    Last visit, next appointment, total visits, lifetime spend — each counted
    from the diary and the till rather than from a column something else has
    to remember to keep in step. See App\Support\ClientVisitSummary for what
    each one counts and, more importantly, what each one refuses to count.

    A Vue island rather than four Blade cards, because they go stale while
    somebody else works: a payment taken at the till, a booking marked
    complete in the diary. The island asks for them again when the tab comes
    back to the front.
--}}
@php
    $summaryProps = [
        'url' => route('clients.visit-summary', $client),
        'summary' => $visitSummary,
        'labels' => [
            'last_visit' => __('clients.module.workspace.summary.last_visit'),
            'last_visit_empty' => __('clients.module.workspace.summary.no_visits'),
            'next_appointment' => __('clients.module.workspace.summary.next_appointment'),
            'next_appointment_empty' => __('clients.module.workspace.summary.no_upcoming'),
            'total_visits' => __('clients.module.workspace.summary.total_visits'),
            'total_visits_empty' => __('clients.module.workspace.summary.no_visits_yet'),
            'lifetime_spend' => __('clients.module.workspace.summary.lifetime_spend'),
            'lifetime_spend_empty' => __('clients.module.workspace.summary.nothing_paid'),
        ],
    ];
@endphp

<div data-vue-component="ClientVisitSummary" data-props='@json($summaryProps)'></div>

{{-- Without the island the figures still have to be readable, so they are
     stated in plain HTML rather than leaving an empty row. --}}
<noscript>
    <div class="styledesk_metrics">
        @foreach ([['last_visit', 'blue'], ['next_appointment', 'violet'], ['total_visits', 'teal'], ['lifetime_spend', 'amber']] as [$key, $tone])
            <div class="styledesk_metric styledesk_metric--{{ $tone }}">
                <p class="styledesk_metric__label">{{ $summaryProps['labels'][$key] }}</p>

                @if (! empty($visitSummary[$key]['value']))
                    <p class="styledesk_metric__value {{ mb_strlen($visitSummary[$key]['value']) > 12 ? 'styledesk_metric__value--compact' : '' }}">{{ $visitSummary[$key]['value'] }}</p>
                    @if (! empty($visitSummary[$key]['detail']))
                        <p class="text-[12px] text-sub mt-0.5">{{ $visitSummary[$key]['detail'] }}</p>
                    @endif
                @else
                    <p class="styledesk_metric__empty">{{ $summaryProps['labels'][$key.'_empty'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</noscript>
