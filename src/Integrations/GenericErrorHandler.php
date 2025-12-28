<?php

declare(strict_types=1);

namespace Checkend\Integrations;

use Checkend\Checkend;
use Checkend\Filters\SanitizeFilter;
use ErrorException;
use Throwable;

/**
 * Generic PHP error handler for Checkend.
 *
 * Usage:
 *     use Checkend\Integrations\GenericErrorHandler;
 *
 *     Checkend::configure(['api_key' => 'your-api-key']);
 *     GenericErrorHandler::register();
 *
 *     // Your application code...
 */
class GenericErrorHandler
{
    private static bool $registered = false;
    /** @var callable|null */
    private static $previousErrorHandler = null;
    /** @var callable|null */
    private static $previousExceptionHandler = null;

    /**
     * Register the error and exception handlers.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // Set error handler
        self::$previousErrorHandler = set_error_handler([self::class, 'handleError']);

        // Set exception handler
        self::$previousExceptionHandler = set_exception_handler([self::class, 'handleException']);

        // Register shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        // Set up request context
        if (php_sapi_name() !== 'cli') {
            self::setRequestContext();
        }
    }

    /**
     * Unregister the error handlers.
     */
    public static function unregister(): void
    {
        if (!self::$registered) {
            return;
        }

        restore_error_handler();
        restore_exception_handler();

        self::$registered = false;
    }

    /**
     * Handle PHP errors.
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0,
    ): bool {
        // Check if error reporting is suppressed
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $exception = new ErrorException($errstr, 0, $errno, $errfile, $errline);
        Checkend::notify($exception);

        // Call previous error handler if exists
        if (self::$previousErrorHandler !== null) {
            return call_user_func(
                self::$previousErrorHandler,
                $errno,
                $errstr,
                $errfile,
                $errline,
            );
        }

        return false;
    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handleException(Throwable $exception): void
    {
        Checkend::notify($exception);

        // Call previous exception handler if exists
        if (self::$previousExceptionHandler !== null) {
            call_user_func(self::$previousExceptionHandler, $exception);
        }
    }

    /**
     * Handle fatal errors on shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'], $fatalErrors, true)) {
            return;
        }

        $exception = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );

        Checkend::notify($exception);
    }

    private static function setRequestContext(): void
    {
        $configuration = Checkend::getConfiguration();
        if ($configuration === null || !$configuration->sendRequestData) {
            return;
        }

        $request = [
            'url' => self::getCurrentUrl(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'headers' => self::getHeaders(),
        ];

        // Add sanitized query parameters
        if (!empty($_GET)) {
            $request['params'] = self::sanitizeData($_GET);
        }

        // Add remote IP
        $clientIp = self::getClientIp();
        if ($clientIp !== null) {
            $request['remote_ip'] = $clientIp;
        }

        Checkend::setRequest($request);
    }

    private static function getCurrentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * @return array<string, string>
     */
    private static function getHeaders(): array
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
    private static function getClientIp(): ?string
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
    private static function sanitizeData(array $data): array
    {
        $configuration = Checkend::getConfiguration();
        if ($configuration === null) {
            return $data;
        }

        $filter = new SanitizeFilter($configuration->filterKeys);
        return $filter->filter($data);
    }
}
