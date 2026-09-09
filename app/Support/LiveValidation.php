<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The message bundle the browser validates with.
 *
 * One place, so that every form opting into live validation says the same
 * things in the same words — and says them in the reader's language, which a
 * bundle written in JavaScript could not.
 *
 * The JavaScript half is resources/js/live-validation.js; a form connects the
 * two by carrying data-validate-form and data-validation-messages.
 */
class LiveValidation
{
    /**
     * @param  array<string, string>  $extra  form-specific messages, keyed by
     *                                        rule name — a "taken" that says
     *                                        what was taken, for instance
     * @return array<string, string>
     */
    public static function messages(array $extra = []): array
    {
        return array_merge(__('common.validation'), $extra);
    }
}
