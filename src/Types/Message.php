<?php

namespace MaxBot\Types;

class Message
{
    public function __construct(
        public readonly ?string $messageId = null,
        public readonly ?string $text = null,
        public readonly ?User $sender = null,
        public readonly ?Chat $chat = null,
        public readonly ?int $timestamp = null,
        public readonly array $attachments = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['body']['mid'] ?? $data['message_id'] ?? null,
            text: $data['body']['text'] ?? $data['text'] ?? null,
            sender: isset($data['sender']) ? User::fromArray($data['sender']) : null,
            chat: isset($data['recipient']) ? Chat::fromArray($data['recipient']) : null,
            timestamp: $data['timestamp'] ?? null,
            attachments: $data['body']['attachments'] ?? $data['attachments'] ?? [],
        );
    }
}
