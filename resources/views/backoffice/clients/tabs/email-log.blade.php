{{--
    What this business has emailed its own clients.

    The rows are copies of what went out rather than a view onto what would go
    out now — the subject, the recipient and the sender are all written onto
    the row when it is sent — which is what makes the log worth reading a year
    after the template it came from was rewritten.

    Details open in place rather than in a dialog. A hundred rows is a hundred
    dialogs carrying a hundred message bodies, and the reader comparing two
    failures wants both open at once.
--}}
@php
    $columns = [
        ['label' => __('backoffice.emails.col.sent'), 'sort' => 'sent', 'desc_first' => true],
        ['label' => __('backoffice.emails.col.recipient'), 'sort' => 'recipient'],
        ['label' => __('backoffice.emails.col.type')],
        ['label' => __('backoffice.emails.col.subject'), 'sort' => 'subject'],
        ['label' => __('backoffice.emails.col.status'), 'sort' => 'status'],
        ['label' => __('backoffice.emails.col.sent_by')],
        ['label' => __('backoffice.emails.col.provider')],
        ['label' => __('backoffice.emails.col.action'), 'align' => 'right'],
    ];

    $tones = [
        'queued' => 'bg-slate-100 text-slate-600',
        'sent' => 'bg-sky-50 text-sky-700',
        'delivered' => 'bg-emerald-50 text-emerald-700',
        'failed' => 'bg-red-50 text-red-700',
    ];

    $statusOptions = collect($emailStatuses)
        ->mapWithKeys(fn (string $status): array => [$status => __('backoffice.emails.statuses.'.$status)])
        ->all();
@endphp

<x-backoffice.data-table
    :paginator="$emails"
    :columns="$columns"
    :sort="$emailFilters['sort']"
    :direction="$emailFilters['direction']"
    :search="$emailFilters['search']"
    :search-placeholder="__('backoffice.emails.search_placeholder')"
    :per-page="$emailFilters['per_page']"
    :caption="__('backoffice.tabs.email_log')"
    :empty="__('backoffice.emails.empty')"
    min-width="1100px"
    :filters="[[
        'name' => 'status',
        'label' => __('backoffice.emails.col.status'),
        'value' => $emailFilters['status'],
        'options' => $statusOptions,
    ]]">

    @foreach ($emails as $email)
        <tr class="odd:bg-white even:bg-[#fcfcfd] hover:bg-brand/[0.04] transition-colors">
            <td class="px-4 py-3 align-top whitespace-nowrap text-sub">
                {{ \App\Support\TimeFormat::dateTime($email->sent_at ?? $email->created_at) }}
            </td>

            <td class="px-4 py-3 align-top text-head break-all">{{ $email->recipient_email }}</td>

            <td class="px-4 py-3 align-top text-sub">
                {{-- The template it came from, as a readable phrase. A message
                     somebody typed at the desk has no template and is not a
                     defect, so it is named rather than dashed. --}}
                {{ $email->template_key
                    ? \Illuminate\Support\Str::headline($email->template_key)
                    : __('backoffice.emails.manual') }}
            </td>

            <td class="px-4 py-3 align-top text-head">{{ $email->subject }}</td>

            <td class="px-4 py-3 align-top">
                <span @class([
                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11.5px] font-semibold whitespace-nowrap',
                    $tones[$email->status] ?? 'bg-slate-100 text-slate-600',
                ])>
                    {{ __('backoffice.emails.statuses.'.$email->status) }}
                </span>
            </td>

            <td class="px-4 py-3 align-top text-sub">
                {{ $email->sentBy?->name ?? __('backoffice.emails.automatic') }}
            </td>

            <td class="px-4 py-3 align-top text-sub whitespace-nowrap">
                {{ $email->provider ? \Illuminate\Support\Str::headline($email->provider) : __('backoffice.clients.none') }}
            </td>

            <td class="px-4 py-3 align-top text-right">
                <button type="button" class="text-[13px] font-semibold text-link hover:underline"
                        data-email-toggle="email-{{ $email->id }}"
                        aria-expanded="false" aria-controls="email-{{ $email->id }}">
                    {{ __('backoffice.emails.view_details') }}
                </button>
            </td>
        </tr>

        <tr id="email-{{ $email->id }}" class="bg-[#fbfbfc]" hidden>
            <td colspan="{{ count($columns) }}" class="px-4 py-4">
                <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-2 text-[12.5px]">
                    @php
                        $detail = [
                            __('backoffice.emails.detail.sender') => trim(($email->sender_name ?? '').' <'.$email->sender_email.'>'),
                            __('backoffice.emails.detail.queued') => \App\Support\TimeFormat::dateTime($email->queued_at)
                                ?? __('backoffice.clients.none'),
                            __('backoffice.emails.detail.sent') => \App\Support\TimeFormat::dateTime($email->sent_at)
                                ?? __('backoffice.clients.none'),
                            __('backoffice.emails.detail.booking') => $email->booking_id
                                ? '#'.$email->booking_id
                                : __('backoffice.clients.none'),
                        ];
                    @endphp

                    @foreach ($detail as $term => $value)
                        <div>
                            <dt class="text-sub">{{ $term }}</dt>
                            <dd class="text-head font-medium break-all">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($email->failure_reason)
                    <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[12.5px] text-red-800">
                        {{ __('backoffice.emails.detail.failure') }}: {{ $email->failure_reason }}
                    </p>
                @endif

                <div class="mt-3">
                    <p class="text-[12.5px] text-sub">{{ __('backoffice.emails.detail.message') }}</p>
                    {{-- The body as the recipient got it, wrapped rather than
                         rendered: this is a record being read, not an email
                         being previewed, and nothing a customer typed should
                         become markup on a console screen. --}}
                    <pre class="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-line bg-white px-3 py-2 text-[12.5px] text-ink font-sans">{{ $email->message }}</pre>
                </div>
            </td>
        </tr>
    @endforeach
</x-backoffice.data-table>

<script>
    /* Delegated, because the rows are replaced on every search and page. */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-email-toggle]');

        if (!button) {
            return;
        }

        const row = document.getElementById(button.dataset.emailToggle);
        const open = row.hasAttribute('hidden');

        row.toggleAttribute('hidden', !open);
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open
            ? @json(__('backoffice.emails.hide_details'))
            : @json(__('backoffice.emails.view_details'));
    });
</script>
