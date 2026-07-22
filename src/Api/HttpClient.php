<?php

namespace MaxBot\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class HttpClient
{
    /** @var int[] */
    private const ATTACHMENT_RETRY_DELAYS_MS = [1000, 2000, 4000, 8000, 16000];

    private Client $client;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://platform-api2.max.ru',
        private readonly int $timeout = 30,
    ) {
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
        $retry = 0;

        while (true) {
            try {
                $response = $this->postRequest($endpoint, $body, $query);

                break;
            } catch (ClientException $exception) {
                if (! $this->isAttachmentNotReady($endpoint, $exception)
                    || ! isset(self::ATTACHMENT_RETRY_DELAYS_MS[$retry])) {
                    throw $exception;
                }

                $this->sleepBeforeRetry(self::ATTACHMENT_RETRY_DELAYS_MS[$retry]);
                $retry++;
            }
        }

        return json_decode($response->getBody()->getContents(), true) ?? [];
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
            'json' => $body,
            'query' => $query,
        ]);
    }

    protected function sleepBeforeRetry(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    private function isAttachmentNotReady(string $endpoint, ClientException $exception): bool
    {
        if (trim($endpoint, '/') !== 'messages') {
            return false;
        }

        $response = $exception->getResponse();
        $data = $response ? json_decode((string) $response->getBody(), true) : null;

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
        $uploadClient = new Client(['timeout' => 120]);

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
