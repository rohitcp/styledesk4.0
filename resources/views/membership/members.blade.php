@extends('layouts.app')

@section('title', __('membership.members.title'))

{{--
    Clients → Membership → Members.

    Everybody holding one, ordered by who renews soonest. That is the question
    this list is opened to answer: a renewal that fails is a member who
    quietly stops being one, and a list sorted by name would bury them.

    A plain table rather than the data grid the plans use. This list is read,
    not filtered — the counts above it are the filters anybody wants — and a
    grid would be a JSON endpoint and a column config for six columns nobody
    reorders.
--}}

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    @include('membership._header')

    @if ($memberships->isEmpty())
      <div class="mt-6 border-t border-line py-16 text-center">
        <p class="text-[15px] font-semibold text-head">{{ __('membership.members.none') }}</p>
        <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto">{{ __('membership.members.none_hint') }}</p>
      </div>
    @else
      <div class="mt-5 styledesk_gridframe overflow-x-auto">
        <table class="w-full min-w-[720px] text-[13px]">
          <thead>
            <tr class="border-b border-line text-left">
              @foreach (['client', 'membership', 'status', 'started', 'next_billing', 'credits'] as $column)
                <th class="px-4 py-2.5 text-[12px] font-semibold text-sub">
                  {{ __('membership.members.columns.'.$column) }}
                </th>
              @endforeach
            </tr>
          </thead>

          <tbody class="divide-y divide-line">
            @foreach ($memberships as $membership)
              @php
                /* What is actually left, across every credit on this
                   membership. Expired ones are not counted — a number that
                   included them would be a promise the desk cannot keep. */
                $left = $membership->credits->sum(fn ($credit) => $credit->isSpendable() ? $credit->remaining() : 0);
              @endphp

              <tr>
                <td class="px-4 py-3">
                  <a href="{{ route('clients.show', $membership->client) }}"
                     class="font-semibold text-head hover:text-brand transition-colors">
                    {{ $membership->client->displayName() }}
                  </a>
                </td>

                <td class="px-4 py-3">
                  <a href="{{ route('membership.show', $membership->plan) }}"
                     class="text-ink hover:text-brand transition-colors">{{ $membership->plan->name }}</a>
                  <span class="block text-[12px] text-sub">{{ $membership->priceLabel() }}</span>
                </td>

                <td class="px-4 py-3">
                  <span class="styledesk_badge {{ $membership->statusClass() }}">{{ $membership->statusLabel() }}</span>
                </td>

                <td class="px-4 py-3 text-sub">{{ $membership->starts_on->translatedFormat('j M Y') }}</td>

                <td class="px-4 py-3 text-sub">
                  {{ $membership->next_billing_on?->translatedFormat('j M Y') ?? __('membership.members.no_billing') }}
                </td>

                <td class="px-4 py-3">
                  <span @class(['font-semibold text-head' => $left > 0, 'text-sub' => $left === 0])>
                    {{ $left > 0 ? $left : __('membership.members.credits_none') }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-4">{{ $memberships->links() }}</div>
    @endif
  </main>
@endsection
