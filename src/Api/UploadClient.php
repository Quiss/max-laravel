<?php

namespace MaxBot\Api;

use GuzzleHttp\Exception\GuzzleException;

class UploadClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Upload a file and return the attachment payload for use in messages.
     *
     * Depending on the MAX upload host, the attachment token may be returned
     * either with the upload URL or by the upload server after file upload.
     *
     * @param  string  $type  image|video|audio|file
     *
     * @throws GuzzleException
     */
    public function upload(string $filePath, string $type): array
    {
        // Step 1: Get an upload URL from MAX.
        $uploadData = $this->httpClient->post('uploads', query: ['type' => $type]);

        $uploadUrl = $uploadData['url'] ?? null;

        if (! $uploadUrl) {
            throw new \RuntimeException('Failed to get upload URL from MAX API');
        }

        // Some audio/video upload hosts return the token together with the
        // upload URL and respond with XML such as <retval>1</retval> after
        // receiving the bytes. Use that token without trying to parse XML.
        if (in_array($type, ['audio', 'video'], true)
            && is_string($uploadData['token'] ?? null)
            && $uploadData['token'] !== '') {
            $this->httpClient->uploadFileRaw($uploadUrl, $filePath);

            return [
                'type' => $type,
                'payload' => [
                    'token' => $uploadData['token'],
                ],
            ];
        }

        // Other upload hosts return a JSON token after receiving the file.
        $uploadResult = $this->httpClient->uploadFile($uploadUrl, $filePath);

        return $this->buildAttachment($type, $uploadResult);
    }

    /**
     * Build attachment payload from the upload server response.
     *
     * Image response: {"photos": {"<id>": {"token": "..."}}}
     * File response: {"token": "..."}
     */
    private function buildAttachment(string $type, array $uploadResult): array
    {
        if ($type === 'image') {
            // Photos response can be {"photos": {"key": {"token": "..."}}} or {"photos": [{"token": "..."}]}
            $photos = $uploadResult['photos'] ?? [];
            $firstPhoto = is_array($photos) ? reset($photos) : null;
            $token = $firstPhoto['token'] ?? null;

            if (! $token) {
                throw new \RuntimeException(
                    'Could not find token in photo upload response: '.json_encode($uploadResult)
                );
            }

            return [
                'type' => 'image',
                'payload' => [
                    'token' => $token,
                ],
            ];
        }

        // Audio, video, and file responses contain the token at the root.
        $token = $uploadResult['token'] ?? null;

        if (! $token) {
            throw new \RuntimeException(
                "Could not find token in {$type} upload response: ".json_encode($uploadResult)
            );
        }

        return [
            'type' => $type,
            'payload' => [
                'token' => $token,
            ],
        ];
    }
}
