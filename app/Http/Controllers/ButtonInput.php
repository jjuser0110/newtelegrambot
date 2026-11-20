<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;

/** Normalises the buttons[] rows posted by the reply / send forms. */
class ButtonInput
{
    public static function clean(array $rows): array
    {
        $buttons = [];
        foreach (array_values($rows) as $i => $row) {
            $text = trim($row['text'] ?? '');
            $value = trim($row['value'] ?? '');
            $type = ($row['type'] ?? 'url') === 'callback' ? 'callback' : 'url';

            if ($text === '' && $value === '') {
                continue;
            }
            if ($text === '' || $value === '') {
                throw ValidationException::withMessages(["buttons.$i.text" => __('Button :number needs both a label and a value.', ['number' => $i + 1])]);
            }
            if ($type === 'url' && ! preg_match('#^(https?|tg)://#i', $value)) {
                throw ValidationException::withMessages(["buttons.$i.value" => __('Button :number: link must start with https://, http:// or tg://', ['number' => $i + 1])]);
            }
            if ($type === 'callback' && strlen($value) > 64) {
                throw ValidationException::withMessages(["buttons.$i.value" => __('Button :number: keyword must be at most 64 bytes.', ['number' => $i + 1])]);
            }
            $buttons[] = compact('text', 'type', 'value');
        }

        return $buttons;
    }
}
