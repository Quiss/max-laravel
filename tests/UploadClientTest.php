<?php

namespace MaxBot\Tests;

use MaxBot\Api\HttpClient;
use MaxBot\Api\UploadClient;
use PHPUnit\Framework\TestCase;

class UploadClientTest extends TestCase
{
    public function test_audio_uses_the_token_returned_with_the_upload_url(): void
    {
        $http = new FakeHttpClient(
            uploadSlot: ['url' => 'https://omu.okcdn.test/audio', 'token' => 'slot-audio-token'],
            uploadResult: [],
            rawUploadResult: '<retval>1</retval>',
        );

        $attachment = (new UploadClient($http))->upload('/tmp/song.mp3', 'audio');

        $this->assertSame(['type' => 'audio', 'payload' => ['token' => 'slot-audio-token']], $attachment);
        $this->assertSame(['url' => 'https://omu.okcdn.test/audio', 'path' => '/tmp/song.mp3'], $http->rawFileUploadRequest);
        $this->assertNull($http->fileUploadRequest);
    }

    public function test_audio_uses_the_token_returned_after_file_upload(): void
    {
        $http = new FakeHttpClient(
            uploadSlot: ['url' => 'https://upload.max.test/audio'],
            uploadResult: ['token' => 'audio-token'],
        );

        $attachment = (new UploadClient($http))->upload('/tmp/song.mp3', 'audio');

        $this->assertSame(['type' => 'audio', 'payload' => ['token' => 'audio-token']], $attachment);
        $this->assertSame(['endpoint' => 'uploads', 'query' => ['type' => 'audio']], $http->uploadSlotRequest);
        $this->assertSame(['url' => 'https://upload.max.test/audio', 'path' => '/tmp/song.mp3'], $http->fileUploadRequest);
    }

    public function test_image_uses_the_nested_photo_token(): void
    {
        $http = new FakeHttpClient(
            uploadSlot: ['url' => 'https://upload.max.test/image'],
            uploadResult: ['photos' => ['photo-id' => ['token' => 'image-token']]],
        );

        $attachment = (new UploadClient($http))->upload('/tmp/cover.jpg', 'image');

        $this->assertSame(['type' => 'image', 'payload' => ['token' => 'image-token']], $attachment);
    }
}

class FakeHttpClient extends HttpClient
{
    public ?array $uploadSlotRequest = null;

    public ?array $fileUploadRequest = null;

    public ?array $rawFileUploadRequest = null;

    public function __construct(
        private readonly array $uploadSlot,
        private readonly array $uploadResult,
        private readonly string $rawUploadResult = '',
    ) {}

    public function post(string $endpoint, array $body = [], array $query = []): array
    {
        $this->uploadSlotRequest = compact('endpoint', 'query');

        return $this->uploadSlot;
    }

    public function uploadFile(string $uploadUrl, string $filePath, string $fieldName = 'data'): array
    {
        $this->fileUploadRequest = ['url' => $uploadUrl, 'path' => $filePath];

        return $this->uploadResult;
    }

    public function uploadFileRaw(string $uploadUrl, string $filePath, string $fieldName = 'data'): string
    {
        $this->rawFileUploadRequest = ['url' => $uploadUrl, 'path' => $filePath];

        return $this->rawUploadResult;
    }
}
