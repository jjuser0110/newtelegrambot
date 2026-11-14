@extends('layouts.app')
@section('title', $reply->exists ? 'Edit reply' : 'Add reply')

@section('content')
<nav class="mb-2"><a href="{{ route('bots.show', $bot) }}">&larr; &#64;{{ $bot->username }}</a></nav>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h3 class="mb-3">{{ $reply->exists ? 'Edit reply' : 'Add reply' }}</h3>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" data-loading="Saving reply…"
                      action="{{ $reply->exists ? route('bots.replies.update', [$bot, $reply]) : route('bots.replies.store', $bot) }}">
                    @csrf
                    @if($reply->exists) @method('PUT') @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Trigger</label>
                            <input name="trigger" value="{{ old('trigger', $reply->trigger) }}" class="form-control" required list="trigger-options">
                            <datalist id="trigger-options"><option value="/start"><option value="*"></datalist>
                            <div class="form-text"><code>/start</code> = user starts the bot · <code>*</code> = any other message · or a keyword (not case-sensitive).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Order</label>
                            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $reply->sort_order) }}" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        @include('partials.saved-message-picker', ['savedMessages' => $savedMessages, 'bot' => $bot, 'selectedId' => $reply->saved_message_id, 'selectedMode' => $reply->send_mode])
                    </div>

                    <div class="mb-3 compose-only">
                        <label class="form-label">Message</label>
                        <textarea name="message" rows="6" class="form-control" maxlength="4096">{{ old('message', $reply->message) }}</textarea>
                        <div class="form-text">With an image, the first 1024 characters are used as the caption.</div>
                    </div>

                    <div class="mb-3 compose-only">
                        <label class="form-label">Image</label>
                        @if($reply->image_path)
                            <input type="hidden" name="keep_image" value="1">
                            <div class="mb-2"><img src="{{ asset('storage/'.$reply->image_path) }}" style="max-height:160px" class="rounded border" alt=""></div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                                <label class="form-check-label" for="remove_image">Remove current image</label>
                            </div>
                        @endif
                        <input type="file" name="image" accept="image/*" class="form-control">
                        <div class="form-text">JPG / PNG / GIF / WEBP, max 5 MB.</div>
                    </div>

                    <div class="mb-3">
                        @include('partials.buttons', ['buttons' => $reply->buttons ?? []])
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            {{ old('is_active', $reply->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <button class="btn btn-primary">Save reply</button>
                    <a href="{{ route('bots.show', $bot) }}" class="btn btn-link">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
