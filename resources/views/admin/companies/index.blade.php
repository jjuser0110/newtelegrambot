@extends('layouts.app')
@section('title', __('Companies'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">{{ __('Companies') }}</h3>
    <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New Company') }}</a>
</div>

<form class="mb-3" method="GET">
    <div class="input-group" style="max-width: 360px">
        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search name or username') }}">
        <button class="btn btn-outline-secondary">{{ __('Search') }}</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>{{ __('Name') }}</th><th>{{ __('Username') }}</th><th>{{ __('Bots used / limit') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
            </thead>
            <tbody>
            @forelse($companies as $c)
                <tr>
                    <td>{{ $c->name }}</td>
                    <td><code>{{ $c->username }}</code></td>
                    <td><a href="{{ route('bots.index', ['company' => $c->id]) }}">{{ $c->bots_count }} / {{ $c->bot_limit }}</a></td>
                    <td>
                        @if($c->is_active)<span class="badge text-bg-success">{{ __('Active') }}</span>
                        @else<span class="badge text-bg-secondary">{{ __('Disabled') }}</span>@endif
                    </td>
                    <td>{{ $c->created_at->format('Y-m-d') }}</td>
                    <td class="text-end">
                        <a href="{{ route('bots.index', ['company' => $c->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('View bots') }}</a>
                        <a href="{{ route('admin.companies.edit', $c) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.companies.destroy', $c) }}" class="d-inline"
                              onsubmit="return confirm(@js(__('Delete :name and all its bots, chats and messages?', ['name' => $c->name])))">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No companies yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $companies->links('pagination::bootstrap-5') }}</div>
@endsection
