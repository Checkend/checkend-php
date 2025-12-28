<?php

declare(strict_types=1);

namespace Checkend\Integrations;

use Checkend\Checkend;
use Checkend\Filters\SanitizeFilter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * PSR-15 middleware for capturing exceptions and request context.
 *
 * This middleware should be added early in the middleware stack to capture
 * all exceptions that occur during request processing.
 *
 * Usage with any PSR-15 compatible framework:
 *
 *     $app->add(new \Checkend\Integrations\Psr15Middleware());
 */
class Psr15Middleware implements MiddlewareInterface
{
    /**
     * Headers to exclude from request context (contain sensitive data).
     */
    private const FILTERED_HEADERS = [
        'cookie',
        'authorization',
        'x-api-key',
        'x-auth-token',
    ];

    /**
     * Headers to exclude from request context (not useful for debugging).
     */
    private const EXCLUDED_HEADERS = [
        'host',
        'connection',
        'accept-encoding',
        'content-length',
    ];

    /**
     * Process an incoming server request.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Set request context before processing
        $this->setRequestContext($request);

        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            Checkend::notify($exception);
            throw $exception;
        } finally {
            // Clear context after request is complete
            Checkend::clear();
        }
    }

    /**
     * Extract and set request context from PSR-7 request.
     */
    private function setRequestContext(ServerRequestInterface $request): void
    {
        $configuration = Checkend::getConfiguration();
        if ($configuration === null || !$configuration->sendRequestData) {
            return;
        }

        $uri = $request->getUri();

        $requestData = [
            'url' => (string) $uri,
            'method' => $request->getMethod(),
            'path' => $uri->getPath(),
        ];

        // Add query string if present
        $queryString = $uri->getQuery();
        if ($queryString !== '') {
            $requestData['query_string'] = $queryString;
        }

        // Add filtered headers
        $headers = $this->extractHeaders($request);
        if (!empty($headers)) {
            $requestData['headers'] = $headers;
        }

        // Add sanitized query parameters
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $requestData['params'] = $this->sanitizeData($queryParams);
        }

        // Add sanitized body parameters (for POST/PUT/PATCH)
        $parsedBody = $request->getParsedBody();
        if (!empty($parsedBody) && is_array($parsedBody)) {
            $requestData['body'] = $this->sanitizeData($parsedBody);
        }

        // Add client IP
        $serverParams = $request->getServerParams();
        $clientIp = $this->getClientIp($serverParams);
        if ($clientIp !== null) {
            $requestData['remote_ip'] = $clientIp;
        }

        // Add common headers as top-level fields
        $userAgent = $request->getHeaderLine('User-Agent');
        if ($userAgent !== '') {
            $requestData['user_agent'] = $userAgent;
        }

        $referer = $request->getHeaderLine('Referer');
        if ($referer !== '') {
            $requestData['referer'] = $referer;
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if ($contentType !== '') {
            $requestData['content_type'] = $contentType;
        }

        Checkend::setRequest($requestData);
    }

    /**
     * Extract headers, filtering out sensitive and excluded ones.
     *
     * @return array<string, string>
     */
    private function extractHeaders(ServerRequestInterface $request): array
    {
        $headers = [];
        $configuration = Checkend::getConfiguration();

        foreach ($request->getHeaders() as $name => $values) {
            $lowerName = strtolower($name);

            // Skip filtered (sensitive) headers
            if (in_array($lowerName, self::FILTERED_HEADERS, true)) {
                continue;
            }

            // Skip excluded (not useful) headers
            if (in_array($lowerName, self::EXCLUDED_HEADERS, true)) {
                continue;
            }

            // Use first value for each header
            $headers[$name] = $values[0] ?? '';
        }

        // Sanitize headers using configuration filter keys
        if ($configuration !== null) {
            $filter = new SanitizeFilter($configuration->filterKeys);
            $headers = $filter->filter($headers);
        }

        return $headers;
    }

    /**
     * Get client IP address, handling proxy headers.
     *
     * @param array<string, mixed> $serverParams
     */
    private function getClientIp(array $serverParams): ?string
    {
        // Check for forwarded headers (in reverse proxy situations)
        $forwardedHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];

        foreach ($forwardedHeaders as $header) {
            if (isset($serverParams[$header]) && is_string($serverParams[$header])) {
                // X-Forwarded-For can contain multiple IPs, take the first
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                if ($ip !== '') {
                    return $ip;
                }
            }
        }

        // Fall back to REMOTE_ADDR
        if (isset($serverParams['REMOTE_ADDR']) && is_string($serverParams['REMOTE_ADDR'])) {
            return $serverParams['REMOTE_ADDR'];
        }

        return null;
    }

    /**
     * Sanitize data using the configured filter keys.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $configuration = Checkend::getConfiguration();
        if ($configuration === null) {
            return $data;
        }

        $filter = new SanitizeFilter($configuration->filterKeys);
        return $filter->filter($data);
    }
}
