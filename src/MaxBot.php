<?php

namespace MaxBot;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use MaxBot\Api\HttpClient;
use MaxBot\Api\UploadClient;
use MaxBot\Types\Keyboard\InlineKeyboardMarkup;
use MaxBot\Types\Message;
use MaxBot\Types\Update;
use MaxBot\Webhook\UpdateType;
use MaxBot\Webhook\WebhookHandler;

class MaxBot
{
    private HttpClient $http;

    private UploadClient $uploader;

    private WebhookHandler $webhookHandler;

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://platform-api2.max.ru',
        private readonly int $timeout = 30,
    ) {
        $this->http = new HttpClient($this->token, $this->apiUrl, $this->timeout);
        $this->uploader = new UploadClient($this->http);
        $this->webhookHandler = new WebhookHandler;
    }

    // ──────────────────────────────────────────────────────────────
    // Text Messages
    // ──────────────────────────────────────────────────────────────

    /**
     * Send a text message to a user.
     *
     * @throws GuzzleException
     */
    public function sendMessage(
        int $userId,
        string $text,
        ?InlineKeyboardMarkup $keyboard = null,
        string $format = 'html'
    ): Message {
        return $this->sendMessageTo(['user_id' => $userId], $text, $keyboard, $format);
    }

    /**
     * Send a text message to a chat.
     *
     * @throws GuzzleException
     */
    public function sendChatMessage(
        int $chatId,
        string $text,
        ?InlineKeyboardMarkup $keyboard = null,
        string $format = 'html'
    ): Message {
        return $this->sendMessageTo(['chat_id' => $chatId], $text, $keyboard, $format);
    }

    /**
     * @throws GuzzleException
     */
    private function sendMessageTo(
        array $query,
        string $text,
        ?InlineKeyboardMarkup $keyboard = null,
        string $format = 'html'
    ): Message {
        $body = [
            'text' => $text,
            'format' => $format,
        ];

        if ($keyboard && ! $keyboard->isEmpty()) {
            $body['attachments'] = [$keyboard->toAttachment()];
        }

        $response = $this->http->post('messages', $body, $query);

        return Message::fromArray($response['message'] ?? $response);
    }

    // ──────────────────────────────────────────────────────────────
    // Media Messages (upload → send)
    // ──────────────────────────────────────────────────────────────

    /**
     * Send a photo to a user.
     *
     * @throws GuzzleException
     */
    public function sendPhoto(
        int $userId,
        string $filePath,
        ?string $caption = null,
        string $format = 'html'
    ): Message {
        $attachment = $this->uploader->upload($filePath, 'image');

        return $this->sendMediaMessage($userId, $attachment, $caption, $format);
    }

    /**
     * Send a video to a user.
     *
     * @throws GuzzleException
     */
    public function sendVideo(
        int $userId,
        string $filePath,
        ?string $caption = null,
        ?int $width = null,
        ?int $height = null
    ): Message {
        $attachment = $this->uploader->upload($filePath, 'video');

        return $this->sendMediaMessage($userId, $attachment, $caption);
    }

    /**
     * Send audio to a user.
     *
     * @throws GuzzleException
     */
    public function sendAudio(
        int $userId,
        string $filePath,
        ?string $caption = null,
        ?string $title = null
    ): Message {
        $attachment = $this->uploader->upload($filePath, 'audio');

        return $this->sendMediaMessage($userId, $attachment, $caption);
    }

    /**
     * Send a document/file to a user.
     *
     * @throws GuzzleException
     */
    public function sendDocument(
        int $userId,
        string $filePath,
        ?string $caption = null
    ): Message {
        $attachment = $this->uploader->upload($filePath, 'file');

        return $this->sendMediaMessage($userId, $attachment, $caption);
    }

    /**
     * Send a media message with an attachment.
     *
     * @throws GuzzleException
     */
    private function sendMediaMessage(
        int $userId,
        array $attachment,
        ?string $caption = null,
        string $format = 'html'
    ): Message {
        $body = [
            'attachments' => [$attachment],
            'format' => $format,
        ];

        if ($caption) {
            $body['text'] = $caption;
        }

        $response = $this->http->post('messages', $body, ['user_id' => $userId]);

        return Message::fromArray($response['message'] ?? $response);
    }

    // ──────────────────────────────────────────────────────────────
    // Bot Info
    // ──────────────────────────────────────────────────────────────

    /**
     * Get bot info.
     *
     * @throws GuzzleException
     */
    public function getMe(): array
    {
        return $this->http->get('me');
    }

    // ──────────────────────────────────────────────────────────────
    // Webhook Management
    // ──────────────────────────────────────────────────────────────

    /**
     * Set webhook URL for receiving updates.
     *
     * @param  string[]  $updateTypes
     *
     * @throws GuzzleException
     */
    public function setWebhook(string $url, array $updateTypes = [], ?string $secret = null): bool
    {
        $body = ['url' => $url];

        if (! empty($updateTypes)) {
            $body['update_types'] = $updateTypes;
        }

        if ($secret) {
            $body['secret'] = $secret;
        }

        $response = $this->http->post('subscriptions', $body);

        return ($response['success'] ?? false) === true;
    }

    /**
     * Get current webhook subscriptions.
     *
     * @throws GuzzleException
     */
    public function getSubscriptions(): array
    {
        return $this->http->get('subscriptions');
    }

    /**
     * Answer an inline keyboard callback with an updated message and/or a one-time notification.
     *
     * @throws GuzzleException
     */
    public function answerCallback(string $callbackId, ?array $message = null, ?string $notification = null): bool
    {
        $body = array_filter([
            'message' => $message,
            'notification' => $notification,
        ], fn (mixed $value): bool => $value !== null);

        $response = $this->http->post('answers', $body, ['callback_id' => $callbackId]);

        return ($response['success'] ?? false) === true;
    }

    /**
     * Delete webhook subscription.
     *
     * @throws GuzzleException
     */
    public function deleteWebhook(): bool
    {
        $response = $this->http->delete('subscriptions');

        return ($response['success'] ?? false) === true;
    }

    // ──────────────────────────────────────────────────────────────
    // Webhook Handler Registration
    // ──────────────────────────────────────────────────────────────

    public function on(UpdateType $type, callable|string $handler): self
    {
        $this->webhookHandler->on($type, $handler);

        return $this;
    }

    public function onBotStarted(callable|string $handler): self
    {
        $this->webhookHandler->onBotStarted($handler);

        return $this;
    }

    public function onBotStopped(callable|string $handler): self
    {
        $this->webhookHandler->onBotStopped($handler);

        return $this;
    }

    public function onMessageCreated(callable|string $handler): self
    {
        $this->webhookHandler->onMessageCreated($handler);

        return $this;
    }

    public function onCommand(string $pattern, callable|string $handler): self
    {
        $this->webhookHandler->onCommand($pattern, $handler);

        return $this;
    }

    public function onText(string $pattern, callable|string $handler): self
    {
        $this->webhookHandler->onText($pattern, $handler);

        return $this;
    }

    public function onCallback(callable|string $handler): self
    {
        $this->webhookHandler->onCallback($handler);

        return $this;
    }

    /**
     * Process an incoming webhook payload.
     */
    public function processWebhookUpdate(array $payload): void
    {
        try {
            $update = Update::fromArray($payload);
            $this->webhookHandler->process($update);
        } catch (\Throwable $e) {
            Log::error('MaxBot: Failed to process webhook update', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }

    public function getWebhookHandler(): WebhookHandler
    {
        return $this->webhookHandler;
    }
}
