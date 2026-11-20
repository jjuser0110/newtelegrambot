<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcastBatch;
use App\Models\Bot;
use App\Models\Broadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function index(Bot $bot): View
    {
        $this->ownBot($bot);
        $broadcasts = $bot->broadcasts()->with(['savedMessage', 'creator'])->latest()->paginate(20);

        return view('broadcasts.index', compact('bot', 'broadcasts'));
    }

    public function create(Bot $bot): View
    {
        $this->ownBot($bot);
        $audience = $bot->telegramUsers()->where('is_blocked', false)->count();
        $savedMessages = $bot->savedMessages()->latest()->get();

        return view('broadcasts.create', compact('bot', 'audience', 'savedMessages'));
    }

    public function store(Request $request, Bot $bot): RedirectResponse
    {
        $this->ownBot($bot);

        $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'saved_message_id' => ['nullable', 'integer', Rule::exists('saved_messages', 'id')->where('bot_id', $bot->id)],
            'send_mode' => ['nullable', 'in:copy,forward'],
            'message' => ['nullable', 'string', 'max:4096', 'required_without_all:image,saved_message_id'],
            'image' => ['nullable', 'image', 'max:5120'],
            'buttons' => ['nullable', 'array', 'max:20'],
            'scheduled_at' => ['nullable', 'date'],
            'batch_size' => ['required', 'integer', 'min:1', 'max:3000'],
            'batch_delay_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'messages_per_second' => ['nullable', 'integer', 'min:1', 'max:'.Broadcast::MAX_MESSAGES_PER_SECOND],
        ], ['message.required_without_all' => __('Please enter a message, choose an image or pick a saved message.')]);

        $buttons = ButtonInput::clean($request->input('buttons', []));
        $usesSaved = $request->filled('saved_message_id');
        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::parse($request->scheduled_at, config('app.timezone'))
            : now();

        $broadcast = $bot->broadcasts()->create([
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'message' => $usesSaved ? null : $request->message,
            'image_path' => ! $usesSaved && $request->hasFile('image') ? $request->file('image')->store('broadcasts', 'public') : null,
            'buttons' => $buttons ?: null,
            'saved_message_id' => $usesSaved ? $request->saved_message_id : null,
            'send_mode' => $request->input('send_mode', 'copy'),
            'status' => Broadcast::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'batch_size' => $request->integer('batch_size'),
            'batch_delay_minutes' => $request->integer('batch_delay_minutes'),
            'messages_per_second' => $request->filled('messages_per_second') ? $request->integer('messages_per_second') : 15,
        ]);

        if ($scheduledAt->lte(now())) {
            SendBroadcastBatch::dispatch($broadcast);

            return redirect()->route('broadcasts.show', [$bot, $broadcast])->with('success', __('Broadcast started.'));
        }

        return redirect()->route('broadcasts.show', [$bot, $broadcast])
            ->with('success', __('Broadcast scheduled for :time (:tz).', ['time' => $scheduledAt->format('Y-m-d H:i'), 'tz' => config('app.timezone')]));
    }

    public function show(Request $request, Bot $bot, Broadcast $broadcast): View
    {
        $this->ownBot($bot);
        $broadcast->load(['savedMessage', 'creator']);

        $recipients = $broadcast->recipients()
            ->with('telegramUser')
            ->when(in_array($request->status, ['pending', 'sent', 'failed'], true), fn ($query) => $query->where('status', $request->status))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('broadcasts.show', compact('bot', 'broadcast', 'recipients'));
    }

    /**
     * Polled by the progress bar.
     */
    public function progress(Bot $bot, Broadcast $broadcast): JsonResponse
    {
        $this->ownBot($bot);

        return response()->json([
            'status' => $broadcast->status,
            'total' => $broadcast->total,
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
            'pending' => max(0, $broadcast->total - $broadcast->sent_count - $broadcast->failed_count),
            'percent' => $broadcast->progressPercent(),
            'next_batch_at' => $broadcast->status === Broadcast::STATUS_RUNNING && $broadcast->next_batch_at?->isFuture()
                ? $broadcast->next_batch_at->format('Y-m-d H:i')
                : null,
            'finished' => $broadcast->isFinished(),
            'messages_per_second' => $broadcast->messages_per_second,
            'minutes_left' => $broadcast->estimatedMinutesRemaining(),
        ]);
    }

    /**
     * Change speed / batch settings of a scheduled, running or paused broadcast. Applies within a few messages.
     */
    public function updateSpeed(Request $request, Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if ($broadcast->isFinished()) {
            return back()->with('warning', __('This broadcast has already finished.'));
        }

        $broadcast->update($request->validate([
            'messages_per_second' => ['required', 'integer', 'min:1', 'max:'.Broadcast::MAX_MESSAGES_PER_SECOND],
            'batch_size' => ['required', 'integer', 'min:1', 'max:3000'],
            'batch_delay_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]));

        return back()->with('success', __('Speed updated to :speed message(s)/second, :size per batch, :pause min pause.', ['speed' => $broadcast->messages_per_second, 'size' => $broadcast->batch_size, 'pause' => $broadcast->batch_delay_minutes]));
    }

    public function pause(Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if ($broadcast->status !== Broadcast::STATUS_RUNNING) {
            return back()->with('warning', __('Only a running broadcast can be paused.'));
        }

        $broadcast->update(['status' => Broadcast::STATUS_PAUSED]);

        return back()->with('success', __('Broadcast paused. It stops within a few messages; press Resume to continue.'));
    }

    public function resume(Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if ($broadcast->status !== Broadcast::STATUS_PAUSED) {
            return back()->with('warning', __('Only a paused broadcast can be resumed.'));
        }

        $broadcast->update(['status' => Broadcast::STATUS_RUNNING, 'next_batch_at' => now()]);
        SendBroadcastBatch::dispatch($broadcast);

        return back()->with('success', __('Broadcast resumed.'));
    }

    public function cancel(Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if (! $broadcast->isFinished()) {
            $broadcast->update(['status' => Broadcast::STATUS_CANCELLED, 'finished_at' => now(), 'next_batch_at' => null]);
        }

        return back()->with('success', __('Broadcast cancelled. Messages already sent cannot be recalled.'));
    }

    /**
     * Put failed recipients back in the queue (e.g. after a network problem).
     */
    public function retryFailed(Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if (! in_array($broadcast->status, [Broadcast::STATUS_RUNNING, Broadcast::STATUS_COMPLETED], true)) {
            return back()->with('warning', __('Only running or completed broadcasts can retry failed recipients.'));
        }

        $count = $broadcast->recipients()->where('status', 'failed')->update(['status' => 'pending', 'error' => null, 'sent_at' => null]);
        if ($count === 0) {
            return back()->with('warning', __('There are no failed recipients to retry.'));
        }

        $broadcast->update([
            'status' => Broadcast::STATUS_RUNNING,
            'failed_count' => max(0, $broadcast->failed_count - $count),
            'finished_at' => null,
            'next_batch_at' => now(),
        ]);
        SendBroadcastBatch::dispatch($broadcast);

        return back()->with('success', __('Retrying :count failed recipient(s).', ['count' => $count]));
    }

    public function destroy(Bot $bot, Broadcast $broadcast): RedirectResponse
    {
        $this->ownBot($bot);

        if ($broadcast->status === Broadcast::STATUS_RUNNING) {
            return back()->with('warning', __('Cancel the broadcast before deleting it.'));
        }
        if ($broadcast->image_path) {
            Storage::disk('public')->delete($broadcast->image_path);
        }
        $broadcast->delete();

        return redirect()->route('broadcasts.index', $bot)->with('success', __('Broadcast deleted.'));
    }
}
