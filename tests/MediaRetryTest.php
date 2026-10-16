<?php

namespace MaxBot\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MaxBot\Api\HttpClient;
use MaxBot\Api\UploadClient;
use MaxBot\MaxBot;
use MaxBot\Types\Keyboard\InlineKeyboardButton;
use MaxBot\Types\Keyboard\InlineKeyboardMarkup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MediaRetryTest extends TestCase
{
    public static function media(): array
    {
        return [['sendPhoto', 'image'], ['sendDocument', 'file'], ['sendAudio', 'audio'], ['sendVideo', 'video']];
    }

    private function keyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(InlineKeyboardButton::make('Next', callbackData: 'next'));
    }

    private static function notReady(): Response
    {
        return new Response(400, [], json_encode([
            'code' => 'attachment.not.ready',
            'message' => 'Key: errors.process.attachment.file.not.processed',
        ]));
    }

    private function bot(TestMediaHttpClient $http): MaxBot
    {
        $bot = new MaxBot('test-token');
        (new ReflectionProperty(MaxBot::class, 'http'))->setValue($bot, $http);
        (new ReflectionProperty(MaxBot::class, 'uploader'))->setValue($bot, new UploadClient($http));

        return $bot;
    }

    #[DataProvider('media')]
    public function test_upload_once_then_retry_identical_message_with_keyboard(string $method, string $type): void
    {
        $http = new TestMediaHttpClient([
            new Response(200, [], json_encode(['url' => 'https://upload.test/private', 'token' => 'slot-token'])),
            self::notReady(), self::notReady(), new Response(200, [], '{}'),
        ]);
        $this->bot($http)->$method(userId: 42, filePath: '/unused', caption: 'Caption', format: 'markdown', keyboard: $this->keyboard());

        $this->assertSame(1, $http->uploadCount);
        $this->assertCount(4, $http->history);
        $this->assertSame('/uploads', $http->history[0]['request']->getUri()->getPath());
        $this->assertSame([1000, 2000], $http->delays);
        $request = $http->history[1]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('user_id=42', $request->getUri()->getQuery());
        $this->assertSame('Caption', $body['text']);
        $this->assertSame('markdown', $body['format']);
        $this->assertSame([
            ['type' => $type, 'payload' => ['token' => in_array($type, ['audio', 'video']) ? 'slot-token' : 'uploaded-token']],
            $this->keyboard()->toAttachment(),
        ], $body['attachments']);
        foreach (array_slice($http->history, 1) as $entry) {
            $this->assertSame((string) $request->getBody(), (string) $entry['request']->getBody());
            $this->assertSame((string) $request->getUri(), (string) $entry['request']->getUri());
        }
    }

    #[DataProvider('media')]
    public function test_first_success_and_old_positional_and_named_arguments(string $method, string $type): void
    {
        foreach ([false, true] as $named) {
            $http = new TestMediaHttpClient([
                new Response(200, [], '{"url":"https://upload.test/private","token":"slot-token"}'),
                new Response(200, [], '{}'),
            ]);
            $bot = $this->bot($http);
            if ($named) {
                $bot->$method(userId: 42, filePath: '/unused', caption: 'Caption');
            } else {
                match ($method) {
                    'sendPhoto' => $bot->sendPhoto(42, '/unused', 'Caption', 'html'),
                    'sendAudio' => $bot->sendAudio(42, '/unused', 'Caption', 'Title'),
                    'sendVideo' => $bot->sendVideo(42, '/unused', 'Caption', 640, 480),
                    default => $bot->sendDocument(42, '/unused', 'Caption'),
                };
            }
            $body = json_decode((string) $http->history[1]['request']->getBody(), true);
            $this->assertSame([
                'attachments' => [['type' => $type, 'payload' => ['token' => in_array($type, ['audio', 'video']) ? 'slot-token' : 'uploaded-token']]],
                'format' => 'html', 'text' => 'Caption',
            ], $body);
            $this->assertSame([], $http->delays);
            $this->assertSame(1, $http->uploadCount);
        }
    }

    public function test_photo_accepts_fifth_positional_keyboard_and_ignores_empty_keyboard(): void
    {
        foreach ([$this->keyboard(), InlineKeyboardMarkup::make(), null] as $keyboard) {
            $http = new TestMediaHttpClient([
                new Response(200, [], '{"url":"https://upload.test/private"}'),
                new Response(200, [], '{}'),
            ]);
            $bot = $this->bot($http);
            $bot->sendPhoto(42, '/unused', 'Caption', 'markdown', $keyboard);
            $body = json_decode((string) $http->history[1]['request']->getBody(), true);
            $this->assertSame('markdown', $body['format']);
            $this->assertCount($keyboard && ! $keyboard->isEmpty() ? 2 : 1, $body['attachments']);
        }
    }

    public function test_exhaustion_preserves_original_exception_status_and_full_body(): void
    {
        $request = new Request('POST', 'https://max.test/messages');
        $exceptions = array_map(fn () => new ClientException('original', $request, self::notReady()), range(1, 5));
        $http = new TestMediaHttpClient($exceptions);
        try {
            $http->post('messages');
            $this->fail('Expected failure');
        } catch (ClientException $e) {
            $this->assertSame($exceptions[4], $e);
            $this->assertSame(400, $e->getResponse()->getStatusCode());
            $this->assertSame([
                'code' => 'attachment.not.ready',
                'message' => 'Key: errors.process.attachment.file.not.processed',
            ], json_decode($e->getResponse()->getBody()->getContents(), true));
        }
        $this->assertCount(5, $http->history);
        $this->assertSame([1000, 2000, 4000, 8000], $http->delays);
    }

    public static function noRetry(): array
    {
        return [
            [400, '{"code":"attachment.invalid"}'], [400, ''], [400, '<html>Error</html>'],
            [400, '{"code":"attachment.not.ready.extra"}'], [400, 'null'],
            [403, '{"code":"attachment.not.ready"}'], [403, '{"code":"bot.blocked"}'],
            [500, '{"code":"attachment.not.ready"}'],
        ];
    }

    #[DataProvider('noRetry')]
    public function test_other_errors_are_not_retried(int $status, string $body): void
    {
        $http = new TestMediaHttpClient([new Response($status, [], $body)]);
        try {
            $http->post('messages');
            $this->fail('Expected failure');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->assertSame($status, $e->getResponse()->getStatusCode());
            $this->assertSame($body, $e->getResponse()->getBody()->getContents());
        }
        $this->assertCount(1, $http->history);
        $this->assertSame([], $http->delays);
    }

    public function test_ambiguous_network_failure_is_not_retried_even_after_not_ready(): void
    {
        foreach ([[], [self::notReady()]] as $prefix) {
            $exception = new ConnectException('timeout', new Request('POST', 'https://max.test/messages'));
            $http = new TestMediaHttpClient([...$prefix, $exception]);
            try {
                $http->post('messages');
                $this->fail('Expected failure');
            } catch (ConnectException $e) {
                $this->assertSame($exception, $e);
            }
            $this->assertCount(count($prefix) + 1, $http->history);
            $this->assertSame($prefix ? [1000] : [], $http->delays);
        }
    }

    public function test_other_endpoints_and_disabled_retries(): void
    {
        foreach ([['uploads', [1000]], ['messages', []]] as [$endpoint, $delays]) {
            $http = new TestMediaHttpClient([self::notReady()], $delays);
            try {
                $http->post($endpoint);
                $this->fail('Expected failure');
            } catch (ClientException) {
            }
            $this->assertCount(1, $http->history);
            $this->assertSame([], $http->delays);
        }
    }

    public function test_request_time_and_sleep_share_a_deadline_and_cap_http_timeout(): void
    {
        $http = new TestMediaHttpClient([self::notReady(), self::notReady()], budget: 5);
        $http->requestSeconds = 1.5;
        try {
            $http->post('messages');
            $this->fail('Expected failure');
        } catch (ClientException) {
        }
        $this->assertCount(2, $http->history);
        $this->assertSame([1000], $http->delays);
        $this->assertSame(5.0, $http->history[0]['options']['timeout']);
        $this->assertSame(2.5, $http->history[1]['options']['timeout']);
        $this->assertSame(4.0, $http->now);
    }

    public function test_slow_error_exhausts_budget_without_sleep_and_restores_timeout(): void
    {
        $exception = new ClientException('original', new Request('POST', 'https://max.test/messages'), self::notReady());
        $http = new TestMediaHttpClient([$exception, new Response(200, [], '{}')], budget: 2);
        $http->requestSeconds = 2;
        try {
            $http->post('messages');
            $this->fail('Expected failure');
        } catch (ClientException $e) {
            $this->assertSame($exception, $e);
        }
        $this->assertSame([], $http->delays);
        $this->assertCount(1, $http->history);
        $http->post('uploads');
        $this->assertSame(30, $http->history[1]['options']['timeout']);
    }

    public function test_oversleep_does_not_start_another_request(): void
    {
        $http = new TestMediaHttpClient([self::notReady()], budget: 2);
        $http->oversleepSeconds = 2;
        try {
            $http->post('messages');
            $this->fail('Expected failure');
        } catch (ClientException $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }
        $this->assertCount(1, $http->history);
    }

    public static function invalidSettings(): array
    {
        return [[['timeout' => 0]], [['uploadTimeout' => 0]], [['attachmentRetryTimeout' => 0]],
            [['attachmentRetryTimeout' => INF]], [['attachmentRetryDelaysMs' => [-1]]],
            [['attachmentRetryDelaysMs' => [1.5]]]];
    }

    #[DataProvider('invalidSettings')]
    public function test_unbounded_or_invalid_settings_are_rejected(array $settings): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient('test-token', ...$settings);
    }
}

class TestMediaHttpClient extends HttpClient
{
    public array $history = [];
    public array $delays = [];
    public int $uploadCount = 0;
    public float $now = 0;
    public float $requestSeconds = 0;
    public float $oversleepSeconds = 0;

    public function __construct(array $responses, array $retryDelays = [1000, 2000, 4000, 8000], float $budget = 20)
    {
        parent::__construct('test-token', attachmentRetryDelaysMs: $retryDelays, attachmentRetryTimeout: $budget);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $stack->push(function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $this->now += $this->requestSeconds;

                return $handler($request, $options);
            };
        });
        (new ReflectionProperty(HttpClient::class, 'client'))->setValue($this, new Client([
            'base_uri' => 'https://max.test', 'handler' => $stack,
        ]));
    }

    public function uploadFile(string $uploadUrl, string $filePath, string $fieldName = 'data'): array
    {
        $this->uploadCount++;

        return ['token' => 'uploaded-token', 'photos' => [['token' => 'uploaded-token']]];
    }

    public function uploadFileRaw(string $uploadUrl, string $filePath, string $fieldName = 'data'): string
    {
        $this->uploadCount++;

        return '<retval>1</retval>';
    }

    protected function monotonicTime(): float
    {
        return $this->now;
    }

    protected function sleepBeforeRetry(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
        $this->now += $milliseconds / 1000 + $this->oversleepSeconds;
    }
}
