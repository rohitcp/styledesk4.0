<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    // Registered by hand: `php artisan tenancy:install` still writes to the
    // Laravel 10-style config/app.php providers array, which Laravel 11+ no
    // longer reads. Without this line the provider never boots, so tenancy
    // events, bootstrappers and routes/tenant.php are all silently inert.
    App\Providers\TenancyServiceProvider::class,
];
