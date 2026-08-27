<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Branding;
use App\Support\BrandPalette;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The Branding module: the business's identity wherever a client sees it.
 *
 * Phase 1 is the logo, the favicon and three colours. Fonts, per-email
 * branding, booking-page themes and white-label controls are deliberately
 * absent — each is a screen of its own, and shipping a stub of one is how a
 * settings page ends up full of controls that do nothing.
 *
 * Access is Owner/Administrator through the `can-manage-settings` middleware
 * on the route group; this controller does not re-check it, because a second
 * copy of the rule is a second thing to keep in step.
 */
class BrandingController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('settings.branding.edit', $this->branding($tenant));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'brand_primary' => ['required', 'string', $this->hexRule()],
            'brand_secondary' => ['required', 'string', $this->hexRule()],
            'brand_accent' => ['required', 'string', $this->hexRule()],

            /**
             * The paths come from the upload endpoints, not from a file input.
             *
             * Both are already stored by the time this runs, so the form
             * carries a path. A path that is not one of ours is refused below
             * rather than trusted — it ends up in a src attribute on every
             * page the business renders.
             */
            'logo_path' => ['nullable', 'string', 'max:255'],
            'favicon_path' => ['nullable', 'string', 'max:255'],
        ], [
            'brand_primary.required' => __('branding.colours.required_primary'),
            'brand_secondary.required' => __('branding.colours.required_secondary'),
            'brand_accent.required' => __('branding.colours.required_accent'),
        ]);

        $colors = [
            'brand_primary' => BrandPalette::normalise($data['brand_primary']),
            'brand_secondary' => BrandPalette::normalise($data['brand_secondary']),
            'brand_accent' => BrandPalette::normalise($data['brand_accent']),
        ];

        $assets = [
            'logo_path' => $this->acceptedPath($data['logo_path'] ?? null, Branding::LOGO_DIR),
            'favicon_path' => $this->acceptedPath($data['favicon_path'] ?? null, Branding::FAVICON_DIR),
        ];

        try {
            /**
             * The replaced files are deleted only once the row has saved.
             *
             * The other order loses the old logo and then fails to record the
             * new one, leaving a business with no logo at all and nothing to
             * restore it from.
             */
            $previous = ['logo_path' => $tenant->logo_path, 'favicon_path' => $tenant->favicon_path];

            $tenant->forceFill([...$colors, ...$assets]);

            if ($tenant->save() !== true) {
                // save() returning false means the write did not happen.
                // Success must never be reported on the strength of having
                // reached this line.
                throw new RuntimeException('The branding record reported an unsuccessful save.');
            }

            foreach ($previous as $field => $path) {
                if ($path !== $assets[$field]) {
                    Branding::forget($path);
                }
            }
        } catch (Throwable $e) {
            /**
             * The reason is logged, never shown. A driver message can carry
             * table names, credentials and the shape of the schema, none of
             * which the person who pressed Save can act on.
             */
            Log::error('Branding could not be saved.', [
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $request->user()->id,
                'exception' => $e->getMessage(),
            ]);

            // Stay on the form. Redirecting away would discard everything
            // chosen, for a failure that was not the user's doing.
            return back()->withInput()->with('toast', [
                'type' => 'danger',
                'message' => __('branding.save_failed'),
            ]);
        }

        return redirect()
            ->route('settings.branding.show')
            ->with('toast', ['type' => 'success', 'message' => __('branding.saved')]);
    }

    /**
     * Put everything back to StyleDesk's own identity.
     *
     * Nulls rather than a copy of the defaults: a stored default is
     * indistinguishable from a deliberate choice, and the next release that
     * changes the house palette would leave every reset business on the old
     * one.
     */
    public function reset(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $previous = [$tenant->logo_path, $tenant->favicon_path];

        $tenant->forceFill([
            'brand_primary' => null,
            'brand_secondary' => null,
            'brand_accent' => null,
            'logo_path' => null,
            'favicon_path' => null,
        ])->save();

        foreach ($previous as $path) {
            Branding::forget($path);
        }

        return redirect()
            ->route('settings.branding.show')
            ->with('toast', ['type' => 'success', 'message' => __('branding.reset_done')]);
    }

    // -------------------------------------------------------------- uploads

    public function uploadLogo(Request $request): JsonResponse
    {
        return $this->upload($request, 'logo', Branding::LOGO_MIMES, Branding::LOGO_DIR);
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        return $this->upload($request, 'favicon', Branding::FAVICON_MIMES, Branding::FAVICON_DIR);
    }

    /**
     * Store one asset and hand back its path and URL.
     *
     * Uploaded on choosing rather than on save, so the preview beside the
     * field is the real file rather than a browser-side approximation of it —
     * a logo that will not upload should say so before the whole form is
     * submitted.
     *
     * @param  array<int, string>  $mimes
     */
    private function upload(Request $request, string $field, array $mimes, string $directory): JsonResponse
    {
        $request->validate([
            /**
             * `file` with an explicit mime list, not `image`.
             *
             * The `image` rule rejects SVG outright, and the spec asks for it.
             * The mime list is what keeps the door narrow: an .ico is not an
             * image by that rule either, and both are named here deliberately
             * rather than by loosening the check.
             */
            $field => ['required', 'file', 'mimes:'.implode(',', $mimes), 'max:'.Branding::MAX_KB],
        ], [
            $field.'.mimes' => __('branding.upload.mimes', ['formats' => mb_strtoupper(implode(', ', $mimes))]),
            $field.'.max' => __('branding.upload.too_large'),
        ]);

        $path = Branding::store($request->file($field), $directory);

        return response()->json(['path' => $path, 'url' => Branding::url($path)]);
    }

    // -------------------------------------------------------------- helpers

    /** A six-digit hex colour, with or without its hash. */
    private function hexRule(): string
    {
        return 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
    }

    /**
     * A stored path, or null.
     *
     * Checked against the directory it should be in and against the disk
     * itself, because this value reaches a `src` attribute on every page the
     * business renders. A crafted path would otherwise point the whole app's
     * logo at anything the storage URL can address.
     */
    private function acceptedPath(?string $path, string $directory): ?string
    {
        if (blank($path)) {
            return null;
        }

        $isOurs = str_starts_with($path, $directory.'/')
            && ! str_contains($path, '..')
            && Storage::disk(Branding::DISK)->exists($path);

        return $isOurs ? $path : null;
    }

    /**
     * Everything the screen needs.
     *
     * @return array<string, mixed>
     */
    private function branding(Tenant $tenant): array
    {
        $palette = BrandPalette::forTenant($tenant);

        return [
            'tenant' => $tenant,
            'palette' => $palette,
            'tokens' => $palette->tokens(),
            'logoUrl' => Branding::logoUrl($tenant),
            'faviconUrl' => Branding::faviconUrl($tenant),
            'isDefault' => $palette->isDefault() && blank($tenant->logo_path) && blank($tenant->favicon_path),
            /**
             * White text on the primary colour — not the computed ink.
             *
             * Grading against inkFor() is tautological: it returns whichever
             * of black and white scores better, so the ratio always passes
             * and the warning could never fire. The app bar, the booking
             * page header and the email header all print white on this
             * colour, so white is the question actually worth asking, and a
             * pale primary is exactly the case that fails it.
             */
            'primaryGrade' => BrandPalette::grade($palette->colors['primary'], '#ffffff'),
        ];
    }
}
