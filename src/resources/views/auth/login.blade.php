@extends('layouts.auth', ['title' => 'Anmelden'])

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">E-Mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       autocomplete="username" autocapitalize="off" required autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" checked>
                <label class="form-check-label" for="remember">Angemeldet bleiben</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Anmelden</button>
        </form>
    </div>
</div>
@endsection
