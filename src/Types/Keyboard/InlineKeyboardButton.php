<?php

namespace MaxBot\Types\Keyboard;

class InlineKeyboardButton
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $url = null,
        public readonly ?string $callbackData = null,
    ) {}

    public static function make(string $text, ?string $url = null, ?string $callbackData = null): self
    {
        return new self(
            text: $text,
            url: $url,
            callbackData: $callbackData,
        );
    }

    public function toArray(): array
    {
        if ($this->url) {
            return [
                'type' => 'link',
                'text' => $this->text,
                'url' => $this->url,
            ];
        }

        if ($this->callbackData) {
            return [
                'type' => 'callback',
                'text' => $this->text,
                'payload' => $this->callbackData,
            ];
        }

        return [
            'type' => 'callback',
            'text' => $this->text,
            'payload' => '',
        ];
    }
}
