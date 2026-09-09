---
paths:
  - 'app/{Services/MembershipImageSync.php,Http/Controllers/MembershipImageController.php},resources/js/components/BookingBuilder.vue'
---

# Controllers Js Components

## Membership picture claims on save; booking Select Type is server-decided
A membership plan holds `image_file_id` → `stored_files`, never a path (same reason services do). The picture is uploaded async to `membership.images.store` BEFORE the plan exists and is claimed by `MembershipImageSync::sync()` when the plan is written, so the form posts an id. Claiming is guarded on tenant scope + `category === MembershipPlan::IMAGE_CATEGORY` — an id in a form is a number a reader can change, and without the category check a client's private document could be turned into a public membership picture. `sync()` also deletes the file it replaced; a plan carries one picture, so the old one has nothing pointing at it. `duplicate()` nulls `image_file_id` before saving the copy: a file belongs to one row.

Test trap: `StoredFile::withoutGlobalScopes()` drops the SoftDeletingScope too, so a deleted file still comes back — assert `->trashed()`, not `assertNull(find(...))`. The columns are `storage_disk`/`storage_path`/`stored_filename`, not `disk`/`path`.

The booking screen's Select Type accordion reads `purchaseTypes` from `BookingController::purchaseTypes()`. Each entry carries `available` (the module is on and has something to sell) and `ready` (the purchase flow behind it exists) — deliberately separate, so the card can offer a type without opening a path that ends nowhere. Unsellable types render disabled with `reason` beside them rather than being hidden: a missing option reads as a product StyleDesk does not have, a disabled one names the switch to turn on. `chooseType()` re-checks `ready` because `disabled` is a hint to a pointer, not a rule.
