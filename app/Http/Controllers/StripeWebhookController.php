<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Payments\StripeWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Stripe's callbacks, as an HTTP endpoint and nothing more.
 *
 * Deliberately thin: verifying the signature and interpreting the event are
 * the Stripe adapter's business and live in App\Payments. This decides only
 * what to answer.
 *
 * Outside the web group and outside auth on purpose — Stripe is not a
 * signed-in user, and the signature is what proves the request. A webhook that
 * trusted its body would let anybody mark any booking paid.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = app(StripeWebhook::class)->handle(
            $request->getContent(),
            $request->header('Stripe-Signature'),
        );

        return response('', $status);
    }
}
