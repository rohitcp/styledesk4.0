<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantStorageContract;
use App\Models\Service;
use App\Models\StoredFile;
use Illuminate\Support\Collection;

/**
 * Attaches uploaded pictures to a service, and clears up the ones it drops.
 *
 * Shared by the onboarding wizard and the Services module because both do the
 * same thing from different directions: a form arrives naming a set of files
 * by id and which of them leads, and afterwards the service must hold exactly
 * those.
 *
 * Pictures are uploaded before the service they belong to exists — during
 * onboarding the service is not created until Continue is pressed, and on the
 * create form there is no service until Save. So an upload lands unattached
 * and is claimed here. That is also why claiming has to be guarded: an id in
 * a form is a number a reader can change.
 */
class ServiceImageSync
{
    /** The most pictures one service may carry, the default among them. */
    public const MAX_IMAGES = 11;

    public function __construct(private readonly TenantStorageContract $storage) {}

    /**
     * Make this service's pictures exactly the ones named, and no others.
     *
     * @param  list<int|string>  $fileIds  the gallery, in the order given
     * @param  int|string|null  $defaultFileId  which of them leads
     * @return list<int> the ids that were kept, for a caller that is tidying
     *                   up after more than one service
     */
    public function sync(Service $service, array $fileIds, int|string|null $defaultFileId = null): array
    {
        $claimable = $this->claimable($fileIds);

        foreach ($claimable as $file) {
            /* forceFill on the two columns only: re-pointing a file at a new
               service is the whole of what claiming does, and the row's own
               account of what and where it is must not be rewritten. */
            $file->forceFill([
                'entity_type' => 'service',
                'entity_id' => (string) $service->getKey(),
            ])->save();
        }

        $kept = $claimable->map(fn (StoredFile $file) => (int) $file->getKey())->all();

        /* The default has to be one of the pictures that survived. A form
           naming a default it did not also send — or one that failed the
           checks above — falls back to the first, which is what the reader
           sees leading the gallery anyway. */
        $default = in_array((int) $defaultFileId, $kept, true)
            ? (int) $defaultFileId
            : ($kept[0] ?? null);

        $service->forceFill(['image_file_id' => $default])->save();

        $this->deleteUnkept($service, $kept);

        return $kept;
    }

    /**
     * Delete this tenant's service pictures that no service claimed.
     *
     * The onboarding step rewrites the whole price list at once — every
     * service is deleted and recreated — so a picture the reader removed is
     * left pointing at a service that no longer exists. Nothing else would
     * ever collect it.
     *
     * @param  list<int>  $claimedIds
     */
    public function pruneUnclaimed(array $claimedIds): void
    {
        StoredFile::query()
            ->ofCategory(Service::IMAGE_CATEGORY)
            ->where('entity_type', 'service')
            ->whereNotNull('entity_id')
            ->whereNotIn('id', $claimedIds)
            ->get()
            ->each(fn (StoredFile $file) => $this->storage->delete($file));
    }

    /**
     * The files named that this business is actually allowed to attach.
     *
     * Every check here is one an id typed into a form has to pass:
     *
     *  - the tenant scope on StoredFile, which is what stops one business
     *    attaching another's picture by guessing a number;
     *  - the category, so a client's private document cannot be turned into
     *    a public service picture by naming its id here;
     *  - the count, because the form limits the gallery and a hand-made POST
     *    does not.
     *
     * Order is the reader's, not the database's: whereIn comes back in
     * whatever order it likes, so the ids drive the sort.
     *
     * @param  list<int|string>  $fileIds
     * @return Collection<int, StoredFile>
     */
    private function claimable(array $fileIds): Collection
    {
        $ids = collect($fileIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(self::MAX_IMAGES)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $files = StoredFile::query()
            ->whereIn('id', $ids->all())
            ->ofCategory(Service::IMAGE_CATEGORY)
            ->get()
            ->keyBy(fn (StoredFile $file) => (int) $file->getKey());

        return $ids->map(fn (int $id) => $files->get($id))->filter()->values();
    }

    /**
     * @param  list<int>  $kept
     */
    private function deleteUnkept(Service $service, array $kept): void
    {
        StoredFile::for('service', $service->getKey())
            ->ofCategory(Service::IMAGE_CATEGORY)
            ->whereNotIn('id', $kept)
            ->get()
            ->each(fn (StoredFile $file) => $this->storage->delete($file));
    }
}
