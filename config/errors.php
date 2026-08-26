<?php

/*
|--------------------------------------------------------------------------
| Error Page Copy
|--------------------------------------------------------------------------
|
| One place for every error page's wording, so a code cannot say one thing in
| the page title and another in the body. `title` is what the visitor reads;
| `description` is the plain-English cause.
|
| Nothing here is derived from the exception itself. Raw exception messages can
| carry file paths, SQL or internal identifiers, so the page shows only text we
| wrote — the detail belongs in the log, not on the screen.
|
*/

return [

    400 => ['title' => 'Bad request', 'description' => 'The request could not be understood, so nothing was changed.'],
    401 => ['title' => 'Please sign in', 'description' => 'This page needs you to be signed in.'],
    402 => ['title' => 'Payment required', 'description' => 'This feature is not included in your current plan or subscription.'],
    403 => ['title' => 'Access denied', 'description' => 'Your account does not have permission to view this page.'],
    404 => ['title' => 'Page not found', 'description' => 'The page or resource you were looking for does not exist.'],
    405 => ['title' => 'Method not allowed', 'description' => 'That address does not accept this kind of request.'],
    408 => ['title' => 'Request timed out', 'description' => 'The request took too long to complete. Please try again.'],
    409 => ['title' => 'Conflict', 'description' => 'This conflicts with data that has changed since you loaded the page.'],
    410 => ['title' => 'No longer available', 'description' => 'This resource has been permanently removed.'],
    419 => ['title' => 'Your session expired', 'description' => 'The page sat idle for too long. Reload it and try again — nothing was submitted.'],
    422 => ['title' => 'That did not validate', 'description' => 'Some of the information submitted was not accepted. Please check it and try again.'],
    429 => ['title' => 'Too many requests', 'description' => 'You have made a lot of requests in a short time. Please wait a moment and try again.'],
    500 => ['title' => 'Something went wrong', 'description' => 'An unexpected error occurred on our side. It has been logged and we are looking into it.'],
    501 => ['title' => 'Not implemented', 'description' => 'This feature is not supported yet.'],
    502 => ['title' => 'Bad gateway', 'description' => 'An upstream service returned an invalid response.'],
    503 => ['title' => 'Back shortly', 'description' => 'StyleDesk is briefly unavailable while we carry out maintenance.'],
    504 => ['title' => 'Gateway timeout', 'description' => 'An external service took too long to respond, or your session timed out.'],

];
