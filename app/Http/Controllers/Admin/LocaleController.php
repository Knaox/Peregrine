<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the authenticated admin's locale (users.locale) and redirects
 * back to the page they came from. Wired into the Filament admin's user
 * menu via two MenuItems (Français / English).
 *
 * Only `en` and `fr` are accepted today — extending to a third language
 * means adding it here, in SetUserLocale::SUPPORTED, and to the lang
 * directory.
 */
class LocaleController extends Controller
{
    private const SUPPORTED = ['en', 'fr'];

    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->forceFill(['locale' => $locale])->save();
        app()->setLocale($locale);

        Notification::make()
            ->title(__('admin.profile.language_switched', ['locale' => $locale === 'fr' ? 'Français' : 'English']))
            ->success()
            ->send();

        return redirect()->back();
    }
}
