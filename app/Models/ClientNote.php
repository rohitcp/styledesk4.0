<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One note on a client's record.
 *
 * Kept apart from the client's own `notes` field: that is the standing
 * description the business configured, this is what someone wrote on a
 * particular day and signed.
 */
class ClientNote extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Whether the reader looking at this request may see the body.
     *
     * A real property rather than an attribute: attributes are columns to
     * Eloquent, and a stray save() would try to write "readable" to a table
     * that has no such thing.
     */
    public bool $readable = false;

    protected function casts(): array
    {
        return ['is_important' => 'boolean', 'is_private' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The body, ready to be printed as markup.
     *
     * Old notes are plain text and are escaped here, with their line breaks
     * turned into tags so they read as they always did. New ones were
     * sanitised by NoteHtml on the way in — the allowlist ran once, at the
     * only point where untrusted markup could enter — and are returned as
     * they were stored.
     *
     * The one place that decides this, so no view has to remember which kind
     * of note it is holding.
     */
    public function bodyHtml(): HtmlString
    {
        if ($this->format === 'html') {
            return new HtmlString($this->body);
        }

        return new HtmlString(nl2br(e($this->body)));
    }

    /**
     * The people the author named when writing a private note.
     *
     * Owners and Admins are not in here — see the migration. This is the
     * list the author chose, not the list of everyone who can read it.
     *
     * @return BelongsToMany<User, $this>
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_note_access')->withTimestamps();
    }

    /**
     * Whether this person may read the note's body.
     *
     * Everything that renders, previews, searches or returns a note asks
     * this one question, so a new surface cannot invent its own answer and
     * leak a note the author restricted.
     *
     * Four ways in, and the order says which is which: a note that is not
     * private is readable by anyone allowed to read notes at all; the author
     * can always read what they wrote; an Owner or Admin can read everything
     * on the business's records; and anyone else needs to have been named.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $this->is_private) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        /* 'administrator' is the key in config/role_defaults.php; 'admin'
           is only what the role is called out loud. */
        if (in_array($user->roleInTenant(), ['owner', 'administrator'], true)) {
            return true;
        }

        return $this->accessUsers->contains('id', $user->id);
    }

    /**
     * Who wrote it, or an honest stand-in.
     *
     * A note whose author has left the salon still has to say something,
     * and a blank byline reads as a note nobody wrote.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? __('clients.module.workspace.notes.unknown_author');
    }
}
