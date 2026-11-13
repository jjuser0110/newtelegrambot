<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .chat-box { max-height: 60vh; overflow-y: auto; background: #e9eef3; }
        .bubble { max-width: 75%; white-space: pre-wrap; word-break: break-word; }
        .bubble img { max-width: 240px; border-radius: .4rem; }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}"><i class="bi bi-telegram"></i> {{ config('app.name') }}</a>
        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    @if(auth()->user()->isSuperadmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.companies.index') }}">Companies</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('bots.index') }}">My Bots</a></li>
                    @endif
                </ul>
                <span class="navbar-text me-3 text-white-50">
                    <i class="bi bi-person-circle"></i> {{ auth()->user()->username }} ({{ auth()->user()->role }})
                </span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-sm btn-outline-light">Logout</button>
                </form>
            </div>
        @endauth
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
