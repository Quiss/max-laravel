<?php

namespace MaxBot\Types;

use MaxBot\Webhook\UpdateType;

class Update
{
    public function __construct(
        public readonly UpdateType $updateType,
        public readonly int $timestamp,
        public readonly ?Message $message = null,
        public readonly ?User $user = null,
        public readonly ?Chat $chat = null,
        public readonly ?string $userLocale = null,
        public readonly ?string $payload = null,
        public readonly ?string $callbackId = null,
        public readonly array $rawData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $updateType = UpdateType::from($data['update_type']);

        return new self(
            updateType: $updateType,
            timestamp: $data['timestamp'] ?? 0,
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            user: isset($data['user']) ? User::fromArray($data['user']) : null,
            chat: isset($data['chat']) ? Chat::fromArray($data['chat']) : null,
            userLocale: $data['user_locale'] ?? null,
            payload: $data['callback']['payload'] ?? $data['payload'] ?? null,
            callbackId: $data['callback']['callback_id'] ?? $data['callback_id'] ?? null,
            rawData: $data,
        );
    }

    /**
     * Get the user ID from the update (from user, message sender, etc.)
     */
    public function getUserId(): ?int
    {
        if ($this->user) {
            return $this->user->userId;
        }

        if ($this->message?->sender) {
            return $this->message->sender->userId;
        }

        return null;
    }

    /**
     * Get the chat ID from the update
     */
    public function getChatId(): ?int
    {
        if ($this->chat) {
            return $this->chat->chatId;
        }

        if ($this->message?->chat) {
            return $this->message->chat->chatId;
        }

        return null;
    }

    public function getText(): ?string
    {
        return $this->message?->text;
    }
}
