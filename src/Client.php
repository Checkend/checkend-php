<?php

declare(strict_types=1);

namespace Checkend;

use Exception;
use RuntimeException;

/**
 * HTTP client for sending notices to Checkend.
 */
class Client
{
    private Configuration $configuration;
    private string $endpoint;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->endpoint = $configuration->endpoint . '/ingest/v1/errors';
    }

    /**
     * Send a notice to Checkend.
     *
     * @return array<string, mixed>|null
     */
    public function send(Notice $notice): ?array
    {
        if (empty($this->configuration->apiKey)) {
            $this->configuration->log('error', 'Cannot send notice: api_key not configured');
            return null;
        }

        $payload = $notice->toPayload();

        try {
            $response = $this->post($payload);
            $this->configuration->log('debug', 'Notice sent successfully: ' . json_encode($response));
            return $response;
        } catch (Exception $e) {
            $this->configuration->log('error', 'Failed to send notice: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        $data = json_encode($payload);
        if ($data === false) {
            throw new RuntimeException('Failed to encode payload');
        }

        $context = $this->createStreamContext($data);

        $response = @file_get_contents($this->endpoint, false, $context);

        // Get status code from response headers
        // Note: $http_response_header is set by file_get_contents
        /** @var array<int, string> $http_response_header */
        $statusCode = $this->getStatusCode($http_response_header);

        if ($statusCode !== 201) {
            $this->handleHttpError($statusCode, $response ?: '');
            throw new RuntimeException('HTTP error: ' . $statusCode);
        }

        if ($response === false) {
            throw new RuntimeException('Failed to read response');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to decode response');
        }

        return $decoded;
    }

    /**
     * Create stream context with proxy and SSL support.
     *
     * @return resource
     */
    private function createStreamContext(string $data)
    {
        $httpOptions = [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Checkend-Ingestion-Key: ' . $this->configuration->apiKey,
                'User-Agent: checkend-php/' . Checkend::VERSION,
                'Content-Length: ' . strlen($data),
            ]),
            'content' => $data,
            'timeout' => $this->configuration->timeout,
            'ignore_errors' => true,
        ];

        // Add proxy configuration if set
        if ($this->configuration->proxy !== null) {
            $httpOptions['proxy'] = $this->configuration->proxy;
            $httpOptions['request_fulluri'] = true;
        }

        // SSL configuration
        $sslOptions = [
            'verify_peer' => $this->configuration->sslVerify,
            'verify_peer_name' => $this->configuration->sslVerify,
        ];

        if ($this->configuration->sslCaPath !== null) {
            $sslOptions['cafile'] = $this->configuration->sslCaPath;
        }

        return stream_context_create([
            'http' => $httpOptions,
            'ssl' => $sslOptions,
        ]);
    }

    /**
     * @param string[] $headers
     */
    private function getStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/[\d.]+ (\d+)/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    private function handleHttpError(int $statusCode, string $body): void
    {
        switch ($statusCode) {
            case 401:
                $this->configuration->log('error', 'Authentication failed: invalid API key');
                break;
            case 422:
                $this->configuration->log('error', 'Validation error: ' . $body);
                break;
            case 429:
                $this->configuration->log('warning', 'Rate limited by Checkend API');
                break;
            default:
                if ($statusCode >= 500) {
                    $this->configuration->log('error', 'Server error: ' . $statusCode);
                } else {
                    $this->configuration->log('error', 'HTTP error: ' . $statusCode);
                }
        }
    }
}
