<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Tenant storage
    |--------------------------------------------------------------------------
    |
    | Which disk TenantStorageService puts tenant files on: `tenants` for a
    | local directory, `spaces` for DigitalOcean. It is read in exactly one
    | place, so switching provider is this line and nothing else — no feature
    | in the application names a disk.
    |
    */

    'tenant_disk' => env('TENANT_STORAGE_DISK', 'tenants'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /**
         * Brand assets (logos), deliberately NOT tenant-suffixed.
         *
         * The 'public' disk is listed in tenancy.filesystem.disks, so once
         * tenancy initializes its root moves to storage/tenant<id>/app/public
         * while Storage::url() still points at the central /storage symlink —
         * the file uploads fine and then 404s.
         *
         * It also cannot be tenant-scoped in the first place: the logo is
         * uploaded during onboarding step 1, before a tenant exists to scope
         * it to. Filenames are random 40-character hashes, so a central
         * directory is not enumerable.
         */
        'brand' => [
            'driver' => 'local',
            'root' => storage_path('app/public/brand'),
            'url' => env('APP_URL').'/storage/brand',
            'visibility' => 'public',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /**
         * Tenant files, wherever they actually live.
         *
         * One logical disk with two implementations behind it: a local
         * directory in development, a DigitalOcean Space in production. The
         * folder structure inside is identical either way, so a path written
         * on a laptop is the same path in production and nothing above
         * TenantStorageService has to know which is in use.
         *
         * `tenants` is deliberately absent from tenancy.filesystem.disks:
         * the tenant is already the first segment of every path this disk
         * stores, and letting the tenancy package also move the disk root
         * would put it in the path twice.
         */
        'tenants' => [
            'driver' => 'local',
            'root' => storage_path('app/tenants'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/tenants',
            'visibility' => 'private',
            'throw' => false,
        ],

        'spaces' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY'),
            'secret' => env('DO_SPACES_SECRET'),
            'region' => env('DO_SPACES_REGION', 'ams3'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'url' => env('DO_SPACES_URL'),
            'endpoint' => env('DO_SPACES_ENDPOINT'),
            /* Spaces addresses buckets as subdomains, the way S3 does. */
            'use_path_style_endpoint' => env('DO_SPACES_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
