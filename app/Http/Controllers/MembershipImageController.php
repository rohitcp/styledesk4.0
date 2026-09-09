<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantStorageContract;
use App\Models\MembershipPlan;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The picture on a membership.
 *
 * Its own controller rather than an action on MembershipPlanController for
 * the same reason ServiceImageController is separate: the picture is uploaded
 * before the plan it belongs to exists. On the create form there is nothing
 * to attach it to until Save, so it lands unattached and is claimed by
 * MembershipImageSync when the plan is written.
 *
 * Uploaded on choosing rather than on save, so a file that will not upload
 * says so beside the field rather than after eight steps of form.
 */
class MembershipImageController extends Controller
{
    public function store(Request $request, TenantStorageContract $storage): JsonResponse
    {
        $this->authorizeImages($request);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.max' => __('membership.images.too_large'),
            'image.mimes' => __('membership.images.wrong_type'),
        ]);

        /* Public, like a service picture and unlike a client's document:
           this is drawn on a booking page that anyone with the link may
           read, and a private URL would expire while the page was open.

           No entity yet — the plan may not exist — so it is claimed on save. */
        $file = $storage->upload(
            $request->file('image'),
            MembershipPlan::IMAGE_CATEGORY,
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
     * Only ever one that is not yet attached to a plan. A picture that is
     * belongs to a saved membership, and removing it is a change to that
     * membership — which is the save's job, so somebody who removes a picture
     * and then abandons the form still has their plan as they left it.
     */
    public function destroy(Request $request, StoredFile $storedFile, TenantStorageContract $storage): JsonResponse
    {
        $this->authorizeImages($request);

        /* The tenant scope on StoredFile has already made another business's
           file a 404. What is left is that this one is a pending membership
           picture rather than any other file this business owns. */
        abort_unless($storedFile->category === MembershipPlan::IMAGE_CATEGORY, 404);
        abort_unless($storedFile->entity_id === null, 422);

        $storage->delete($storedFile);

        return response()->json(['deleted' => true]);
    }

    /**
     * Who may put a picture on a membership.
     *
     * The same authority as building one: this endpoint exists to serve the
     * membership form and nothing else.
     */
    private function authorizeImages(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('clients.create', 'own')
                || $user?->hasPermission('clients.edit', 'own'),
            403,
        );
    }
}
