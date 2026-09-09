<?php

declare(strict_types=1);

/**
 * What the storage component says when it refuses a file.
 *
 * Each one names the limit rather than the failure: "that image is larger
 * than 5 MB" tells the reader what to do next, where "upload failed" tells
 * them only that something went wrong.
 */
return [
    'upload_failed' => 'That file could not be uploaded. Please try again.',
    'extension_not_allowed' => 'That kind of file is not accepted here. Allowed: :list.',
    'type_not_allowed' => 'That file is not the kind it claims to be, so it was not stored.',
    'too_large' => 'That file is larger than :size.',
    'not_found' => 'That file is no longer available.',
];
