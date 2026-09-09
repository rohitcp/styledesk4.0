---
paths:
  - 'app/{Models/BookingReview.php,Models/ReviewSettings.php,Support/ReviewRequests.php,Jobs/SendReviewRequest.php,Http/Controllers/ReviewController.php,Http/Controllers/Settings/ReviewSettingsController.php},config/reviews.php,resources/views/reviews/**'
---

# Reviews

## Customer reviews: the row starts unanswered, and a GET never writes one
`booking_reviews` is written when a booking is completed, not when the client answers. `rating` is nullable and `submitted_at` is the only test of whether a row is a review — `BookingReview::isSubmitted()`. `booking_id` is unique, so a booking asks once; `ReviewRequests::scheduleFor()` is the single writer and swallows every error (completing an appointment must never fail because the review module could not be reached).

The email's stars are five links carrying `?rating=`. `ReviewController::show()` PRESELECTS that rating and must never store it: a GET that wrote a review would be answered by every mail scanner between the salon and the client, leaving five-star ratings nobody sat in a chair for. Only `POST review/{token}` writes, and only once.

4+ stars (`config('reviews.positive_from')`) sees the Google button; 1–3 never does, and `reviews.google` 404s for them — the split is the point of the feature, not a styling choice. Google URLs live on `locations`, one listing per branch.

`appointments.complete` and the `complete` status action (`config/bookings.php`, from `arrived` only) exist because nothing else in the app ever set `status = 'completed'`. It is the review trigger; keep the conditional-`update()` race guard, or a double completion asks the client twice.
