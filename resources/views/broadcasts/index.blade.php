@extends('layouts.app')
@section('title', 'Broadcasts')

@php
    $statusColors = ['scheduled' => 'info', 'running' => 'primary', 'paused' => 'warning', 'completed' => 'success', 'cancelled' => 'secondary'];
@endphp

@section('content')
<nav class="mb-2"><a href="{{ route('bots.show', $bot) }}">&larr; &#64;{{ $bot->username }}</a></nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Broadcasts of &#64;{{ $bot->username }}</h3>
    <a href="{{ route('broadcasts.create', $bot) }}" class="btn btn-primary"><i class="bi bi-megaphone"></i> New broadcast</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>#</th><th>Content</th><th>Status</th><th style="min-width: 180px">Progress</th><th>Sent</th><th>Failed</th><th>Scheduled / started</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($broadcasts as $broadcast)
                <tr>
                    <td>{{ $broadcast->id }}</td>
                    <td class="small">{{ $broadcast->contentSummary() }}</td>
                    <td><span class="badge text-bg-{{ $statusColors[$broadcast->status] ?? 'light' }}">{{ $broadcast->status }}</span></td>
                    <td>
                        <div class="progress" style="height: 8px"><div class="progress-bar" style="width: {{ $broadcast->progressPercent() }}%"></div></div>
                        <div class="small text-muted">{{ $broadcast->progressPercent() }}% of {{ $broadcast->total ?: '?' }}</div>
                    </td>
                    <td class="text-success">{{ $broadcast->sent_count }}</td>
                    <td class="text-danger">{{ $broadcast->failed_count }}</td>
                    <td class="small">{{ ($broadcast->started_at ?? $broadcast->scheduled_at)?->format('Y-m-d H:i') }}</td>
                    <td class="text-end"><a href="{{ route('broadcasts.show', [$bot, $broadcast]) }}" class="btn btn-sm btn-outline-primary">Details</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No broadcasts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $broadcasts->links('pagination::bootstrap-5') }}</div>
@endsection
