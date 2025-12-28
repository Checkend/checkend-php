<?php

declare(strict_types=1);

namespace Checkend;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Represents an error notice to be sent to Checkend.
 */
class Notice
{
    public string $errorClass;
    public string $message;
    /** @var string[] */
    public array $backtrace;
    public ?string $fingerprint;
    /** @var string[] */
    public array $tags;
    /** @var array<string, mixed> */
    public array $context;
    /** @var array<string, mixed> */
    public array $request;
    /** @var array<string, mixed> */
    public array $user;
    public string $environment;
    public string $occurredAt;
    /** @var array<string, string> */
    public array $notifier;

    /**
     * @param string[] $backtrace
     * @param string[] $tags
     * @param array<string, mixed> $context
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     * @param array<string, string> $notifier
     */
    public function __construct(
        string $errorClass,
        string $message,
        array $backtrace = [],
        ?string $fingerprint = null,
        array $tags = [],
        array $context = [],
        array $request = [],
        array $user = [],
        string $environment = 'development',
        ?string $occurredAt = null,
        array $notifier = [],
    ) {
        $this->errorClass = $errorClass;
        $this->message = $message;
        $this->backtrace = $backtrace;
        $this->fingerprint = $fingerprint;
        $this->tags = $tags;
        $this->context = $context;
        $this->request = $request;
        $this->user = $user;
        $this->environment = $environment;
        $this->occurredAt = $occurredAt ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
        $this->notifier = $notifier;
    }

    /**
     * Convert the notice to an API payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $context = array_merge(['environment' => $this->environment], $this->context);

        $payload = [
            'error' => [
                'class' => $this->errorClass,
                'message' => $this->message,
                'backtrace' => $this->backtrace,
                'occurred_at' => $this->occurredAt,
            ],
            'context' => $context,
            'notifier' => $this->notifier,
        ];

        if ($this->fingerprint !== null) {
            $payload['error']['fingerprint'] = $this->fingerprint;
        }

        if (!empty($this->tags)) {
            $payload['error']['tags'] = $this->tags;
        }

        if (!empty($this->request)) {
            $payload['request'] = $this->request;
        }

        if (!empty($this->user)) {
            $payload['user'] = $this->user;
        }

        return $payload;
    }
}
