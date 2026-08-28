{{-- Who they ask for and where they go. --}}
<h2 class="styledesk_heading">{{ __('clients.module.workspace.preferred.title') }}</h2>

<dl class="mt-3 space-y-3">
    <div>
        <dt class="styledesk_label">{{ __('clients.module.workspace.preferred.staff') }}</dt>
        <dd class="mt-0.5">
            @if ($client->preferredStaff)
                <a href="{{ route('settings.staff.show', $client->preferredStaff) }}"
                   class="text-[13px] font-medium text-head hover:text-link transition-colors">
                    {{ $client->preferredStaff->displayName() }}
                </a>
                @if ($client->preferredStaff->roleName())
                    <p class="text-[12px] text-sub">{{ $client->preferredStaff->roleName() }}</p>
                @endif
            @else
                <span class="text-[13px] text-faint">{{ __('clients.module.workspace.preferred.none') }}</span>
            @endif
        </dd>
    </div>

    <div>
        <dt class="styledesk_label">{{ __('clients.module.workspace.preferred.location') }}</dt>
        <dd class="mt-0.5">
            @if ($client->preferredLocation)
                <a href="{{ route('settings.locations.show', $client->preferredLocation) }}"
                   class="text-[13px] font-medium text-head hover:text-link transition-colors">
                    {{ $client->preferredLocation->name }}
                </a>
            @else
                <span class="text-[13px] text-faint">{{ __('clients.module.workspace.preferred.none') }}</span>
            @endif
        </dd>
    </div>
</dl>
