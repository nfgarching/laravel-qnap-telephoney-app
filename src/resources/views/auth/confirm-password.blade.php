@extends('layouts.auth', ['title' => 'Passwort bestätigen'])

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        <p class="text-body-secondary">Bitte zur Sicherheit das Passwort erneut eingeben.</p>
        <form method="POST" action="{{ route('password.confirm.store') }}">
            @csrf
            <div class="mb-3">
                <label for="password" class="form-label">Passwort</label>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required autofocus>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Bestätigen</button>
        </form>
    </div>
</div>
@endsection
