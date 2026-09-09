<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantStorageContract;
use App\Models\MembershipPlan;
use App\Models\StoredFile;

/**
 * Attaches an uploaded picture to a membership, and clears up the one it drops.
 *
 * The single-picture cousin of ServiceImageSync, and it exists for the same
 * reason: pictures are uploaded before the plan they belong to exists, so an
 * upload lands unattached and is claimed here.
 *
 * That is also why claiming has to be guarded. The id arrives in a form, and
 * a number in a form is a number a reader can change — the tenant scope stops
 * one business claiming another's file, and the category check stops a
 * client's private document being turned into a public membership picture by
 * naming its id.
 */
class MembershipImageSync
{
    public function __construct(private readonly TenantStorageContract $storage) {}

    /**
     * Make this plan's picture exactly the one named, and no other.
     *
     * A null id means the reader removed it: the plan keeps no picture and
     * the file it used to hold is deleted, because nothing else would ever
     * collect it.
     */
    public function sync(MembershipPlan $plan, int|string|null $fileId): void
    {
        $previous = $plan->image_file_id === null
            ? null
            : StoredFile::query()->find($plan->image_file_id);

        $claimed = $this->claimable($fileId);

        if ($claimed !== null) {
            /* forceFill on the two columns only: re-pointing a file at a plan
               is the whole of what claiming does, and the row's own account
               of what and where it is must not be rewritten. */
            $claimed->forceFill([
                'entity_type' => 'membership_plan',
                'entity_id' => (string) $plan->getKey(),
            ])->save();
        }

        $plan->forceFill(['image_file_id' => $claimed?->getKey()])->save();

        /* The one it replaced, if it is not the one it kept. Deleted rather
           than orphaned: a membership carries one picture, so the old one has
           nothing left pointing at it. */
        if ($previous !== null && $previous->getKey() !== $claimed?->getKey()) {
            $this->storage->delete($previous);
        }
    }

    /**
     * The file named, if this business is actually allowed to attach it.
     *
     * Either of the two checks failing means the id was not one this form
     * could have produced, so nothing is attached rather than something
     * being guessed.
     */
    private function claimable(int|string|null $fileId): ?StoredFile
    {
        $id = (int) $fileId;

        if ($id <= 0) {
            return null;
        }

        return StoredFile::query()
            ->whereKey($id)
            ->ofCategory(MembershipPlan::IMAGE_CATEGORY)
            ->first();
    }
}
