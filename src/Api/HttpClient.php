<?php

namespace MaxBot\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class HttpClient
{
    private ?float $postTimeout = null;

    private Client $client;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://platform-api2.max.ru',
        private readonly int $timeout = 30,
        private readonly array $attachmentRetryDelaysMs = [1000, 2000, 4000, 8000],
        private readonly float $attachmentRetryTimeout = 20,
        private readonly int $uploadTimeout = 45,
    ) {
        if ($timeout <= 0 || $uploadTimeout <= 0 || ! is_finite($attachmentRetryTimeout) || $attachmentRetryTimeout <= 0) {
            throw new \InvalidArgumentException('MAX HTTP and attachment retry timeouts must be positive and finite.');
        }
        foreach ($attachmentRetryDelaysMs as $delay) {
            if (! is_int($delay) || $delay < 0) {
                throw new \InvalidArgumentException('MAX attachment retry delays must be non-negative integer milliseconds.');
            }
        }

        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/',
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, [
            'query' => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * @throws GuzzleException
     */
    public function post(string $endpoint, array $body = [], array $query = []): array
    {
        $delays = array_values($this->attachmentRetryDelaysMs);
        $deadline = $this->monotonicTime() + $this->attachmentRetryTimeout;
        $isMessages = trim($endpoint, '/') === 'messages';
        $lastException = null;
        $previousTimeout = $this->postTimeout;

        try {
            for ($attempt = 0; ; $attempt++) {
                if ($isMessages) {
                    $remaining = $deadline - $this->monotonicTime();
                    if ($remaining <= 0 && $lastException !== null) {
                        throw $lastException;
                    }
                    // Every request, including the first, shares the same budget.
                    $this->postTimeout = min($this->timeout, max(0.001, $remaining));
                }

                try {
                    $response = $this->postRequest($endpoint, $body, $query);

                    return json_decode($response->getBody()->getContents(), true) ?? [];
                } catch (ClientException $exception) {
                    if (! $this->isAttachmentNotReady($endpoint, $exception)
                        || ! isset($delays[$attempt])) {
                        throw $exception;
                    }

                    $lastException = $exception;
                    $delay = $delays[$attempt];
                    if ($delay / 1000 >= $deadline - $this->monotonicTime()) {
                        throw $exception;
                    }
                    $this->sleepBeforeRetry($delay);
                }
            }
        } finally {
            $this->postTimeout = $previousTimeout;
        }
    }

    /**
     * @throws GuzzleException
     */
    public function put(string $endpoint, array $body = [], array $query = []): array
    {
        $response = $this->client->put($endpoint, [
            'json' => $body,
            'query' => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    protected function postRequest(string $endpoint, array $body, array $query): ResponseInterface
    {
        return $this->client->post($endpoint, [
            'timeout' => $this->postTimeout ?? $this->timeout,
            // Guzzle serializes an empty PHP array as `[]`, while MAX POST
            // endpoints expect a JSON object even when every field is optional.
            'json' => $body === [] ? (object) [] : $body,
            'query' => $query,
        ]);
    }

    protected function sleepBeforeRetry(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    protected function monotonicTime(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    private function isAttachmentNotReady(string $endpoint, ClientException $exception): bool
    {
        if (trim($endpoint, '/') !== 'messages') {
            return false;
        }

        $response = $exception->getResponse();
        if ($response->getStatusCode() !== 400) {
            return false;
        }

        $stream = $response->getBody();
        // Inspect without consuming the body needed by the caller's diagnostics.
        if (! $stream->isSeekable()) {
            return false;
        }
        $position = $stream->tell();
        try {
            $data = json_decode((string) $stream, true);
        } finally {
            $stream->seek($position);
        }

        return is_array($data) && ($data['code'] ?? null) === 'attachment.not.ready';
    }

    /**
     * @throws GuzzleException
     */
    public function delete(string $endpoint, array $query = []): array
    {
        $response = $this->client->delete($endpoint, [
            'query' => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * Upload file and return parsed JSON response (for image/file uploads).
     *
     * @throws GuzzleException
     */
    public function uploadFile(string $uploadUrl, string $filePath, string $fieldName = 'data'): array
    {
        $response = $this->doUpload($uploadUrl, $filePath, $fieldName);

        return json_decode($response, true) ?? [];
    }

    /**
     * Upload a file without assuming that the upload server returns JSON.
     *
     * @throws GuzzleException
     */
    public function uploadFileRaw(string $uploadUrl, string $filePath, string $fieldName = 'data'): string
    {
        return $this->doUpload($uploadUrl, $filePath, $fieldName);
    }

    /**
     * @throws GuzzleException
     */
    private function doUpload(string $uploadUrl, string $filePath, string $fieldName): string
    {
        $uploadClient = new Client(['timeout' => $this->uploadTimeout]);

        $response = $uploadClient->post($uploadUrl, [
            'multipart' => [
                [
                    'name' => $fieldName,
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ],
            ],
        ]);

        return $response->getBody()->getContents();
    }
}
