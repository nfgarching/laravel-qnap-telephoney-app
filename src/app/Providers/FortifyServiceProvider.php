<?php

namespace App\Providers;

use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Login: 5 Versuche/Minute je E-Mail+IP, zusätzlich 20/Stunde je IP
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perHour(20)->by('ip|'.$request->ip()),
            ];
        });

        // 2FA-Code: 5 Versuche/Minute je angefangener Anmeldung und je IP
        RateLimiter::for('two-factor', function (Request $request) {
            return [
                Limit::perMinute(5)->by('2fa|'.$request->session()->get('login.id')),
                Limit::perMinute(10)->by('2fa-ip|'.$request->ip()),
            ];
        });
    }
}
