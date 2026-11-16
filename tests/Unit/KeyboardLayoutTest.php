<?php

namespace Tests\Unit;

use App\Services\MessageSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KeyboardLayoutTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: array<int, int>}>
     */
    public static function layouts(): array
    {
        return [
            '1 button = one full row' => [1, [1]],
            '2 buttons = half + half' => [2, [2]],
            '3 buttons = half + half, then full' => [3, [2, 1]],
            '4 buttons = two half rows' => [4, [2, 2]],
            '5 buttons = two half rows, then full' => [5, [2, 2, 1]],
        ];
    }

    /**
     * @param  array<int, int>  $expectedRowSizes
     */
    #[DataProvider('layouts')]
    public function test_buttons_are_laid_out_two_per_row(int $buttonCount, array $expectedRowSizes): void
    {
        $buttons = array_map(
            fn (int $i) => ['text' => "B$i", 'type' => 'url', 'value' => "https://example.com/$i"],
            range(1, $buttonCount),
        );

        $rows = (new MessageSender)->buildKeyboard($buttons)['inline_keyboard'];

        $this->assertSame($expectedRowSizes, array_map('count', $rows));
        $this->assertSame('B1', $rows[0][0]['text']);
        $this->assertSame("B$buttonCount", end($rows)[array_key_last(end($rows))]['text']);
    }

    public function test_empty_buttons_are_skipped_and_callback_buttons_keep_their_keyword(): void
    {
        $keyboard = (new MessageSender)->buildKeyboard([
            ['text' => 'Price', 'type' => 'callback', 'value' => 'price'],
            ['text' => '', 'type' => 'url', 'value' => ''],
            ['text' => 'Site', 'type' => 'url', 'value' => 'https://example.com'],
            ['text' => 'Help', 'type' => 'callback', 'value' => 'help'],
        ]);

        $this->assertSame([
            [['text' => 'Price', 'callback_data' => 'price'], ['text' => 'Site', 'url' => 'https://example.com']],
            [['text' => 'Help', 'callback_data' => 'help']],
        ], $keyboard['inline_keyboard']);

        $this->assertNull((new MessageSender)->buildKeyboard([]));
    }
}
