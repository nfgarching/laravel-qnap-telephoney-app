<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
| Benutzerverwaltung – Registrierung ist im Web abgeschaltet.
*/
Artisan::command('user:create {email?} {--name=}', function (?string $email = null) {
    if (! stream_isatty(STDIN)) {
        $this->error('Die Passwortabfrage braucht ein interaktives Terminal (z. B. SSH-Sitzung).');

        return 1;
    }

    $email ??= text('E-Mail', required: true);
    $name = $this->option('name') ?: text('Name', required: true);
    $pass = password('Passwort (mind. 12 Zeichen)', required: true);

    $v = Validator::make(compact('email', 'name') + ['password' => $pass], [
        'email' => ['required', 'email', 'unique:users,email'],
        'name' => ['required', 'string', 'max:255'],
        'password' => ['required', Password::min(12)->letters()->numbers()],
    ]);

    if ($v->fails()) {
        foreach ($v->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($pass)]);
    $this->info("Benutzer #{$user->id} angelegt. Nach der ersten Anmeldung wird die Zwei-Faktor-Anmeldung eingerichtet.");
})->purpose('Benutzer anlegen');

Artisan::command('user:password {email}', function (string $email) {
    if (! stream_isatty(STDIN)) {
        $this->error('Die Passwortabfrage braucht ein interaktives Terminal (z. B. SSH-Sitzung).');

        return 1;
    }

    $user = User::where('email', $email)->firstOrFail();
    $pass = password('Neues Passwort (mind. 12 Zeichen)', required: true);

    $v = Validator::make(['password' => $pass], ['password' => [Password::min(12)->letters()->numbers()]]);
    if ($v->fails()) {
        $this->error($v->errors()->first());

        return 1;
    }

    $user->forceFill(['password' => Hash::make($pass), 'remember_token' => null])->save();
    $this->info('Passwort geändert, „Angemeldet bleiben“ auf allen Geräten aufgehoben.');
})->purpose('Passwort eines Benutzers setzen');

Artisan::command('user:reset-2fa {email}', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();
    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ])->save();
    $this->info('Zwei-Faktor-Anmeldung zurückgesetzt; wird bei der nächsten Anmeldung neu eingerichtet.');
})->purpose('Zwei-Faktor-Anmeldung zurücksetzen (z. B. Handy verloren)');

Artisan::command('user:list', function () {
    $this->table(['ID', 'Name', 'E-Mail', '2FA'], User::all()->map(fn ($u) => [
        $u->id, $u->name, $u->email, $u->two_factor_confirmed_at ? 'ja' : 'nein',
    ]));
})->purpose('Benutzer auflisten');

Artisan::command('user:delete {email}', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();
    if ($this->confirm("Benutzer {$user->name} <{$user->email}> löschen?")) {
        $user->delete();
        $this->info('Gelöscht.');
    }
})->purpose('Benutzer löschen');

/*
| Telephony
*/
Schedule::command('telephony:sync')->hourly()->withoutOverlapping();
Schedule::command('telephony:prune')->daily();
