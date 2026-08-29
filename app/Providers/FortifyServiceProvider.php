<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse as FortifyFailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse as FortifySuccessfulPasswordResetLinkRequestResponse;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * Signing out lands on the login screen, not the marketing page.
         *
         * Fortify's default sends people to '/', which for someone who has
         * just deliberately signed out is the front door of a product they
         * were already inside. The login form is what they want next, whether
         * they are switching accounts or handing the machine over.
         */
        $this->app->singleton(LogoutResponse::class, fn () => new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                return redirect()->route('login');
            }
        });

        /**
         * An address we have no account for is answered exactly like one we
         * do.
         *
         * Fortify's own response refuses it with "we can't find a user with
         * that email address", which turns this form into a way of asking
         * which addresses hold StyleDesk accounts, one guess at a time — and
         * a salon's client-facing email is not hard to guess. The reader who
         * mistyped their own address is no worse off: they are told to go and
         * read an email, find none, and try again.
         *
         * Throttling is deliberately left alone. "Please wait" is not a fact
         * about whether an account exists, and hiding it would leave someone
         * waiting for an email that was never sent.
         */
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, function ($app, array $parameters) {
            $status = $parameters['status'] ?? Password::INVALID_USER;

            if ($status === Password::RESET_THROTTLED) {
                return new FortifyFailedPasswordResetLinkRequestResponse($status);
            }

            return new FortifySuccessfulPasswordResetLinkRequestResponse(Password::RESET_LINK_SENT);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Fortify is used headless: it owns the auth logic, we own the views.
         *
         * config/fortify.php keeps 'views' => true, so Fortify registers the
         * GET routes but resolves each page through these callbacks. Without
         * them the route exists and then fails at render time with
         * "Target [Laravel\Fortify\Contracts\RegisterViewResponse] is not
         * instantiable" — a 500, not a 404, which is what makes it confusing.
         *
         * These are deliberately plain forms built from the styledesk_ CSS
         * layer. They are placeholders for the prototype's login.html /
         * signup.html designs, not a substitute for porting them.
         */
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        /**
         * Sign-up throttling.
         *
         * Registration creates records and sends mail, so it is worth
         * rate limiting on its own rather than leaving it open. Keyed by IP
         * because there is no account to key on yet.
         */
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        /**
         * Login throttling, answered on the login form rather than with a 429.
         *
         * Without the response callback the framework renders its own "Too
         * Many Requests" page: a dead end with no explanation, no way back to
         * the form, and no indication that waiting will fix it. Someone who
         * has simply mistyped their password five times is told the site is
         * broken. The limit itself is unchanged — only what the reader is
         * shown when they reach it.
         *
         * Retry-After comes from the limiter, so the count in the message is
         * the real one rather than the window's nominal length.
         */
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                $message = trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]);

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                /* The address comes back with it: being made to retype it
                   after being told to wait is a second small punishment for
                   the same mistake. The password does not — see the sign-up
                   form for why a password is never returned to the page. */
                return back()
                    ->withInput($request->only(Fortify::username()))
                    ->withErrors([Fortify::username() => $message]);
            });
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
