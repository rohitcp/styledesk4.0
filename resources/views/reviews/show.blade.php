{{--
    "How was your visit?" — the page a client lands on from a review request.

    Its own page rather than the app layout: whoever is reading this is a
    client, not a StyleDesk user, and a page wrapped in a nav bar for an app
    they cannot sign into is a page that invites them to try.

    Three states in one file, because they are one page at three moments and
    splitting them would mean three headers to keep in step: the question, the
    thank-you, and the "you have already answered this".

    The form is one form. The rating step and the comment step described in the
    spec are what a client with JavaScript sees, and the enhancement at the
    bottom is all it takes; underneath, everything posts together, so a client
    whose phone blocked the bundle answers in one go rather than losing their
    rating between two round trips.
--}}
@php
    $submitted = $review->isSubmitted();
    $positive = $review->isPositive();
    $ratings = config('reviews.ratings');
    /* Built here rather than inside the directive below: Blade counts
       brackets rather than reading PHP, so an argument carrying a comma
       inside brackets does not parse. */
    $ratingLabels = collect($ratings)->mapWithKeys(fn (int $r) => [$r => __('reviews.rating_labels.'.$r)])->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Never indexed and never followed: a page that names somebody's
         appointment has no business in a search result. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('reviews.page.title') }} — {{ $businessName }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-hover text-ink text-[13px]">

<main class="min-h-screen flex items-start justify-center px-4 py-10">
    <div class="w-full max-w-[440px]">

        <p class="text-[13px] text-sub text-center">{{ $businessName }}</p>

        @if ($submitted)

            {{-- Answered. Which branch they see is decided by what they said,
                 not by what we would like them to say: §14 is explicit that a
                 client who was unhappy is never pushed at a public listing. --}}
            <section class="mt-4 bg-white border border-line rounded-card p-6 text-center">
                @if ($positive)
                    <h1 class="text-[20px] font-bold text-head tracking-tight">
                        {{ __('reviews.page.thanks_title') }}
                    </h1>
                    <p class="text-[13.5px] text-sub mt-2 leading-relaxed">
                        {{ __('reviews.page.thanks_positive') }}
                    </p>

                    @if ($googleUrl)
                        <a href="{{ $googleUrl }}" rel="noopener"
                           class="inline-flex items-center justify-center w-full h-11 px-4 mt-5 rounded-lg
                                  bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                            {{ __('reviews.page.google_cta') }}
                        </a>
                    @endif
                @else
                    <h1 class="text-[20px] font-bold text-head tracking-tight">
                        {{ __('reviews.page.thanks_title') }}
                    </h1>
                    <p class="text-[13.5px] text-sub mt-2 leading-relaxed">
                        {{ __('reviews.page.thanks_negative') }}
                    </p>

                    @if ($review->contact_requested)
                        <p class="text-[13px] text-head font-medium mt-3">
                            {{ __('reviews.page.thanks_contact') }}
                        </p>
                    @endif
                @endif

                <p class="text-[12px] text-faint mt-5">{{ __('reviews.page.already') }}</p>
            </section>

        @else

            <h1 class="text-[22px] font-bold text-head tracking-tight mt-1 text-center">
                {{ __('reviews.page.question') }}
            </h1>
            <p class="text-[13px] text-sub mt-1.5 text-center">{{ __('reviews.page.question_hint') }}</p>

            @if ($booking)
                <p class="text-[12.5px] text-faint mt-3 text-center">
                    {{ $booking->date->translatedFormat('l j F Y') }}
                    @if ($booking->services->isNotEmpty())
                        · {{ $booking->services->pluck('name')->implode(', ') }}
                    @endif
                </p>
            @endif

            <form method="POST" action="{{ route('reviews.store', ['token' => $review->token]) }}"
                  class="mt-5 bg-white border border-line rounded-card p-5" data-review-form>
                @csrf

                {{-- Radios rather than buttons, so the rating is part of the
                     form and survives a page with no JavaScript. The labels
                     carry the star; the inputs themselves are off-screen but
                     focusable, which is what keeps this usable by keyboard and
                     by a screen reader. --}}
                <fieldset>
                    <legend class="sr-only">{{ __('reviews.page.question') }}</legend>
                    <div class="flex items-center justify-center gap-1" data-review-stars>
                        @foreach ($ratings as $value)
                            <label class="cursor-pointer p-1.5 text-[34px] leading-none select-none"
                                   data-review-star="{{ $value }}"
                                   title="{{ __('reviews.rating_labels.'.$value) }}">
                                <input type="radio" name="rating" value="{{ $value }}" class="sr-only"
                                       @checked($preselected === $value)
                                       aria-label="{{ trans_choice('reviews.stars', $value, ['count' => $value]) }}">
                                <span aria-hidden="true"
                                      class="{{ $preselected !== null && $value <= $preselected ? 'text-[#f5a623]' : 'text-line' }}">★</span>
                            </label>
                        @endforeach
                    </div>

                    <p class="text-[12.5px] text-sub text-center mt-1 min-h-[18px]" data-review-label>
                        {{ $preselected !== null ? __('reviews.rating_labels.'.$preselected) : '' }}
                    </p>

                    @error('rating')
                        <p class="text-[12.5px] text-danger text-center mt-1">{{ __('reviews.page.choose_rating') }}</p>
                    @enderror
                </fieldset>

                {{-- The apology. Hidden until a low rating is chosen where the
                     browser can do that, and simply absent from the page for
                     everybody else — a client who has not rated anything yet
                     should not be read an apology. --}}
                <div class="mt-5 hidden" data-review-sorry>
                    <p class="text-[13.5px] font-semibold text-head">{{ __('reviews.page.sorry_title') }}</p>
                    <p class="text-[12.5px] text-sub mt-1">{{ __('reviews.page.sorry_hint') }}</p>
                </div>

                <div class="mt-5">
                    <label for="comment" class="block text-[13px] font-medium text-head">
                        {{ __('reviews.page.comment_label') }}
                        <span class="text-faint font-normal">· {{ __('reviews.page.comment_optional') }}</span>
                    </label>
                    <textarea id="comment" name="comment" rows="4"
                              class="sd-input mt-1.5 w-full"
                              placeholder="{{ __('reviews.page.comment_placeholder') }}">{{ old('comment') }}</textarea>
                </div>

                <fieldset class="mt-5">
                    <legend class="block text-[13px] font-medium text-head">
                        {{ __('reviews.page.recommend_label') }}
                        <span class="text-faint font-normal">· {{ __('reviews.page.comment_optional') }}</span>
                    </legend>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach (['yes', 'maybe', 'no'] as $answer)
                            <label class="cursor-pointer">
                                <input type="radio" name="recommend" value="{{ $answer }}" class="sr-only peer"
                                       @checked(old('recommend') === $answer)>
                                <span class="inline-flex items-center h-9 px-4 rounded-full border border-line
                                             text-[13px] font-medium text-ink transition-colors
                                             peer-checked:bg-brand peer-checked:text-white peer-checked:border-transparent
                                             peer-focus-visible:outline peer-focus-visible:outline-2
                                             peer-focus-visible:outline-offset-1">
                                    {{ __('reviews.page.recommend.'.$answer) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Offered to everybody without JavaScript and revealed only
                     on the unhappy path with it. A five-star client ticking it
                     is still a client asking to be called, and refusing to
                     record that would be the wrong kind of tidy. --}}
                <label class="flex items-start gap-2.5 mt-5 cursor-pointer" data-review-contact>
                    <input type="checkbox" name="contact_requested" value="1" class="sd-check mt-0.5"
                           @checked(old('contact_requested'))>
                    <span class="text-[13px] text-head">{{ __('reviews.page.contact_label') }}</span>
                </label>

                <button type="submit" data-review-submit
                        class="w-full h-11 mt-6 rounded-lg bg-brand hover:bg-brand-dark
                               text-white text-[14px] font-semibold transition-colors">
                    {{ __('reviews.page.submit') }}
                </button>
            </form>

        @endif

    </div>
</main>

@unless ($submitted)
    <script>
        /* Progressive enhancement, and nothing more.
           Everything below only changes what is shown before the form is
           posted: which stars are lit, whether the apology is visible, and
           what the button says. With this script blocked the page is still a
           complete, submittable review. */
        (function () {
            var form = document.querySelector('[data-review-form]');
            if (!form) return;

            var stars = Array.prototype.slice.call(form.querySelectorAll('[data-review-star]'));
            var label = form.querySelector('[data-review-label]');
            var sorry = form.querySelector('[data-review-sorry]');
            var contact = form.querySelector('[data-review-contact]');
            var submit = form.querySelector('[data-review-submit]');

            var LABELS = @json($ratingLabels);
            var POSITIVE_FROM = {{ (int) config('reviews.positive_from') }};
            var SUBMIT = @json(__('reviews.page.submit'));
            var SUBMIT_NEGATIVE = @json(__('reviews.page.submit_negative'));

            /* The contact checkbox belongs to the unhappy journey. Hidden
               until then rather than removed, so a client who lowers their
               rating after ticking it does not lose the tick. */
            if (contact) contact.classList.add('hidden');

            function paint(value) {
                stars.forEach(function (star) {
                    var mine = parseInt(star.getAttribute('data-review-star'), 10);
                    var mark = star.querySelector('span');
                    mark.classList.toggle('text-[#f5a623]', mine <= value);
                    mark.classList.toggle('text-line', mine > value);
                });

                if (label) label.textContent = LABELS[value] || '';

                var unhappy = value > 0 && value < POSITIVE_FROM;
                if (sorry) sorry.classList.toggle('hidden', !unhappy);
                if (contact) contact.classList.toggle('hidden', !unhappy);
                if (submit) submit.textContent = unhappy ? SUBMIT_NEGATIVE : SUBMIT;
            }

            stars.forEach(function (star) {
                var input = star.querySelector('input');
                input.addEventListener('change', function () {
                    paint(parseInt(input.value, 10));
                });
            });

            var chosen = form.querySelector('input[name="rating"]:checked');
            paint(chosen ? parseInt(chosen.value, 10) : 0);
        })();
    </script>
@endunless

</body>
</html>
