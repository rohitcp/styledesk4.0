<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * What a business's email needs to look like itself.
 *
 * A confirmation is from the salon to its client, not from StyleDesk to a
 * user: the client chose Smile Spa, has never heard of us, and an email
 * wearing our colours reads as somebody else's mail about their appointment.
 * So these are the tenant's colours and the tenant's logo.
 *
 * Gathered here rather than in each template because a mail is rendered
 * without a browser and often without a tenant in context — a queued job
 * runs long after the request that made it — so every mailable resolves this
 * once, from the record it already holds, and passes the answer in.
 *
 * @phpstan-type BrandArray array{name: string, logo: string|null, primary: string, ink: string, banner: string, accent: string}
 */
class EmailBrand
{
    /**
     * The brand, with StyleDesk's own as the fallback at every step.
     *
     * @return array<string, string|null>
     */
    public static function for(?Tenant $tenant, ?string $name = null): array
    {
        $tokens = BrandPalette::forTenant($tenant)->tokens();

        return [
            'name' => $name ?? $tenant?->name ?? (string) config('app.name'),
            /* Absent for most businesses, and the layout says the name in
               words when it is. A broken image icon where a logo should be
               is worse than no logo. */
            'logo' => Branding::logoUrl($tenant),
            'primary' => $tokens['--sd-brand'],
            /* Black or white, whichever is legible on the primary. Computed,
               never chosen — this is what stops a pale brand shipping white
               text on a white band. */
            'ink' => $tokens['--sd-btn-ink'],
            'banner' => $tokens['--sd-banner'],
            'accent' => $tokens['--sd-accent'],
        ];
    }
}
