<?php

namespace MaxBot\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient
{
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
        $response = $this->client->post($endpoint, [
            'json' => $body,
            'query' => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
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
     * Upload file without parsing response (for audio/video where response is not JSON).
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
