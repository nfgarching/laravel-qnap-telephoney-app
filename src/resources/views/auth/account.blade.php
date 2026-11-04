@extends('layouts.auth', ['title' => 'Konto', 'wide' => true])

@php
    $user = auth()->user();
    $pending = $user->two_factor_secret && ! $user->two_factor_confirmed_at;
    $active = (bool) $user->two_factor_confirmed_at;
@endphp

@section('content')
<p class="text-center text-body-secondary mb-4">
    {{ $user->name }} · {{ $user->email }}
</p>

@if (! $active && config('auth.require_two_factor'))
    <div class="alert alert-warning">
        <i class="bi bi-shield-exclamation me-1"></i>
        Bevor die Anrufliste geöffnet werden kann, muss die Zwei-Faktor-Anmeldung eingerichtet werden.
    </div>
@endif

{{-- Zwei-Faktor-Anmeldung --}}
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-shield-lock me-1"></i> Zwei-Faktor-Anmeldung</h2>

        @if ($active)
            <p class="text-success mb-3"><i class="bi bi-check-circle me-1"></i> Aktiv</p>

            @if (session('status') === 'recovery-codes-generated' || session('show-codes'))
                <p class="mb-2">Wiederherstellungscodes – sicher aufbewahren:</p>
                <ul class="list-unstyled codes bg-body-tertiary rounded p-3">
                    @foreach ($user->recoveryCodes() as $code) <li>{{ $code }}</li> @endforeach
                </ul>
            @endif

            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                    @csrf
                    <button class="btn btn-outline-secondary">Neue Wiederherstellungscodes</button>
                </form>
                @unless (config('auth.require_two_factor'))
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger">Deaktivieren</button>
                    </form>
                @endunless
            </div>
        @elseif ($pending)
            <ol class="ps-3">
                <li>QR-Code mit einer Authenticator-App scannen (z. B. 2FAS, Aegis, Google/Microsoft Authenticator, 1Password).</li>
                <li>Den angezeigten 6-stelligen Code eingeben.</li>
            </ol>
            <div class="qr text-center my-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
            <details class="mb-3">
                <summary class="small text-body-secondary">Schlüssel manuell eingeben</summary>
                <code class="user-select-all">{{ decrypt($user->two_factor_secret) }}</code>
            </details>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       class="form-control @error('code', 'confirmTwoFactorAuthentication') is-invalid @enderror"
                       placeholder="123456" autocomplete="one-time-code" required autofocus>
                <button class="btn btn-primary text-nowrap">Bestätigen</button>
            </form>
            @error('code', 'confirmTwoFactorAuthentication')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        @else
            <p>Zusätzlich zum Passwort wird bei der Anmeldung ein Code aus einer Authenticator-App abgefragt.</p>
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <button class="btn btn-primary">Einrichten</button>
            </form>
        @endif
    </div>
</div>

{{-- Passwort --}}
<div class="card shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-key me-1"></i> Passwort ändern</h2>
        @if (session('status') === 'password-updated')
            <div class="alert alert-success py-2">Passwort geändert.</div>
        @endif
        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf @method('PUT')
            @foreach ([
                'current_password' => ['Aktuelles Passwort', 'current-password'],
                'password' => ['Neues Passwort', 'new-password'],
                'password_confirmation' => ['Neues Passwort wiederholen', 'new-password'],
            ] as $field => [$label, $ac])
                <div class="mb-3">
                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" type="password" name="{{ $field }}" autocomplete="{{ $ac }}"
                           class="form-control @error($field, 'updatePassword') is-invalid @enderror" required>
                    @error($field, 'updatePassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between">
    @if ($active || ! config('auth.require_two_factor'))
        <a href="{{ route('telephony.index') }}" class="btn btn-link"><i class="bi bi-arrow-left"></i> Zur Anrufliste</a>
    @else
        <span></span>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i> Abmelden</button>
    </form>
</div>
@endsection
