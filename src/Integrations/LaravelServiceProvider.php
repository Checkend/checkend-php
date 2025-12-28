<?php

declare(strict_types=1);

namespace Checkend\Integrations;

use Checkend\Checkend;
use Checkend\Filters\SanitizeFilter;

/**
 * Laravel Service Provider for Checkend.
 *
 * Add to your config/app.php providers array:
 *     Checkend\Integrations\LaravelServiceProvider::class,
 *
 * Or for Laravel 11+, add to bootstrap/providers.php.
 */
class LaravelServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Configuration will be loaded from environment variables
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // Build configuration options
        $options = [
            'api_key' => $_ENV['CHECKEND_API_KEY'] ?? getenv('CHECKEND_API_KEY'),
            'environment' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production',
        ];

        // Add Laravel-specific root path if available
        if (function_exists('base_path')) {
            $options['root_path'] = base_path();
        }

        // Add app name from Laravel config if available
        if (function_exists('config')) {
            $appName = config('app.name');
            if ($appName !== null) {
                $options['app_name'] = $appName;
            }
        }

        // Configure Checkend
        Checkend::configure($options);

        // Set up request context if in HTTP context
        if (php_sapi_name() !== 'cli') {
            $this->setRequestContext();
        }

        // Register queue handler for job failure tracking
        if (class_exists(\Illuminate\Queue\Events\JobFailed::class)) {
            LaravelQueueHandler::register();
        }
    }

    private function setRequestContext(): void
    {
        $configuration = Checkend::getConfiguration();
        if ($configuration === null || !$configuration->sendRequestData) {
            return;
        }

        $request = [
            'url' => $this->getCurrentUrl(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'headers' => $this->getHeaders(),
        ];

        // Add sanitized query parameters
        if (!empty($_GET)) {
            $request['params'] = $this->sanitizeData($_GET);
        }

        // Add remote IP
        $clientIp = $this->getClientIp();
        if ($clientIp !== null) {
            $request['remote_ip'] = $clientIp;
        }

        Checkend::setRequest($request);
    }

    private function getCurrentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = [];
        $headerKeys = [
            'HTTP_USER_AGENT' => 'User-Agent',
            'HTTP_ACCEPT' => 'Accept',
            'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language',
            'HTTP_REFERER' => 'Referer',
            'CONTENT_TYPE' => 'Content-Type',
        ];

        foreach ($headerKeys as $serverKey => $headerName) {
            if (isset($_SERVER[$serverKey])) {
                $headers[$headerName] = $_SERVER[$serverKey];
            }
        }

        return $headers;
    }

    /**
     * Get client IP address, handling proxy headers.
     */
    private function getClientIp(): ?string
    {
        // Check for forwarded headers (in reverse proxy situations)
        $forwardedHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];

        foreach ($forwardedHeaders as $header) {
            if (isset($_SERVER[$header]) && is_string($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs, take the first
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if ($ip !== '') {
                    return $ip;
                }
            }
        }

        // Fall back to REMOTE_ADDR
        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
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
