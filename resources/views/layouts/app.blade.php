<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Dashboard')) · {{ config('app.name') }}</title>
    <script>
        /* Apply the saved light/dark theme before the page paints (no white flash). */
        (() => {
            let theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) { /* storage blocked */ }
            if (theme !== 'light' && theme !== 'dark') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme=light] { --app-bg: #f4f6f9; --app-chat-bg: #e9eef3; }
        [data-bs-theme=dark] { --app-bg: #15181c; --app-chat-bg: #1b1f24; }
        body { background: var(--app-bg); }
        .chat-box { max-height: 60vh; overflow-y: auto; background: var(--app-chat-bg); }
        .bubble { max-width: 75%; min-width: 180px; word-break: break-word; }
        .msg-text { white-space: pre-wrap; }
        .bubble img { max-width: 240px; border-radius: .4rem; }
        /* Bootstrap's "light" helpers stay light in dark mode; make them follow the theme. */
        [data-bs-theme=dark] .table-light { --bs-table-bg: var(--bs-tertiary-bg); --bs-table-color: var(--bs-body-color); --bs-table-border-color: var(--bs-border-color); }
        [data-bs-theme=dark] .text-bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; }
        [data-bs-theme=dark] .table-warning { --bs-table-bg: rgba(255, 193, 7, .14); --bs-table-color: var(--bs-body-color); --bs-table-border-color: var(--bs-border-color); }
        [data-bs-theme=dark] .navbar.bg-primary { background-color: #0b3d91 !important; }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}"><i class="bi bi-telegram"></i> {{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                @auth
                    @if(auth()->user()->isSuperadmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.companies.index') }}">{{ __('Companies') }}</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('bots.index') }}">{{ __('All Bots') }}</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('bots.index') }}">{{ __('My Bots') }}</a></li>
                    @endif
                @endauth
            </ul>
            <form method="POST" action="{{ route('locale.update') }}" class="me-3 my-2 my-md-0">
                @csrf
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent text-white border-light"><i class="bi bi-translate"></i></span>
                    <select name="locale" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Language') }}">
                        @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <button type="button" id="theme-toggle" class="btn btn-sm btn-outline-light me-3 my-2 my-md-0" title="{{ __('Switch light / dark mode') }}">
                <i class="bi bi-moon-stars"></i>
            </button>
            @auth
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> {{ auth()->user()->username }} <span class="opacity-75">({{ __(ucfirst(auth()->user()->role)) }})</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key"></i> {{ __('Change password') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> {{ __('Logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </div>
    </div>
</nav>

<main class="container pb-5">
    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls)
        @if(session($key))
            <div class="alert alert-{{ $cls }} alert-dismissible fade show">{{ session($key) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
    @endforeach
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</main>

{{-- Loading overlay: add data-loading="message" to any form to show it on submit. --}}
<div id="loading-overlay" class="position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
     style="display: none; background: rgba(0,0,0,.45); z-index: 2000;">
    <div class="bg-body rounded-3 shadow p-4 text-center" style="max-width: 360px;">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div id="loading-text" class="fw-semibold">{{ __('Please wait…') }}</div>
        <div class="small text-muted mt-2">{{ __("Please don't close or refresh this page.") }}</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form[data-loading]') || event.defaultPrevented) {
        return;
    }
    document.getElementById('loading-text').textContent = form.dataset.loading || @js(__('Please wait…'));
    document.getElementById('loading-overlay').style.display = 'flex';
    form.querySelectorAll('button, input[type=submit]').forEach((button) => {
        button.disabled = true;
    });
});
// Hide the overlay if the user comes back with the browser Back button.
window.addEventListener('pageshow', () => document.getElementById('loading-overlay').style.display = 'none');

/** Light / dark toggle. The choice is remembered in this browser; without a choice it follows the device setting. */
(() => {
    const button = document.getElementById('theme-toggle');
    const labels = @js(['dark' => __('Dark mode'), 'light' => __('Light mode')]);
    const current = () => document.documentElement.getAttribute('data-bs-theme');
    const show = () => {
        const dark = current() === 'dark';
        button.innerHTML = `<i class="bi ${dark ? 'bi-sun' : 'bi-moon-stars'}"></i>`;
        button.setAttribute('aria-label', dark ? labels.light : labels.dark);
    };
    button.addEventListener('click', () => {
        const next = current() === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) { /* storage blocked: still switches for this page */ }
        show();
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        let saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) { /* ignore */ }
        if (!saved) {
            document.documentElement.setAttribute('data-bs-theme', event.matches ? 'dark' : 'light');
            show();
        }
    });
    show();
})();
</script>
@stack('scripts')
</body>
</html>
