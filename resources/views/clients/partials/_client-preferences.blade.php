{{-- What this client likes. Five, then the rest behind a disclosure. --}}
<h2 class="styledesk_heading">{{ __('clients.module.workspace.preferences.title') }}</h2>

@if (! $settings->preferences_enabled || $client->preferences->isEmpty())
    <p class="text-[12px] text-faint mt-2">{{ __('clients.module.workspace.preferences.none') }}</p>
@else
    <ul class="mt-2.5 space-y-1.5">
        @foreach ($client->preferences->take(5) as $preference)
            <li class="flex items-start gap-2 text-[13px] text-ink">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-0.5 text-brand" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="min-w-0">{{ $preference->label }}</span>
            </li>
        @endforeach
    </ul>

    @if ($client->preferences->count() > 5)
        <details class="mt-2">
            <summary class="text-[12px] font-medium text-link cursor-pointer hover:underline">
                {{ __('clients.module.workspace.preferences.view_all', ['count' => $client->preferences->count()]) }}
            </summary>
            <ul class="mt-1.5 space-y-1.5">
                @foreach ($client->preferences->skip(5) as $preference)
                    <li class="flex items-start gap-2 text-[13px] text-ink">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-0.5 text-brand" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="min-w-0">{{ $preference->label }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
@endif
