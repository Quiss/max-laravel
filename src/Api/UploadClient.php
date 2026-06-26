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
     * Flow differs by type:
     * - Audio/Video: token comes from POST /uploads BEFORE file upload
     * - Image/File: token comes from upload server response AFTER file upload
     *
     * @param  string  $type  image|video|audio|file
     *
     * @throws GuzzleException
     */
    public function upload(string $filePath, string $type): array
    {
        // Step 1: Get upload URL (and pre-upload token for audio/video)
        $uploadData = $this->httpClient->post('uploads', query: ['type' => $type]);

        $uploadUrl = $uploadData['url'] ?? null;

        if (! $uploadUrl) {
            throw new \RuntimeException('Failed to get upload URL from MAX API');
        }

        // Audio/Video: token is returned before upload, upload response is not JSON
        if (in_array($type, ['audio', 'video'])) {
            $token = $uploadData['token'] ?? null;

            if (! $token) {
                throw new \RuntimeException("MAX API did not return a pre-upload token for type '{$type}'");
            }

            // Upload file (response is not JSON for audio/video)
            $this->httpClient->uploadFileRaw($uploadUrl, $filePath);

            return [
                'type' => $type,
                'payload' => [
                    'token' => $token,
                ],
            ];
        }

        // Image/File: token comes from the upload server response
        $uploadResult = $this->httpClient->uploadFile($uploadUrl, $filePath);

        return $this->buildAttachment($type, $uploadResult);
    }

    /**
     * Build attachment payload for image/file from upload server response.
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

        // File
        $token = $uploadResult['token'] ?? null;

        if (! $token) {
            throw new \RuntimeException(
                'Could not find token in file upload response: '.json_encode($uploadResult)
            );
        }

        return [
            'type' => 'file',
            'payload' => [
                'token' => $token,
            ],
        ];
    }
}
