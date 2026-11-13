<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BotReplyController extends Controller
{
    public function create(Bot $bot)
    {
        $this->ownBot($bot);
        $reply = new BotReply([
            'trigger' => '/start',
            'is_active' => true,
            'sort_order' => ($bot->replies()->max('sort_order') ?? 0) + 1,
        ]);

        return view('replies.form', compact('bot', 'reply'));
    }

    public function store(Request $request, Bot $bot)
    {
        $this->ownBot($bot);
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('replies', 'public');
        }

        $bot->replies()->create($data);

        return redirect()->route('bots.show', $bot)->with('success', 'Reply saved.');
    }

    public function edit(Bot $bot, BotReply $reply)
    {
        $this->ownBot($bot);

        return view('replies.form', compact('bot', 'reply'));
    }

    public function update(Request $request, Bot $bot, BotReply $reply)
    {
        $this->ownBot($bot);
        $data = $this->validated($request);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            if ($reply->image_path) {
                Storage::disk('public')->delete($reply->image_path);
            }
            $data['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store('replies', 'public')
                : null;
        }

        $reply->update($data);

        return redirect()->route('bots.show', $bot)->with('success', 'Reply updated.');
    }

    public function destroy(Bot $bot, BotReply $reply)
    {
        $this->ownBot($bot);
        if ($reply->image_path) {
            Storage::disk('public')->delete($reply->image_path);
        }
        $reply->delete();

        return back()->with('success', 'Reply deleted.');
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'trigger' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:4096', 'required_without_all:image,keep_image'],
            'image' => ['nullable', 'image', 'max:5120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'buttons' => ['nullable', 'array', 'max:20'],
            'buttons.*.text' => ['nullable', 'string', 'max:64'],
            'buttons.*.type' => ['nullable', 'in:url,callback'],
            'buttons.*.value' => ['nullable', 'string', 'max:500'],
        ], ['message.required_without_all' => 'Please enter a message or upload an image.']);

        return [
            'trigger' => trim($request->trigger),
            'message' => $request->message,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
            'buttons' => ButtonInput::clean($request->input('buttons', [])),
        ];
    }
}
