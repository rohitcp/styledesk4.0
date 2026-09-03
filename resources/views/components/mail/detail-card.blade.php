{{--
    The structured block a transactional email shows: appointment details,
    payment summary, whatever the event is about.

    Rows in, layout out. The owner chooses which rows appear; StyleDesk decides
    what they look like, so nobody has to build a table in an email client that
    renders through Word.

    A row whose value is empty is dropped rather than shown blank — "Staff:" on
    a line by itself reads as data the product lost.
--}}
@props([
    'title' => null,
    /** [label => value]. Empty values are dropped. */
    'rows' => [],
    /** Rendered heavier, with a rule above: the total, the balance. */
    'strongRows' => [],
])

@php
    $ink = '#0f0f10';
    $muted = '#6b7280';
    $line = '#e5e7eb';

    $rows = array_filter($rows, fn ($value) => filled($value));
    $strongRows = array_filter($strongRows, fn ($value) => filled($value));
@endphp

@if (count($rows) || count($strongRows))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%; margin-top:24px; background-color:#fafafa; border:1px solid {{ $line }}; border-radius:10px;">
        <tr>
            <td style="padding:20px 24px;">
                @if ($title)
                    <p style="margin:0 0 14px 0; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:{{ $muted }};">
                        {{ $title }}
                    </p>
                @endif

                {{-- Two columns on a desktop, one on a phone.
                     Each cell is a label above its value rather than a label
                     opposite it: paired left-and-right works at full width and
                     falls apart at 320px, where a long value wraps under a
                     label it no longer lines up with.

                     The stacking is a media query on `.sd-cell`, so Outlook —
                     which ignores media queries and renders through Word —
                     keeps the two-column table it can draw properly. --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    @foreach (array_chunk(array_keys($rows), 2) as $pair)
                        <tr>
                            @foreach ($pair as $label)
                                <td class="sd-cell" width="50%" valign="top"
                                    style="width:50%; padding:6px 12px 6px 0; vertical-align:top;">
                                    <span style="display:block; font-size:12px; line-height:1.5; color:{{ $muted }};">{{ $label }}</span>
                                    <span style="display:block; font-size:14px; line-height:1.5; font-weight:600; color:{{ $ink }};">{{ $rows[$label] }}</span>
                                </td>
                            @endforeach

                            {{-- An odd number of rows leaves the last cell
                                 half-width; the filler keeps the table square
                                 rather than letting it stretch. --}}
                            @if (count($pair) === 1)
                                <td class="sd-cell" width="50%" style="width:50%;">&nbsp;</td>
                            @endif
                        </tr>
                    @endforeach

                    @foreach ($strongRows as $label => $value)
                        <tr>
                            <td colspan="2" style="padding:8px 0 0 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                    <tr><td style="border-top:1px solid {{ $line }}; font-size:0; line-height:0;">&nbsp;</td></tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0 0 0; font-size:14px; line-height:1.6; font-weight:700; color:{{ $ink }};">{{ $label }}</td>
                            <td align="right" style="padding:8px 0 0 0; font-size:16px; line-height:1.6; font-weight:700; color:{{ $ink }};">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif
