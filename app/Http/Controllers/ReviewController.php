<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingReview;
use App\Models\ReviewSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The page a client lands on from a review request.
 *
 * Outside auth, and deliberately thin. The token is the whole credential, so
 * the page shows one appointment and one question — no client record, no
 * history, no way to reach anything the token does not name. Somebody who
 * finds this link in a forwarded email learns that an appointment happened and
 * nothing further.
 *
 * The star in the email arrives here as `?rating=`, and it is preselected
 * rather than saved. A GET that wrote a review would be answered by every mail
 * scanner and link preview between the business and the client — five-star
 * ratings from software nobody sat in the chair for. The tap is still one tap:
 * the star is already lit and Submit is the only thing left.
 */
class ReviewController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $review = $this->find($token);

        $rating = (int) $request->query('rating');

        return view('reviews.show', $this->payload($review) + [
            /* Only a star the email could actually have offered. Anything
               else in the query string is somebody experimenting. */
            'preselected' => in_array($rating, config('reviews.ratings'), true) ? $rating : null,
        ]);
    }

    /**
     * The answer.
     *
     * One post rather than two screens. §9 and §10 describe a rating step and
     * a comment step, and with JavaScript that is what a client sees — but
     * the form underneath is one form, so a client whose phone blocked the
     * bundle still answers in one go rather than losing the rating between
     * two round trips.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $review = $this->find($token);

        /* Already answered. Not an error: they have done what was asked, and
           the page they land back on says so. §22 — the link stops being a
           way to write a second review. */
        if ($review->isSubmitted()) {
            return redirect()->route('reviews.show', ['token' => $token]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', Rule::in(config('reviews.ratings'))],
            'comment' => ['nullable', 'string', 'max:2000'],
            'recommend' => ['nullable', Rule::in(['yes', 'maybe', 'no'])],
            'contact_requested' => ['nullable', 'boolean'],
        ]);

        $review->forceFill([
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
            'recommend' => $data['recommend'] ?? null,
            /* Only meaningful on the unhappy journey — §14 is where the box
               is offered — but stored as asked either way rather than
               silently dropped. */
            'contact_requested' => (bool) ($data['contact_requested'] ?? false),
            'submitted_at' => now(),
            /* The column the client panel has always read. Kept in step
               rather than replaced: `submitted_at` is when they answered and
               `reviewed_at` is what the rest of the app already asks for. */
            'reviewed_at' => now(),
        ])->save();

        return redirect()->route('reviews.show', ['token' => $token]);
    }

    /**
     * On to Google, for the ones who enjoyed it.
     *
     * Its own route rather than a bare link so that "did anybody actually go"
     * is something the reports know. Only ever offered to a happy review, and
     * only where the branch has a listing to send them to.
     */
    public function google(string $token): RedirectResponse
    {
        $review = $this->find($token);

        $url = $review->location?->google_review_url;

        abort_if(! $review->isPositive() || blank($url), 404);

        if ($review->google_opened_at === null) {
            $review->forceFill(['google_opened_at' => now()])->save();
        }

        return redirect()->away($url);
    }

    /**
     * The review this token names.
     *
     * Without global scopes, like the payment link: there is no signed-in
     * user to resolve a tenant from, and the token is the only thing that
     * says which business this belongs to.
     */
    private function find(string $token): BookingReview
    {
        $review = BookingReview::withoutGlobalScopes()
            ->with(['booking.services', 'booking.staff', 'booking.location', 'client', 'location'])
            ->where('token', $token)
            ->first();

        abort_if($review === null, 404);

        return $review;
    }

    /**
     * What every state of the page needs.
     *
     * @return array<string, mixed>
     */
    private function payload(BookingReview $review): array
    {
        $tenant = $review->booking?->tenant;
        $settings = ReviewSettings::forTenant($tenant);

        return [
            'review' => $review,
            'booking' => $review->booking,
            'businessName' => $tenant?->name ?? config('app.name'),
            'clientName' => $review->client?->first_name ?? $review->booking?->clientName() ?? '',
            /* Offered only when the business wants it and the branch has a
               listing. A button that leads nowhere is worse than no button. */
            'googleUrl' => $review->isPositive()
                && $settings->google_enabled
                && filled($review->location?->google_review_url)
                    ? route('reviews.google', ['token' => $review->token])
                    : null,
        ];
    }
}
