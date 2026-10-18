<?php

namespace MaxBot\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MaxBot\Api\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;

class BeforeRequestTest extends TestCase
{
    public function test_hook_runs_for_every_api_request_including_attachment_retries(): void
    {
        $http = new HttpClient('token', attachmentRetryDelaysMs: [0]);
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{}'),
            new Response(200, [], '{"url":"upload"}'),
            new Response(400, [], '{"code":"attachment.not.ready"}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]));
        (new ReflectionProperty(HttpClient::class, 'client'))->setValue($http, new Client(['base_uri' => 'https://example.test/', 'handler' => $stack]));
        $calls = [];
        $http->beforeRequest(function (RequestInterface $request) use (&$calls): void {
            $calls[] = $request->getMethod().' '.$request->getUri()->getPath();
        });
        $http->get('me');
        $http->post('uploads');
        $http->post('messages');
        $http->put('messages');
        $http->delete('subscriptions');

        $this->assertSame(['GET /me', 'POST /uploads', 'POST /messages', 'POST /messages', 'PUT /messages', 'DELETE /subscriptions'], $calls);
    }

    public function test_hook_exception_prevents_network_delivery(): void
    {
        $http = new HttpClient('token');
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
        $history = [];
        $stack->push(Middleware::history($history));
        (new ReflectionProperty(HttpClient::class, 'client'))->setValue($http, new Client(['handler' => $stack]));
        $http->beforeRequest(function (RequestInterface $request): void {
            throw new \RuntimeException('Limit reached');
        });

        try {
            $http->get('https://example.test/me');
            $this->fail('Expected the hook exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Limit reached', $exception->getMessage());
        }

        $this->assertSame([], $history);
    }
}
