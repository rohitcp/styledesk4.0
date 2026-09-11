<?php

declare(strict_types=1);

namespace App\Messaging;

/**
 * What a provider said when it was handed a message.
 *
 * Deliberately not a delivery: an API that accepted a message has not put it
 * on a phone. `sent` here means "the provider has it", and only a webhook
 * later says whether a carrier managed anything with it.
 */
class SmsResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function accepted(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function refused(?string $code, ?string $message): self
    {
        return new self(false, null, $code, $message);
    }
}
