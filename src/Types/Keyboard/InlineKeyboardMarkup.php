<?php

namespace MaxBot\Types\Keyboard;

class InlineKeyboardMarkup
{
    /** @var array<array<InlineKeyboardButton>> */
    private array $rows = [];

    public static function make(): self
    {
        return new self;
    }

    public function addRow(InlineKeyboardButton ...$buttons): self
    {
        $this->rows[] = array_values($buttons);

        return $this;
    }

    /**
     * Convert to MAX API attachment format
     */
    public function toAttachment(): array
    {
        return [
            'type' => 'inline_keyboard',
            'payload' => [
                'buttons' => array_map(
                    fn (array $row) => array_map(
                        fn (InlineKeyboardButton $button) => $button->toArray(),
                        $row
                    ),
                    $this->rows
                ),
            ],
        ];
    }

    /**
     * Check if keyboard has any buttons
     */
    public function isEmpty(): bool
    {
        return empty($this->rows);
    }
}
