<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantStorageContract;
use App\Models\Service;
use App\Models\StoredFile;
use App\Services\ServiceImageSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uploading and discarding the pictures on a service.
 *
 * Its own controller rather than an action on ServiceController because both
 * the Services module and the onboarding wizard need it, and onboarding runs
 * before a service exists. The picture is stored first and told which service
 * it belongs to later — see ServiceImageSync.
 *
 * Deliberately outside the `onboarded` gate for that reason: step 3 of the
 * wizard is precisely where a business that has not finished setup adds its
 * first service picture.
 */
class ServiceImageController extends Controller
{
    /**
     * Store one picture and hand back what the page needs to draw it.
     *
     * Asynchronous, like the logo upload on step 1: the alternative is a form
     * that appears to hang while several megabytes go up, with nothing on
     * screen to say why.
     */
    public function store(Request $request, TenantStorageContract $storage): JsonResponse
    {
        $this->authorizeImages($request);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.max' => __('services.images.too_large'),
            'image.mimes' => __('services.images.wrong_type'),
        ]);

        /**
         * Public, like a logo and unlike a client's file: this picture is
         * drawn on a booking page that anyone with the link may read, and a
         * private URL would expire while the page was still open.
         *
         * No entity yet. The service may not exist — on the create form and
         * in the wizard it does not — so the file is claimed on save.
         */
        $file = $storage->upload(
            $request->file('image'),
            Service::IMAGE_CATEGORY,
            null,
            null,
            'public',
        );

        return response()->json([
            'id' => $file->id,
            'url' => $storage->url($file),
            'name' => $file->original_filename,
            'size' => $file->readableSize(),
        ]);
    }

    /**
     * Discard a picture the reader removed before saving.
     *
     * Only ever a file that is not yet attached to a service. One that is
     * belongs to a saved service, and removing it is a change to that service
     * — which is the save's job, so that a reader who removes a picture and
     * then abandons the form still has their service as they left it.
     */
    public function destroy(Request $request, StoredFile $storedFile, TenantStorageContract $storage): JsonResponse
    {
        $this->authorizeImages($request);

        /* The tenant scope on StoredFile has already made another business's
           file a 404. What is left to check is that this one is a pending
           service picture rather than any other file this business owns. */
        abort_unless($storedFile->category === Service::IMAGE_CATEGORY, 404);
        abort_unless($storedFile->entity_id === null, 422);

        $storage->delete($storedFile);

        return response()->json(['deleted' => true]);
    }

    /**
     * Who may add a picture to a service.
     *
     * The owner running the wizard has no `services.create` permission yet —
     * there is no staff record to carry one until step 4 — so the check is
     * "may edit this business's services, or is the person setting it up".
     */
    private function authorizeImages(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('services.create', 'all')
                || $user->hasPermission('services.edit', 'all')
                || $user->isOwner(),
            403,
        );
    }
}
