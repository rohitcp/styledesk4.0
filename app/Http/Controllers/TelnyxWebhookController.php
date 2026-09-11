<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Messaging\TelnyxWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where Telnyx reports back.
 *
 * Public, unauthenticated and outside the session — a carrier has no cookie —
 * so everything it says is verified against the signing key before a single
 * row moves. See App\Messaging\TelnyxWebhook.
 */
class TelnyxWebhookController extends Controller
{
    public function __invoke(Request $request, TelnyxWebhook $webhook): Response
    {
        return response('', $webhook->handle(
            $request->getContent(),
            $request->header('telnyx-signature-ed25519'),
            $request->header('telnyx-timestamp'),
        ));
    }
}
