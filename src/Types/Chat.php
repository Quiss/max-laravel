<?php

namespace MaxBot\Types;

class Chat
{
    public function __construct(
        public readonly int $chatId,
        public readonly string $type,
        public readonly ?string $title = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chatId: $data['chat_id'],
            type: $data['type'] ?? 'dialog',
            title: $data['title'] ?? null,
        );
    }
}
