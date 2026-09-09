<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The four screens of My Account, and which one is open.
 *
 * One list, read by the left navigation, the mobile selector and the account
 * menu in the app bar. Three copies of it is how a section ends up reachable
 * from one place and not another.
 *
 * Deliberately unrelated to App\Support\Nav and the settings modules: nothing
 * here is a permission question. Every signed-in person may open every one of
 * these, because they are all about that person.
 */
class AccountSection
{
    /**
     * @return array<int, array{key: string, label: string, route: string, icon: string}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'profile', 'label' => __('account.sections.profile'), 'route' => 'account.profile', 'icon' => 'user'],
            ['key' => 'preferences', 'label' => __('account.sections.preferences'), 'route' => 'account.preferences', 'icon' => 'sliders'],
            ['key' => 'password', 'label' => __('account.sections.password'), 'route' => 'account.password', 'icon' => 'lock'],
            ['key' => 'notifications', 'label' => __('account.sections.notifications'), 'route' => 'account.notifications', 'icon' => 'bell'],
        ];
    }
}
