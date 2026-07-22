<?php

namespace MaxBot\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MaxBot\Api\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

class HttpClientTest extends TestCase
{
    public function test_empty_post_body_is_encoded_as_a_json_object(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]));
        $stack->push(Middleware::history($history));
        $client = new HttpClient('token');
        (new ReflectionProperty(HttpClient::class, 'client'))->setValue($client, new Client(['handler' => $stack]));

        $client->post('answers', [], ['callback_id' => 'callback-1']);

        $this->assertSame('{}', (string) $history[0]['request']->getBody());
        $this->assertSame('callback_id=callback-1', $history[0]['request']->getUri()->getQuery());
    }

    public function test_messages_are_retried_while_an_attachment_is_being_processed(): void
    {
        $client = new RetryHttpClient([
            $this->attachmentNotReadyException(),
            $this->attachmentNotReadyException(),
            new Response(200, [], json_encode(['message' => ['body' => ['mid' => 'mid.1']]])),
        ]);

        $response = $client->post('messages', ['attachments' => []], ['user_id' => 42]);

        $this->assertSame('mid.1', $response['message']['body']['mid']);
        $this->assertSame([1000, 2000], $client->sleepDelays);
        $this->assertSame(3, $client->requestCount);
    }

    public function test_other_client_errors_are_not_retried(): void
    {
        $exception = new ClientException(
            'Bad request',
            new Request('POST', 'https://platform-api2.max.ru/messages'),
            new Response(400, [], json_encode(['code' => 'attachment.invalid'])),
        );
        $client = new RetryHttpClient([$exception]);

        try {
            $client->post('messages');
            $this->fail('Expected the client exception to be rethrown.');
        } catch (ClientException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame([], $client->sleepDelays);
        $this->assertSame(1, $client->requestCount);
    }

    private function attachmentNotReadyException(): ClientException
    {
        return new ClientException(
            'Attachment is not ready',
            new Request('POST', 'https://platform-api2.max.ru/messages'),
            new Response(400, [], json_encode([
                'code' => 'attachment.not.ready',
                'message' => 'Key: errors.process.attachment.video.not.processed',
            ])),
        );
    }
}

class RetryHttpClient extends HttpClient
{
    public array $sleepDelays = [];

    public int $requestCount = 0;

    public function __construct(private array $responses) {}

    protected function postRequest(string $endpoint, array $body, array $query): ResponseInterface
    {
        $response = $this->responses[$this->requestCount++] ?? null;

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    protected function sleepBeforeRetry(int $milliseconds): void
    {
        $this->sleepDelays[] = $milliseconds;
    }
}
