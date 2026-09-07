{{--
    This client's rewards balance, and where it came from.

    Four figures, a target, and the ledger underneath. The ledger is the
    authority: the balance is the sum of its lines, and every line was written
    when the thing happened, by whoever did it. Nothing on this tab can edit
    one — there is no route that does and the model refuses it — because a
    balance somebody can tidy is not a balance anybody can quote at the desk.

    Read the whole tab as one question a receptionist asks with a client in
    front of them: "how much have you got, and can you use it today".
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

<div id="panel-rewards" role="tabpanel" aria-labelledby="tab-rewards" data-panel="rewards" class="pt-4" hidden>

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

        @if ($canAdjustPoints && $loyalty->is_enabled)
            <button type="button" class="styledesk_action shrink-0" data-rewards-open>
                <x-icon name="sliders" size="14" />
                {{ __('loyalty.client.adjust') }}
            </button>
        @endif
    </div>

    {{-- ----------------------------------------------------- the figures --}}
    <div class="styledesk_metrics mt-4">
        @foreach ([['available', 'blue'], ['pending', 'violet'], ['lifetime_earned', 'teal'], ['lifetime_redeemed', 'amber']] as [$key, $tone])
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
                @endif
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------- progress and the rule --}}
    <div class="grid gap-4 sm:grid-cols-2 mt-4">
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

        <ol class="mt-3">
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

                <li data-rewards-line="{{ $groups }}" class="flex items-start gap-3 py-3 border-b border-line">
                    <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                        <x-icon :name="$line->icon()" size="14" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-[13px] font-semibold text-head">{{ $line->label() }}</p>
                            <time datetime="{{ $line->created_at?->toIso8601String() }}" class="text-[12px] text-sub">
                                {{ $line->created_at?->isoFormat('D MMM Y · h:mm A') }}
                            </time>
                        </div>

                        <p class="text-[12px] mt-0.5 {{ $line->isSystem() ? 'text-faint italic' : 'text-sub' }}">
                            {{ __('loyalty.client.by', ['name' => $line->actor()]) }}
                        </p>

                        @if ($line->reasonLabel())
                            <p class="text-[12px] text-sub mt-1">
                                <span class="text-faint">{{ __('loyalty.client.reason') }}:</span>
                                {{ $line->reasonLabel() }}
                            </p>
                        @endif

                        @if (filled($line->note))
                            <p class="text-[12px] text-sub mt-1 whitespace-pre-line">
                                <span class="text-faint">{{ __('loyalty.client.note') }}:</span>
                                {{ \Illuminate\Support\Str::limit($line->note, 240) }}
                            </p>
                        @endif

                        @if ($line->location || $line->booking)
                            <p class="text-[12px] text-sub mt-1">
                                {{ collect([$line->booking?->reference, $line->location?->name])->filter()->join(' · ') }}
                            </p>
                        @endif

                        @if ($line->expires_at)
                            <p class="text-[12px] text-faint mt-1">
                                {{ __('loyalty.client.expires', ['date' => $line->expires_at->isoFormat('D MMM Y')]) }}
                            </p>
                        @endif
                    </div>

                    {{-- The number and the balance it left, right-aligned so
                         the column reads down like a statement. --}}
                    <div class="shrink-0 text-right">
                        <p class="text-[14px] font-bold tracking-tight {{ $line->isCredit() ? 'text-success' : 'text-danger' }}">
                            {{ $line->pointsLabel() }}
                        </p>
                        <p class="text-[12px] text-sub mt-0.5">{{ number_format($line->balance_after) }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="text-[13px] text-sub mt-4" data-rewards-empty hidden>{{ __('loyalty.client.none_filtered') }}</p>
    @endif
</div>

{{-- ------------------------------------------------------------- the modal --}}
@if ($canAdjustPoints && $loyalty->is_enabled)
    <div id="rewardsAdjustModal" class="styledesk_modal" hidden>
        <div class="styledesk_modal__scrim" data-rewards-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="rewardsAdjustTitle">
            <div class="styledesk_modal__head">
                <h2 id="rewardsAdjustTitle" class="text-[15px] font-semibold text-head">{{ __('loyalty.client.adjust_title') }}</h2>

                <button type="button" class="styledesk_modal__close" data-rewards-close aria-label="{{ __('common.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('clients.rewards.adjust', $client) }}">
                @csrf

                <div class="styledesk_modal__body space-y-4">
                    <p class="text-[12.5px] text-sub leading-relaxed">{{ __('loyalty.client.adjust_intro') }}</p>

                    <div>
                        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.client.direction') }}</span>

                        <div class="grid gap-2 sm:grid-cols-2">
                            {{-- Direction is its own answer rather than a
                                 sign typed into the points box: "-100" in a
                                 field labelled Points is a number somebody
                                 eventually enters meaning the opposite. --}}
                            <x-choice type="radio" name="direction" value="add"
                                      :label="__('loyalty.client.add')" :checked="true" />
                            <x-choice type="radio" name="direction" value="remove"
                                      :label="__('loyalty.client.remove')" />
                        </div>
                    </div>

                    <div>
                        <label for="rewardsPoints" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('loyalty.client.points') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="rewardsPoints" name="points" type="number" min="1" step="1" required class="sd-input">
                    </div>

                    <div>
                        <label for="rewardsReason" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('loyalty.client.reason') }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="rewardsReason" name="reason" required class="sd-input">
                            @foreach (config('loyalty.adjustment_reasons') as $reason)
                                <option value="{{ $reason }}">{{ __('loyalty.reasons.'.$reason) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="rewardsNote" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.client.note') }}</label>
                        <textarea id="rewardsNote" name="note" rows="3" maxlength="500" class="sd-input"></textarea>
                        <p class="mt-1.5 text-[12px] text-sub">{{ __('loyalty.client.note_hint') }}</p>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="button" class="styledesk_action" data-rewards-close>{{ __('common.cancel') }}</button>

                    <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('loyalty.client.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@push('scripts')
  <script>
    /* The rewards tab: the filters over the ledger already on the page, and
       the dialog that adds a line to it.

       Filtered in the browser rather than by a request, the same way the
       activity tab is: the whole history is already here, and a round trip to
       hide six rows is a round trip a receptionist waits through. */
    (function () {
      var lines = Array.prototype.slice.call(document.querySelectorAll('[data-rewards-line]'));
      var empty = document.querySelector('[data-rewards-empty]');

      if (lines.length) {
        document.querySelectorAll('[data-rewards-filter]').forEach(function (button) {
          button.addEventListener('click', function () {
            var wanted = button.getAttribute('data-rewards-filter');
            var shown = 0;

            lines.forEach(function (line) {
              var on = line.getAttribute('data-rewards-line').split(' ').indexOf(wanted) !== -1;
              line.hidden = !on;
              if (on) shown++;
            });

            document.querySelectorAll('[data-rewards-filter]').forEach(function (other) {
              other.classList.toggle('is-active', other === button);
              other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            });

            if (empty) empty.hidden = shown !== 0;
          });
        });
      }

      var modal = document.getElementById('rewardsAdjustModal');
      if (!modal) return;

      function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
      }

      document.querySelectorAll('[data-rewards-open]').forEach(function (opener) {
        opener.addEventListener('click', function () {
          modal.hidden = false;
          document.body.style.overflow = 'hidden';

          var points = modal.querySelector('#rewardsPoints');
          if (points) { points.value = ''; points.focus(); }
        });
      });

      modal.querySelectorAll('[data-rewards-close]').forEach(function (closer) {
        closer.addEventListener('click', close);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
      });
    }());
  </script>
@endpush
