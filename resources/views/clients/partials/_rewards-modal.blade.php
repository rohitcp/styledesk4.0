{{--
    Adjusting a balance by hand, and the script the rewards view runs on.

    Kept out of the panel it belongs to: the profile renders that panel inside
    a tab, which carries `hidden` whenever another tab is showing — and a
    dialog inside a hidden container is a dialog that cannot open. Fixed
    position is not enough; the ancestor decides.
--}}
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

