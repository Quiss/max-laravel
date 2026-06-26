<?php

namespace MaxBot\Types;

class User
{
    public function __construct(
        public readonly int $userId,
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly ?string $username = null,
        public readonly bool $isBot = false,
        public readonly ?int $lastActivityTime = null,
        public readonly ?string $description = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?string $fullAvatarUrl = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            firstName: $data['first_name'] ?? $data['name'] ?? '',
            lastName: $data['last_name'] ?? null,
            username: $data['username'] ?? null,
            isBot: $data['is_bot'] ?? false,
            lastActivityTime: $data['last_activity_time'] ?? null,
            description: $data['description'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            fullAvatarUrl: $data['full_avatar_url'] ?? null,
        );
    }
}
