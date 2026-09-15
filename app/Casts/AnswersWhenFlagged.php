<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Form answers, encrypted at rest when the form said they were sensitive.
 *
 * The decision is read from the ROW's own `answers_encrypted` column rather
 * than from the form's current setting, and that is the whole point of this
 * class. A business that turns "contains sensitive information" off next year
 * would otherwise make every row written while it was on undecryptable, and a
 * business that turns it on would make the plain rows already stored
 * unreadable — in both directions, silently, and only noticed the day
 * somebody opens a signed medical history and finds base64.
 *
 * So each row states how it stored itself, and this reads what it says.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class AnswersWhenFlagged implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        /*
         * The flag has to be loaded for this to be answerable.
         *
         * A partial select — `->select('answers')`, or Eloquent's `value()`,
         * which is `first([$column])` — brings the answers without it, and
         * guessing "not encrypted" there decodes ciphertext as json, fails,
         * and hands back null. Empty answers on a signed medical history is
         * the worst possible way to be wrong, so say so instead.
         *
         * Absent on a model that does not exist yet is different: nothing has
         * been stored, and the default is what a new row would get.
         */
        if (! array_key_exists('answers_encrypted', $attributes)) {
            if ($model->exists) {
                throw new \LogicException(
                    'form_submissions.answers cannot be read without answers_encrypted: select both columns.'
                );
            }

            return json_decode((string) $value, true) ?: null;
        }

        $json = empty($attributes['answers_encrypted'])
            ? (string) $value
            : Crypt::decryptString((string) $value);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        /* Whatever the row is being written with, not what it holds now: a
           submission's flag is set from the form when it is assigned, and
           both the flag and the answers travel in the same save. */
        $encrypt = (bool) ($attributes['answers_encrypted'] ?? $model->getAttribute('answers_encrypted'));

        return [$key => $encrypt ? Crypt::encryptString($json) : $json];
    }
}
