<!DOCTYPE html>
<html lang="de" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#115e59">
    <title>{{ $title ?? 'Anmelden' }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ route('telephony.icon', ['size' => 192]) }}">
    <link rel="apple-touch-icon" href="{{ route('telephony.icon', ['size' => 192]) }}">
    <script>
        (() => {
            const dark = window.matchMedia('(prefers-color-scheme: dark)');
            const apply = () => document.documentElement.setAttribute('data-bs-theme', dark.matches ? 'dark' : 'light');
            apply();
            dark.addEventListener('change', apply);
        })();
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body { background: var(--bs-tertiary-bg); min-height: 100dvh; }
        .auth-wrap { max-width: 26rem; margin: 0 auto; padding: calc(2rem + env(safe-area-inset-top, 0px)) 1rem 2rem; }
        .auth-wrap.wide { max-width: 40rem; }
        .auth-logo { width: 3.5rem; height: 3.5rem; border-radius: 1rem; background: #115e59; color: #fff;
                     display: grid; place-items: center; font-size: 1.6rem; margin: 0 auto 1rem; }
        .card { border: 0; border-radius: 1rem; }
        .btn-primary { --bs-btn-bg: #115e59; --bs-btn-border-color: #115e59;
                       --bs-btn-hover-bg: #0f4f4b; --bs-btn-hover-border-color: #0f4f4b;
                       --bs-btn-active-bg: #0c3f3c; --bs-btn-active-border-color: #0c3f3c; }
        .qr svg { width: 12rem; height: 12rem; background: #fff; padding: .5rem; border-radius: .5rem; }
        .codes { font-family: var(--bs-font-monospace); columns: 2; }
    </style>
</head>
<body>
    <main class="auth-wrap {{ $wide ?? false ? 'wide' : '' }}">
        <div class="auth-logo"><i class="bi bi-telephone-inbound"></i></div>
        <h1 class="h4 text-center mb-4">{{ $title ?? 'Anmelden' }}</h1>

        @if (session('status'))
            <div class="alert alert-success">{{ __(session('status')) }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
