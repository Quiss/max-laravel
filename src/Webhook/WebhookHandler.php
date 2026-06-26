<?php

namespace MaxBot\Webhook;

use MaxBot\Types\Update;

class WebhookHandler
{
    /** @var array<string, callable[]> */
    private array $handlers = [];

    /** @var array<int, array{pattern: string, handler: callable|string}> */
    private array $commandHandlers = [];

    /** @var array<int, array{pattern: string, handler: callable|string}> */
    private array $textHandlers = [];

    /**
     * Register a handler for a specific update type.
     */
    public function on(UpdateType $type, callable|string $handler): self
    {
        $this->handlers[$type->value][] = $handler;

        return $this;
    }

    public function onBotStarted(callable|string $handler): self
    {
        return $this->on(UpdateType::BotStarted, $handler);
    }

    public function onBotStopped(callable|string $handler): self
    {
        return $this->on(UpdateType::BotStopped, $handler);
    }

    public function onMessageCreated(callable|string $handler): self
    {
        return $this->on(UpdateType::MessageCreated, $handler);
    }

    public function onCommand(string $pattern, callable|string $handler): self
    {
        $this->commandHandlers[] = [
            'pattern' => $this->compileCommandPattern($pattern),
            'handler' => $handler,
        ];

        return $this;
    }

    public function onText(string $pattern, callable|string $handler): self
    {
        $this->textHandlers[] = [
            'pattern' => $this->compileTextPattern($pattern),
            'handler' => $handler,
        ];

        return $this;
    }

    public function onCallback(callable|string $handler): self
    {
        return $this->on(UpdateType::MessageCallback, $handler);
    }

    /**
     * Process an incoming webhook update.
     */
    public function process(Update $update): void
    {
        $type = $update->updateType->value;

        if (isset($this->handlers[$type])) {
            foreach ($this->handlers[$type] as $handler) {
                $this->executeHandler($handler, $update);
            }
        }

        if ($update->updateType !== UpdateType::MessageCreated) {
            return;
        }

        $text = trim((string) $update->getText());

        if ($text === '') {
            return;
        }

        if ($this->processCommandHandlers($update, $text)) {
            return;
        }

        $this->processTextHandlers($update, $text);
    }

    private function processCommandHandlers(Update $update, string $text): bool
    {
        foreach ($this->commandHandlers as $handler) {
            if (preg_match($handler['pattern'], $text, $matches) !== 1) {
                continue;
            }

            $this->executeHandler($handler['handler'], $update, $this->extractNamedMatches($matches));

            return true;
        }

        return false;
    }

    private function processTextHandlers(Update $update, string $text): void
    {
        foreach ($this->textHandlers as $handler) {
            if (preg_match($handler['pattern'], $text, $matches) !== 1) {
                continue;
            }

            $this->executeHandler($handler['handler'], $update, $this->extractNamedMatches($matches));

            return;
        }
    }

    /**
     * Execute a handler (supports callable and class string).
     */
    private function executeHandler(callable|string $handler, Update $update, array $parameters = []): void
    {
        if (is_string($handler) && class_exists($handler)) {
            $handler = app($handler);
        }

        $arguments = [$update];

        if ($parameters !== [] && $this->acceptsSecondArgument($handler)) {
            $arguments[] = $parameters;
        }

        $handler(...$arguments);
    }

    private function compileCommandPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        $pattern = ltrim($pattern, '/');

        if ($pattern === '') {
            throw new \InvalidArgumentException('Command pattern cannot be empty.');
        }

        $tokens = preg_split('/\s+/', $pattern) ?: [];
        $parts = [];

        foreach ($tokens as $index => $token) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $token, $matches) === 1) {
                $parts[] = $index === array_key_last($tokens)
                    ? '(?P<'.$matches[1].'>.+)'
                    : '(?P<'.$matches[1].'>\S+)';

                continue;
            }

            $parts[] = preg_quote($token, '~');
        }

        return '~^/?'.implode('\s+', $parts).'$~u';
    }

    private function compileTextPattern(string $pattern): string
    {
        if ($pattern === '') {
            throw new \InvalidArgumentException('Text pattern cannot be empty.');
        }

        return '~^'.$pattern.'$~u';
    }

    /**
     * @param  array<int|string, mixed>  $matches
     * @return array<string, string>
     */
    private function extractNamedMatches(array $matches): array
    {
        return array_filter(
            $matches,
            fn (mixed $value, int|string $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function acceptsSecondArgument(callable $handler): bool
    {
        $reflection = is_array($handler)
            ? new \ReflectionMethod($handler[0], $handler[1])
            : new \ReflectionFunction(\Closure::fromCallable($handler));

        return $reflection->isVariadic() || $reflection->getNumberOfParameters() >= 2;
    }
}
