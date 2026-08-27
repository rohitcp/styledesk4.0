<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The business's logo and favicon: where they live, and how to reach them.
 *
 * Separate from BrandPalette because these are files rather than arithmetic,
 * and because a caller usually wants one or the other — a document head needs
 * the favicon and the colours, an email needs the logo and the colours, and
 * nothing needs all three at once.
 */
class Branding
{
    /** The disk both assets live on, already public and already configured. */
    public const DISK = 'brand';

    public const LOGO_DIR = 'logos';

    public const FAVICON_DIR = 'favicons';

    /** Two megabytes, in kilobytes, as Laravel's `max` rule counts them. */
    public const MAX_KB = 2048;

    /**
     * SVG is accepted for the logo and favicon, and it is the reason these
     * files are served from their own disk rather than inlined anywhere.
     *
     * An SVG is a document: it can carry script, and a browser that renders
     * one from our origin would run that script with our cookies. Serving
     * from a storage path — never inlining the file's contents into a page —
     * is what keeps an uploaded logo a picture rather than a payload.
     */
    public const LOGO_MIMES = ['jpeg', 'jpg', 'png', 'svg', 'webp'];

    public const FAVICON_MIMES = ['png', 'svg', 'ico'];

    public static function logoUrl(?Tenant $tenant): ?string
    {
        return self::url($tenant?->logo_path);
    }

    public static function faviconUrl(?Tenant $tenant): ?string
    {
        return self::url($tenant?->favicon_path);
    }

    public static function url(?string $path): ?string
    {
        return filled($path) ? Storage::disk(self::DISK)->url($path) : null;
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * Delete a stored asset, if it is still there.
     *
     * Replacing a logo would otherwise leave the old file on disk forever —
     * invisible, still publicly reachable by anyone who noted the URL, and
     * accumulating one file per change.
     */
    public static function forget(?string $path): void
    {
        if (filled($path) && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
