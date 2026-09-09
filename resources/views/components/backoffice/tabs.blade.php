{{--
    The client record's six faces.

    Links, not panels toggled by a script: each tab is a URL an administrator
    can bookmark, send to a colleague and reload without losing the search and
    the page they were on. That also keeps every tab's query — the services
    search, the email-log filter — server-side, where the console's tables
    already live.
--}}
@props(['tabs', 'current'])

<div class="mt-5 border-b border-line">
    <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="{{ __('backoffice.tabs.label') }}">
        @foreach ($tabs as $tab)
            @php $active = $tab['key'] === $current; @endphp

            <a href="{{ $tab['url'] }}"
               @if ($active) aria-current="page" @endif
               @class([
                   'whitespace-nowrap px-3.5 py-2.5 text-[13px] font-semibold border-b-2 transition-colors',
                   'border-brand text-brand' => $active,
                   'border-transparent text-sub hover:text-head hover:border-line' => ! $active,
               ])>
                {{ $tab['label'] }}

                @if (($tab['count'] ?? null) !== null)
                    <span @class([
                        'ml-1.5 rounded-full px-1.5 py-0.5 text-[11px] tabular-nums',
                        'bg-brand/10 text-brand' => $active,
                        'bg-slate-100 text-slate-500' => ! $active,
                    ])>{{ number_format($tab['count']) }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
