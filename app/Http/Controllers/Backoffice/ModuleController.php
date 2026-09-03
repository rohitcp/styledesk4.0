<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The modules the navigation names but that are not built yet.
 *
 * Real routes with real permission middleware, answering an honest "not yet"
 * rather than a 404. Two reasons this is not a placeholder in the navigation
 * instead: the permission gate is exercised from the first day, so a role that
 * should never have reached Billing is caught now rather than on the day
 * Billing arrives; and a reader clicking Plans learns that Plans is coming,
 * which is true, instead of that the product is broken, which is not.
 *
 * Each of these is replaced by its own controller as its phase lands. The
 * route names do not change, so nothing else has to.
 */
class ModuleController extends Controller
{
    public function clients(): View
    {
        return $this->soon('clients');
    }

    public function plans(): View
    {
        return $this->soon('plans');
    }

    public function billing(): View
    {
        return $this->soon('billing');
    }

    public function settings(): View
    {
        return $this->soon('settings');
    }

    private function soon(string $key): View
    {
        return view('backoffice.soon', [
            'module' => $key,
            'title' => __('backoffice.nav.'.$key),
        ]);
    }
}
