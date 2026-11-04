@extends('layouts.auth', ['title' => 'Bestätigungscode'])

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        @php $recovery = $errors->has('recovery_code') || old('recovery_code'); @endphp

        <form method="POST" action="{{ route('two-factor.login.store') }}" id="totp" @class(['d-none' => $recovery])>
            @csrf
            <p class="text-body-secondary">Code aus der Authenticator-App eingeben.</p>
            <div class="mb-3">
                <label for="code" class="form-label">Code</label>
                <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                       autocomplete="one-time-code" @unless($recovery) autofocus @endunless>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Bestätigen</button>
            <button type="button" class="btn btn-link w-100" onclick="toggle()">Wiederherstellungscode verwenden</button>
        </form>

        <form method="POST" action="{{ route('two-factor.login.store') }}" id="recovery" @class(['d-none' => ! $recovery])>
            @csrf
            <p class="text-body-secondary">Einen der Wiederherstellungscodes eingeben. Jeder Code gilt nur einmal.</p>
            <div class="mb-3">
                <label for="recovery_code" class="form-label">Wiederherstellungscode</label>
                <input id="recovery_code" type="text" name="recovery_code" autocapitalize="off"
                       class="form-control @error('recovery_code') is-invalid @enderror" autocomplete="off">
                @error('recovery_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Bestätigen</button>
            <button type="button" class="btn btn-link w-100" onclick="toggle()">Code aus der App verwenden</button>
        </form>
    </div>
</div>
<script>
    function toggle() {
        document.getElementById('totp').classList.toggle('d-none');
        document.getElementById('recovery').classList.toggle('d-none');
    }
</script>
@endsection
