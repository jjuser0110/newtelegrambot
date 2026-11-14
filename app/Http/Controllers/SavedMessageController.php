<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\SavedMessage;
use App\Models\TelegramMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Messages received by the bot (e.g. Premium-decorated text) that can be copied/forwarded as replies or broadcasts.
 */
class SavedMessageController extends Controller
{
    public function index(Bot $bot): View
    {
        $this->ownBot($bot);
        $savedMessages = $bot->savedMessages()->latest()->get();

        return view('saved-messages.index', compact('bot', 'savedMessages'));
    }

    /**
     * Save either an incoming chat message (telegram_message_id) or a manually entered chat_id + message_id.
     */
    public function store(Request $request, Bot $bot): RedirectResponse
    {
        $this->ownBot($bot);

        $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'telegram_message_id' => ['nullable', 'integer', Rule::exists('telegram_messages', 'id')->where('bot_id', $bot->id)],
            'from_chat_id' => ['required_without:telegram_message_id', 'nullable', 'integer'],
            'message_id' => ['required_without:telegram_message_id', 'nullable', 'integer', 'min:1'],
        ]);

        if ($request->filled('telegram_message_id')) {
            $source = TelegramMessage::findOrFail($request->telegram_message_id);
            abort_if($source->direction !== 'in' || ! $source->telegram_message_id, 422, 'Only messages received from users can be saved.');

            $attributes = [
                'from_chat_id' => $source->chat_id,
                'message_id' => $source->telegram_message_id,
                'preview' => $source->message,
                'has_custom_emoji' => $this->hasCustomEmoji($source->payload ?? []),
            ];
        } else {
            $attributes = [
                'from_chat_id' => (int) $request->from_chat_id,
                'message_id' => (int) $request->message_id,
                'preview' => null,
                'has_custom_emoji' => false,
            ];
        }

        $savedMessage = $bot->savedMessages()->firstOrNew([
            'from_chat_id' => $attributes['from_chat_id'],
            'message_id' => $attributes['message_id'],
        ]);
        $savedMessage->fill($attributes);
        $savedMessage->label = $request->label ?: ($savedMessage->label ?? 'Message #'.$attributes['message_id']);
        $savedMessage->save();

        return back()->with('success', 'Saved message "'.$savedMessage->label.'". You can now use it in replies, chats and broadcasts.');
    }

    public function update(Request $request, Bot $bot, SavedMessage $savedMessage): RedirectResponse
    {
        $this->ownBot($bot);
        $savedMessage->update($request->validate(['label' => ['required', 'string', 'max:100']]));

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Bot $bot, SavedMessage $savedMessage): RedirectResponse
    {
        $this->ownBot($bot);
        $savedMessage->delete();

        return back()->with('success', 'Saved message removed.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasCustomEmoji(array $payload): bool
    {
        $entities = array_merge($payload['entities'] ?? [], $payload['caption_entities'] ?? []);

        return collect($entities)->contains(fn (array $entity): bool => ($entity['type'] ?? null) === 'custom_emoji');
    }
}
