{{--
    This client's rewards balance, and where it came from — the whole of it,
    shared by the two screens that show it.

    The profile's Rewards tab and the Loyalty module's own client page are the
    same question asked from two directions: "how much have they got, and can
    they use it today". One copy, so the two cannot drift into disagreeing
    about what a balance is worth or which rewards are on offer.

    The ledger is the authority: the balance is the sum of its lines, and
    every line was written when the thing happened, by whoever did it.
    Nothing here can edit one — there is no route that does and the model
    refuses it — because a balance somebody can tidy is not a balance anybody
    can quote at the desk.
--}}
@php
    $currency = \App\Support\Currencies::resolve();

    $available = $loyaltySummary['available'];
    $rewardMinor = $loyalty->rewardMinorFor($available);
    $target = $loyalty->nextRewardAt($available);
    $toGo = $loyalty->pointsToNextReward($available);

    /* The bar fills against the balance the next reward unlocks at, so it
       empties and refills as rewards are reached rather than creeping towards
       a lifetime total nobody is working towards. */
    $progress = $target > 0 ? min(100, (int) round(($available / $target) * 100)) : 0;
@endphp

    @unless ($loyalty->is_enabled)
        {{-- Off, not empty. The balances and the history below are real and
             stay readable; only the earning and the spending have stopped. --}}
        <div class="sd-alert sd-alert--info" role="status">
            <div class="min-w-0">
                <p class="font-semibold">{{ __('loyalty.client.off') }}</p>
                <p class="mt-0.5">{{ __('loyalty.client.off_hint') }}</p>
            </div>
        </div>
    @endunless

    <div class="flex flex-wrap items-center gap-3 {{ $loyalty->is_enabled ? '' : 'mt-4' }}">
        <h2 class="text-[15px] font-semibold text-head min-w-0 flex-1">{{ $loyalty->program_name }}</h2>

        {{-- Not where the page puts it in its own header. The Loyalty
             module's client page does, because adjusting is the job that
             screen is open for; the profile's tab keeps it here, beside the
             balance it moves. --}}
        @if ($canAdjustPoints && $loyalty->is_enabled && ! ($rewardsAdjustInHeader ?? false))
            <button type="button" class="styledesk_action shrink-0" data-rewards-open>
                <x-icon name="sliders" size="14" />
                {{ __('loyalty.client.adjust') }}
            </button>
        @endif
    </div>

    {{-- ----------------------------------------------------- the figures --}}
    <div class="styledesk_metrics mt-4">
        @foreach ([['available', 'blue'], ['pending', 'violet'], ['lifetime_earned', 'teal'], ['lifetime_redeemed', 'amber'], ['expiring_soon', 'rose']] as [$key, $tone])
            <div class="styledesk_metric styledesk_metric--{{ $tone }}">
                <p class="styledesk_metric__label">{{ __('loyalty.client.'.$key) }}</p>
                <p class="styledesk_metric__value">{{ number_format($loyaltySummary[$key]) }}</p>

                @if ($key === 'available')
                    <p class="text-[12px] text-sub mt-0.5">
                        {{ $rewardMinor > 0
                            ? __('loyalty.client.worth', ['value' => \App\Support\Money::format($rewardMinor / 100, $currency)])
                            : __('loyalty.client.worth_nothing') }}
                    </p>
                @elseif ($key === 'pending')
                    <p class="text-[12px] text-sub mt-0.5">{{ __('loyalty.client.pending_hint') }}</p>
                @elseif ($key === 'expiring_soon')
                    <p class="text-[12px] text-sub mt-0.5">
                        {{ __('loyalty.client.expiring_hint', ['days' => $loyaltyExpiryDays]) }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ------------------------- membership, progress and the rule --------
         Three cards in the order somebody reads them: who they are in the
         scheme, how close they are to the next thing, and what a point is
         worth. The membership used to sit above the figures, which put the
         answer to "are they a member" between the heading and the balance
         that only exists because they are. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-4">
        {{-- Who they are in the scheme. A heading like its two neighbours,
             and the facts stacked rather than laid across: in a third of a
             row they would wrap into an uneven block, and these are read one
             at a time rather than compared. --}}
        <div class="sd-card p-4">
            <h3 class="text-[13px] font-semibold text-head">{{ __('loyalty.enrollment.status') }}</h3>

            @if ($client->isEnrolledInLoyalty())
                <p class="text-[20px] font-bold text-success mt-1.5 tracking-tight">
                    {{ $client->loyaltyStatusLabel() ?? __('loyalty.enrollment.enrolled') }}
                </p>

                <dl class="mt-2.5 space-y-1.5">
                    <div class="flex items-baseline gap-2">
                        <dt class="text-[12px] text-sub shrink-0">{{ __('loyalty.enrollment.member_id') }}</dt>
                        <dd class="text-[12.5px] font-semibold text-head ml-auto text-right">{{ $client->loyalty_member_id }}</dd>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <dt class="text-[12px] text-sub shrink-0">{{ __('loyalty.enrollment.member_since') }}</dt>
                        <dd class="text-[12.5px] font-semibold text-head ml-auto text-right">
                            {{ $client->loyalty_enrolled_at->isoFormat('D MMM Y') }}
                        </dd>
                    </div>

                    @if ($client->loyaltyEnrollmentSourceLabel())
                        <div class="flex items-baseline gap-2">
                            <dt class="text-[12px] text-sub shrink-0">{{ __('loyalty.client.source') }}</dt>
                            <dd class="text-[12.5px] font-semibold text-head ml-auto text-right">
                                {{ $client->loyaltyEnrollmentSourceLabel() }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($client->loyaltyEnrolledBy)
                    <p class="text-[12px] text-faint mt-2 leading-relaxed">
                        {{ __('loyalty.enrollment.enrolled_by', ['name' => $client->loyaltyEnrolledBy->name]) }}
                        @if ($client->loyaltyEnrollmentLocation)
                            <span class="text-faint">·</span> {{ $client->loyaltyEnrollmentLocation->name }}
                        @endif
                    </p>
                @endif
            @else
                {{-- Earning without ever having joined. A real state rather
                     than a broken one: every client predating the enrolment
                     step is in it, and their balances work exactly as
                     before. --}}
                <p class="text-[20px] font-bold text-head mt-1.5 tracking-tight">
                    {{ __('loyalty.enrollment.not_enrolled') }}
                </p>

                <p class="text-[12.5px] text-sub mt-2 leading-relaxed">
                    {{ __('loyalty.enrollment.not_enrolled_hint') }}
                </p>
            @endif
        </div>

        <div class="sd-card p-4">
            <h3 class="text-[13px] font-semibold text-head">{{ __('loyalty.client.next_reward') }}</h3>

            <p class="text-[20px] font-bold text-head mt-1.5 tracking-tight">
                {{ __('loyalty.client.progress', ['have' => number_format($available), 'need' => number_format($target)]) }}
            </p>

            <div class="mt-2.5 h-2 rounded-full bg-hover overflow-hidden"
                 role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div>
            </div>

            <p class="text-[12.5px] text-sub mt-2 leading-relaxed">
                {{ $toGo > 0
                    ? __('loyalty.client.to_go', [
                        'points' => number_format($toGo),
                        'value' => \App\Support\Money::format($loyalty->reward_value_minor / 100, $currency),
                    ])
                    : __('loyalty.client.unlocked', ['value' => \App\Support\Money::format($rewardMinor / 100, $currency)]) }}
            </p>
        </div>

        <div class="sd-card p-4">
            <h3 class="text-[13px] font-semibold text-head">{{ __('loyalty.client.reward_value') }}</h3>
            <p class="text-[20px] font-bold text-head mt-1.5 tracking-tight">{{ $loyalty->rewardRuleLabel($currency) }}</p>
            <p class="text-[12.5px] text-sub mt-2 leading-relaxed">
                {{ $rewardMinor > 0
                    ? __('loyalty.client.unlocked', ['value' => \App\Support\Money::format($rewardMinor / 100, $currency)])
                    : __('loyalty.client.worth_nothing') }}
            </p>
        </div>
    </div>

    {{-- ---------------------------------------------------- the catalogue --}}
    @if ($loyalty->is_enabled)
        <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('loyalty.client.catalogue') }}</h3>
        <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.client.catalogue_hint') }}</p>

        @if ($loyaltyRewards->isEmpty())
            <p class="text-[13px] text-sub mt-3">{{ __('loyalty.client.catalogue_empty') }}</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-3">
                @foreach ($loyaltyRewards as $reward)
                    @php
                        /* Afforded, or how far off. The balance is the whole
                           question a receptionist has with a client in front
                           of them, so it is answered on the card rather than
                           left to be worked out from two numbers. */
                        $short = max(0, (int) $reward->points_required - $available);
                    @endphp

                    <div class="sd-card p-4 {{ $short > 0 ? 'opacity-70' : '' }}">
                        <p class="text-[13.5px] font-semibold text-head">{{ $reward->name }}</p>

                        <p class="text-[12.5px] text-sub mt-0.5">
                            {{ $reward->typeLabel() }}
                            <span class="text-faint">·</span>
                            {{ $reward->valueLabel() }}
                        </p>

                        <p class="text-[18px] font-bold text-head mt-2 tracking-tight">
                            {{ __('loyalty.rewards.points', ['count' => number_format($reward->points_required)]) }}
                        </p>

                        <p class="text-[12px] mt-1 {{ $short > 0 ? 'text-sub' : 'text-success font-semibold' }}">
                            {{ $short > 0
                                ? __('loyalty.client.short_by', ['points' => number_format($short)])
                                : __('loyalty.client.affordable') }}
                        </p>

                        <p class="text-[12px] text-faint mt-1.5 leading-relaxed">
                            {{ $reward->scope === 'all_services'
                                ? __('loyalty.client.reward_any')
                                : __('loyalty.rewards.scopes.'.$reward->scope) }}
                        </p>

                        @if ($reward->description)
                            <p class="text-[12px] text-sub mt-1.5 leading-relaxed">{{ $reward->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ------------------------------------------------------- the ledger --}}
    <h3 class="text-[13px] font-semibold text-head mt-6">{{ __('loyalty.client.activity') }}</h3>

    @if ($loyaltyHistory->isEmpty())
        <p class="text-[13px] text-sub mt-3">{{ __('loyalty.client.none') }}</p>
    @else
        <div class="flex flex-wrap gap-1.5 mt-3">
            @foreach (config('loyalty.filters') as $filter => $types)
                <button type="button" data-rewards-filter="{{ $filter }}"
                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                        class="styledesk_action styledesk_action--sm {{ $loop->first ? 'is-active' : '' }}">
                    {{ __('loyalty.client.filters.'.$filter) }}
                </button>
            @endforeach
        </div>

        {{-- A statement, read across rather than down.

             The list this replaced said the same things in prose, which is
             right for a timeline and wrong for a ledger: "what was this
             against, where, who, and what became of it" is four questions
             with four answers per line, and four answers are columns.

             The reason and the note stay inside the activity cell rather
             than becoming columns of their own — they are a sentence about
             one line, not a value to compare down a column. --}}
        <div class="mt-3 overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-line">
                @foreach (['date', 'activity', 'points', 'balance', 'source', 'location', 'staff', 'status'] as $column)
                  <th scope="col"
                      class="text-[12px] font-semibold text-sub whitespace-nowrap py-2 pr-4 {{ in_array($column, ['points', 'balance'], true) ? 'text-right' : '' }}">
                    {{ __('loyalty.client.table_'.$column) }}
                  </th>
                @endforeach
              </tr>
            </thead>

            <tbody>
              @foreach ($loyaltyHistory as $line)
                @php
                    /* Which filter buttons this line answers to, worked out
                       from the config rather than from a list in the markup —
                       a seventh activity type is one config entry. */
                    $groups = collect(config('loyalty.filters'))
                        ->filter(fn (array $types, string $key) => $key === 'all' || in_array($line->type, $types, true))
                        ->keys()
                        ->join(' ');
                @endphp

                <tr data-rewards-line="{{ $groups }}" class="border-b border-line align-top">
                  <td class="py-3 pr-4 whitespace-nowrap">
                    <time datetime="{{ $line->created_at?->toIso8601String() }}" class="text-[12.5px] text-sub">
                      {{ $line->created_at?->isoFormat('D MMM Y') }}
                    </time>
                    <p class="text-[11.5px] text-faint">{{ $line->created_at?->isoFormat('h:mm A') }}</p>
                  </td>

                  <td class="py-3 pr-4 min-w-[180px]">
                    <div class="flex items-start gap-2">
                      <span class="styledesk_settingcard__icon shrink-0 !h-6 !w-6" aria-hidden="true">
                        <x-icon :name="$line->icon()" size="12" />
                      </span>

                      <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-head">{{ $line->label() }}</p>

                        @if ($line->reasonLabel())
                          <p class="text-[12px] text-sub mt-0.5">{{ $line->reasonLabel() }}</p>
                        @endif

                        @if (filled($line->note))
                          <p class="text-[12px] text-sub mt-0.5 whitespace-pre-line">
                            {{ \Illuminate\Support\Str::limit($line->note, 160) }}
                          </p>
                        @endif

                        @if ($line->expires_at && $line->isCredit())
                          <p class="text-[11.5px] text-faint mt-0.5">
                            {{ __('loyalty.client.expires', ['date' => $line->expires_at->isoFormat('D MMM Y')]) }}
                          </p>
                        @endif
                      </div>
                    </div>
                  </td>

                  <td class="py-3 pr-4 text-right whitespace-nowrap">
                    <span class="text-[13.5px] font-bold tracking-tight {{ $line->isCredit() ? 'text-success' : 'text-danger' }}">
                      {{ $line->pointsLabel() }}
                    </span>
                  </td>

                  <td class="py-3 pr-4 text-right whitespace-nowrap text-[13px] text-sub">
                    {{ number_format($line->balance_after) }}
                  </td>

                  <td class="py-3 pr-4 whitespace-nowrap text-[12.5px] text-sub">
                    {{ $line->sourceLabel() ?? '—' }}
                  </td>

                  <td class="py-3 pr-4 text-[12.5px] text-sub">
                    {{ $line->location?->name ?? '—' }}
                  </td>

                  <td class="py-3 pr-4 text-[12.5px] {{ $line->staffName() ? 'text-sub' : 'text-faint italic' }}">
                    {{ $line->staffName() ?? __('loyalty.activity.system') }}
                  </td>

                  <td class="py-3 whitespace-nowrap">
                    <span class="styledesk_badge {{ $line->statusClass() }}">{{ $line->statusLabel() }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <p class="text-[13px] text-sub mt-4" data-rewards-empty hidden>{{ __('loyalty.client.none_filtered') }}</p>
    @endif
